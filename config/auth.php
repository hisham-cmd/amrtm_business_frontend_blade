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
        | حارسا business / office: الحالة تُحقن من الـ API عبر
        | AppServiceProvider (setUser) — لا تسجيل دخول محلي ولا Models وهمية.
        | guard=business للعميل/الأدمن، و guard=office لمستخدمي المكاتب.
        */
        'business' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'office' => [
            'driver' => 'session',
            'provider' => 'office_users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        /*
        | مزوّد مستخدمي المكاتب: النموذج الحقيقي App\Models\Business\OfficeUser
        | على اتصال business. بدونه لا يمكن لـ setUser() قبول كائن المكتب،
        | فيبقى auth('office')->check() === false وتختفي كل خصائص اللوحة.
        */
        'office_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\Business\OfficeUser::class,
            'connection' => 'business',
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