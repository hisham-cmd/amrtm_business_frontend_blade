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

    /** صفحة تصنيف — GET /api/v1/catalog/{key} */
    public function categoryPage(Request $request, string $key): View
    {
        $viaApi = BackendApi::get("/api/v1/catalog/{$key}");
        if ($viaApi->isNotEmpty()) {
            $cat = $viaApi->get('category');
            $cat = is_array($cat) ? $cat : (array) $cat;
            $raw = $viaApi->get('entities', []);
            $raw = $raw instanceof \Illuminate\Support\Collection ? $raw->all() : (array) $raw;

            $category = (object) [
                'id'      => $cat['id'] ?? null,
                'key'     => $cat['key'] ?? $key,
                'name_ar' => $cat['name_ar'] ?? '',
                'name_en' => $cat['name_en'] ?? '',
                'icon'    => $cat['icon'] ?? null,
                'color'   => $cat['color'] ?? '#006C35',
                'bg'      => $cat['bg'] ?? null,
            ];
            $entities = collect($raw)->map(function ($e) {
                $e = (array) $e;
                $services = collect($e['services'] ?? []);

                return (object) [
                    'id'             => $e['id'] ?? null,
                    'name_ar'        => $e['name_ar'] ?? '',
                    'name_en'        => $e['name_en'] ?? '',
                    'icon'           => $e['icon'] ?? null,
                    'color'          => $e['color'] ?? '#006C35',
                    'bg'             => $e['bg'] ?? null,
                    'tag_ar'         => $e['tag_ar'] ?? null,
                    'tag_en'         => $e['tag_en'] ?? null,
                    'images'         => $e['images'] ?? null,
                    'govServices'    => $services,
                    'services'       => $services,
                    'services_count' => $services->count(),
                ];
            });
            $totalServices    = $entities->sum(fn($e) => $e->services->count());
            $allServicesCount = $totalServices;

            return view('update_service.catalog_category', compact('category', 'entities', 'totalServices', 'allServicesCount'));
        }

        // Fallback بسيط بدون DB
        $category = (object) ['id' => null, 'key' => $key, 'name_ar' => $key, 'name_en' => $key, 'color' => '#006C35', 'bg' => null];
        $entities = collect();
        $totalServices = 0;
        $allServicesCount = 0;

        return view('update_service.catalog_category', compact('category', 'entities', 'totalServices', 'allServicesCount'));
    }

    /** صفحة جهة — GET /api/v1/catalog/{key}/{id} */
    public function entityPage(Request $request, string $key, int $entityId): View
    {
        $viaApi = BackendApi::get("/api/v1/catalog/{$key}/{$entityId}");
        if ($viaApi->isNotEmpty() && $viaApi->get('entity')) {
            $cat  = (array) $viaApi->get('category', []);
            $ent  = (array) $viaApi->get('entity');
            $svcs = $viaApi->get('services', []);

            $category = (object) [
                'id'      => $cat['id'] ?? null,
                'key'     => $cat['key'] ?? $key,
                'name_ar' => $cat['name_ar'] ?? '',
                'name_en' => $cat['name_en'] ?? '',
                'color'   => $cat['color'] ?? '#006C35',
                'bg'      => $cat['bg'] ?? null,
            ];
            $entity = (object) [
                'id'          => $ent['id'] ?? $entityId,
                'name_ar'     => $ent['name_ar'] ?? '',
                'name_en'     => $ent['name_en'] ?? '',
                'icon'        => $ent['icon'] ?? null,
                'color'       => $ent['color'] ?? '#006C35',
                'bg'          => $ent['bg'] ?? null,
                'tag_ar'      => $ent['tag_ar'] ?? null,
                'tag_en'      => $ent['tag_en'] ?? null,
                'images'      => $ent['images'] ?? null,
                'govServices' => collect($svcs)->map(fn($s) => (object) (array) $s)->values(),
            ];
            $allServices = $entity->govServices;
            $services    = $entity->govServices;

            return view('update_service.catalog_entity', compact('category', 'entity', 'allServices', 'services'));
        }

        $category = (object) ['id' => null, 'key' => $key, 'name_ar' => 'الجهة', 'name_en' => 'Entity', 'color' => '#006C35', 'bg' => null];
        $entity = (object) ['id' => $entityId, 'name_ar' => 'الجهة المطلوبة', 'name_en' => 'Requested Entity', 'govServices' => collect()];
        $allServices = collect();
        $services    = collect();

        return view('update_service.catalog_entity', compact('category', 'entity', 'allServices', 'services'));
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