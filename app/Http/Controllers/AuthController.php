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

        // توجيه حسب نوع الحساب:
        //   مكتب            → /office/dashboard
        //   مدير/مشرف       → /admin
        //   عميل            → /dashboard
        $loginUser = is_array($value)
            ? ($value['user'] ?? [])
            : (($value instanceof \Illuminate\Support\Collection ? $value->get('user') : null) ?: []);

        $loginUser = is_array($loginUser) ? $loginUser : [];
        $isOffice  = ($loginUser['type'] ?? '') === 'office'
            || ($loginUser['account_type'] ?? '') === 'office';
        $isAdmin   = in_array($loginUser['role'] ?? '', ['admin', 'supervisor'], true);

        if ($isOffice) {
            return redirect()->route('amrtm.office.dashboard');
        }

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

        // الصفحة النشطة: من اسم المسار (أدق من مطابقة المسار بالنص)
        $adminPage = $this->adminPageFromRoute($request);

        // جلب بيانات كل تبويب عبر نقاط النهاية المخصصة له (نفس شكل استجابة الـ JSON)
        // حتى تُعرض القوالب السيرفرية والـ JS البيانات الصحيحة لكل صفحة.
        // نقاط النهاية المرقّمة (paginator: data/current_page/total...) تُمرَّر كما هي،
        // بينما المصفوفات البسيطة تُستخرج من مفتاح data إن وُجد.
        $unwrap = function ($resp, bool $keepPaginator = false) {
            if ($resp->has('value')) {
                $v = $resp->get('value');
                $v = is_array($v) ? $v : [];

                return $keepPaginator ? collect($v) : collect($v['data'] ?? $v);
            }

            return $keepPaginator
                ? collect($resp->all())
                : collect($resp->get('data', is_array($resp->all()) ? $resp->all() : []));
        };

        $pageData = [];

        // الإحصاءات العامة (تُستخدم في نظرة عامة وقائمة السايدبار)
        $stats = collect($unwrap($this->callAuthed('GET', '/api/v1/dashboard/admin'))->all());

        $hubStats = [
            'requests'  => (int) (data_get($stats, 'requests.total', data_get($stats, 'total_requests', 0))),
            'offices'   => (int) data_get($stats, 'offices', 0),
            'users'     => (int) data_get($stats, 'users', 0),
            'contracts' => (int) data_get($stats, 'contracts', 0),
        ];

        if ($adminPage === 'overview') {
            $pageData['stats'] = $stats->all();
        } elseif ($adminPage === 'requests') {
            $pageData['requests'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/requests?status=all'), true)->all();
        } elseif ($adminPage === 'pricing') {
            $pageData['pricing']['services'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/catalog/services'))->all();
        } elseif ($adminPage === 'catalog') {
            $pageData['catalog']['categories'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/catalog/categories'))->all();
            $pageData['catalog']['entities']   = $unwrap($this->callAuthed('GET', '/api/v1/admin/catalog/entities'))->all();
            $pageData['catalog']['services']   = $unwrap($this->callAuthed('GET', '/api/v1/admin/catalog/services'))->all();
        } elseif ($adminPage === 'users') {
            $pageData['userStats'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/users/stats'))->all();
            $pageData['users']     = $unwrap($this->callAuthed('GET', '/api/v1/admin/users'), true)->all();
        } elseif ($adminPage === 'analytics') {
            $pageData['analytics'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/analytics?months=6'))->all();
        } elseif ($adminPage === 'logs') {
            $pageData['logs'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/logs'), true)->all();
        } elseif ($adminPage === 'offices') {
            $pageData['officeStats'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/offices/stats'))->all();
            $pageData['offices']     = $unwrap($this->callAuthed('GET', '/api/v1/admin/offices'), true)->all();
        } elseif ($adminPage === 'office-specialties') {
            $pageData['specialties'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/specialties'))->all();
        } elseif ($adminPage === 'services-approvals') {
            $pageData['pendingServices'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/office-services/pending'))->all();
        } elseif ($adminPage === 'finance') {
            $pageData['finance'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/finance'))->all();
        } elseif ($adminPage === 'off-finance') {
            $pageData['officeFinancial'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/office-financial'))->all();
            $pageData['officeRequests']  = $unwrap($this->callAuthed('GET', '/api/v1/admin/office-requests'), true)->all();
        } elseif ($adminPage === 'contracts') {
            $pageData['settlements'] = $unwrap($this->callAuthed('GET', '/api/v1/admin/settlements'))->all();
        } elseif ($adminPage === 'permissions') {
            $pageData['admins'] = $unwrap($this->callAuthed('GET', '/api/v1/supervisor/admins'))->all();
        }

        // بناء البنية التي ينتظرها dashboard/admin/layout (persona + قوائم count)
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

    /**
     * اسم تبويب لوحة الأدمن من اسم المسار.
     * الاعتماد على route name بدل str_contains على المسار يمنع خطأ
     * /admin/overview (لا يحتوي أي كلمة من قائمة $known فيعود overview افتراضياً)،
     * ويمنع تعارض /admin/off-finance مع /admin/finance.
     */
    private function adminPageFromRoute(Request $request): string
    {
        $map = [
            'overview'            => 'overview',
            'requests'            => 'requests',
            'offices'             => 'offices',
            'offices.create-form' => 'offices',
            'offices.edit-form'   => 'offices',
            'office-specialties'  => 'office-specialties',
            'services-approvals'  => 'services-approvals',
            'users'               => 'users',
            'catalog'             => 'catalog',
            'pricing'             => 'pricing',
            'contracts'           => 'contracts',
            'analytics'           => 'analytics',
            'logs'                => 'logs',
            'permissions'         => 'permissions',
            'settings'            => 'settings',
            'off-finance'         => 'off-finance',
            'finance'             => 'finance',
        ];

        $name  = (string) ($request->route()?->getName() ?? '');
        $short = str_starts_with($name, 'amrtm.admin.') ? substr($name, strlen('amrtm.admin.')) : '';
        if ($short !== '' && isset($map[$short])) {
            return $map[$short];
        }

        // احتياط: مطابقة أدق للمسار (الطويل أولاً)
        $path = trim($request->path(), '/');
        foreach (['off-finance', 'office-specialties', 'services-approvals', 'requests', 'offices', 'users', 'catalog', 'pricing', 'contracts', 'analytics', 'permissions', 'settings', 'finance', 'logs'] as $key) {
            if (str_contains($path, $key)) {
                return $map[$key];
            }
        }

        return 'overview';
    }

    /** حارس مشترك: يجب أن يوجد توكن + دور إداري، وإلا يوجّه */
    private function requireAdmin(): ?\Illuminate\Http\RedirectResponse
    {
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        $apiUser = $this->apiUserFromSession();
        if (!in_array($apiUser['role'] ?? '', ['admin', 'supervisor'], true)) {
            return redirect()->route('amrtm.user.dashboard');
        }

        return null;
    }

    /** هيكل الأدمن الموحّد (persona + القائمة + العنوان) */
    private function adminShell(string $pageTitle): array
    {
        $apiUser = $this->apiUserFromSession() ?? [];
        $role    = $apiUser['role'] ?? 'admin';

        return [
            'apiUser'       => $apiUser,
            'persona'       => [
                'key'   => $role,
                'label' => $role === 'supervisor' ? 'مشرف' : 'مدير النظام',
                'types' => [\App\Support\DashboardRegistry::TYPE_ADMIN],
                'name'  => ($apiUser['name'] ?? '') !== '' ? $apiUser['name'] : 'مدير النظام',
            ],
            'pageTitle'     => $pageTitle,
            'hubStats'      => [],
            'dashboardMenu' => \App\Support\DashboardRegistry::menuFor(
                [\App\Support\DashboardRegistry::TYPE_ADMIN],
                'admin'
            ),
        ];
    }

    /** الهيكل التنظيمي — مصفوفة الأنواع × الواجهات من السجل الموحّد */
    public function adminOrgStructure(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }

        $interfaceKeys = \App\Support\DashboardRegistry::interfaceKeys();
        $interfaceDefs = \App\Support\DashboardRegistry::interfaces();
        $matrix = [];
        foreach (\App\Support\DashboardRegistry::typeKeys() as $typeKey) {
            $enabled = [];
            foreach ($interfaceKeys as $interfaceKey) {
                $enabled[$interfaceKey] = \App\Support\DashboardRegistry::enabledState(null, $typeKey, $interfaceKey);
            }
            $matrix[$typeKey] = [
                'def'     => \App\Support\DashboardRegistry::types()[$typeKey],
                'enabled' => $enabled,
            ];
        }

        $types          = \App\Support\DashboardRegistry::types();
        $interfaceGroups = \App\Support\DashboardRegistry::groups();

        return view(
            'update_service.dashboard.org_structure',
            array_merge(
                $this->adminShell('الهيكل التنظيمي'),
                compact('matrix', 'types', 'interfaceDefs', 'interfaceGroups')
            )
        );
    }

    /** المحادثات والمخالفات */
    public function adminMessages(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }

        // العدادات ثلاث صفحات من الـ API (بقيم آمنة عند غياب البيانات)
        $conversations = $this->callAuthed('GET', '/api/v1/admin/messages/conversations');
        $messages      = $this->callAuthed('GET', '/api/v1/admin/messages');
        $violations    = $this->callAuthed('GET', '/api/v1/admin/violations');

        $unwrap = static function ($c) {
            if ($c->has('value')) {
                $v = $c->get('value');
                $c = collect(is_array($v) ? $v : []);
            }

            return $c;
        };

        $conversations = $unwrap($conversations);
        $messages      = $unwrap($messages);
        $violations    = $unwrap($violations);

        return view('update_service.admin_messages', array_merge(
            $this->adminShell('التواصل والمخالفات'),
            [
                'conversations_count' => (int) ($conversations->get('total') ?? $conversations->count()),
                'messages_count'      => (int) ($messages->get('total') ?? $messages->count()),
                'violations_count'    => (int) ($violations->get('total') ?? $violations->count()),
            ]
        ));
    }

    /** إعدادات الصفحة الرئيسية */
    public function adminHomepage(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }

        return view('update_service.admin_homepage', $this->adminShell('إدارة الواجهة والمحتوى'));
    }

    /** مكتبة الأيقونات */
    public function adminIcons(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }

        return view('update_service.admin_icons', $this->adminShell('مكتبة الأيقونات'));
    }

    /**
     * مركز اللوحات — يوجّه كل نوع حساب إلى لوحته الصحيحة.
     * كان يعيد دائماً /dashboard (لوحة العميل) حتى للمدير والمشرف،
     * فكان شعار القائمة الجانبية يفتح واجهة غير مخصّصة للأدمن.
     */
    public function hub(Request $request): \Illuminate\Http\RedirectResponse
    {
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        return redirect()->to($this->homeUrlForCurrentUser());
    }

    /** رابط اللوحة الصحيحة لنوع الحساب الحالي (مصدر واحد للتوجيه) */
    private function homeUrlForCurrentUser(): string
    {
        $apiUser = $this->apiUserFromSession() ?? [];
        $type    = $apiUser['type'] ?? 'business';
        $role    = $apiUser['role'] ?? 'user';

        if ($type === 'office' || ($apiUser['account_type'] ?? '') === 'office') {
            return route('amrtm.office.dashboard');
        }

        if (in_array($role, ['admin', 'supervisor'], true)) {
            return route('amrtm.admin.dashboard');
        }

        return route('amrtm.user.dashboard');
    }

    /* ═══ لوحة المكاتب ═══════════════════════════════════════════════ */

    /** يتطلب توكن من نوع office — وإلا يحوّل للوحة الصحيحة */
    private function requireOfficeUser(): \Illuminate\Http\RedirectResponse|\App\Models\Business\OfficeUser|null
    {
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        $apiUser = $this->apiUserFromSession();
        $isOffice = ($apiUser['type'] ?? '') === 'office'
            || ($apiUser['account_type'] ?? '') === 'office';

        if (!$isOffice) {
            return redirect()->to($this->homeUrlForCurrentUser());
        }

        $class  = \App\Models\Business\OfficeUser::class;
        $user   = null;
        $id     = $apiUser['id'] ?? null;

        try {
            if ($id) {
                $user = $class::on('business')->find($id);
            }
            if (!$user && !empty($apiUser['email'])) {
                $user = $class::on('business')->where('email', $apiUser['email'])->first();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('requireOfficeUser: ' . $e->getMessage());
        }

        if (!$user) {
            return redirect()->route('amrtm.login')
                ->withErrors(['email' => 'تعذّر العثور على حساب المكتب.']);
        }

        return $user;
    }

    /** لوحة تحكم المكتب — إحصاءات وطلبات من الـ API */
    public function officeDashboard(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $guard = $this->requireOfficeUser();
        if ($guard instanceof \Illuminate\Http\RedirectResponse) {
            return $guard;
        }

        /** @var \App\Models\Business\OfficeUser $officeUser */
        $officeUser = $guard;
        $office = $officeUser->office;

        if (!$office) {
            return redirect()->route('amrtm.login')
                ->withErrors(['email' => 'حساب المكتب غير مرتبط بمنشأة.']);
        }

        $apiUser   = $this->apiUserFromSession() ?? [];
        $stats     = $this->callAuthed('GET', '/api/v1/office/stats');
        $requests  = $this->callAuthed('GET', '/api/v1/office/requests');
        $services  = $this->callAuthed('GET', '/api/v1/office/services');

        $unwrap = static function ($c) {
            if ($c->has('value')) {
                $v = $c->get('value');
                $c = collect(is_array($v) ? $v : []);
            }

            return $c;
        };

        $stats    = $unwrap($stats);
        $requests = $unwrap($requests);
        $services = $unwrap($services);

        $requestsList = collect($requests->get('data', $requests->all()))->values();
        $servicesList = collect($services->get('data', $services->all()))->values();

        // عدّادات القائمة الجانبية
        $hubStats = [
            'requests'  => $requestsList->count(),
            'services'  => $servicesList->count(),
            'contracts' => (int) ($stats->get('contracts.total') ?? $stats->get('contracts') ?? 0),
        ];

        $accountTypes = array_values($office->accountTypes())
            ?: [\App\Support\DashboardRegistry::TYPE_SUPPORT_OFFICE];

        $dashboardMenu = \App\Support\DashboardRegistry::menuFor($accountTypes, 'office');
        foreach ($dashboardMenu as &$grp) {
            foreach ($grp['items'] as &$itm) {
                $itm['count'] = $hubStats[$itm['key']] ?? null;
            }
            unset($itm);
        }
        unset($grp);

        $personaLabel = implode(' + ', array_filter(array_map(
            static fn ($t) => \App\Models\Business\Office::$accountTypeLabels[$t]['ar'] ?? null,
            $accountTypes
        ))) ?: 'منشأة';

        $persona = [
            'key'   => implode(',', $accountTypes),
            'label' => $personaLabel,
            'types' => $accountTypes,
            'name'  => $office->name_ar ?: $office->name_en,
        ];

        return view('update_service.office.dashboard', [
            'office'        => $office,
            'officeUser'    => $officeUser,
            'apiUser'       => $apiUser,
            'persona'       => $persona,
            'pageTitle'     => $persona['name'] . ' — ' . $personaLabel,
            'dashboardMenu' => $dashboardMenu,
            'hubStats'      => $hubStats,
            'stats'         => $stats,
            'requests'      => $requestsList,
            'services'      => $servicesList,
            // التخصصات مرتبطة بالمكتب في القالب (OFFICE_SPECIALTIES / OFFICE_SELECTED_IDS)
            'specialties'   => $this->officeSpecialties($office),
            'selectedIds'   => $this->officeSelectedSpecialtyIds($office),
        ]);
    }

    /** تخصصات المكتب (قيمة فارغة آمنة إن لم تتوفر العلاقة) */
    private function officeSpecialties($office): \Illuminate\Support\Collection
    {
        try {
            if (method_exists($office, 'specialties')) {
                return $office->specialties()->get();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('officeSpecialties: ' . $e->getMessage());
        }

        return collect();
    }

    /** معرّفات التخصصات المختارة للمكتب (جدول bs_office_specialties) */
    private function officeSelectedSpecialtyIds($office): array
    {
        try {
            return \Illuminate\Support\Facades\DB::connection('business')
                ->table('bs_office_specialties')
                ->where('office_id', $office->id)
                ->pluck('specialty_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('officeSelectedSpecialtyIds: ' . $e->getMessage());
        }

        return [];
    }

    /** لوحة المستخدم — القالب الأصلي (user_dashboard) مع بيانات الـ API */
    public function dashboard(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        \Illuminate\Support\Facades\Log::info('PW-DASH in token=' . ($this->token() ? 'Y' : 'N'));
        if (!$this->token()) {
            return redirect()->route('amrtm.login');
        }

        /*
         | حساب المكتب ليس عميلاً: /api/v1/dashboard/user محمي بـ auth.api:business
         | فكان حساب المكتب يتلقى 401 عند فتح هذه اللوحة.
         */
        $sessionUser = $this->apiUserFromSession();
        if (($sessionUser['type'] ?? '') === 'office' || ($sessionUser['account_type'] ?? '') === 'office') {
            return redirect()->route('amrtm.office.dashboard');
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