<?php
/**
 * إصلاح (2): المكوّنات المتبقية الممتدة على عدة أسطر داخل JavaScript.
 * نقرأ الملف كاملاً، ونطابق بـ preg_replace_callback مع عدّاد استبدال محدود،
 * ولا نكتب إن كانت النتيجة فارغة/أصغر بكثير.
 */
$f = 'C:/react_projects/amrtm_business/blade-frontend-clean/resources/views/update_service/dashboard/admin/_admin_js.blade.php';
$src = file_get_contents($f);
if ($src === false || strlen($src) < 1000) {
    exit("ABORT: source read failed/too small\n");
}
$len0 = strlen($src);
$before = substr_count($src, '<x-');

$map = [
    'button' => 'button', 'input' => 'input', 'select' => 'select',
    'textarea' => 'textarea', 'label' => 'label', 'a' => 'a', 'span' => 'span',
    'div' => 'div', 'p' => 'p', 'i' => 'i', 'table' => 'table', 'tr' => 'tr',
    'td' => 'td', 'th' => 'th', 'ul' => 'ul', 'li' => 'li', 'h1' => 'h1',
    'h2' => 'h2', 'h3' => 'h3', 'strong' => 'strong', 'small' => 'small',
    'option' => 'option', 'form' => 'form', 'img' => 'img', 'b' => 'b',
];

$n = 0;
// نمط يسمح بأسطر متعددة داخل الوسوم عبر (?s) على السمات التي لا تحتوي < > من مكونات أخرى
$pattern = '#<x-ui\.([a-zA-Z]+)((?:\s(?:[^<>]|<(?!\/?x-))*?)?)>(.*?)</x-ui\.\1>#s';

$out = preg_replace_callback(
    $pattern,
    static function ($m) use ($map, &$n) {
        $n++;
        $name  = strtolower($m[1]);
        $attrs = trim($m[2]);
        $inner = $m[3];
        $tag   = $map[$name] ?? $name;
        if ($name === 'checkbox') {
            return '<input type="checkbox" ' . $attrs . '>' . $inner . '</input>';
        }
        if ($name === 'input') {
            return '<input ' . $attrs . '>' . $inner . '</input>';
        }
        return '<' . $tag . ' ' . $attrs . '>' . $inner . '</' . $tag . '>';
    },
    $src,
    -1,
    $count
);

if ($out === null || strlen($out) < 1000) {
    exit("ABORT: replacement produced invalid/empty output\n");
}

$after = substr_count($out, '<x-');
$w = file_put_contents($f, $out);
echo "orig bytes : $len0" . PHP_EOL;
echo "new  bytes : " . strlen($out) . PHP_EOL;
echo "replaced   : $count (callbacks=$n)" . PHP_EOL;
echo "write      : " . var_export($w, true) . PHP_EOL;
echo "before <x- : $before" . PHP_EOL;
echo "after  <x- : $after" . PHP_EOL;
