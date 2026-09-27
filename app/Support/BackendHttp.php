<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * BackendHttp — غلاف حول نداءات الباك اند يتعامل تلقائياً مع
 * صفحة تحدي مكافحة الروبوتات في استضافة InfinityFree.
 *
 * كيف تعمل الحماية على الاستضافة؟
 *   أول طلب لأي مسار يُجاب بصفحة HTML صغيرة تحتوي aes.js تحسب كوكي
 *   "__test" بـ AES-128-CBC داخل المتصفح ثم تعيد الطلب — نحن نحسب
 *   نفس الكوكي في PHP (slowAES وضع 2 = CBC) ونعيد الطلب فوراً.
 *   الكوكي صالح 6 ساعات فنخزنه في الكاش ونعيد استخدامه.
 *
 * ⚠️ لماذا هذا الملفمعدّل؟
 *   كان Cache::get/put غير محميين. على الاستضافة CACHE_STORE=database،
 *   فأي عطل في اتصال قاعدة البيانات كان **يُسقط الطلب كله** ويُرجع
 *   PROXY_ERROR للواجهة رغم أن الباك اند سليم تماماً. الآن:
 *     1) كل عملية كاش داخل try/catch — فشل الكاش لا يُفشل الطلب.
 *     2) عند فشل النداء نحاول مرة ثانية **بلا كوكي** (قد يكون الكوكي
 *        القديم هو سبب الرفض لا الت��دي).
 *     3) عند فشل النداء نُسجّل سبباً واضحاً في السجل بدل رسالة غامضة.
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
     * @param \Closure(string $url, ?string $cookie): Response $build
     */
    public static function send(\Closure $build, string $url): Response
    {
        $cookie = self::cookieHeader();

        $resp = $build($url, $cookie);

        if (! self::isChallenge($resp)) {
            return $resp;
        }

        /*
         * وصلتنا صفحة تحدي، ف نحلّها ونعيد الطلب بالكوكي الجديد.
         * إن تكرّر التحدي رغم الكوكي الجديد فالكوكي المخزَّن كان فاسداً
         * (تغيير APP_KEY مثلاً) ⇒ ننفضّه ونعيد الطلب بلا كوكي.
         */
        $solved = self::solveChallenge((string) $resp->body());

        if ($solved !== null) {
            // الكوكي على الاستضافة max-age=21600 (6 ساعات)
            self::rememberCookie($solved);

            $retry = $build($url, '__test=' . $solved);

            if (! self::isChallenge($retry)) {
                return $retry;
            }

            // الكوكي الجديد لم يُقبل أيضاً — ننفض الكاش ونجرب بلا كوكي
            self::forgetCookie();
        } else {
            self::forgetCookie();
        }

        return $build($url, null);
    }

    /**
     * قراءة الكوكي من الكاش بشكل آمن.
     * فشل الكاش (قاعدة بيانات غير متاحة مثلاً) لا يجوز أن يُفشل الطلب.
     */
    private static function cookieHeader(): ?string
    {
        $value = self::readCookie();

        return is_string($value) && $value !== '' ? '__test=' . $value : null;
    }

    private static function readCookie(): ?string
    {
        try {
            $v = Cache::get(self::cookieCacheKey());
        } catch (\Throwable $e) {
            Log::warning('BackendHttp: تعذّرت قراءة كوكي الباك اند من الكاش: ' . $e->getMessage());

            return null;
        }

        return is_string($v) ? $v : null;
    }

    private static function rememberCookie(string $value): void
    {
        try {
            Cache::put(self::cookieCacheKey(), $value, now()->addHours(6));
        } catch (\Throwable $e) {
            // لا(cache) - سيُحلّ الت��دي في كل طلب، وهو أبطأ لكن يعمل
            Log::warning('BackendHttp: تعذّر حفظ كوكي الباك اند في الكاش: ' . $e->getMessage());
        }
    }

    private static function forgetCookie(): void
    {
        try {
            Cache::forget(self::cookieCacheKey());
        } catch (\Throwable) {
            // تجاهُل — لا يؤثر على النتيجة
        }
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

        // hex2bin يفشل على مدخل غير صالح ⇒ نُرجع null بدل تحذير
        $key  = @hex2bin($keyHex);
        $iv   = @hex2bin($ivHex);
        $data = @hex2bin($dataHex);

        if ($key === false || $iv === false || $data === false) {
            return null;
        }

        $plain = openssl_decrypt(
            $data,
            'aes-128-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );

        return $plain === false ? null : bin2hex($plain);
    }
}
