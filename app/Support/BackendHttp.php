<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/**
 * BackendHttp — غلاف حول نداءات الباك اند يتعامل تلقائياً مع
 * صفحة تحدي مكافحة الروبوتات في استضافة InfinityFree.
 *
 * كيف تعمل الحماية على الاستضافة؟
 *   أول طلب لأي مسار يُجاب بصفحة HTML صغيرة تحتوي aes.js تحسب كوكي
 *   "__test" بـ AES-128-CBC داخل المتصفح ثم تعيد الطلب — نحن نحسب
 *   نفس الكوكي في PHP (slowAES وضع 2 = CBC) ونعيد الطلب فوراً.
 *   الكوكي صالح 6 ساعات فنخزنه في الكاش ونعيد استخدامه.
 */
class BackendHttp
{
    /** مفتاح الكاش الذي يحفظ قيمة كوكي المرور. */
    public static function cookieCacheKey(): string
    {
        return 'amrtm.backend.__test_cookie';
    }

    /**
     * تنفيذ طلب للباك اند مع فك تحدي aes.js تلقائياً عند ظهوره.
     *
     * $build يستقبل (الرابط، كوكي المرور أو null) ويعيد الاستجابة.
     *
     * مثال:
     *   BackendHttp::send(
     *       fn (string $url, ?string $cookie) =>
     *           Http::timeout(10)->withHeaders($cookie ? ['Cookie' => $cookie] : [])->get($url),
     *       $url,
     *   );
     *
     * @param \Closure(string $url, ?string $cookie): Response $build
     */
    public static function send(\Closure $build, string $url): Response
    {
        $cookieValue = Cache::get(self::cookieCacheKey());
        $cookie      = $cookieValue ? '__test=' . $cookieValue : null;

        $resp = $build($url, $cookie);

        if (! self::isChallenge($resp)) {
            return $resp;
        }

        $solved = self::solveChallenge($resp->body());

        if ($solved === null) {
            // تعذر فك التحدي — نُرجع صفحة التحدي كما هي (المتصل سيرى HTML)
            return $resp;
        }

        // الكوكي على الاستضافة max-age=21600 (6 ساعات)
        Cache::put(self::cookieCacheKey(), $solved, now()->addHours(6));

        return $build($url, '__test=' . $solved);
    }

    /** هل هذه الاستجابة صفحة تحدي aes.js وليست JSON حقيقياً؟ */
    public static function isChallenge(Response $resp): bool
    {
        $body = (string) $resp->body();

        return str_contains($body, '/aes.js') && str_contains($body, 'toNumbers(');
    }

    /**
     * حساب قيمة الكوكي من صفحة التحدي.
     * الصفحة: slowAES.decrypt(c, 2, a, b) حيث a=المفتاح، b=الـ IV، c=المشفَّر، 2=CBC.
     */
    public static function solveChallenge(string $body): ?string
    {
        if (! preg_match_all('/toNumbers\("([0-9a-fA-F]+)"\)/', $body, $m) || count($m[1]) < 3) {
            return null;
        }

        [$keyHex, $ivHex, $dataHex] = array_slice($m[1], 0, 3);

        $plain = openssl_decrypt(
            hex2bin($dataHex),
            'aes-128-cbc',
            hex2bin($keyHex),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            hex2bin($ivHex)
        );

        return $plain === false ? null : bin2hex($plain);
    }
}
