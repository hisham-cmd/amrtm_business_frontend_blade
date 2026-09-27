<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Proxy (النسخة النظيفة المسلَّمة)
|--------------------------------------------------------------------------
| الواجهة لا تحتوي أي منطق بيانات — كل طلب /api/* يُمرَّر حرفياً إلى
| سيرفر الباك اند المنفصل (BACKEND_API_URL) مع إرفاق توكن الجلسة إن وجد.
|
| الباك اند يخدم الـ API تحت البادئة /api/v1 بينما الواجهة (JS القديم)
| تطلب /api/* — لذلك يُضاف البادئة v1 تلقائياً إن لم تكن موجودة.
|
/*
 | مثال:  GET /admin/offices/stats  →  backend: /api/v1/admin/offices/stats
 |        GET /api/v1/requests      →  backend: /api/v1/requests (كما هو)
 */

/**
 * إعادة بناء الطلب متعدد الأجزاء (multipart) للتمرير للباك اند.
 *
 * نستخرج كل الملفات أولاً مع اسمها الأصلي ومحتواها، ثم الحقول النصية،
 * ونعيد المصفوفة على الشكل الذي يفهمه Guzzle مع asMultipart().
 */
if (! function_exists('amrtm_proxy_multipart')) {
    function amrtm_proxy_multipart(Request $request): array
    {
        $multipart = [];

        /*
         * تسمية ملفات الجذع المتداخلة.
         *
         * allFiles() يوسّع الشجرة إلى مستوى واحد (مفتاح "custom_fields" بلا
         * مفتاح الحقل الداخلي)، فنحتاج request->file() الذي يحافظ على ال��سلسل
         * الهرمي الحقيقي. نُسجّل index كل ملف في المصفوفة ثم نضع اسمه بعد
         * اجتياز الشجرة.
         */
        $slots = [];

        foreach ($request->allFiles() as $uploads) {
            // cast على كائن PHP يحوّله إلى مصفوفة خصائصه (خطأ) — نطبّع أولاً:
            $list = is_array($uploads) ? $uploads : [$uploads];

            foreach ($list as $file) {
                if (! $file instanceof \Illuminate\Http\UploadedFile) {
                    continue;
                }

                $path = $file->getRealPath();
                if ($path === false || ! is_readable($path)) {
                    continue;
                }

                $slots[] = ['file' => $file, 'index' => count($multipart)];

                $multipart[] = [
                    'name'     => '__FILE__',
                    'contents' => fopen($path, 'rb'),
                    'filename' => $file->getClientOriginalName(),
                    'headers'  => [
                        'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
                    ],
                ];
            }
        }

        $label = function (array $node, string $prefix) use (&$label, &$slots): void {
            foreach ($node as $key => $child) {
                $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

                if ($child instanceof \Illuminate\Http\UploadedFile) {
                    foreach ($slots as $i => $slot) {
                        if ($slot['file'] === $child) {
                            $slots[$i]['name'] = $name;
                            break;
                        }
                    }

                    continue;
                }

                if (is_array($child)) {
                    $label($child, $name);
                }
            }
        };

        $label($request->file(), '');

        $fileRoots = [];
        foreach ($slots as $slot) {
            $name = $slot['name'] ?? '__FILE__';
            $multipart[$slot['index']]['name'] = $name;
            $fileRoots[] = explode('[', $name)[0];
        }

        // الحقول النصية مرفقة بالملفات.
        // ملاحظة مهمة: custom_fields تصل كمصفوفة متداخلة
        // (custom_fields[key] = value) و array_merge أو foreach بسيط
        // كانا يحوّلانها إلى "Array" نصياً فتفقد كل قيمة وتبقى مطلوبة فارغة.
        // لذلك نُسطّح المصفوفات بالشكل/name[a][b] الذي يفهمه Laravel في
        // request->file('custom_fields.مفتاح') و request->input() معاً.
        $flatten = function (array $data, string $prefix = '') use (&$flatten): array {
            $out = [];

            foreach ($data as $key => $value) {
                $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

                if (is_array($value)) {
                    $out += $flatten($value, $name);
                    continue;
                }

                $out[$name] = $value;
            }

            return $out;
        };

        /*
         * نستبعد فقط الجذور التي لها ملف مرفوع فعلاً. سابقاً كان الاستبعاد
         * على array_keys(allFiles()) وهو يعيد "custom_fields" فقط، فكان
         * يُسقط كل قيم custom_fields النصية مع ملفها. إن كان الحقل الجذر
         * يحمل ملفات وقيماً معاً نحتفظ بالقيم النصية ونضيفها بأسمائها.
         */
        $fileKeys = array_keys($request->allFiles());
        $keepText = array_values(array_diff($request->keys(), $fileKeys));

        foreach ($request->only($keepText) as $key => $value) {
            if (is_array($value)) {
                foreach ($flatten([$key => $value]) as $name => $flat) {
                    if (is_scalar($flat) || $flat === null) {
                        $multipart[] = ['name' => $name, 'contents' => (string) $flat];
                    }
                }

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $multipart[] = ['name' => $key, 'contents' => (string) $value];
            }
        }

        return $multipart;
    }
}

