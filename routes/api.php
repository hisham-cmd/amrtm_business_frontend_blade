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

        foreach ($request->allFiles() as $key => $uploads) {
            // كلFiles обычно UploadedFile واحد (غير مصفوفة). cast إلى array
            // على كائن PHP يحوّله إلى مصفوفة عناصره الداخلية (خصائصه!)
            // فيتولد خطأ — لذا نطبّع بشكل صحيح:
            $list = is_array($uploads) ? $uploads : [$uploads];

            foreach ($list as $file) {
                if (! $file instanceof \Illuminate\Http\UploadedFile) {
                    continue;
                }

                $path = $file->getRealPath();
                if ($path === false || ! is_readable($path)) {
                    continue;
                }

                $multipart[] = [
                    'name'     => $key,
                    'contents' => fopen($path, 'rb'),
                    'filename' => $file->getClientOriginalName(),
                    'headers'  => [
                        'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
                    ],
                ];
            }
        }

        // الحقول النصية مرفقة بالملفات
        foreach ($request->except(array_keys($request->allFiles())) as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $sub) {
                    if (is_scalar($sub) || $sub === null) {
                        $multipart[] = ['name' => $key . '[]', 'contents' => (string) $sub];
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

    return response($resp->body(), $resp->status())
        ->header('Content-Type', $resp->header('Content-Type') ?: 'application/json');
})->where('path', '.*');
