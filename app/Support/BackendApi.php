<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BackendApi — عميل الـ API المنفصل (النسخة النظيفة المسلَّمة).
 *
 * الواجهة لا تلمس قاعدة البيانات إطلاقاً:
 * كل البيانات تأتي من سيرفر الباك اند عبر /api/v1/*.
 * عنوان الباك اند يُضبط في .env: BACKEND_API_URL
 */
class BackendApi
{
    public static function baseUrl(): string
    {
        return rtrim((string) env('BACKEND_API_URL', 'http://127.0.0.1:8000'), '/');
    }

    public static function get(string $path, array $query = []): \Illuminate\Support\Collection
    {
        return self::request('GET', $path, $query);
    }

    public static function post(string $path, array $body = []): \Illuminate\Support\Collection
    {
        return self::request('POST', $path, $body);
    }

    /**
     * POST بإرسال multipart/form-data — مطلوب عند وجود ملفات في $body.
     *
     * ⚠️ لماذا لا يكفي post() العادي؟
     * PendingRequest::$bodyFormat الافتراضي هو 'json'، فأي UploadedFile داخل
     * $body يُحوَّل إلى JSON ويفقد محتواه — فيصل للباك اند كنص فارغ
     * (وليس ملفاً) وتفشل قاعدة 'image' بالخطأ validation.image.
     *
     * ⚠️ ولماذا نستخدم attach() بدل وضع الملف داخل asMultipart()->post()؟
     * parseMultipartBodyFormat() يغلّف كل قيمة بـ ['name'=>..,'contents'=>..]
     * دون 'filename'، و Guzzle لا يقرأ كائن UploadedFile من 'contents'
     * فيرسله فارغاً. attach() هي الطريقة المعتمدة: هي من يضيف
     * 'filename' فعلياً من PendingRequest::$pendingFiles.
     *
     * الشكل المتوقّع:
     *   $body  => الحقول النصية فقط.
     *   $files => [ 'اسم_الحقل' => UploadedFile ]  وتُرسَل عبر attach().
     */
    public static function postMultipart(string $path, array $body = [], array $files = []): \Illuminate\Support\Collection
    {
        return self::request('POST', $path, $body, $files);
    }

    /**
     * هل نجح الطلب فعلاً؟
     *
     * ⚠️ كان غياب مفتاح isSuccess يُعتبر «نجاحاً» (return true)، فتنجح
     * حالة أخطر من أي خطأ: الباك اند يردّ 302 (فشل مصادقة أو back() بلا
     * Referer) فيتابعه عميل HTTP ويصل 200 من صفحة الجذر — وهي استجابة
     * بلا isSuccess ← فكانت الدالة تُرجع true بينما **لم يُحفظ شيء**،
     * وتعرض الواجهة «تم التحديث بنجاح» كذباً.
     *
     * الاستجابة الوحيدة التي بلا isSuccess وتُعد نجاحاً هي نقطة الفهرسة
     * (/api/v1) لأنها فعلاً تعيد ذلك. لذلك نتعامل مع الغياب كفشل ونطلب
     * isSuccess صراحةً.
     *
     * ⚠️ استثناء: نقاط الكتالوج العامة
     * (/api/v1/catalog/{key} و /catalog/{key}/{entityId} و /api/v1/services
     *  و /api/v1/home …) ترجع بياناتها **الظاهرة** بلا غلاف isSuccess،
     * لأنها نقاط قراءة عامة للواجهة. ف-controller الخاص بها
     * (categoryPage/entityPage) يستدعي isFailed() عليها، فكان كل هذا
     * المحتوى يرجع 404 «التصنيف غير موجود» رغم أن البيانات سليمة.
     * نميّزها: إن لم يوجد isSuccess ووجدنا حمولة بيانات حقيقية فهي نجاح.
     */
    public static function isSuccess(\Illuminate\Support\Collection $response): bool
    {
        if ($response->isEmpty()) {
            return false;
        }

        if ($response->has('isSuccess')) {
            return (bool) $response->get('isSuccess');
        }

        /*
         | نقاط قراءة عامة بلا غلاف isSuccess: نجاح ما دامت تحمل بيانات.
         | نفحص مفاتيح محتوى معروفة (قائمة/تصنيف) ولا نكتفي بـ "غير فارغ"
         | حتى لا تُحسب رسالة خطأ عارضة كنجاح.
         */
        return $response->hasAny([
            'category', 'entities', 'services', 'items', 'data', 'results',
            'ministries', 'authorities', 'home', 'sections', 'slides',
            'icons', 'offices', 'types', 'specialties',
        ]);
    }

