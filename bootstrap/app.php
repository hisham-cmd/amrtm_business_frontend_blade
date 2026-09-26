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

        /*
         | وكيل /api/* يحتاج جلسة Laravel لقراءة amrtm_api_token.
         | بدونها مجموعة api middleware لا تشغّل StartSession، فيصل الطلب
         | للباك اند بلا ترويسة Authorization فيرجع 401 لكل نداء.
         |
         | ملاحظة جوهرية: StartSession وحده لا يكفي — يجب أن يسبقه EncryptCookies.
         | مجموعة web تُشفّر كوكي الجلسة عند الإخراج، فلو دخل الطلب إلى /api/*
         | دون فك التشفير لكان StartSession سيتعامل مع القيمة المشفَّرة كـ session id
         | فلا يطابق أي ملف جلسة ← جلسة فارغة ← لا توكن ← 401.
         |
         | لذلك نضيف نفس سلسلة الجلسة الموجودة في مجموعة web:
         | EncryptCookies → AddQueuedCookiesToResponse → StartSession
         | → ShareErrorsFromSession (Validation of CSRF مستثنى في الأسفل).
         */
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        ]);

        // مسارات API المفتوحة: لا تتطلب CSRF
        $middleware->validateCsrfTokens(except: [
            'login',
            'register',
            'logout',
            'nafath/*',
            'contracts',
            'provider-account',
            'office/profile/update',
            'api/*',
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