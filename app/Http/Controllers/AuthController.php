<?php

namespace App\Http\Controllers;

use App\Support\BackendApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
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

    /** معالجة POST الدخول → يرسل إلى الـ API ويخزّن التوكن */
    public function submit(Request $request)
    {
        $data = BackendApi::post('/api/v1/auth/login', [
            'email'    => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        $token = $data->get('value.token') ?? $data->get('token');
        if (!$token) {
            return back()->withErrors([
                'email' => $data->get('error.message', 'البريد أو كلمة المرور غير صحيحة.'),
            ])->withInput();
        }

        // تخزين التوكن في cookie آمن (HTTP only)
        Cookie::queue('amrtm_api_token', $token, 60 * 24 * 7, null, null, false, true);

        $redirect = $request->input('redirect', '/');

        return redirect($redirect);
    }

    /** معالجة POST التسجيل (إنشاء حساب عميل) */
    public function register(Request $request)
    {
        $data = BackendApi::post('/api/v1/auth/register', [
            'name'                  => $request->input('name'),
            'email'                 => $request->input('email'),
            'phone'                 => $request->input('phone'),
            'password'              => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation', $request->input('password')),
            'account_type'          => $request->input('account_type', 'individual'),
        ]);

        $token = $data->get('value.token') ?? $data->get('token');
        if (!$token) {
            return back()->withErrors([
                'email' => $data->get('error.message', 'تعذر إنشاء الحساب.'),
            ])->withInput();
        }

        Cookie::queue('amrtm_api_token', $token, 60 * 24 * 7, null, null, false, true);

        return redirect()->route('amrtm.index')->with('success', 'تم إنشاء حسابك بنجاح!');
    }

    /** تسجيل الخروج — إلغاء الـ cookie */
    public function logout()
    {
        Cookie::queue(Cookie::forget('amrtm_api_token'));

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
        return request()->cookie('amrtm_api_token');
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

        // عند عدم توفّر بيانات API كاملة، نعرض واجهة السجل مع بيانات خفيفة
        $stats   = $this->callAuthed('GET', '/api/v1/dashboard/user');
        $requests = $this->callAuthed('GET', '/api/v1/requests');

        return view('update_service.user_dashboard', [
            'stats'    => $stats,
            'requests' => collect($requests->get('data', []))->values(),
        ]);
    }

    /** تتبع طلب — يعرض القالب العام (التفاصيل تُجلب عبر الـ API) */
    public function track(int $id): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        return view('update_service.request_track', [
            'serviceRequest' => $this->callAuthed('GET', "/api/v1/requests/{$id}"),
        ]);
    }

    /** صفحة الدفع (عرض نموذج — الإتمام عبر الـ API) */
    public function payment(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        return view('update_service.payment_checkout');
    }
}