<?php

use App\Http\Controllers\ServiceCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| الواجهة الأمامية (Blade) — نسخة نظيفة مسلَّمة للعميل
|--------------------------------------------------------------------------
|
| تعرض الصفحات العامة فقط، وكل البيانات من الـ BackendApi المنفصل.
| لا توجد لوحات إدارة أو مكاتب هنا (تبقى على سيرفر الباك اند).
|
*/

Route::get('/', [ServiceCatalogController::class, 'index'])->name('amrtm.index');

// الكتالوج
Route::get('/catalog/{key}', [ServiceCatalogController::class, 'categoryPage'])->name('amrtm.catalog.category');
Route::get('/catalog/{key}/{entityId}', [ServiceCatalogController::class, 'entityPage'])->name('amrtm.catalog.entity');

// المستشارون
Route::get('/consultants', [ServiceCatalogController::class, 'consultantsDirectory'])->name('amrtm.consultants.directory');

// المكاتب المهنية
Route::get('/offices/{type}', [ServiceCatalogController::class, 'officeDirectory'])->name('amrtm.offices.directory');
Route::get('/offices/{type}/{id}', fn () => redirect()->route('amrtm.index'))->name('amrtm.offices.detail')->where('id', '[0-9]+');

/*
|--------------------------------------------------------------------------
| مسارات وهمية (اسمية فقط) — القوالب القديمة تنتظر أسماء routes محددة
| مثل amrtm.user.dashboard في navbar — نعرف المسارات إلى الرئيسية.
| البقاء في الباك اند الحقيقي.
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', fn () => redirect()->route('amrtm.index'))->name('amrtm.user.dashboard');
Route::get('/requests/{id}/track', fn () => redirect()->route('amrtm.index'))->name('amrtm.request.track');
Route::get('/login', fn () => redirect()->route('amrtm.index'))->name('login');
Route::get('/register', fn () => redirect()->route('amrtm.index'))->name('register');
// أسماء routes الأصيلة فقط (مطلوبة من القوالب) — تُعيد التوجيه للرئيسية
Route::get('/log-in', fn () => redirect()->route('amrtm.index'))->name('amrtm.login');
Route::get('/sign-up', fn () => redirect()->route('amrtm.index'))->name('amrtm.register');
Route::post('/logout', fn () => redirect()->route('amrtm.index'))->name('amrtm.logout');
Route::get('/office/login', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.login');
Route::post('/office/logout', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.logout');
Route::get('/admin', fn () => redirect()->route('amrtm.index'))->name('amrtm.admin.dashboard');
Route::get('/office/dashboard', fn () => redirect()->route('amrtm.index'))->name('amrtm.office.dashboard');
Route::get('/office', fn () => redirect()->route('amrtm.index'));
Route::get('/create-contract', fn () => redirect()->route('amrtm.index'))->name('amrtm.create-contract');
Route::get('/contracts/my', fn () => redirect()->route('amrtm.index'))->name('amrtm.contracts.my');
Route::get('/contracts/incoming', fn () => redirect()->route('amrtm.index'))->name('amrtm.contracts.incoming');
Route::get('/contracts/{id}', fn () => redirect()->route('amrtm.index'))->name('amrtm.contracts.show');
Route::post('/api/v1/payments/charge', fn () => response()->json(['message' => 'ادفع عبر الباك اند'] , 501))->name('amrtm.api.payments.charge');
Route::get('/provider-account/create', fn () => redirect()->route('amrtm.index'))->name('amrtm.provider.account.create');
Route::get('/consultants/{id}', fn () => redirect()->route('amrtm.index'))->name('amrtm.consultants.detail');
Route::get('/consultants/specialty/{id}', fn () => redirect()->route('amrtm.index'))->name('amrtm.consultants.specialty');