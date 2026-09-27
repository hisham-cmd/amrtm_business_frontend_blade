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
            /*
             | الاستضافة (InfinityFree) تحذف ترويسة Authorization قبل PHP —
             | يُرسل التوكن في ترويسة مخصصة يفهمها الباك اند أيضاً.
             | وكل النداءات تمر عبر BackendHttp لفك تحدي aes.js تلقائياً
             | (بدونه تُستلم صفحة التحدي HTML كأنها استجابة ناجحة — وهذه
             | كانت سبب فشل صامت في لوحة الأدمن).
             */
            $isPost = strtoupper($method) === 'POST';
            $build  = function (string $u, ?string $cookie) use ($isPost, $body) {
                $req = Http::timeout(10)->withHeaders(array_filter([
                    'Accept'         => 'application/json',
                    'Authorization'  => 'Bearer ' . $this->token,
                    'X-AMRTM-TOKEN'  => $this->token,
                    'Cookie'         => $cookie,
                ]));

                return $isPost ? $req->post($u, $body) : $req->get($u);
            };

            $resp = \App\Support\BackendHttp::send($build, BackendApi::baseUrl() . $path);

            return $this->wrap($resp, $method, $path);
        } catch (\Throwable $e) {
            return $this->fail($e, $method, $path);
        }
    }

    /**
     * استدعاء يرسل ملفات (multipart/form-data) مع توكن المصادقة.
     * لازم Kuazle/guzzle يضبط الـ boundary بنفسه، فلا نثبّت Content-Type.
     */
    public function callMultipart(string $method, string $path, array $fields = [], array $files = []): \Illuminate\Support\Collection
    {
        try {
            $client = Http::timeout(60)->withHeaders([
                'Accept'         => 'application/json',
                'Authorization'  => 'Bearer ' . $this->token,
                'X-AMRTM-TOKEN'  => $this->token,
            ]);

            $payload = [];

            foreach ($files as $key => $file) {
                if (! $file instanceof \Illuminate\Http\UploadedFile || ! $file->isValid()) {
                    continue;
                }

                $path_ = $file->getRealPath();
                if ($path_ === false || ! is_readable($path_)) {
                    continue;
                }

                $payload[] = [
                    'name'     => $key,
                    'contents' => fopen($path_, 'rb'),
                    'filename' => $file->getClientOriginalName(),
                    'headers'  => ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream'],
                ];
            }

            foreach ($fields as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $sub) {
                        if (is_scalar($sub) || $sub === null) {
                            $payload[] = ['name' => $key . '[]', 'contents' => (string) $sub];
                        }
                    }

                    continue;
                }

                if (is_scalar($value) || $value === null) {
                    $payload[] = ['name' => $key, 'contents' => (string) $value];
                }
            }

            $resp = strtoupper($method) === 'PUT'
                ? $client->asMultipart()->put(BackendApi::baseUrl() . $path, $payload)
                : $client->asMultipart()->post(BackendApi::baseUrl() . $path, $payload);

            return $this->wrap($resp, $method, $path);
        } catch (\Throwable $e) {
            return $this->fail($e, $method, $path);
        }
    }

    private function wrap($resp, string $method, string $path): \Illuminate\Support\Collection
    {
        if (! $resp->successful()) {
            Log::warning("BackendApiWithToken {$method} {$path} => HTTP {$resp->status()}");

            return collect([
                'error' => ['message' => $resp->json('error.message') ?: 'فشل الاتصال بالباك اند.'],
                // إشارة صريحة: التوكن غير صالح (بخلاف أخطاء الشبكة/الخادم)
                'unauthorized' => in_array($resp->status(), [401, 419], true),
                'statusCode'   => $resp->status(),
            ]);
        }

        return collect($resp->json() ?: []);
    }

    private function fail(\Throwable $e, string $method, string $path): \Illuminate\Support\Collection
    {
        Log::warning("BackendApiWithToken {$method} {$path} error: " . $e->getMessage());

        return collect([
            'error' => ['message' => $e->getMessage()],
            'unauthorized' => false,
        ]);
    }
}
