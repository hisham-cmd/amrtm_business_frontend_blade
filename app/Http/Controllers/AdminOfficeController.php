<?php

namespace App\Http\Controllers;

use App\Support\BackendApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * AdminOfficeController — تعديل بيانات مكتب قائم من لوحة الأدمن.
 *
 * الخلفية: صفحة «إدارة المكاتب» في لوحة الأدمن كان زر «تعديل» ينقل إلى
 * /admin/offices/{id}/edit-form، وهو مسار يعرض لوحة الأدمن نفسها بلا أي
 * نموذج تعديل — أي أن الضغط لا يفعل شيئاً. كما لا يوجد endpoint تعديل
 * في الباك اند أصلاً.
 *
 * هذا الـ controller يمرّر نموذج «إنشاء الحساب» نفسه (بوضع التعديل) إلى:
 *   PUT {BACKEND_API_URL}/api/v1/admin/offices/{id}
 *
 * يُستدعى من داخل النافذة المنبثقة (iframe) في لوحة الأدمن، لذلك يردّ
 * JSON عند طلب JSON و302 + withErrors عند الطلب العادي، تماماً مثل
 * ProviderAccountController.
 */
class AdminOfficeController extends Controller
{
    /**
     * حقول نموذج المكتب (نفس قائمة ProviderAccountController::FIELDS)
     * مع استبعاد حقول إنشاء الحساب التي لا معنى لتعديلها.
     */
    private const FIELDS = [
        'name_ar', 'name_en', 'office_type', 'entity_type',
        'phone', 'mobile', 'email', 'office_code',
        'business_activity', 'category', 'account_types',
        'country', 'governorate', 'region', 'city', 'district', 'street',
        'building_number', 'office_number', 'nationality',
        'cr_number', 'cr_expiry_date', 'license_number', 'license_expiry_date',
        'tax_number', 'trademark_registration_number',
        'description_ar', 'description_en',
        'specialties', 'categories', 'specialty_ids', 'service_ids', 'custom_specialty',
        'legal_name', 'id_number', 'handled_cases',
    ];

    /** حقول تصل كمصفوفات (multi-select) */
    private const ARRAY_FIELDS = [
        'specialties', 'categories', 'specialty_ids', 'service_ids', 'account_types',
        'custom_services', 'handled_cases',
    ];

    /**
     * PUT /admin/offices/{id}
     */
    public function update(Request $request, int $id)
    {
        $payload = $this->payload($request);

        if (empty($payload)) {
            return $this->fail($request, 'لا توجد بيانات قابلة للتحديث.');
        }

        $files = $this->files($request);
        $token = session('amrtm_api_token');

        try {
            $response = $this->callUpdate($id, $payload, $files, $token);
        } catch (\Throwable $e) {
            return $this->fail($request, 'تعذر الاتصال بالخادم: ' . $e->getMessage());
        }

        if (! BackendApi::isSuccess($response)) {
            return $this->fail($request, $this->errorMessage($response));
        }

        $message = 'تم تحديث بيانات المكتب بنجاح.';

        if ($request->expectsJson()) {
            return response()->json([
                'isSuccess'  => true,
                'message'    => $message,
                'redirect'   => null,
                'value'      => ['id' => $id],
                'statusCode' => 200,
            ], 200);
        }

        // الرجوع لنفس صفحة التعديل مضمّنةً في النافذة المنبثقة
        return redirect()
            ->route('amrtm.provider.account.create', [
                'type'   => $request->input('mode', 'office'),
                'embed'  => 1,
                'edit'   => $id,
            ])
            ->with('success', $message);
    }

    /** يرسل PUT مع multipart للملفات وتوكن الجلسة إن وُجد. */
    private function callUpdate(int $id, array $payload, array $files, ?string $token): \Illuminate\Support\Collection
    {
        $url = BackendApi::baseUrl() . '/api/v1/admin/offices/' . $id;

        $resp = \App\Support\BackendHttp::send(
            function (string $u, ?string $cookie) use ($payload, $files, $token) {
                $req = Http::timeout(60)->withHeaders(array_filter([
                    'Accept'        => 'application/json',
                    'Authorization' => $token ? 'Bearer ' . $token : null,
                    'Cookie'        => $cookie,
                ]));

                if (empty($files)) {
                    return $req->put($u, $payload);
                }

                $req = $req->asMultipart();

                foreach ($payload as $key => $value) {
                    if (is_array($value)) {
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

                return $req->put($u);
            },
            $url,
        );

        return $this->toCollection($resp);
    }

    /** يبني الحمولة من الطلب (نفس منطق ProviderAccountController::payload). */
    private function payload(Request $request): array
    {
        $payload = [];

        foreach (self::FIELDS as $field) {
            $value = $request->input($field);
            if ($value === null) {
                continue;
            }
            $payload[$field] = is_array($value)
                ? array_values(array_filter($value, 'strlen'))
                : $value;
        }

        foreach (self::ARRAY_FIELDS as $field) {
            $value = $request->input($field);
            if (is_array($value)) {
                $payload[$field] = array_values(array_filter(
                    array_map(fn ($v) => is_string($v) ? trim($v) : $v, $value),
                    fn ($v) => $v !== '' && $v !== null
                ));
            }
        }

        return $payload;
    }

    /** يجمع الملفات المرفوعة الصالحة. */
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

    /** يستخرج رسالة الخطأ من استجابة الباك اند. */
    private function errorMessage(\Illuminate\Support\Collection $response): string
    {
        $error = $response->get('error');
        if (is_array($error)) {
            return (string) ($error['message'] ?? 'تعذر تحديث بيانات المكتب.');
        }

        return (string) ($error ?: 'تعذر تحديث بيانات المكتب.');
    }

    /** يحوّل استجابة Http إلى Collection موحّد الشكل. */
    private function toCollection($resp): \Illuminate\Support\Collection
    {
        if ($resp->successful()) {
            return collect($resp->json() ?: []);
        }

        $errors = $resp->json('errors');
        $error  = [
            'message' => $resp->json('error.message') ?: $resp->json('message') ?: 'تعذر تحديث بيانات المكتب (' . $resp->status() . ').',
            'code'    => $resp->json('error.code') ?: 'BACKEND_ERROR',
        ];

        if (is_array($errors)) {
            $error['fields'] = $errors;
        }

        return collect([
            'isSuccess'  => false,
            'value'      => null,
            'statusCode' => $resp->status(),
            'error'      => $error,
        ]);
    }

    /**
     * إرجاع الأخطاء على صفحة التعديل نفسها.
     *
     * نحتفظ بـ type/embed/edit في الرابط حتى تعود النافذة المنبثقة إلى
     * نفس الحالة (النوع المختار + المكتب المُعدَّل).
     */
    private function fail(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'isSuccess'  => false,
                'message'    => $message,
                'errors'     => [],
                'error'      => ['message' => $message, 'code' => 'UPDATE_FAILED'],
                'statusCode' => 422,
            ], 422);
        }

        return redirect()
            ->route('amrtm.provider.account.create', [
                'type'  => $request->input('mode', 'office'),
                'embed' => 1,
                'edit'  => (int) $request->route('id'),
            ])
            ->with('error', $message)
            ->withErrors(['name_ar' => $message])
            ->withInput();
    }
}
