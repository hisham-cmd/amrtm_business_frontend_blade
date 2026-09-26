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
     * هل نجح الطلب فعلاً؟ — يعتمد على استجابة الـ API نفسها لا على تغيّر شكل البيانات.
     * أي فشل (شبكة، 4xx، 5xx، أو استجابة بلا isSuccess) ⇒ false.
     */
    public static function isSuccess(\Illuminate\Support\Collection $response): bool
    {
        if ($response->isEmpty()) {
            return false;
        }

        // استجابات /api/v1 تعيد دائماً isSuccess؛ الـ endpoints الأخرى قد لا تفعل.
        if ($response->has('isSuccess')) {
            return (bool) $response->get('isSuccess');
        }

        return true;
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
    private static function request(string $method, string $path, array $payload): \Illuminate\Support\Collection
    {
        $url = self::baseUrl() . $path;

        try {
            $request = Http::timeout(10)->withHeaders(['Accept' => 'application/json']);

            $resp = strtoupper($method) === 'POST'
                ? $request->post($url, $payload)
                : $request->get($url, $payload);

            if (! $resp->successful()) {
                $status = $resp->status();
                $code   = $resp->json('error.code');
                $msg    = $resp->json('error.message');

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

            return collect($resp->json());
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