    /** هل فشلت الاستجابة؟ العكس المنطقي لـ isSuccess. */
    public static function isFailed(\Illuminate\Support\Collection $response): bool
    {
        return ! self::isSuccess($response);
    }

    /**
     * تنفيذ الطلب وإرجاع البيانات كما هي من الـ API.
     *
     * عند الفشل تُرجع مغلف خطأ موحّد بنفس شكل استجابة الـ API:
     *   { isSuccess:false, value:null, error:{ message, code }, statusCode }
     * حتى تتمكن الواجهة من تمييز:
     *   - بيانات دخول خاطئة  (رسالة الباك اند الأصلية)
     *   - الباك اند متوقف / الشبكة  (رسالة تشغيلية واضحة)
     */
    private static function request(string $method, string $path, array $payload, array $files = []): \Illuminate\Support\Collection
    {
        $url = self::baseUrl() . $path;

        try {
            /*
             | بناء الطلب داخل دالة قابلة لإعادة التنفيذ: BackendHttp::send قد
             | يعيد إرسال الطلب بعد حل تحدي aes.js من الاستضافة، والكوكي
             | يمرّ عبر معامل $cookie.
             */
            $isPost = strtoupper($method) === 'POST';
            $build  = function (string $u, ?string $cookie) use ($isPost, $payload, $files) {
                $req = Http::timeout($files ? 60 : 10)->withHeaders(['Accept' => 'application/json']);
                if ($cookie) {
                    $req = $req->withHeaders(['Cookie' => $cookie]);
                }

                if ($files) {
                    // asMultipart + attach لكل ملف (انظر شرح postMultipart)
                    $req = $req->asMultipart();
                    foreach ($files as $field => $file) {
                        if ($file instanceof \Illuminate\Http\UploadedFile) {
                            /*
                             * ⚠️ نمرّر مورداً (resource) وليس نص المسار:
                             * Guzzle يرسل أي قيمة نصية في 'contents' كما هي،
                             * فلو مرّرنا getRealPath() لأرسل نص «C:\...\php.tmp»
                             * على أنه محتوى الملف، فيرفضه التحقق 'image'
                             * بالخطأ validation.image.
                             * المورد القابل للقراءة يرسل البايتات الفعلية.
                             */
                            $handle = @fopen($file->getRealPath(), 'rb');
                            if ($handle === false) {
                                continue;
                            }
                            $req = $req->attach(
                                $field,
                                $handle,
                                $file->getClientOriginalName(),
                                ['Content-Type' => $file->getClientMimeType()]
                            );
                        }
                    }

                    return $req->post($u, $payload);
                }

                return $isPost ? $req->post($u, $payload) : $req->get($u, $payload);
            };

            $resp = \App\Support\BackendHttp::send($build, $url);

            if (! $resp->successful()) {
                $status = $resp->status();
                $code   = $resp->json('error.code');
                $msg    = $resp->json('error.message');

                /*
                 | ⚠️ كان يُقرأ error.message فقط، لكن استجابة التحقق القياسية في
                 | Laravel هي { message, errors:{field:[…]} } بلا error.*، فكانت
                 | كل أخطاء التحقق (422) تتحوّل إلى «بيانات الدخول غير صحيحة»
                 | وتفقد المستخدم سبب الرفض تماماً. نقرأ message/errors أيضاً.
                 */
                if ($msg === null || $msg === '') {
                    $msg = $resp->json('message');
                }

                $fieldErrors = $resp->json('errors');
                if (is_array($fieldErrors) && $fieldErrors !== []) {
                    $flat = [];
                    foreach ($fieldErrors as $field => $list) {
                        foreach ((array) $list as $line) {
                            $flat[] = $line;
                        }
                    }
                    $msg = $msg ? ($msg . ' — ' . implode(' • ', array_slice($flat, 0, 4))) : implode(' • ', array_slice($flat, 0, 4));
                }

                Log::warning("BackendApi {$method} {$path} => HTTP {$status}: " . ($msg ?? 'no message'));

                // 4xx = رفض بيانات (رسالة الباك اند) — 5xx = خلل خادم
                if ($status >= 400 && $status < 500) {
                    return self::errorResponse(
                        $status,
                        $msg ?: 'بيانات الدخول غير صحيحة.',
                        $code ?: 'INVALID_REQUEST'
                    );
                }

                return self::errorResponse(
                    $status,
                    $msg ?: 'الباك اند واجه خطأ داخلياً (' . $status . '). أعد المحاولة بعد قليل.',
                    $code ?: 'BACKEND_ERROR'
                );
            }

            /*
             | نعيد كتابة روابط الوسائط المطلقة القادمة من الباك اند لتصبح
             | على نطاق الواجهة (same-origin). بدون هذا الخطوة، الصور تعمل
             | محلياً بالصدفة (لأن BACKEND_API_URL = 127.0.0.1) وتفشل في
             | الإنتاج: طلب عبر-النطاقات يُرفض من حماية منع السرقة الساخنة
             | على الاستضافة. انظر AmrtmMedia.
             */
            return AmrtmMedia::rewrite(collect($resp->json()));
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // الباك اند غير مشغّل / المنفذ مغلق / لا شبكة
            Log::warning("BackendApi {$method} {$path} connection error: " . $e->getMessage());

            return self::errorResponse(
                0,
                'تعذّر الاتصال بسيرفر الباك اند على ' . self::baseUrl() . '. تأكد أن الخدمة تعمل ثم أعد المحاولة.',
                'BACKEND_UNREACHABLE'
            );
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::warning("BackendApi {$method} {$path} timeout/transport error: " . $e->getMessage());

            return self::errorResponse(
                0,
                'انتهت مهلة الاتصال بالباك اند بعد 10 ثوانٍ. تحقق من الشبكة ثم أعد المحاولة.',
                'BACKEND_TIMEOUT'
            );
        } catch (\Throwable $e) {
            Log::warning("BackendApi {$method} {$path} error: " . $e->getMessage());

            return self::errorResponse(
                0,
                'خطأ غير متوقع أثناء الاتصال بالباك اند: ' . $e->getMessage(),
                'UNEXPECTED'
            );
        }
    }

    /**
     * مغلف خطأ موحّد بنفس شكل استجابة الـ API.
     * statusCode = 0 تعني «الطلب لم يصل الباك اند أصلاً».
     */
    public static function errorResponse(int $status, string $message, ?string $code = null): \Illuminate\Support\Collection
    {
        return collect([
            'isSuccess'  => false,
            'value'      => null,
            'error'      => ['message' => $message, 'code' => $code],
            'statusCode' => $status,
        ]);
    }

    /**
     * هل الفشل بنية تحتية (شبكة/باك اند متوقف) لا رفض بيانات؟
     *用它 لعرض رسالة تشغيلية مختلفة عن "كلمة المرور غير صحيحة".
     */
    public static function isInfraError(\Illuminate\Support\Collection $response): bool
    {
        $code = $response->get('error')['code'] ?? null;
        $status = (int) $response->get('statusCode');

        if (in_array($code, ['BACKEND_UNREACHABLE', 'BACKEND_TIMEOUT', 'BACKEND_ERROR', 'UNEXPECTED'], true)) {
            return true;
        }

        return $status === 0 || $status >= 500;
    }
}