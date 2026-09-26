@php
    $bodyClass = $bodyClass ?? 'ar font-sans bg-surface text-gray-900 min-h-screen';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'منصة آمر تم')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/new-logo1.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="{{ $bodyClass }}">
    @php
        /*
         | حقن المستخدم الحالي (من الجلسة عبر الـ API) — تعتمد عليه
         | Auth.isLoggedIn() في السكربتات و updateNavAuth() في الناف بار.
         | محتوى @section يُصيَّر بعد هذا السكربت، فأي قالب فرعي كان يعيد
         | كتابة AMRTM_USER = null يمحو حالة الدخول — لذلك نثبّت
         | __amrtmUserPinned هنا ونمنع التصفير في الصفحة الرئيسية.
         */
        $__u = $currentAuthUser ?? $frontUser ?? null;
        $__uArray = $__u === null
            ? null
            : (method_exists($__u, 'toArray') ? $__u->toArray() : (array) $__u);
        $__authed = (bool) ($frontAuthed ?? false) || $__u !== null;
    @endphp
    <script>
        window.__amrtmUserPinned = @json($__authed);
        window.AMRTM_USER = @json($__uArray);
        window.AMRTM_NAV_AUTHED = @json($__authed);
    </script>
    @yield('content')

    <x-ui.notifications />

    @stack('scripts')
</body>
</html>