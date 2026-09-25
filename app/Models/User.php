<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * مستخدم وهمي محلي — لا يُستخدم أبداً في النسخة النظيفة.
 * موجود فقط لإرضاء إعدادات Laravel الافتراضية (config/auth).
 * كل المصادقة الفعلية تحدث في الباك اند عبر BackendApi.
 */
class User extends Authenticatable
{
    /** لا جدول — نسخة نظيفة بلا قاعدة بيانات محلية. */
    protected $table = null;
}