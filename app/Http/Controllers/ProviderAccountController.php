<?php

namespace App\Http\Controllers;

use App\Support\BackendApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ProviderAccountController — إنشاء حساب مكتب/مستشار/عميل.
 *
 * الصفحة نفسها (/provider-account/create) موجودة في الواجهة الأمامية،
 * لكن مسار الإرسال كان stub يُعيد التوجيه للصفحة الرئيسية بلا تنفيذ،
 * فيختفي كل ما كتبه المستخدم دون أي رسالة.
 *
 * هذا الـ controller يمرّر الطلب كما هو إلى:
 *   POST {BACKEND_API_URL}/api/v1/provider-account
 * الذي ينشئ bs_offices + bs_office_users + bs_office_profiles في الباك اند.
 *
 * ملاحظة: نقطة /api/v1/provider-account محميّة بـ auth:sanctum، أي أنها
 * تتوقّع مستخدماً مسجّلاً. لذلك نمرّر Authorization إن كان في الجلسة،
 * ونستخدم نقطة auth/register البديلة عند غيابه (انظر storeViaRegister).
 */
class ProviderAccountController extends Controller
{
    /** الحقول التي يرسلها نموذج التسجيل */
    private const FIELDS = [
        // بيانات المكتب
        'name_ar', 'name_en', 'office_type', 'entity_type', 'subscription_type',
        'phone', 'mobile', 'email', 'password', 'password_confirmation', 'role',
        'office_code', 'business_activity', 'category', 'account_types',
        // العنوان
        'country', 'governorate', 'region', 'city', 'district', 'street',
        'building_number', 'office_number', 'nationality',
        // السجلات
        'cr_number', 'cr_expiry_date', 'license_number', 'license_expiry_date',
        'tax_number', 'trademark_registration_number',
        // الوصف
        'description_ar', 'description_en',
        // التخصصات والخدمات (hidden inputs متكررة)
        // ملاحظة: النموذج يرسل التخصصات باسم "specialties" (وهو ما يقرؤه
        // ProviderAccountController في الباك اند: input('specialties', []))
        // لا باسم specialty_ids — لذلك يجب تمريره كمصفوفة كما هو.
        'specialties', 'categories', 'specialty_ids', 'service_ids', 'custom_specialty',
        // مستخدم المكتب
        'user_name', 'user_email', 'user_phone',
        // عام
        'account_type', 'id_number', 'legal_name', 'handled_cases',
        'profile_completed', 'accepted_terms', 'mode',
    ];

    /** الحقول التي قد تأتي كمصفوفات (multi-select) */
    private const ARRAY_FIELDS = [
        'specialties', 'categories', 'specialty_ids', 'service_ids', 'account_types',
        'custom_services', 'custom_fields', 'handled_cases',
    ];

    /**
     * حقول يرسلها النموذج كقيمة مفردة لكن الباك اند يقرأها كمصفوفة.
     *
     * النموذج يستخدم <select name="specialty"> (مفرد) بينما
     * ProviderAccountController في الباك اند يقرأ input('specialties', []).
     * لذلك نرسل التخصص المختار مرتين: specialty[] للمصفوفة وspecialty للمفردة.
     */
    private const SINGLE_TO_ARRAY = [
        'specialty' => 'specialties',
    ];

    /**
     * POST /provider-account — إنشاء حساب مقدم خدمة.
     */
    public function store(Request $request)
    {
        $payload = $this->payload($request);

        // لا يوجد بريد أو كلمة مرور = ناقص، نُعيد النموذج مع تنبيه
        if (empty($payload['email']) || empty($payload['password'])) {
            return redirect()
                ->route('amrtm.provider.account.create', $this->backParams($request))
                ->withErrors(['email' => 'البريد الإلكتروني وكلمة المرور مطلوبان.'])
                ->withInput();
        }

        $token = session('amrtm_api_token');

        $files = $this->files($request);

        try {
            $response = $token
                ? $this->callWithToken($token, $payload, $files)
                : $this->callBackend($payload, $files);
        } catch (\Throwable $e) {
            return $this->fail($request, collect([
                'error' => ['message' => 'تعذر الاتصال بالخادم: ' . $e->getMessage()],
            ]));
        }

        if (! BackendApi::isSuccess($response)) {
            return $this->fail($request, $response);
        }

        /*
        |----------------------------------------------------------------------
        | بعد التسجيل الناجح
        |----------------------------------------------------------------------
        | الطلب الجديد بانتظار مراجعة الإدارة، ومكتبه ما زال pending، لذا
        | التوجيه إلى لوحة المكتب يجعله restricted فوراً. نوديه إلى صفحة
        | الدخول مع رسالة توضّح أن الطلب مُرسل وأن الحساب يُفعّل بعد المراجعة.
        |
        | القالب يرسل عبر fetch (Accept: application/json) فيتوقع JSON فيه
        | redirect — لذلك نردّ JSON في تلك الحالة، و302 في غيرها.
        */

        session()->forget('amrtm_api_token');
        session()->regenerate();

        $message = 'تم إرسال طلب تسجيل المكتب بنجاح. سيتم تفعيل حسابك بعد مراجعة الإدارة.';

        /*
         * في وضع التضمين نوجّه الـ iframe إلى صفحة النموذج نفسها (embed)
         * بدل /login — وإلا استبدلت النافذة المنبثقة محتواها بصفحة دخول
         * كاملة. الصفحة الهدف تعرض بطاقة النجاح وتُعلم النافذة الأم.
         */
        $redirectUrl = $this->wantsEmbed($request)
            ? route('amrtm.provider.account.create', $this->embedParams($request))
            : route('amrtm.login');

        if ($request->expectsJson()) {
            // نخزّن الرسالة في الجلسة أيضاً لأن التحويل بعدها إلى الجلسة
            // (fetch ثم window.location) سيقرأها flash عند فتح /login.
            session()->flash('success', $message);

            return response()->json([
                'isSuccess'  => true,
                'message'    => $message,
                'redirect'   => $redirectUrl,
                'value'      => [
                    'redirect' => $redirectUrl,
                ],
                'statusCode' => 201,
            ], 201);
        }

        if ($this->wantsEmbed($request)) {
            return redirect()
                ->route('amrtm.provider.account.create', $this->embedParams($request))
                ->with('success', $message);
        }

        return redirect()
            ->route('amrtm.login')
            ->with('success', $message);
    }

