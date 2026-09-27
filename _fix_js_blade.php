<?php
/**
 * إصلاح: تحويل Blade components داخل نصوص JavaScript إلى HTML خام.
 * الطريقة: استبدال نصّي مباشر (آمن، بدون preg على الملف كامل).
 */
$f = 'C:/react_projects/amrtm_business/blade-frontend-clean/resources/views/update_service/dashboard/admin/_admin_js.blade.php';
$src = file_get_contents($f);
if ($src === false || $src === '') {
    exit("READ FAIL / EMPTY\n");
}
$len0 = strlen($src);
$before = substr_count($src, '<x-');

// خريطة: اسم المكوّن -> الوسم الخام المقابل
$map = [
    'button'   => 'button',
    'input'    => 'input',
    'select'   => 'select',
    'textarea' => 'textarea',
    'label'    => 'label',
    'a'        => 'a',
    'span'     => 'span',
    'div'      => 'div',
    'p'        => 'p',
    'i'        => 'i',
    'table'    => 'table',
    'tr'       => 'tr',
    'td'       => 'td',
    'th'       => 'th',
    'ul'       => 'ul',
    'li'       => 'li',
    'h1'       => 'h1',
    'h2'       => 'h2',
    'h3'       => 'h3',
    'strong'   => 'strong',
    'small'    => 'small',
    'option'   => 'option',
    'form'     => 'form',
    'img'      => 'img',
];

// نبحث فقط داخل أسطر template literals التي تحتوي <x-ui. (لا نلمس Blade PHP الحقيقي)
$lines = explode("\n", $src);
$changed = 0;
$out = [];
foreach ($lines as $ln => $line) {
    if (strpos($line, '<x-ui.') === false) {
        $out[] = $line;
        continue;
    }
    $orig = $line;

    // 1) checkbox / input ثنائية等形式: <x-ui.checkbox ... />  => <input type="checkbox" ...>
    $line = preg_replace_callback(
        '#<x-ui\.checkbox\s+([^<>]*?)/>#',
        static fn($m) => '<input type="checkbox" ' . trim($m[1]) . '>',
        $line
    ) ?: $line;

    // 2) كل الأزواج <x-ui.NAME ...>...</x-ui.NAME>
    $line = preg_replace_callback(
        '#<x-ui\.([a-zA-Z]+)((?:\s[^<>]*?)?)>(.*?)</x-ui\.\1>#s',
        static function ($m) use ($map) {
            $name = strtolower($m[1]);
            $attrs = trim($m[2]);
            $inner = $m[3];
            $tag = $map[$name] ?? $name;
            if ($name === 'checkbox') {
                return '<input type="checkbox" ' . $attrs . '>' . $inner . '</input>';
            }
            if ($name === 'input') {
                return '<input ' . $attrs . '>' . $inner . '</input>';
            }
            return '<' . $tag . ' ' . $attrs . '>' . $inner . '</' . $tag . '>';
        },
        $line
    ) ?: $line;

    // 3) أي متبقٍ مكوّن مكشوف/غير مكتمل
    $line = str_replace('<x-ui.checkbox ', '<input type="checkbox" ', $line);
    $line = str_replace('</x-ui.checkbox>', '</input>', $line);
    $line = str_replace('<x-ui.input ', '<input ', $line);
    $line = str_replace('</x-ui.input>', '</input>', $line);

    if ($line !== $orig) {
        $changed++;
    }
    $out[] = $line;
}

$new = implode("\n", $out);
$after = substr_count($new, '<x-');

if (strlen($new) < 1000) {
    exit("ABORT: output too small\n");
}
if (file_put_contents($f, $new) === false) {
    exit("WRITE FAIL\n");
}

echo "orig bytes: $len0" . PHP_EOL;
echo "new  bytes: " . strlen($new) . PHP_EOL;
echo "lines changed: $changed" . PHP_EOL;
echo "before <x- : $before" . PHP_EOL;
echo "after  <x- : $after" . PHP_EOL;
echo ($after < $before ? "OK" : "NO CHANGE") . PHP_EOL;
