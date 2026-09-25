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
Route::get('/offices/{type}/{specialty}', [ServiceCatalogController::class, 'specialtyDetail'])->name('amrtm.offices.specialty');