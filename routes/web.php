<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| الواجهة الأمامية (Blade) — نسخة نظيفة مسلَّمة للعميل
|--------------------------------------------------------------------------
| تعرض كل الصفحات العامة + الدخول + التسجيل، وكل البيانات من الـ
| BackendApi المنفصل. لا توجد قاعدة بيانات محلية ولا لوحات إدارة.
*/

Route::get('/', [ServiceCatalogController::class, 'index'])->name('amrtm.index');

/* ═══ الكتالوج ═══ */
Route::get('/catalog/{key}', [ServiceCatalogController::class, 'categoryPage'])->name('amrtm.catalog.category');
Route::get('/catalog/{key}/{entityId}', [ServiceCatalogController::class, 'entityPage'])->name('amrtm.catalog.entity');

/* ═══ المستشارون ═══ */
Route::get('/consultants', [ServiceCatalogController::class, 'consultantsDirectory'])->name('amrtm.consultants.directory');
Route::get('/consultants/{id}', [ServiceCatalogController::class, 'consultantDetail'])->name('amrtm.consultants.detail')->where('id', '[0-9]+');
Route::get('/consultants/specialty/{id}', [ServiceCatalogController::class, 'consultantSpecialty'])->name('amrtm.consultants.specialty')->where('id', '[0-9]+');

/* ═══ المكاتب المهنية ═══ */
Route::get('/offices/{type}', [ServiceCatalogController::class, 'officeDirectory'])->name('amrtm.offices.directory');
Route::get('/offices/{type}/{id}', fn () => redirect()->route('amrtm.index'))->name('amrtm.offices.detail')->where('id', '[0-9]+');

