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
     * عند الفشل تُرجع مجموعة فارغة + رسالة الخطأ في سجل اللوج،
     * بلا أي بيانات بديلة أو افتراضية.
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
                $error = $resp->json('error.message') ?? ('HTTP ' . $resp->status());
                Log::warning("BackendApi {$method} {$path} => HTTP {$resp->status()}: {$error}");

                return collect();
            }

            return collect($resp->json());
        } catch (\Throwable $e) {
            Log::warning("BackendApi {$method} {$path} error: " . $e->getMessage());

            return collect();
        }
    }
}