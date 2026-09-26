<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

/**
 * ApiUser — كائن المستخدم الحالي في واجهة Blade النظيفة.
 *
 * الواجهة لا تملك قاعدة بيانات محلية؛ المستخدم يأتي من
 * GET /api/v1/auth/me عبر BackendApiWithToken.
 *
 * لماذا هذا الصنف؟
 * ‏SessionGuard::setUser() يطلب كائناً يطبّق Illuminate\Contracts\Auth\Authenticatable.
 * تمرير stdClass كان يرمي TypeError (يُلتقط بصمت) فيبقى
 * auth('business')->check() === false و auth('business')->user() === null،
 * فيختفي الحساب المسجّل من كل الصفحات التي تعتمد على الـ guard.
 *
 * كل الخصائص تُقرأ من مصفوفة بيانات الـ API عبر __get، فيعمل أي قالب
 * يحتاج ->name أو ->email أو ->role أو ->office أو ->logo_url.
 */
class ApiUser implements AuthenticatableContract
{
    /** @var array<string,mixed> بيانات المستخدم كما أعادتها الـ API */
    protected array $data;

    /** @var string|null */
    protected $rememberToken = null;

    public function __construct(array $data = [])
    {
        // تطبيع مبدئي: الأدوار التي تدير النظام
        $role = $data['role'] ?? 'user';
        $data['role']          = $role;
        $data['account_type']  = $data['account_type'] ?? 'individual';
        $data['is_active']     = $data['is_active'] ?? true;
        $data['is_admin']      = $data['is_admin'] ?? in_array($role, ['admin', 'supervisor'], true);
        $data['name']          = $data['name'] ?? '';
        $data['email']         = $data['email'] ?? '';
        $data['phone']         = $data['phone'] ?? '';

        $this->data = $data;
    }

    /* ═══ Authenticatable ═══════════════════════════════════════════════ */

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->data['id'] ?? null;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->data['password'] ?? '');
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken($value): void
    {
        $this->rememberToken = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /* ═══ وصول حر للخصائص ═════════════════════════════════════════════ */

    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function __toString(): string
    {
        return (string) ($this->data['name'] ?? '');
    }

    /* ═══ مساعدات ═════════════════════════════════════════════════════ */

    /** هل المستخدم مدير/مشرف؟ */
    public function isAdmin(): bool
    {
        return (bool) ($this->data['is_admin'] ?? false);
    }

    /** أول اسم من الاسم الكامل (للعرض في الناف بار) */
    public function firstName(): string
    {
        $name = trim((string) ($this->data['name'] ?? ''));

        return $name === '' ? '' : (explode(' ', $name)[0] ?? $name);
    }

    /** كل البيانات (للحقن في JSON) */
    public function toArray(): array
    {
        return $this->data;
    }
}
