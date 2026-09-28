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

$php = PHP_BINARY ?: 'php';

/* 1) اقرأ آخر خطأ من سجل Laravel قبل أي شيء — هذا هو سبب 500 عادةً */
$rows = [];
$rows[] = '=========================================';
$rows[] = ' Laravel Cache Maintenance';
$rows[] = '=========================================';
$rows[] = 'PHP      : ' . PHP_VERSION . "\n";
$rows[] = 'المجلد   : ' . $root . "\n";
$rows[] = 'التاريخ  : ' . date('Y-m-d H:i:s') . "\n";
$rows[] = 'artisan  : ' . (is_file($root . '/artisan') ? 'موجود ✓' : 'غير موجود ✗') . "\n\n";

/* 1) تشخيص: آخر خطأ في السجل */
$rows[] = '--- تشخيص: آخر خطأ في السجل ---';
$logFile = $root . '/storage/logs/laravel.log';
if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $tail  = array_slice($lines, -6);
    if ($tail === []) {
        $rows[] = '  السجل فارغ.';
    } else {
        foreach ($tail as $l) {
            $rows[] = '  ' . mb_substr(trim($l), 0, 400);
        }
    }
} else {
    $rows[] = '  لا يوجد ملف سجل (أو لا صلاحية قراءة).';
}
$rows[] = '';

/* 2) فحص الصلاحيات */
$rows[] = '--- الصلاحيات ---';
foreach ([
    '/storage'             => 'storage',
    '/storage/logs'        => 'storage/logs',
    '/storage/framework'   => 'storage/framework',
    '/storage/framework/cache' => 'كاش الإعدادات',
    '/storage/framework/views'  => 'قوالب مصرَّفة',
    '/bootstrap/cache'     => 'bootstrap/cache',
] as $rel => $label) {
    $d = $root . $rel;
    if (! is_dir($d)) {
        $rows[] = sprintf('  %-26s غير موجود', $label);
        continue;
    }
    $ok      = @is_writable($d);
    $perms   = @decoct(@fileperms($d) & 0777);

    $rows[] = sprintf('  %-26s %s (%s)', $label, $ok ? 'قابل للكتابة ✓' : 'غير قابل للكتابة ✗', $perms);
}
$rows[] = '';

/* 3) فحص .env */
$rows[] = '--- ملف البيئة ---';
$envFile = $root . '/.env';
if (is_file($envFile)) {
    $rows[] = '  موجود ✓ (' . filesize($envFile) . ' بايت)';
    $env = (string) file_get_contents($envFile);
    $vals = [];
    foreach (explode("\n", $env) as $line) {
        $l = trim($line);
        if ($l === '' || $l[0] === '#' || ! str_contains($l, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $l, 2);
        $vals[trim($k)] = trim($v);
    }
    foreach (['APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_KEY', 'DB_HOST', 'DB_DATABASE', 'BACKEND_API_URL', 'CACHE_STORE'] as $k) {
        $v = $vals[$k] ?? '(مفقود)';
        if ($k === 'APP_KEY' && str_starts_with($v, 'base64:')) {
            $v = mb_substr($v, 0, 20) . '…';
        }
        $rows[] = sprintf('  %-16s %s', $k, $v);
    }
} else {
    $rows[] = '  ⚠️ لا يوجد .env! (على الإنتاج يُفضّل نسخ .env.production فوق .env)';
}
$rows[] = '';

/* 4) كشف أخطاء الإقلاع: نحاكي إقلاع Laravel كاملاً ثم نقرأ الإعدادات */
$rows[] = '--- كشف أخطاء الإقلاع ---';
$probe = <<<'PHP'
$out = [];
try {
    require __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';

    // نُقلع الـ kernel حتى تُربط كل الخدمات (منها config)
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    $out['app_env'] = $app->environment();
    $out['app_url'] = config('app.url');
    $out['cache']   = config('cache.default');
    $out['db_host'] = config('database.connections.mysql.host');
    $out['db_name'] = config('database.connections.mysql.database');
} catch (\Throwable $e) {
    $out['ERROR'] = $e::class . ': ' . $e->getMessage();
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
PHP;

$probeFile = $root . '/_probe_boot.php';
file_put_contents($probeFile, "<?php\n" . $probe . "\n");

$o = [];
$c = 0;
exec('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg($php) . ' _probe_boot.php 2>&1', $o, $c);
@unlink($probeFile);

foreach ($o as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    $j = json_decode($line, true);
    if (is_array($j)) {
        foreach ($j as $k => $v) {
            $rows[] = sprintf('  %-16s %s', $k, $v);
        }
    } else {
        $rows[] = '  ' . mb_substr($line, 0, 300);
    }
}
$rows[] = '';

/* 5) تنفيذ أوامر artisan */
$rows[] = '--- تنفيذ الأوامر ---';
$steps = [
    'optimize:clear' => 'تنظيف الكاش',
    'config:cache'   => 'بناء كاش الإعدادات',
    'route:cache'    => 'بناء كاش المسارات',
    'view:cache'     => 'بناء كاش القوالب',
];

foreach ($steps as $cmd => $label) {
    $rows[] = "▶ {$label} ({$cmd}) … ";

    $output = [];
    $code   = 0;
    exec(
        'cd ' . escapeshellarg($root) . ' && ' .
        escapeshellarg($php) . ' artisan ' . $cmd . ' 2>&1',
        $output,
        $code
    );

    $rows[] = ($code === 0 ? '   نجح' : "   فشل (رمز {$code})");
    foreach ($output as $line) {
        $rows[] = '    ' . mb_substr(trim($line), 0, 200);
    }
    $rows[] = '';
}

/* 6) النتيجة النهائية */
$rows[] = '=========================================';
$rows[] = ' النتيجة';
$rows[] = '=========================================';
$rows[] = '  افتح: https://business.amrtm.com.sa/__diag';
$rows[] = '  أو افتح: https://business.amrtm.com.sa/';
$rows[] = '';
$rows[] = '  ⚠️ احذف هذا الملف من الخادم الآن';
$rows[] = '';

echo implode("\n", $rows);
exit;
