<?php

namespace App\Http\Controllers;

use App\Support\BackendApi;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ServiceCatalogController — النسخة النظيفة (المسلَّمة للعميل).
 *
 * هذه الواجهة لا تحتوي أي Models ولا أي اتصال بقاعدة بيانات:
 * كل البيانات تأتي حصرياً من الـ BackendApi عبر سيرفر الباك اند المنفصل.
 * الملفات الحساسة (Models, database, منطق DB) لا وجود لها هنا إطلاقاً.
 */
class ServiceCatalogController extends Controller
{
    /** الصفحة الرئيسية — GET /api/v1/home */
    public function index(): View
    {
        $data = BackendApi::get('/api/v1/home');

        $categories       = collect($data->get('categories', []))->map(fn($c) => (array) $c)->all();
        $officeCounts     = collect($data->get('officeCounts', []))->map(fn($v) => (int) $v)->all();
        $homepageSlides   = collect($data->get('homepageSlides', []))->map(fn($s) => (array) $s)->all();
        $homepageSettings = collect($data->get('homepageSettings', []))->map(fn($v) => (string) $v)->all();
        $homepageMedia    = (array) $data->get('homepageMedia', [
            'video_file'   => 'videos/0829.mp4',
            'video_poster' => 'images/logo2.jpg',
        ]);
        $catColorMap = [
            'ministries'  => '#3B82F6',
            'authorities' => '#A855F7',
            'companies'   => '#22C55E',
            'embassies'   => '#06B6D4',
            'consultants' => '#F97316',
        ];
        $totalEntities = 0;
        $totalServices = 0;

        return view('update_service.index', compact(
            'categories',
            'totalEntities',
            'totalServices',
            'officeCounts',
            'homepageSettings',
            'homepageSlides',
            'homepageMedia',
            'catColorMap'
        ));
    }

    /**
     * صفحة تصنيف — GET /api/v1/catalog/{key}
     *
     * قاعدة البيانات (عبر الـ API) هي المصدر الوحيد.
     * أي فشل ⇒ 404 صريح. لا بيانات وهمية إطلاقاً.
     */
    public function categoryPage(Request $request, string $key): View
    {
        $viaApi = BackendApi::get("/api/v1/catalog/{$key}");

        if (BackendApi::isFailed($viaApi)) {
            $this->logCatalogFailure('categoryPage', $key);
            abort(404, 'التصنيف غير موجود');
        }

        $cat = (array) $viaApi->get('category', []);
        $raw = $viaApi->get('entities', []);
        $raw = $raw instanceof \Illuminate\Support\Collection ? $raw->all() : (array) $raw;

        $category = (object) [
            'id'             => $cat['id'] ?? null,
            'key'            => $cat['key'] ?? $key,
            'name_ar'        => $cat['name_ar'] ?? '',
            'name_en'        => $cat['name_en'] ?? '',
            'icon'           => $cat['icon'] ?? null,
            'color'          => $cat['color'] ?? null,
            'bg'             => $cat['bg'] ?? null,
            'entities_count' => (int) ($cat['entities_count'] ?? count($raw)),
        ];

        $entities = collect($raw)->map(function ($e) {
            $e       = (array) $e;
            $services = collect($e['services'] ?? []);
            $services = $services->map(fn($s) => $this->mapService((array) $s))->values();

            return (object) [
                'id'             => $e['id'] ?? null,
                'name_ar'        => $e['name_ar'] ?? '',
                'name_en'        => $e['name_en'] ?? '',
                'icon'           => $e['icon'] ?? null,
                'color'          => $e['color'] ?? null,
                'bg'             => $e['bg'] ?? null,
                'tag_ar'         => $e['tag_ar'] ?? null,
                'tag_en'         => $e['tag_en'] ?? null,
                'images'         => $e['images'] ?? null,
                'image_url'      => $e['image_url'] ?? null,
                'govServices'    => $services,
                'services'       => $services,
                'services_count' => $services->count(),
            ];
        })->values();

        $totalServices    = $entities->sum(fn($e) => $e->services->count());
        $allServicesCount = (int) ($cat['services_count'] ?? $totalServices);

        return view('update_service.catalog_category', compact('category', 'entities', 'totalServices', 'allServicesCount'));
    }

    /**
     * صفحة جهة — GET /api/v1/catalog/{key}/{id}
     *
     * قاعدة البيانات (عبر الـ API) هي المصدر الوحيد.
     * أي فشل ⇒ 404 صريح. لا بيانات وهمية إطلاقاً.
     */
    public function entityPage(Request $request, string $key, int $entityId): View
    {
        $viaApi = BackendApi::get("/api/v1/catalog/{$key}/{$entityId}");

        if (BackendApi::isFailed($viaApi) || ! $viaApi->get('entity')) {
            $this->logCatalogFailure('entityPage', "{$key}/{$entityId}");
            abort(404, 'الجهة غير موجودة');
        }

        $cat = (array) $viaApi->get('category', []);
        $ent = (array) $viaApi->get('entity');

        $category = $cat ? (object) [
            'id'      => $cat['id'] ?? null,
            'key'     => $cat['key'] ?? $key,
            'name_ar' => $cat['name_ar'] ?? '',
            'name_en' => $cat['name_en'] ?? '',
            'icon'    => $cat['icon'] ?? null,
            'color'   => $cat['color'] ?? null,
            'bg'      => $cat['bg'] ?? null,
        ] : null;

        $entity = (object) [
            'id'         => $ent['id'] ?? $entityId,
            'name_ar'    => $ent['name_ar'] ?? '',
            'name_en'    => $ent['name_en'] ?? '',
            'icon'       => $ent['icon'] ?? null,
            'color'      => $ent['color'] ?? null,
            'bg'         => $ent['bg'] ?? null,
            'tag_ar'     => $ent['tag_ar'] ?? null,
            'tag_en'     => $ent['tag_en'] ?? null,
            'images'     => $ent['images'] ?? null,
            'image_url'  => $ent['image_url'] ?? null,
            'govServices' => collect($viaApi->get('services', []))
                ->map(fn($s) => $this->mapService((array) $s))
                ->values(),
        ];

        $allServices = $entity->govServices;
        $services    = $entity->govServices;

        return view('update_service.catalog_entity', compact('category', 'entity', 'allServices', 'services'));
    }

