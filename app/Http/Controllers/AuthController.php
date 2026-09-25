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

        // تشخيص سريري موجز
        \Illuminate\Support\Facades\Log::info('PW-LOGIN email=' . $request->input('email'));

        // استخراج التوكن من المغلف { isSuccess, value: { token }, error }
        $value = $data->get('value');
        $token = is_array($value) ? ($value['token'] ?? null) : ($data->get('token') ?? null);
        if (!$token && $value instanceof \Illuminate\Support\Collection) {
            $token = $value->get('token');
        }
        \Illuminate\Support\Facades\Log::info('PW-LOGIN token=' . ($token ? 'OK' : 'NULL'));
        if (!$token) {
            $err  = $data->get('error');
            $msg  = is_array($err) ? ($err['message'] ?? 'فشل الدخول.') : 'فشل الدخول.';

            // التوجيه الصريح بدل back() — مضمون كنوع RedirectResponse (يتفادى TypeError)
            return redirect()->route('amrtm.login')->withErrors(['email' => $msg]);
        }

        // تخزين التوكن في الجلسة (آمن، يمر عبر Laravel session مع web middleware)
        session(['amrtm_api_token' => $token]);

        // توجيه حسب نوع الحساب: مدير/مشرف → لوحة الإدارة، وإلا لوحة العميل
        $isAdmin = is_array($value)
            ? in_array($value['user']['role'] ?? '', ['admin', 'supervisor'], true)
            : in_array(($value->get('user')['role'] ?? ''), ['admin', 'supervisor'], true);

        return redirect()->route($isAdmin ? 'amrtm.admin.dashboard' : 'amrtm.user.dashboard');
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

    /** لوحة الإدارة — القالب الأصلي الكامل (dashboard/admin/layout + 14 تبويب) */
    public function adminDashboard(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        $apiUser = $this->apiUserFromSession();
        $isAdmin = in_array($apiUser['role'] ?? '', ['admin', 'supervisor'], true);
        if (!$isAdmin) {
            return redirect()->route('amrtm.user.dashboard');
        }

        // الصفحة النشطة: من المسار (مثال /admin/requests → requests) أو default overview
        $adminPage = 'overview';
        $path = trim($request->path(), '/');
        $known = ['requests', 'offices', 'office-specialties', 'services-approvals', 'users', 'catalog', 'pricing', 'contracts', 'analytics', 'logs', 'permissions', 'settings', 'finance', 'off-finance', 'messages', 'homepage'];
        foreach ($known as $p) {
            if (str_contains($path, $p)) { $adminPage = $p; break; }
        }

        // جلب إحصاءات لوحة الإدارة من الـ API
        $stats = $this->callAuthed('GET', '/api/v1/dashboard/admin');
        if ($stats->has('value')) { $v = $stats->get('value'); $stats = collect(is_array($v) ? $v : []); }

        // جلب بيانات التبويبات اللازمة (نظرة عامة + طلبات)
        $requests = $this->callAuthed('GET', '/api/v1/admin/requests?status=all');
        if ($requests->has('value')) { $v = $requests->get('value'); $requests = collect(is_array($v) ? $v : []); }

        $reqArr = $requests->get('data', $requests->all());
        if ($reqArr instanceof \Illuminate\Support\Collection) { $reqArr = $reqArr->all(); }

        $pageData = [
            'stats' => $stats->all(),
            'requests' => collect($reqArr ?? [])->values(),
        ];

        // بناء البنية التي ينتظرها dashboard/admin/layout (persona + قوائم count)
        $hubStats = [
            'requests'  => (int) ($stats->get('requests.total') ?? $stats->get('total_requests') ?? 0),
            'offices'   => (int) ($stats->get('offices') ?? 0),
            'users'     => (int) ($stats->get('users') ?? 0),
            'contracts' => (int) ($stats->get('contracts') ?? 0),
        ];
        $dashboardMenu = \App\Support\DashboardRegistry::menuFor([\App\Support\DashboardRegistry::TYPE_ADMIN], 'admin');
        foreach ($dashboardMenu as &$grp) {
            foreach ($grp['items'] as &$itm) {
                $itm['count'] = $hubStats[$itm['key']] ?? null;
            }
            unset($itm);
        }
        unset($grp);

        // تحديد الصفحة الفعلية (كل صفحة تبويب تُضمّن admin-content داخل layout)
        $viewName = 'update_service.dashboard.admin.pages.' . $adminPage;
        if (!view()->exists($viewName)) {
            $viewName = 'update_service.dashboard.admin.pages.overview';
        }

        return view($viewName, compact(
            'apiUser', 'pageData', 'adminPage', 'dashboardMenu', 'hubStats'
        ));
    }

    /** لوحة المستخدم — القالب الأصلي (user_dashboard) مع بيانات الـ API */
    public function dashboard(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        \Illuminate\Support\Facades\Log::info('PW-DASH in token=' . ($this->token() ? 'Y' : 'N'));
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        $stats    = $this->callAuthed('GET', '/api/v1/dashboard/user');
        $requests = $this->callAuthed('GET', '/api/v1/requests');
        $payments = $this->callAuthed('GET', '/api/v1/payments/history');

        // فك المغلف { value: {...} }
        if ($stats->has('value')) { $v = $stats->get('value'); $stats = collect(is_array($v) ? $v : []); }
        if ($requests->has('value')) { $v = $requests->get('value'); $requests = collect(is_array($v) ? $v : []); }
        if ($payments->has('value')) { $v = $payments->get('value'); $payments = collect(is_array($v) ? $v : []); }

        $requestsList = collect($requests->get('data', $requests->all()))->values();
        $myRequestsCount = $requestsList->count();

        // بناء المتغيرات التي يتوقعها layouts/dashboard + user_dashboard الأصلي
        $apiUser  = $this->apiUserFromSession();
        $persona = [
            'key'   => 'business',
            'label' => 'مستخدم',
            'types' => ['business'],
            'name'  => $apiUser['name'] ?? 'مستخدم',
        ];
        $dashboardMenu = [
            ['label' => 'الرئيسية', 'items' => [
                ['href' => route('amrtm.user.dashboard'), 'ar' => 'نظرة عامة', 'en' => 'Overview', 'icon' => 'ti-dashboard', 'count' => null],
                ['href' => route('amrtm.index'),          'ar' => 'الموقع العام', 'en' => 'Website', 'icon' => 'ti-world', 'count' => null],
            ]],
            ['label' => 'طلباتي', 'items' => [
                ['href' => route('amrtm.index'), 'ar' => 'طلبات جديدة', 'en' => 'New', 'icon' => 'ti-file-plus', 'count' => null],
                ['href' => route('amrtm.request.track', ['id' => 1]), 'ar' => 'تتبع الطلبات', 'en' => 'Track', 'icon' => 'ti-route', 'count' => null],
            ]],
            ['label' => 'المالية والعقود', 'items' => [
                ['href' => route('amrtm.payment.checkout'), 'ar' => 'دفع الخدمات', 'en' => 'Pay', 'icon' => 'ti-wallet', 'count' => null],
                ['href' => route('amrtm.contracts.my'), 'ar' => 'عقودي', 'en' => 'Contracts', 'icon' => 'ti-file-contract', 'count' => null],
            ]],
        ];
        $hubStats = [
            'requests' => $requestsList->count(),
            'services' => 0,
            'contracts'=> 0,
        ];
        $pageTitle = 'لوحة التحكم — مستخدم';
        $user = (object) $apiUser;
        $isAdmin = false;

        try {
            return view('update_service.user_dashboard', compact(
                'stats', 'requests', 'requestsList', 'payments',
                'persona', 'dashboardMenu', 'hubStats', 'pageTitle', 'user', 'isAdmin', 'myRequestsCount'
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('user_dashboard fallback: ' . $e->getMessage());

            // نسخة احتياطية أنيقة (لا تعتمد على نماذج) — نفس السايدبار عبر layouts.dashboard
            return view('update_service.api.dashboard', compact(
                'stats', 'requests', 'requestsList', 'payments', 'persona', 'dashboardMenu', 'pageTitle', 'user'
            ));
        }
    }

    /** المستخدم الحالي من الجلسة عبر الـ API (مصفوفة) */
    private function apiUserFromSession(): ?array
    {
        $token = $this->token();
        if (!$token) {
            return null;
        }
        $resp = (new \App\Support\BackendApiWithToken($token))->call('GET', '/api/v1/auth/me');
        $value = $resp->get('value');

        return is_array($value) ? ($value['user'] ?? null) : null;
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