<?php
// تحقق نهائي: بناء كل سكربتات JS وتمريرها على node --check
$f   = 'C:/react_projects/amrtm_business/blade-frontend-clean/resources/views/update_service/dashboard/admin/_admin_js.blade.php';
$new = file_get_contents($f);
echo 'file bytes: ' . strlen($new) . PHP_EOL;
echo 'remaining <x-  : ' . substr_count($new, '<x-') . PHP_EOL;
echo 'remaining </x- : ' . substr_count($new, '</x-') . PHP_EOL;
echo 'blade @if/@foreach count: ' . preg_match_all('/@(if|foreach|php|endif|endforeach)/', $new) . PHP_EOL;

$tmpDir = 'C:/Users/hisha/AppData/Local/Temp/opencode';
// نجمع JS من داخل @push('scripts') blocks ونصوص script
preg_match_all('/<script(?:\s[^>]*)?>(.*?)<\/script>/s', $new, $m);
$i = 0;
$fail = 0;
foreach ($m[1] as $js) {
    // إزالة تعليقات Blade إن وجدت
    $js = trim($js);
    if ($js === '') {
        continue;
    }
    $i++;
    $p = $tmpDir . '/_chk' . $i . '.js';
    file_put_contents($p, $js);
    $out = [];
    $rc = 1;
    exec('node --check "' . $p . '" 2>&1', $out, $rc);
    echo "script #$i (" . strlen($js) . "b) => " . ($rc === 0 ? 'OK' : 'FAIL') . PHP_EOL;
    if ($rc !== 0) {
        $fail++;
        foreach (array_slice($out, 0, 6) as $l) {
            echo '    ' . $l . PHP_EOL;
        }
    }
}
echo "total=$i failed=$fail" . PHP_EOL;
