<?php

namespace App\Http\Controllers;

use App\Support\BackendApi;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AuthController — تسجيل الدخول / إنشاء حساب (نسخة نظيفة عبر الـ API).
 *
 * لا توجد قاعدة بيانات محلية: المصادقة تتم في سيرفر الباك اند عبر
 * POST /api/v1/auth/login و POST /api/v1/auth/register.
 * التوكن الناتج يُحفظ في Cookie (مشفَّر) ويرافَق كل الطلبات لاحقاً.
 */
class AuthController extends Controller
{
    /** معروض صفحة تسجيل الدخول (amrtm.login) */
    public function showLogin(): View
    {
        return view('amrtm.auth.login');
    }

    /** معروض صفحة إنشاء حساب (amrtm.register) */
    public function showRegister(): View
    {
        return view('amrtm.auth.register');
    }

    /** معالجة POST الدخول → يرسل إلى الـ API ويخزّن التوكن */
    public function submit(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = BackendApi::post('/api/v1/auth/login', [
            'email'    => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        // استخراج التوكن من المغلف { isSuccess, value: { token }, error }
        $value = $data->get('value');
        $token = is_array($value) ? ($value['token'] ?? null) : ($data->get('token') ?? null);
        if (!$token && $value instanceof \Illuminate\Support\Collection) {
            $token = $value->get('token');
        }
        if (!$token) {
            $err  = $data->get('error');
            $msg  = is_array($err) ? ($err['message'] ?? 'فشل الدخول.') : 'فشل الدخول.';

            // التوجيه الصريح بدل back() — مضمون كنوع RedirectResponse (يتفادى TypeError)
            return redirect()->route('amrtm.login')
                ->withErrors(['email' => $msg])
                ->withInput();
        }

        // تخزين التوكن في الجلسة (آمن، يمر عبر Laravel session مع web middleware)
        session(['amrtm_api_token' => $token]);

        // توجيه الوجهة: قيمة غير فارغة من النموذج، وإلا لوحة المستخدم
        $redirect = trim((string) $request->input('redirect', ''));
        if ($redirect === '') {
            $redirect = route('amrtm.user.dashboard');
        }

        // نضمن نصاً غير فارغ دائماً → redirect() تعيد RedirectResponse
        return redirect($redirect);
    }

    /** معالجة POST التسجيل (إنشاء حساب عميل) */
    public function register(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = BackendApi::post('/api/v1/auth/register', [
            'name'                  => $request->input('name'),
            'email'                 => $request->input('email'),
            'phone'                 => $request->input('phone'),
            'password'              => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation', $request->input('password')),
            'account_type'          => $request->input('account_type', 'individual'),
        ]);

        // استخراج التوكن من المغلف (نفس منطق login)
        $value = $data->get('value');
        $token = is_array($value) ? ($value['token'] ?? null) : ($data->get('token') ?? null);
        if (!$token && $value instanceof \Illuminate\Support\Collection) {
            $token = $value->get('token');
        }
        if (!$token) {
            $err  = $data->get('error');
            $msg  = is_array($err) ? ($err['message'] ?? 'تعذر إنشاء الحساب.') : 'تعذر إنشاء الحساب.';

            return redirect()->route('amrtm.register')
                ->withErrors(['email' => $msg])
                ->withInput();
        }

        session(['amrtm_api_token' => $token]);

        return redirect()->route('amrtm.index')->with('success', 'تم إنشاء حسابك بنجاح!');
    }

    /** تسجيل الخروج — مسح جلسة التوكن */
    public function logout(): \Illuminate\Http\RedirectResponse
    {
        session()->forget('amrtm_api_token');

        return redirect()->route('amrtm.index');
    }

    /*
    |--------------------------------------------------------------------------
    | لوحات المستخدم والعمليات — تُظهر صفحات العرض مع تفاصيل الـ API
    | في حال وجود توكن، وإلا وجّهت لتسجيل الدخول (مطابق للواجهة القديمة).
    |--------------------------------------------------------------------------
    */

    private function token(): ?string
    {
        return session('amrtm_api_token');
    }

    private function callAuthed(string $method, string $path, array $body = []): \Illuminate\Support\Collection
    {
        $token = $this->token();
        if (!$token) {
            return collect(['error' => ['message' => 'يجب تسجيل الدخول أولاً.']]);
        }

        return (new \App\Support\BackendApiWithToken($token))->call($method, $path, $body);
    }

    /** لوحة المستخدم — GET /api/v1/dashboard/user + /requests + /payments/history */
    public function dashboard(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        $stats    = $this->callAuthed('GET', '/api/v1/dashboard/user');
        $requests = $this->callAuthed('GET', '/api/v1/requests');

        // الاستخراج من المغلف { value: {...} } إن وجد (مستوى واحد)
        if ($stats->has('value')) {
            $v = $stats->get('value');
            $stats = collect(is_array($v) ? $v : []);
        }
        if ($requests->has('value')) {
            $v = $requests->get('value');
            $requests = collect(is_array($v) ? $v : []);
        }
        if ($requests->has('requests')) {
            $requests = collect($requests->get('requests', []));
        }

        return view('update_service.api.dashboard', [
            'stats'    => $stats,
            'requests' => collect($requests->get('data', $requests->all()))->values(),
        ]);
    }

    /** تتبع طلب — بيانات من الـ API */
    public function track(int $id): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        $data = $this->callAuthed('GET', "/api/v1/requests/{$id}");
        if ($data->has('value')) {
            $v = $data->get('value');
            $data = collect(is_array($v) ? $v : []);
        }

        return view('update_service.api.track', [
            'serviceRequest' => $data,
        ]);
    }

    /** صفحة الدفع (عرض نموذج — الإتمام عبر الـ API) */
    public function payment(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        return view('update_service.api.payment');
    }

    /** صفحة العقود — بيانات من الـ API */
    public function contracts(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        $tab = $request->route()?->getName() === 'amrtm.contracts.incoming' ? 'incoming' : 'my';

        $contracts = $this->callAuthed('GET', "/api/v1/contracts/{$tab}");
        if ($contracts->has('value')) {
            $v = $contracts->get('value');
            $contracts = collect(is_array($v) ? $v : []);
        }
        $stats = $this->callAuthed('GET', '/api/v1/dashboard/user');
        if ($stats->has('value')) {
            $v = $stats->get('value');
            $stats = collect(is_array($v) ? $v : []);
        }
        $requests = $this->callAuthed('GET', '/api/v1/requests');
        if ($requests->has('value')) {
            $v = $requests->get('value');
            $requests = collect(is_array($v) ? $v : []);
        }

        return view('update_service.api.contracts', [
            'contracts' => $contracts,
            'stats'     => $stats,
            'requests'  => collect($requests->get('data', []))->values(),
        ]);
    }
}