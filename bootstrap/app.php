<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| النسخة النظيفة المسلَّمة — واجهة عرض فقط
|--------------------------------------------------------------------------
| بدون أي middleware مصادقة محلية (لا مستخدمون/مكاتب/أدمن محليون):
| كل شيء يعتمد BackendApi. الـ web middleware الأساسي فقط.
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        // المصادقة عبر الـ API المنفصل — لا نحتاج CSRF محلي لموارد الدخول/التسجيل
        $middleware->validateCsrfTokens(except: [
            'login',
            'register',
            'logout',
            'nafath/*',
            'contracts',
            'provider-account',
            'office/profile/update',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'انتهت صلاحية الجلسة.'], 419);
            }

            return redirect()->route('amrtm.index');
        });
    })->create();