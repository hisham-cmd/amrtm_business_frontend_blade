<?php

namespace App\Providers;

use App\Support\BackendApiWithToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private ?array $cachedUser = null;
    private bool $fetched = false;

    public function register(): void {}

    /**
     * النسخة النظيفة: لا Models ولا DB محلية.
     * نضخ «المستخدم الحالي» من الـ API المنفصل إلى كل قالب:
     * - frontUser / frontAuthed → يستهلكه الناف بار (nb-auth / nb-guest).
     * - نثبّت المستخدم على حارس business عبر setUser (بلا DB) → أي كود
     *   قديم يستدعي auth('business')->check()/user() يرى الحالة.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $userObj = $this->apiUserObject();
            $view->with('frontUser', $userObj);
            $view->with('frontAuthed', $userObj !== null);
            $view->with('currentAuthUser', $userObj);

            if ($userObj) {
                try {
                    Auth::guard('business')->setUser($userObj);
                } catch (\Throwable) {
                }
            }
        });
    }

    /** كائن مستخدم خفيف (أو null) من الـ API — مجلوب مرة واحدة لكل طلب */
    private function apiUserObject(): ?\stdClass
    {
        $user = $this->apiUser();
        if (!$user) {
            return null;
        }

        $u = new \stdClass();
        $u->id           = $user['id'] ?? null;
        $u->name         = $user['name'] ?? '';
        $u->email        = $user['email'] ?? '';
        $u->phone        = $user['phone'] ?? '';
        $u->role         = $user['role'] ?? 'user';
        $u->account_type = $user['account_type'] ?? 'individual';
        $u->is_active    = true;
        $u->is_admin     = in_array($u->role, ['admin', 'supervisor'], true);

        return $u;
    }

    private function token(): ?string
    {
        try {
            $s = app('session.store');

            return $s->get('amrtm_api_token');
        } catch (\Throwable) {
            return request()->session()->get('amrtm_api_token');
        }
    }

    private function apiUser(): ?array
    {
        if ($this->fetched) {
            return $this->cachedUser;
        }
        $this->fetched = true;

        $token = $this->token();
        if (!$token) {
            return $this->cachedUser = null;
        }

        try {
            $resp  = (new BackendApiWithToken($token))->call('GET', '/api/v1/auth/me');
            $value = $resp->get('value');
            $user  = is_array($value) ? ($value['user'] ?? null) : null;
        } catch (\Throwable) {
            $user = null;
        }

        if ($user === null) {
            try { request()->session()->forget('amrtm_api_token'); } catch (\Throwable) {}
        }

        return $this->cachedUser = $user;
    }
}