    /** هل الطلب قادم من داخل النافذة المنبثقة؟ */
    private function wantsEmbed(Request $request): bool
    {
        return $request->boolean('embed') || $request->boolean('return_embed');
    }

    /** معاملات الرابط للعودة إلى صفحة النموذج داخل النافذة. */
    private function embedParams(Request $request): array
    {
        $params = [
            'type'  => $request->input('mode', 'office') ?: 'office',
            'embed' => 1,
        ];

        // عميل: نحفظ نوع الحساب المختار حتى تعود الحالة كما كانت
        if ($params['type'] === 'client') {
            $accountType = (string) $request->input('account_type', 'individual');
            $params['account_type'] = in_array($accountType, ['establishment', 'individual'], true)
                ? $accountType
                : 'individual';
        }

        return $params;
    }

    /**
     * يرسل الطلب مع التوكن و multipart للملفات.
     */
    private function callWithToken(string $token, array $payload, array $files = []): \Illuminate\Support\Collection
    {
        $url = BackendApi::baseUrl() . '/api/v1/provider-account';

        $resp = \App\Support\BackendHttp::send(
            function (string $u, ?string $cookie) use ($payload, $token, $files) {
                $req = \Illuminate\Support\Facades\Http::timeout(60)
                    ->withHeaders(array_filter([
                        'Accept'        => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'Cookie'        => $cookie,
                    ]));

                return $this->sendBody($req, $u, $payload, $files);
            },
            $url,
        );

        return $this->toCollection($resp);
    }

    /** يرسل الطلب بلا توكن (التسجيل نفسه) مع multipart للملفات. */
    private function callBackend(array $payload, array $files = []): \Illuminate\Support\Collection
    {
        $url = BackendApi::baseUrl() . '/api/v1/provider-account';

        $resp = \App\Support\BackendHttp::send(
            function (string $u, ?string $cookie) use ($payload, $files) {
                $req = \Illuminate\Support\Facades\Http::timeout(60)
                    ->withHeaders(array_filter([
                        'Accept' => 'application/json',
                        'Cookie' => $cookie,
                    ]));

                return $this->sendBody($req, $u, $payload, $files);
            },
            $url,
        );

        return $this->toCollection($resp);
    }

    /** يرسل البيانات نصّية أو multipart حسب وجود ملفات. */
    private function sendBody($req, string $url, array $payload, array $files)
    {
        if (! empty($files)) {
            $req = $req->asMultipart();

            foreach ($payload as $key => $value) {
                if (is_array($value)) {
                    // multipart لا يقبل مصفوفات: نحوّلها إلى payload[name][] الصيغة التي
                    // يعيد بناءها Laravel في Request::input() كمصفوفة
                    foreach ($value as $item) {
                        $req = $req->attach($key . '[]', (string) $item);
                    }
                    continue;
                }

                $req = $req->attach($key, (string) $value);
            }

            foreach ($files as $name => $file) {
                $req = $req->attach($name, $file->get(), $file->getClientOriginalName());
            }

            return $req->post($url);
        }

        return $req->post($url, $payload);
    }

