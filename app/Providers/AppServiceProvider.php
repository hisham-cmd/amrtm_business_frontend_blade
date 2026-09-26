<?php

namespace App\Providers;

use App\Support\ApiUser;
use App\Support\BackendApiWithToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<string,mixed>|null البيانات الخام كما أعادتها الـ API */
    private ?array $cachedRaw = null;
    /** @var ApiUser|null كائن المستخدم الجاهز للقوالب */
    private ?ApiUser $cachedUser = null;
    private bool $fetched = false;

    public function register(): void {}

    /**
     * النسخة النظيفة: المصادقة كلها في الباك اند.
     * نضخ «المستخدم الحالي» من الـ API إلى كل قالب:
     * - frontUser / frontAuthed / currentAuthUser  → يستهلكها الناف بار واللوحات
     * - currentUserType                             → 'business' | 'office'
     *
     * ونثبّت المستخدم على الحارس الصحيح:
     * - type=business → Auth::guard('business')  (عميل/أدمن/مشرف)
     * - type=office   → Auth::guard('office')    (مستخدم مكتب)
     *
     * ملاحظة: ApiUser يطبّق Authenticatable — بدونه يرمي setUser() TypeError
     * ويبقى auth('business')->check() === false في كل المشروع.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $userObj = $this->apiUserObject();
            $type    = $this->userType();

            $view->with('frontUser', $userObj);
            $view->with('frontAuthed', $userObj !== null);
            $view->with('currentAuthUser', $userObj);
            $view->with('currentUserType', $userObj ? $type : null);

            $this->attachGuard($userObj, $type);
        });
    }

    /** يثبّت المستخدم على حارسه الصحيح ويمسح الحارس الآخر */
    private function attachGuard(?ApiUser $user, string $type): void
    {
        $guards = ['business', 'office'];

        foreach ($guards as $g) {
            if ($user && $g === $type) {
                continue;
            }
            // لا مسجّل على هذا الحارس — نظّف أي حالة قديمة
            try {
                if (Auth::guard($g)->check()) {
                    Auth::guard($g)->logout();
                }
            } catch (\Throwable) {
            }
        }

        if (!$user) {
            return;
        }

        $guard = $type === 'office' ? 'office' : 'business';

        try {
            if ($type === 'office') {
                $officeUser = $this->officeUserModel($user);
                if ($officeUser) {
                    Auth::guard('office')->setUser($officeUser);
                } else {
                    // بلا سجل محلي: نستخدم كائن ApiUser (يطبّق Authenticatable)
                    Auth::guard('office')->setUser($user);
                }
            } else {
                Auth::guard('business')->setUser($user);
            }
        } catch (\Throwable $e) {
            Log::warning("setUser({$guard}) failed: " . $e->getMessage());
        }
    }

    /**
     * سجل مستخدم المكتب الحقيقي من قاعدة البيانات (BusinessUser-like).
     * يعيد null إن لم تتوفر الـ DB أو السجل — عندها نكتفي بـ ApiUser.
     */
    private function officeUserModel(ApiUser $user): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        $class = \App\Models\Business\OfficeUser::class;
        if (!class_exists($class)) {
            return null;
        }

        try {
            $id    = $user->getAuthIdentifier();
            $model = $id ? $class::on('business')->find($id) : null;
            if (!$model && !empty($user->email)) {
                $model = $class::on('business')->where('email', $user->email)->first();
            }

            return $model;
        } catch (\Throwable $e) {
            Log::warning('officeUserModel failed: ' . $e->getMessage());

            return null;
        }
    }

    /** نوع الحساب كما تراه الـ API */
    private function userType(): string
    {
        $user = $this->cachedUser;
        if (!$user) {
            return 'business';
        }

        if (($user->type ?? null) === 'office' || ($user->account_type ?? null) === 'office') {
            return 'office';
        }

        return 'business';
    }

    /** كائن مستخدم (ApiUser) من الـ API — مجلوب مرة واحدة لكل طلب */
    private function apiUserObject(): ?ApiUser
    {
        if ($this->fetched) {
            return $this->cachedUser;
        }
        $this->fetched = true;

        $user = $this->apiUser();

        return $this->cachedUser = ($user ? new ApiUser($user) : null);
    }

    private function token(): ?string
    {
        try {
            return app('session.store')->get('amrtm_api_token');
        } catch (\Throwable) {
            try {
                return request()->session()->get('amrtm_api_token');
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function apiUser(): ?array
    {
        $token = $this->token();
        if (!$token) {
            return $this->cachedRaw = null;
        }

        $unauthorized = false;

        try {
            $client       = (new BackendApiWithToken($token))->call('GET', '/api/v1/auth/me');
            $value        = $client->get('value');
            $user         = is_array($value) ? ($value['user'] ?? null) : null;
            // 401 = التوكن غير صالح فعلاً → يُمسح. أي خطأ آخر (تايم آوت/5xx) يُبقيه.
            $unauthorized = (bool) ($client->get('unauthorized') ?? false);
        } catch (\Throwable $e) {
            $user = null;
        }

        if ($user === null && $unauthorized) {
            try { request()->session()->forget('amrtm_api_token'); } catch (\Throwable) {}
        }

        return $this->cachedRaw = $user;
    }
}
