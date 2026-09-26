<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * تصنيفات المستشارين والأنشطة التجارية.
 *
 * المصدر الأساسي هو الباك اند المنفصل (BACKEND_API_URL) عبر
 * GET /api/v1/consultant-specialties الذي يُرجع:
 *   { categories: {key: {label_ar,label_en,icon}}, businessActivities: {...},
 *     specialties: [ {id,name_ar,name_en,office_type,category,business_activity,consultants_count} ] }
 *
 * الدوال هنا에서의 قيم بديلة ثابتة تُستخدم فقط عند فشل الاتصال،
 * وبالعقد نفسه الذي تتوقعه القوالب: مصفوفة مفتاحها المفتاح وكل قيمة
 * ['label_ar' => ..., 'label_en' => ..., 'icon' => ...].
 */
class ConsultantCatalog
{
    /** القيم الاحتياطية (نفس عقد الـ API) — تُستخدم فقط عند تعذّر الاتصال. */
    private const FALLBACK_CATEGORIES = [
        'legal'      => ['label_ar' => 'القانونية والحوكمة', 'label_en' => 'Legal & Governance', 'icon' => 'ti-scale'],
        'finance'    => ['label_ar' => 'المالية والمحاسبة والاستثمار', 'label_en' => 'Finance & Investment', 'icon' => 'ti-coin'],
        'management' => ['label_ar' => 'الإدارة والاستراتيجية', 'label_en' => 'Management & Strategy', 'icon' => 'ti-briefcase'],
        'tech'       => ['label_ar' => 'التقنية والتحول الرقمي', 'label_en' => 'Technology & Digital', 'icon' => 'ti-device-desktop'],
        'industrial' => ['label_ar' => 'الهندسة والتصنيع', 'label_en' => 'Engineering & Manufacturing', 'icon' => 'ti-building-factory'],
        'realestate' => ['label_ar' => 'العقارات والإنشاءات', 'label_en' => 'Real Estate & Construction', 'icon' => 'ti-building-skyscraper'],
        'logistics'  => ['label_ar' => 'النقل واللوجستيات', 'label_en' => 'Transport & Logistics', 'icon' => 'ti-truck'],
        'health'     => ['label_ar' => 'الصحة والدواء', 'label_en' => 'Health & Pharma', 'icon' => 'ti-stethoscope'],
    ];

    private const FALLBACK_ACTIVITIES = [
        'government'   => ['label_ar' => 'القطاع الحكومي وشبه الحكومي', 'label_en' => 'Public Sector', 'icon' => 'ti-building-community'],
        'financial'    => ['label_ar' => 'القطاع المالي والمصرفي', 'label_en' => 'Financial Sector', 'icon' => 'ti-coin'],
        'industrial'   => ['label_ar' => 'القطاع الصناعي والتعديني', 'label_en' => 'Industrial & Mining', 'icon' => 'ti-building-factory'],
        'construction' => ['label_ar' => 'قطاع العقارات والمقاولات', 'label_en' => 'Real Estate & Contracting', 'icon' => 'ti-building-skyscraper'],
        'retail'       => ['label_ar' => 'قطاع التجارة والتجزئة', 'label_en' => 'Retail & Trade', 'icon' => 'ti-shopping-bag'],
        'services'     => ['label_ar' => 'قطاع الخدمات', 'label_en' => 'Services Sector', 'icon' => 'ti-building'],
        'transport'    => ['label_ar' => 'قطاع النقل واللوجستيات', 'label_en' => 'Transport & Logistics', 'icon' => 'ti-truck'],
    ];

    /** ذاكرة مؤقتة للطلب داخل نفس العملية. */
    private static ?array $cache = null;

    /**
     * يستدعي الباك اند ويعيد [categories, businessActivities, specialties]
     * أو null عند الفشل.
     *
     * @return array{0: array, 1: array, 2: array}|null
     */
    private static function fetchFromBackend(): ?array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $base = rtrim((string) env('BACKEND_API_URL', 'http://127.0.0.1:8000'), '/');
            $resp = Http::timeout(15)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($base . '/api/v1/consultant-specialties');

            if ($resp->successful()) {
                $json = $resp->json();

                if (is_array($json) && isset($json['specialties'])) {
                    return self::$cache = [
                        is_array($json['categories'] ?? null) ? $json['categories'] : self::FALLBACK_CATEGORIES,
                        is_array($json['businessActivities'] ?? null) ? $json['businessActivities'] : self::FALLBACK_ACTIVITIES,
                        is_array($json['specialties'] ?? null) ? $json['specialties'] : [],
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ConsultantCatalog fetch failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * تصنيفات المستشارين — ["key" => ["label_ar","label_en","icon"]]
     */
    public static function categories(): array
    {
        return self::fetchFromBackend()[0] ?? self::FALLBACK_CATEGORIES;
    }

    /**
     * الأنشطة التجارية — ["key" => ["label_ar","label_en","icon"]]
     */
    public static function businessActivities(): array
    {
        return self::fetchFromBackend()[1] ?? self::FALLBACK_ACTIVITIES;
    }

    /**
     * التخصصات المعتمدة من الباك اند (اسمها يطابق specialtyCards في القوالب).
     */
    public static function specialties(): array
    {
        return self::fetchFromBackend()[2] ?? [];
    }

    /**
     * ترجمة عربية لمفتاح نوع المكتب (law / services / …) تستخدمه الواجهة
     * عندما لا يصل نص عربي من الـ API.
     */
    public static function officeTypeLabel(?string $type): ?string
    {
        if (! $type) {
            return null;
        }

        return self::OFFICE_TYPE_LABELS[$type] ?? null;
    }

    private const OFFICE_TYPE_LABELS = [
        'law'         => 'مكاتب المحاماة',
        'services'    => 'مكاتب الخدمات والتعقيب',
        'customs'     => 'شركات التخليص الجمركي',
        'accounting'  => 'مكاتب المحاسبة والاستشارات المالية',
        'engineering' => 'مكاتب الهندسة والاستشارات الفنية',
        'freelance'   => 'أصحاب المهن الحرة',
    ];

    /**
     * بيانات تصنيف واحد بمفتاحه — تُستخدم في القوالب:
     *   ConsultantCatalog::category($spec['category'])
     *
     * @return array{label_ar:string,label_en:string,icon:string,key:string}|null
     *         null إذا كان المفتاح غير معروف
     */
    public static function category(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }

        $all = self::categories();
        if (isset($all[$key]) && is_array($all[$key])) {
            return ['key' => $key] + $all[$key];
        }

        return null;
    }

    /**
     * بيانات نشاط تجاري واحد بمفتاحه:
     *   ConsultantCatalog::businessActivity($spec['business_activity'])
     *
     * @return array{label_ar:string,label_en:string,icon:string,key:string}|null
     */
    public static function businessActivity(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }

        $all = self::businessActivities();
        if (isset($all[$key]) && is_array($all[$key])) {
            return ['key' => $key] + $all[$key];
        }

        return null;
    }
}
