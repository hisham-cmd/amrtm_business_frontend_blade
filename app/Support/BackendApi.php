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
        try {
            $resp = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get(self::baseUrl() . $path, $query);

            if (!$resp->successful()) {
                Log::warning("BackendApi GET {$path} => HTTP {$resp->status()}");

                return collect();
            }

            return collect($resp->json());
        } catch (\Throwable $e) {
            Log::warning("BackendApi GET {$path} error: " . $e->getMessage());

            return collect();
        }
    }

    public static function post(string $path, array $body = []): \Illuminate\Support\Collection
    {
        try {
            $resp = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->post(self::baseUrl() . $path, $body);

            if (!$resp->successful()) {
                Log::warning("BackendApi POST {$path} => HTTP {$resp->status()}");

                return collect();
            }

            return collect($resp->json());
        } catch (\Throwable $e) {
            Log::warning("BackendApi POST {$path} error: " . $e->getMessage());

            return collect();
        }
    }
}