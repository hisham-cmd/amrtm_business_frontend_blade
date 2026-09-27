<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * AmrtmMedia — يحوّل روابط الوسائط المطلقة القادمة من الباك اند
 * إلى روابط **نفس أصل الواجهة**، فتُقدَّم عبر /media/…).
 *
 * لماذا هذا ضروري؟
 * ---------------
 * الباك اند يخزّن الصورة كاسم ملف (`952da002-….webp`) ويبني الرابط عبر
 * url()‏ ← في الإنتاج يصبح مثل:
 *     https://amrtmbusiness.rf.gd/media/uploads/952da002-….webp
 * ف عندما تعرضه صفحة تعمل على نطاق آخر (business.amrtm.com.sa أو
 * 127.0.0.1:8001) يصير الطلب **عبر النطاقات**، فيرسل المتصفح ترويسة
 * Referer لنطاق الصفحة. حماية «منع السرقة الساخنة» (hotlink protection)
 * في لوحة تحكم الاستضافة (InfinityFree) ترفض بناءً على هذه الترويسة،
 * بينما فتح الصورة مباشرة في تبويب ينجح (لا Referer Tanniej) — وهو
 * بالضبط ما يُلاحظ: «تظهر الصورة إذا فتحتها، ولا تظهر في الصفحة».
 *
 * الحل: لا نطلب الصورة من نطاق الباك اند إطلاقاً. نطلبها من نطاق الصفحة
 * نفسها عبر المسار /media/… في الواجهة، فيتحوّل الطلب إلى same-origin
 * فلا Referer خارجي ولا حماية سرقة ساخنة ولا مشاكل CORS.
 */
