<?php

namespace App\Support;

/**
 * تصنيفات المستشارين الثابتة — نسخة نظيفة مستقلة عن أي قاعدة بيانات.
 * تُستخدم في صفحات المستشارين (vحتى لو فشل الـ API).
 */
class ConsultantCatalog
{
    public static function categories(): array
    {
        return [
            ['key' => 'legal', 'name_ar' => 'الاستشارات القانونية'],
            ['key' => 'financial', 'name_ar' => 'الاستشارات المالية'],
            ['key' => 'engineering', 'name_ar' => 'الاستشارات الهندسية'],
            ['key' => 'management', 'name_ar' => 'استشارات إدارية'],
        ];
    }

    public static function businessActivities(): array
    {
        return [
            ['key' => 'legal', 'name_ar' => 'أنشطة قانونية'],
            ['key' => 'financial', 'name_ar' => 'أنشطة مالية'],
            ['key' => 'engineering', 'name_ar' => 'أنشطة هندسية'],
        ];
    }
}