<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BackendApiWithToken — استدعاء الـ API مع توكن المصادقة.
 * يُستخدم للبيانات المحمية (لوحة المستخدم، الطلبات، الدفع).
 */
class BackendApiWithToken
{
    public function __construct(protected string $token) {}

    public function call(string $method, string $path, array $body = []): \Illuminate\Support\Collection
    {
        try {
            $client = Http::timeout(10)->withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ]);

            $resp = strtoupper($method) === 'POST'
                ? $client->post(BackendApi::baseUrl() . $path, $body)
                : $client->get(BackendApi::baseUrl() . $path);

            if (!$resp->successful()) {
                Log::warning("BackendApiWithToken {$method} {$path} => HTTP {$resp->status()}");

                return collect(['error' => ['message' => 'فشل الاتصال بالباك اند.']]);
            }

            return collect($resp->json());
        } catch (\Throwable $e) {
            Log::warning("BackendApiWithToken {$method} {$path} error: " . $e->getMessage());

            return collect(['error' => ['message' => $e->getMessage()]]);
        }
    }
}