class AmrtmMedia
{
    /**
     * المضيف/النطاقات التي تُعتبر «الباك اند» وتُحوَّل روابطها.
     * نبنيها من BACKEND_API_URL حتى لو تغيّر النطاق لاحقاً.
     *
     * @return array<int,string>
     */
    public static function backendHosts(): array
    {
        $hosts = [];

        $configured = (string) env('BACKEND_API_URL', '');
        if ($configured !== '') {
            $h = parse_url($configured, PHP_URL_HOST);
            if (is_string($h) && $h !== '') {
                $hosts[] = strtolower($h);
            }
        }

        // إعدادات التطبيق قد تحمل قيمة مختلفة
        $appUrl = (string) config('app.url', '');
        if ($appUrl !== '') {
            $h = parse_url($appUrl, PHP_URL_HOST);
            if (is_string($h) && $h !== '') {
                $hosts[] = strtolower($h);
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /**
     * يحوّل رابطاً واحداً إلى رابط same-origin إن كان يشير إلى الباك اند.
     * أي رابط آخر (أو رابط داخلي) يُعاد كما هو.
     */
    public static function url(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $trimmed = trim($url);

        // روابط داخلية أو data: أو blob: أو javascript: — تُترك
        if (! preg_match('#^https?://#i', $trimmed)) {
            return $url;
        }

        $host = strtolower((string) parse_url($trimmed, PHP_URL_HOST));

        if ($host === '' || ! in_array($host, self::backendHosts(), true)) {
            return $url;
        }

        $path = (string) parse_url($trimmed, PHP_URL_PATH);

        // المسارات التي نعرفها: /media/… و /storage/… و /images/…
        if (! Str::startsWith($path, ['/media/', '/storage/', '/images/'])) {
            return $url;
        }

        if (Str::startsWith($path, '/media/')) {
            // /media/uploads/x.webp ⇒ uploads/x.webp
            $relative = ltrim(Str::after($path, '/media/'), '/');
        } elseif (Str::startsWith($path, '/storage/')) {
            // /storage/homepage/slides/x.jpeg ⇒ homepage/slides/x.jpeg
            $relative = ltrim(Str::after($path, '/storage/'), '/');
        } else {
            /*
             * /images/... ملفات ثابتة (logo2.jpg، official-logo.jpg …).
             *
             * ⚠️ كان الكود يضيف 'uploads/' قبل أي مسار /images/، فحوّل
             * /images/slide-x.jpg (ملف غير موجود) إلى /media/uploads/slide-x.jpg
             * (وهو أيضاً غير موجود) ⇒ 404 بدقّة. لا نفترض أي مجلد:
             * نترك /images/ كما هو لأنه يُخدم مباشرة من نطاق الواجهة،
             * ولا نrewrite إلا ما هو فعلاً على قرص الباك اند.
             */
            return $url;
        }

        if ($relative === '') {
            return $url;
        }

        return route('amrtm.media.proxy', ['path' => $relative]);
    }

    /**
     * يحوّل بنية بيانات (مصفوفة/كائن) ويعيدها بعد تحويل كل روابط
     * الوسائط التي فيها — يحفظ استدعاءات متكررة في القوالب.
     * @param  mixed  $data
     * @return mixed
     */
    public static function rewrite($data)
    {
        /*
         * ⚠️ Collection ليست مصفوفة ولا كائن بيانات:
         * get_object_vars() عليها يُرجع الخصائص الداخلية
         * (items / escapeWhenCastingToString) لا المفاتيح/القيم.
         * لذلك نلفّها أولاً بـ ->all() وإلا لم تُحوَّل أي رابط.
         * (كان هذا سبب بقاء روابط الإنتاج المطلقة كما هي.)
         */
        if ($data instanceof \Illuminate\Support\Collection) {
            /*
             * نعيد Collection كما هو — لا مصفوفة — لأن المستدعين
             * (BackendApi::get) يتعاملون معه بـ ->get('key') و isSuccess()
             * الذي يفحص ->isEmpty()/->has(). إرجاع مصفوفة كان يجعل
             * categoryPage يفشل بـ 404 «التصنيف غير موجود».
             */
            return \Illuminate\Support\Collection::make(self::rewrite($data->all()));
        }

        /*
         * ترتيب الفحص مهم:
         *   1) إن لم تكن القيمة نصاً ⇒ نمرّرها بالاستدعاء الذاتي (بشكل متكرر).
         *   2) نص + المفتاح من مفاتيح الوسائط ⇒ نطبّع الرابط.
         *   3) نص + مفتاح عادي ⇒ نتركه.
         *
         * كان الفحص يبدأ من المفتاح، فحين كان المفتاح 'homepageMedia'
         * وقيمته **مصفوفة** (video_file + video_poster) كان يُستبدل بـ null
         * فتختفي بيانات الفيديو بالكامل. لذلك لا بدّ من فحص النوع أولاً.
         */

        if (is_array($data)) {
            $out = [];
            foreach ($data as $key => $value) {
                $out[$key] = self::rewriteValue((string) $key, $value);
            }
            return $out;
        }

        if (is_object($data)) {
            foreach (get_object_vars($data) as $key => $value) {
                $data->{$key} = self::rewriteValue((string) $key, $value);
            }
            return $data;
        }

        return $data;
    }

    /** يطبّع قيمة واحدة حسب نوعها ومفتاحها. */
    private static function rewriteValue(string $key, $value)
    {
        if (! is_string($value)) {
            // مصفوفة أو كائن ⇒ نمرّره كما هو (الروابط بداخله تُحوَّل)
            return self::rewrite($value);
        }

        if (self::looksLikeMediaKey($key)) {
            return self::url($value);
        }

        return $value;
    }

    /**
     * هل هذا المفتاح من المفاتيح التي تحمل رابط ملف/صورة؟
     *
     * غطّينا سابقاً image_url و logo فقط، فبقيت مفاتيح مثل
     * video_poster و video_file و file_url بلا تحويل — فكانت روابط
     * الإنتاج المطلقة تتسرّب إلى الواجهة.
     */
    private static function looksLikeMediaKey(string $key): bool
    {
        return (bool) preg_match(
            '/(image|images|logo|photo|avatar|banner|thumbnail|icon|poster|file|media|video|qr|cover|picture|attachment|src)$/i',
            $key
        ) || (bool) preg_match('/_url$/i', $key);
    }
}
