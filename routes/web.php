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
Route::get('/consultants/{id}', fn () => redirect()->route('amrtm.index'))->name('amrtm.consultants.detail')->where('id', '[0-9]+');
Route::get('/consultants/specialty/{id}', fn () => redirect()->route('amrtm.index'))->name('amrtm.consultants.specialty')->where('id', '[0-9]+');

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

/* ═══ نفاذ (عرض فقط — غير مفعّل، يَظهر للمستخدم إشعار) ═══ */
Route::get('/nafath', fn (Illuminate\Http\Request $rq) => view('update_service.nafath.verify', [
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
Route::get('/provider-account/create', fn () => view('update_service.provider-account'))->name('amrtm.provider.account.create');
Route::get('/provider-account/specialties', fn () => response()->json(['specialties' => []]))->name('amrtm.provider.account.specialties');
Route::post('/provider-account', fn () => redirect()->route('amrtm.index'))->name('amrtm.provider.account.store');

/* ═══ العقود — إجراءات ═══ */
Route::post('/contracts', fn () => redirect()->route('amrtm.index'))->name('amrtm.contracts.store');
Route::post('/office/profile/update', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.profile.update');

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

/* ═══ تسجيل مزود/مستشار/عميل ═══ */
Route::get('/provider-account/create', fn () => view('update_service.provider-account'))->name('amrtm.provider.account.create');

/* ═══ مسارات اللوحات الإدارية (خارج النطاق — توجيه للرئيسية) ═══ */
Route::get('/office/login', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.login');
Route::post('/office/logout', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.logout');
Route::get('/admin', [AuthController::class, 'adminDashboard'])->name('amrtm.admin.dashboard');
Route::get('/office/dashboard', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.dashboard');
Route::get('/office', fn () => redirect()->route('amrtm.index'));

/* ═══ أسماء routes اسمية (تُعيد للرئيسية — تمنع RouteNotFound في القوالب) ═══ */
Route::get('/dashboard-hub', fn () => redirect()->route('amrtm.user.dashboard'))->name('amrtm.dashboard.hub');
Route::post('/office/complete/save', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.complete.save');
Route::get('/admin/messages', fn () => redirect()->route('amrtm.index'))->name('amrtm.admin.messages');
Route::get('/admin/offices/create-form', fn () => redirect()->route('amrtm.index'))->name('amrtm.admin.offices.create-form');
Route::post('/admin/org-structure/toggle', fn () => redirect()->route('amrtm.index'))->name('amrtm.admin.org-structure.toggle');
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