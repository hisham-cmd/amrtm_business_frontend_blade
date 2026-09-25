<?php

/*
|--------------------------------------------------------------------------
| Authentication (مُعطَّل — نسخة نظيفة)
|--------------------------------------------------------------------------
| النسخة المسلَّمة لا تحتوي أي Models مستخدمين محليين.
| المصادقة تحدث بالكامل في سيرفر الباك اند عبر BackendApi.
| لذلك نترك guard افتراضي فارغاً يعيد null دائماً (لا DB إطلاقاً).
*/

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'token',
            'provider' => 'users',
        ],

        /*
        | حكام وهمية (لا DB): القوالب القديمة تدعو auth('business') و auth('office')
        | في النسخة النظيفة لا يوجد مستخدمون محليون — تعيد null دائماً.
        | المصادقة الحقيقية كلها في الباك اند عبر BackendApi.
        */
        'business' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'office' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];