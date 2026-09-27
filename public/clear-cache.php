<?php
/**
 * clear-cache.php — أداة صيانة لـ cPanel (بدون SSH).
 *
 * ⚠️ احذف هذا الملف من الخادم فور استخدامه.
 *
 * الاستخدام: افتح https://business.amrtm.com.sa/clear-cache.php
 */
/*
 * ⚠️ هذا الملف داخل public/ ليعمل عبر المتصفح، لكن artisan في جذر المشروع.
 * لذلك نرجع مستوى واحداً عند وجود artisan.
 */
$root = __DIR__;

if (! is_file($root . '/artisan') && is_file(dirname($root) . '/artisan')) {
    $root = dirname($root);
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "=========================================\n";
echo " Laravel Cache Maintenance\n";
echo "=========================================\n";
echo "PHP      : " . PHP_VERSION . "\n";
echo "المجلد   : {$root}\n";
echo "التاريخ  : " . date('Y-m-d H:i:s') . "\n";
echo "artisan  : " . (is_file($root . '/artisan') ? 'موجود ✓' : 'غير موجود ✗') . "\n\n";

/* 1) الصلاحيات — مطلوبة على الاستضافات المشتركة */
echo "▶ الصلاحيات … ";
$permsOk = true;
foreach (['/storage', '/bootstrap/cache'] as $dir) {
    $d = $root . $dir;
    if (is_dir($d) && ! @chmod($d, 0775)) {
        $permsOk = false;
        echo "فشل: {$dir} ";
    }
}
echo ($permsOk ? "تم" : "جزئي") . "\n\n";

/* 2) تنفيذ أوامر artisan */
$steps = [
    'optimize:clear' => 'تنظيف الكاش (config + route + view + compiled)',
    'config:cache'   => 'بناء كاش الإعدادات — يقرأ .env',
    'route:cache'    => 'بناء كاش المسارات',
    'view:cache'     => 'بناء كاش القوالب',
];

$php = PHP_BINARY ?: 'php';

foreach ($steps as $cmd => $label) {
    echo "▶ {$label} ({$cmd}) … ";

    $output = [];
    $code   = 0;
    exec(
        'cd ' . escapeshellarg($root) . ' && ' .
        escapeshellarg($php) . ' artisan ' . $cmd . ' 2>&1',
        $output,
        $code
    );

    echo ($code === 0 ? "نجح" : "فشل (رمز {$code})") . "\n";
    foreach ($output as $line) {
        echo '    ' . $line . "\n";
    }
    $output = [];
    echo "\n";
}

/* 3) عرض القيم الفعّالة — أهم خطوة للتأكد */
echo "=========================================\n";
echo " القيم التي يراها Laravel الآن\n";
echo "=========================================\n";

$output = [];
$code   = 0;
$phpCode = <<<'PHP'
$out = [
    'app_env'        => app()->environment(),
    'app_url'        => config('app.url'),
    'backend'        => env('BACKEND_API_URL'),
    'frontend'       => env('FRONTEND_URL'),
    'cache'          => config('cache.default'),
    'session_driver' => config('session.driver'),
    'db_database'    => config('database.connections.mysql.database'),
    'db_host'        => config('database.connections.mysql.host'),
];
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
PHP;

exec(
    'cd ' . escapeshellarg($root) . ' && ' .
    escapeshellarg($php) . ' artisan tinker --execute=' . escapeshellarg($phpCode) . ' 2>&1',
    $output,
    $code
);

foreach ($output as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, 'PHP ')) {
        continue;
    }
    $j = json_decode($line, true);
    if (is_array($j)) {
        foreach ($j as $k => $v) {
            printf("  %-15s %s\n", $k, $v);
        }
    } else {
        echo '  ' . $line . "\n";
    }
}

echo "\n=========================================\n";
echo " اختبارات سريعة\n";
echo "=========================================\n";

$tests = [
    'http://127.0.0.1:8000/up' => 'الباك اند (محلي)',
];

$diag = $root;
echo "  افتح الآن: https://business.amrtm.com.sa/__diag\n";
echo "  المتوقع  : app_env=production و app_url=https://business.amrtm.com.sa\n";
echo "             cookie_cached=true (يعني جدول cache يعمل)\n";

echo "\n=========================================\n";
echo " ⚠️ احذف هذا الملف من الخادم الآن\n";
echo "=========================================\n";
