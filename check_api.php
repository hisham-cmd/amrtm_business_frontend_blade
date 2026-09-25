<?php
// فحص BackendApi من داخل تطبيق Laravel
error_reporting(E_ALL); ini_set('display_errors', '1');
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $d = App\Support\BackendApi::get('/api/v1/home');
    echo "CATS: " . collect($d->get('categories'))->count() . "\n";
    echo "DATA KEYS: " . implode(',', $d->keys()->all()) . "\n";
} catch (Throwable $e) {
    echo "EXC: " . get_class($e) . " :: " . $e->getMessage() . "\n";
    echo "AT: " . $e->getFile() . ":" . $e->getLine() . "\n";
}