    /** يحوّل استجابة Http إلى Collection موحّد الشكل. */
    private function toCollection($resp): \Illuminate\Support\Collection
    {
        if ($resp->successful()) {
            return collect($resp->json() ?: []);
        }

        $errors = $resp->json('errors');
        $error  = [
            'message' => $resp->json('error.message') ?: $resp->json('message') ?: 'تعذر إنشاء الحساب (' . $resp->status() . ').',
            'code'    => $resp->json('error.code') ?: 'BACKEND_ERROR',
        ];

        if (is_array($errors)) {
            // تحويل أخطاء التحقق Laravel (key => [messages]) إلى نفس شكل معالجنا
            $error['fields'] = $errors;
        }

        return collect([
            'isSuccess'  => false,
            'value'      => null,
            'statusCode' => $resp->status(),
            'error'      => $error,
        ]);
    }

    /** يجمع الملفات المرفوعة الصالحة */
    private function files(Request $request): array
    {
        $out = [];

        foreach ($request->allFiles() as $key => $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                $out[$key] = $file;
            }
        }

        return $out;
    }

    /**
     * إعادة التوجيه مع الأخطاء على الصورة الصحيحة.
     *
     * القالب يرسل عبر fetch مع Accept: application/json، فلو أعدنا 302
     * لتجاهله المتصفح وفُقدت كل الأخطاء فبدا للمستخدم "لا شيء حدث".
     * لذلك نردّ JSON بنفس شكل الـ API عند طلب JSON، و302 للنماذج العادية.
     */
    private function fail(Request $request, \Illuminate\Support\Collection $response)
    {
        $error = $response->get('error');
        $message = is_array($error)
            ? ($error['message'] ?? 'تعذر إنشاء الحساب.')
            : ($error ?: 'تعذر إنشاء الحساب.');

        $fields = is_array($error) ? ($error['fields'] ?? []) : [];

        // أخطاء التحقق من الباك اند تصل كمصفوفة قيم، نوحّدها إلى نص لكل حقل
        $fieldErrors = [];
        foreach ($fields as $field => $messages) {
            $fieldErrors[$field] = is_array($messages) ? (string) ($messages[0] ?? $message) : (string) $messages;
        }

        // رسالة عامة ظاهرة أعلى النموذج، لأن عرض الأخطاء بالحقول يعتمد على
        // markup القالب وقد لا يغطي كل الحقول القادمة من الـ API.
        $summary = $fieldErrors
            ? 'تعذر إرسال الطلب: ' . implode(' • ', array_slice(array_values($fieldErrors), 0, 3))
            : $message;

        if ($request->expectsJson()) {
            return response()->json([
                'isSuccess'  => false,
                'message'    => $message,
                'errors'     => $fieldErrors,
                'error'      => [
                    'message' => $message,
                    'fields'  => $fieldErrors,
                    'code'    => 'VALIDATION_FAILED',
                ],
                'statusCode' => (int) ($response->get('statusCode') ?: 422),
            ], (int) ($response->get('statusCode') ?: 422));
        }

        return redirect()
            ->route('amrtm.provider.account.create', $this->backParams($request))
            ->with('error', $summary)
            ->withErrors($fieldErrors ?: ['email' => $message])
            ->withInput();
    }

    /**
     * معاملات العودة إلى صفحة النموذج: تحترم وضع التضمين (embed)
     * وتحفظ type مع account_type حتى لا يرجع النموذج لحالة أخرى.
     */
    private function backParams(Request $request): array
    {
        if ($this->wantsEmbed($request)) {
            return $this->embedParams($request);
        }

        return ['type' => $request->input('mode', 'office') ?: 'office'];
    }

    /** يبني الحمولة من الطلب مع تحويل الحقول المتكررة إلى مصفوفات */
    private function payload(Request $request): array
    {
        $payload = [];

        foreach (self::FIELDS as $field) {
            $value = $request->input($field);
            if ($value === null) {
                continue;
            }
            $payload[$field] = is_array($value) ? array_values(array_filter($value, 'strlen')) : $value;
        }

        // الحقول التي تصل كـ name[] من الـ hidden inputs
        foreach (self::ARRAY_FIELDS as $field) {
            $value = $request->input($field);
            if (is_array($value)) {
                $payload[$field] = array_values(array_filter(
                    array_map(fn ($v) => is_string($v) ? trim($v) : $v, $value),
                    fn ($v) => $v !== '' && $v !== null
                ));
            }
        }

        /*
        |----------------------------------------------------------------------
        | تحويل الحقول المفردة إلى مصفوفات
        |----------------------------------------------------------------------
        | النموذج يرسل <select name="specialty"> بقيمة واحدة، والباك اند يقرأ
        | input('specialties', []) كمصفوفة — فلم يكن يصله أي تخصص ويُرفض الطلب
        | بـ "يرجى اختيار تخصص واحد على الأقل". نحوّلها هنا.
        */

        foreach (self::SINGLE_TO_ARRAY as $single => $target) {
            if (! empty($payload[$target])) {
                continue; // مصفوفة جاهزة من الـ hidden inputs
            }

            $value = $payload[$single] ?? $request->input($single);

            if ($value === null || $value === '' || $value === 'other') {
                continue;
            }

            $payload[$target] = is_array($value) ? $value : [$value];
        }

        return $payload;
    }
}