Route::match(['get', 'post', 'put', 'patch', 'delete'], '/{path}', function (Request $request, string $path) {
    $backend = rtrim((string) env('BACKEND_API_URL', 'http://127.0.0.1:8000'), '/');
    $token   = null;

    /*
     | هذا المسار في مجموعة api middleware (بلا StartSession)، فـ session()
     | يعيد null دائماً — وكان التوكن لا يُرسل للباك اند إطلاقاً فيرجع 401
     | لكل نداء من نداءات الواجهة (لوحة العميل، لوحة المكتب، الإشعارات).
     | الحل: إضافة StartSession لهذا المسار فقط ليُقرأ amrtm_api_token.
     */
    try {
        if (! $request->hasSession()) {
            $request->setLaravelSession(app('session.store'));
        }
        $token = $request->session()->get('amrtm_api_token');
    } catch (\Throwable) {
        $token = null;
    }

    $method  = strtolower($request->method());
    $isMultipart = str_contains((string) $request->header('Content-Type'), 'multipart');

    // إضافة بادئة v1 إن لم تكن موجودة (الباك اند يخدم الـ API تحت /api/v1)
    $apiPath = ($path === 'v1' || str_starts_with($path, 'v1/')) ? $path : 'v1/' . $path;
    $url     = $backend . '/api/' . $apiPath;

    $query = $request->query();
    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    /*
     | بناء الطلب داخل دالة قابلة لإعادة التنفيذ.
     | مهم: الملفات المرفوعة (multipart) تعيش كـ fopen streams تُستهلك من
     | أول إرسال — لذا تُبنى من جديد عند كل محاولة (مثل إعادة الطلب بعد
     | حل تحدي aes.js في BackendHttp::send).
     |
     | مهم أيضاً: لا نثبّت Content-Type هنا إطلاقاً. لو مرّرنا
     | 'Content-Type: application/json' header ثابت فإن asMultipart()
     | سيتجاهله ويبقى يرسل JSON — فتفشل كل عمليات رفع الملفات.
     | Laravel/guzzle يضبط الـ boundary الصحيح تلقائياً مع asMultipart().
     */
    $build = function (string $u, ?string $cookie) use ($request, $method, $token, $isMultipart) {
        $req = Http::timeout(60)
            ->withHeaders([
                'Accept'           => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        if ($token) {
            /*
             | الاستضافة (InfinityFree) تحذف ترويسة Authorization قبل PHP،
             | لذا يُرسل التوكن أيضاً في ترويسة مخصصة يفهمها الباك اند.
             */
            $req = $req->withHeaders([
                'Authorization'  => 'Bearer ' . $token,
                'X-AMRTM-TOKEN'  => $token,
            ]);
        }
        if ($cookie) {
            $req = $req->withHeaders(['Cookie' => $cookie]);
        }

        $multipart = $isMultipart ? amrtm_proxy_multipart($request) : [];
        $body      = $isMultipart ? [] : ($request->json()->all() ?: $request->all());

        if ($method === 'get' || $method === 'delete') {
            return $req->$method($u);
        }
        if ($method === 'put' || $method === 'patch') {
            // PUT مع ملفات (تعديل سلايد + استبدال الصورة) يمر كـ multipart أيضاً
            if ($isMultipart) {
                return $req->asMultipart()->put($u, $multipart);
            }
            return $req->$method($u, $body);
        }
        if ($isMultipart) { // post multipart (رفع ملفات)
            return $multipart
                ? $req->asMultipart()->post($u, $multipart)
                : $req->post($u, $body);
        }
        return $req->post($u, $body); // post JSON
    };

    try {
        $resp = \App\Support\BackendHttp::send($build, $url);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Proxy fetch failed: ' . $e->getMessage());

        return response()->json([
            'isSuccess' => false,
            'value'     => null,
            'error'     => ['message' => 'تعذر الاتصال بالباك اند.', 'code' => 'PROXY_ERROR'],
            'statusCode'=> 502,
        ], 502);
    }

    /*
     |--------------------------------------------------------------------------
     | تحويل روابط الوسائط المطلقة إلى same-origin
     |--------------------------------------------------------------------------
     | الباك اند يبني روابط الصور عبر url()‏ فتصبح مطلقة على نطاقه
     | (https://amrtmbusiness.rf.gd/media/…). عرضها في صفحة تعمل على
     | نطاق آخر يجعل الطلب عبر-نطاقات، فيرسل المتصفح Referer لنطاق
     | الصفحة، وحماية «منع السرقة الساخنة» على الاستضافة ترفضه — بينما
     | فتح الصورة مباشرة ينجح (لا Referer خارجي). وهو سبب ظهور بعض
     | الصور وفشل غيرها.
     |
     | لذلك نُعيد كتابة أي رابط وسائط ليصبح /media/… على **نطاق
     | الواجهة**، فيُخدم من نفس الأصل بلا Referer خارجي.
     */
    $contentType = $resp->header('Content-Type') ?: 'application/json';

    if (str_contains($contentType, 'json')) {
        $decoded = json_decode($resp->body(), true);

        if (is_array($decoded)) {
            $rewritten = \App\Support\AmrtmMedia::rewrite($decoded);
            $body = json_encode(
                $rewritten,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );

            return response($body === false ? $resp->body() : $body, $resp->status())
                ->header('Content-Type', $contentType);
        }
    }

    return response($resp->body(), $resp->status())
        ->header('Content-Type', $contentType);
})->where('path', '.*');