    /**
     * تحويل بيانات الخدمة القادمة من الـ API إلى كائن carries كل الحقول
     * التي تحتاجها القوالب — أهمها `custom_fields` (الحقول المخصصة).
     *
     * @param  array<string, mixed>  $s
     * @return object
     */
    private function mapService(array $s): object
    {
        $customFields = $s['custom_fields'] ?? [];
        if (is_string($customFields)) {
            $customFields = json_decode($customFields, true);
        }
        $customFields = is_array($customFields) ? array_values($customFields) : [];

        return (object) [
            'id'             => $s['id'] ?? null,
            'entity_id'      => $s['entity_id'] ?? null,
            'name_ar'        => $s['name_ar'] ?? '',
            'name_en'        => $s['name_en'] ?? '',
            'icon'           => $s['icon'] ?? null,
            'price'          => $s['price'] ?? 0,
            'duration'       => $s['duration'] ?? null,
            'duration_min'   => $s['duration_min'] ?? null,
            'duration_max'   => $s['duration_max'] ?? null,
            'duration_unit'  => $s['duration_unit'] ?? null,
            'description_ar' => $s['description_ar'] ?? null,
            'description_en' => $s['description_en'] ?? null,
            'custom_fields'  => $customFields,
            'image_url'      => $s['image_url'] ?? null,
        ];
    }

    /**
     * تسجيل واضح عند فشل جلب بيانات الكتالوج — بدل إخفائها بمحتوى وهمي.
     */
    private function logCatalogFailure(string $method, string $target): void
    {
        \Illuminate\Support\Facades\Log::warning(
            "ServiceCatalogController::{$method} failed for [{$target}] — "
            . 'تعذّر جلب البيانات الحقيقية من قاعدة البيانات. تم رفض عرض أي محتوى بديل.'
        );
    }

    /** دليل المستشارين — GET /api/v1/consultants */
    public function consultantsDirectory(): View
    {
        $consultants = BackendApi::get('/api/v1/consultants');
        $specialties  = BackendApi::get('/api/v1/consultant-specialties');

        $data = [
            'consultants' => $consultants,
            'specialties' => $specialties->get('specialties', []),
            'categories'  => \App\Support\ConsultantCatalog::categories(),
            'activities'  => \App\Support\ConsultantCatalog::businessActivities(),
        ];

        return view('update_service.consultants_directory', $data);
    }

    /** دليل المكاتب — GET /api/v1/offices/{type} (تخصصات) */
    public function officeDirectory(Request $request, string $type): View
    {
        $data = BackendApi::get("/api/v1/offices/{$type}");
        $raw = $data->get('specialties', []);
        $raw = $raw instanceof \Illuminate\Support\Collection ? $raw->all() : (array) $raw;

        $typeConfig = $data->get('config', []);
        $typeConfig = $typeConfig instanceof \Illuminate\Support\Collection ? $typeConfig->all() : (array) $typeConfig;

        $specialties = collect($raw)->map(function ($s) {
            $s = $s instanceof \Illuminate\Support\Collection ? $s->all() : (array) $s;
            $servicesRaw = $s['services'] ?? [];
            $servicesRaw = $servicesRaw instanceof \Illuminate\Support\Collection ? $servicesRaw->all() : (array) $servicesRaw;

            // القالب يتوقع: specialty (كائن له name_ar/name_en) + services (مصفوفة) + عدّادات
            return [
                'id'             => $s['id'] ?? null,
                'name_ar'        => $s['name_ar'] ?? '',
                'name_en'        => $s['name_en'] ?? '',
                'services_count' => (int) ($s['services_count'] ?? count($servicesRaw)),
                'offices_count'  => (int) ($s['offices_count'] ?? 0),
                'specialty'      => (object) [
                    'name_ar' => $s['name_ar'] ?? '',
                    'name_en' => $s['name_en'] ?? ($s['name_ar'] ?? ''),
                ],
                'services'       => array_map(fn($svc) => (array) $svc, $servicesRaw),
            ];
        });
        $cfg = [
            'icon'    => $typeConfig['icon'] ?? 'ti-building',
            'color'   => $typeConfig['color'] ?? '#006C35',
            'accent'  => $typeConfig['accent'] ?? '#0B3B2C',
            'gradient'=> $typeConfig['gradient'] ?? 'linear-gradient(135deg,#0B3B2C,#006C35)',
            'badge_ar'=> $typeConfig['badge_ar'] ?? 'مكاتب متخصصة',
            'hint_ar' => $typeConfig['hint_ar'] ?? 'اختر التخصص المناسب',
            'name_ar' => $typeConfig['name_ar'] ?? $type,
            'name_en' => $typeConfig['name_en'] ?? $type,
            'desc_ar' => $typeConfig['desc_ar'] ?? '',
        ];
        $totalOffices = $data->get('total_offices', 0);
        $specialtiesCount = $specialties->count();

        return view('update_service.office_directory', compact('type', 'specialties', 'cfg', 'totalOffices', 'specialtiesCount'));
    }
}