/* ═══ المصادقة (عبر الـ API) ═══ */
Route::get('/login', [AuthController::class, 'showLogin'])->name('amrtm.login');
Route::get('/sign-in', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'submit'])->name('amrtm.login.submit');
Route::get('/register', [AuthController::class, 'showRegister'])->name('amrtm.register');
Route::get('/sign-up', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('amrtm.register.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('amrtm.logout');

/* ═══ نفاذ (عرض فقط — غير مفعّل، يَظهر للمستخدم إشعار) ═══ */Route::get('/nafath', fn (Illuminate\Http\Request $rq) => view('update_service.nafath.verify', [
    'intent'            => $rq->input('intent', 'login'),
    'initialNationalId' => $rq->input('national_id', ''),
    'nafathConfigured'  => false,
]))->name('amrtm.nafath.show');
Route::get('/nafath/wait', fn (Illuminate\Http\Request $rq) => view('update_service.nafath.wait', [
    'intent'       => $rq->input('intent', 'login'),
    'transId'      => $rq->input('trans_id', ''),
    'random'       => random_int(100000, 999999),
    'pollInterval' => 4000,
]))->name('amrtm.nafath.wait');
Route::post('/nafath/verify', fn () => redirect()->route('amrtm.index'))->name('amrtm.nafath.verify');
Route::get('/nafath/status', fn () => response()->json(['status' => 'WAITING', 'wait' => 2]))->name('amrtm.nafath.status');
Route::get('/nafath/callback', fn () => redirect()->route('amrtm.index'))->name('amrtm.nafath.callback');

/* ═══ تسجيل مزود/مستشار/عميل ═══ */
Route::get('/provider-account/create', function (\Illuminate\Http\Request $request) {
    /*
     * القالب يحسب $modeConsultant = ($mode === 'consultant')، وكل شيء داخل
     * partial::provider-office-fields-extra (الفئات + التخصصات المرتبطة بالنشاط)
     * محجوب بـ @if(!empty($modeConsultant)). بلا تمرير $mode كانت الصفحة
     * تظهر بلا فئات ولا تخصصات إطلاقاً.
     * المسموح: client | consultant | office | establishment | mixed
     */
    $allowed = ['client', 'consultant', 'office', 'establishment', 'mixed'];
    $mode    = $request->query('mode', 'office');
    if (! in_array($mode, $allowed, true)) {
        $mode = 'office';
    }

    return view('update_service.provider-account', compact('mode'));
})->name('amrtm.provider.account.create');
Route::get('/provider-account/specialties', fn () => response()->json(['specialties' => []]))->name('amrtm.provider.account.specialties');
Route::post('/provider-account', fn () => redirect()->route('amrtm.index'))->name('amrtm.provider.account.store');

/* ═══ العقود — إجراءات ═══ */
Route::post('/contracts', fn () => redirect()->route('amrtm.index'))->name('amrtm.contracts.store');
Route::post('/office/profile/update', [AuthController::class, 'officeProfileUpdate'])->name('amrtm.office.profile.update');

/* ═══ لوحات المستخدم والعمليات (محسوبة في الباك اند — تُعرض عبر API عند توفر التوكن) ═══ */
Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('amrtm.user.dashboard');
Route::get('/requests/{id}/track', [AuthController::class, 'track'])->name('amrtm.request.track');
Route::get('/payment/checkout', [AuthController::class, 'payment'])->name('amrtm.payment.checkout');
Route::post('/api/v1/payments/charge', fn () => response()->json(['message' => 'ادفع عبر الباك اند'], 501))->name('amrtm.api.payments.charge');

/* ═══ العقود (عرض — بيانات عبر الـ API) ═══ */
Route::get('/create-contract', fn () => view('update_service.contracts_create_static'))->name('amrtm.create-contract');
Route::get('/contracts/my', [AuthController::class, 'contracts'])->name('amrtm.contracts.my');
Route::get('/contracts/incoming', [AuthController::class, 'contracts'])->name('amrtm.contracts.incoming');
Route::get('/contracts/{id}', fn () => view('update_service.contract_show', [
    'contract' => null,
    'company'  => null,
    'clauses'  => collect(),
]))->name('amrtm.contracts.show');

/* ═══ لوحات المكاتب (حساب مكتب — type=office) ═══ */
Route::get('/office/login', fn () => redirect()->route('amrtm.login'))->name('amrtm.office.login');
Route::post('/office/logout', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.logout');
Route::get('/office/dashboard', [AuthController::class, 'officeDashboard'])->name('amrtm.office.dashboard');
Route::get('/office/profile', [AuthController::class, 'officeProfile'])->name('amrtm.office.profile');
Route::get('/office', fn () => redirect()->route('amrtm.office.dashboard'));

/* ═══ لوحات الإدارة — التبويبات (تعرض عبر adminDashboard) ═══ */
Route::get('/admin', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.dashboard');
Route::get('/admin/overview', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.overview');
Route::get('/admin/requests', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.requests');
Route::get('/admin/offices', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.offices');
Route::get('/admin/offices/create-form', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.offices.create-form');
Route::get('/admin/offices/{id}/edit-form', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.offices.edit-form');
Route::get('/admin/office-specialties', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.office-specialties');
Route::get('/admin/services-approvals', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.services-approvals');
Route::get('/admin/users', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.users');
Route::get('/admin/catalog', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.catalog');
Route::get('/admin/pricing', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.pricing');
Route::get('/admin/contracts', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.contracts');
Route::get('/admin/analytics', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.analytics');
Route::get('/admin/logs', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.logs');
Route::get('/admin/permissions', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.permissions');
Route::get('/admin/settings', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.settings');
Route::get('/admin/finance', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.finance');
Route::get('/admin/off-finance', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.off-finance');

/* ═══ لوحات الإدارة — صفحات مستقلة (قوالب layouts.dashboard خاصة) ═══ */
Route::get('/admin/messages', [AuthController::class, 'adminMessages'])->name('amrtm.admin.messages');
Route::get('/admin/homepage', [AuthController::class, 'adminHomepage'])->name('amrtm.admin.homepage');
Route::get('/admin/icons', [AuthController::class, 'adminIcons'])->name('amrtm.admin.icons');
Route::get('/admin/org-structure', [AuthController::class, 'adminOrgStructure'])->name('amrtm.admin.org-structure');

/* ═══ أسماء routes اسمية (تُعيد للرئيسية — تمنع RouteNotFound في القوالب) ═══ */
Route::get('/dashboard-hub', [AuthController::class, 'hub'])->name('amrtm.dashboard.hub');
Route::post('/office/complete/save', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.complete.save');
Route::post('/admin/org-structure/toggle', fn () => redirect()->route('amrtm.admin.org-structure'))->name('amrtm.admin.org-structure.toggle');
/*
 | ═══ نداءات لوحة إدارة الواجهة (homepage / icons / offices) ═══
 | ⚠️ ملاحظة مهمة: الوكيل العام Route::get('/api/{path}') مسجَّل في
 | routes/api.php وهو يلتقط /api/* قبل web routes، فيمرّرها للباك اند.
 | لذلك المسارات أدناهчи shadows名副其实 — الغرض منها فقط توفير
 | أسماء route() المستخدمة في القوالب (route('amrtm.admin.api.homepage...'))
 | حتى لا يحدث RouteNotFoundException. أي طلب فعلي يذهب للـ proxy.
 |
 | الباك اند يخدم تحت /api/v1/admin/... والـ proxy يضيف v1 تلقائياً،
 | لذا المسار الصحيح من الواجهة هو /api/admin/homepage/...
 */
Route::get('/admin/api/homepage/settings', fn () => response()->json([]))->name('amrtm.admin.api.homepage.settings');
Route::post('/admin/api/homepage/settings/save', fn () => redirect()->route('amrtm.index'))->name('amrtm.admin.api.homepage.settings.save');
Route::get('/admin/api/homepage/slides', fn () => response()->json([]))->name('amrtm.admin.api.homepage.slides');
Route::post('/admin/api/homepage/slides/reorder', fn () => redirect()->route('amrtm.index'))->name('amrtm.admin.api.homepage.slides.reorder');
Route::post('/admin/api/homepage/slides/store', fn () => redirect()->route('amrtm.index'))->name('amrtm.admin.api.homepage.slides.store');
Route::get('/admin/api/icons/list', fn () => response()->json([]))->name('amrtm.api.admin.icons.list');
Route::post('/admin/api/icons/upload', fn () => redirect()->route('amrtm.index'))->name('amrtm.api.admin.icons.upload');
Route::post('/admin/api/icons/delete', fn () => redirect()->route('amrtm.index'))->name('amrtm.api.admin.icons.delete');
Route::post('/admin/api/offices/create', fn () => redirect()->route('amrtm.index'))->name('amrtm.api.admin.offices.create');
Route::get('/offices/law/info', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.law.info');
Route::get('/offices/services/info', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.services.info');