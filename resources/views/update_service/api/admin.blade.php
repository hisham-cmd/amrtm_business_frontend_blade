@extends('layouts.dashboard')

@section('title', 'لوحة الإدارة | آمر تم')
@section('page-title', $pageTitle ?? 'لوحة التحكم — الإدارة')
@section('page-sub', 'نظرة عامة على المنصة')

@section('dashboard-content')
    @php
        $req = is_object($stats) ? $stats : collect($stats ?? []);
        $requests = (int) ($req->get('requests.total') ?? $req->get('total_requests') ?? 0);
        $pending  = (int) ($req->get('requests.pending') ?? 0);
        $done     = (int) ($req->get('requests.done') ?? 0);
        $users    = (int) ($req->get('users') ?? 0);
        $offices  = (int) ($req->get('offices') ?? 0);
        $revenue  = (float) ($req->get('revenue') ?? 0);
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">نظرة عامة على المنصة</h1>
            <p class="mt-1 text-sm text-slate-500">أهلاً {{ $apiUser['name'] ?? 'المدير' }} — مؤشرات الأداء الرئيسية</p>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @php
            $cards = [
                ['إجمالي الطلبات', $requests, 'ti-file-text', 'rgba(2,119,189,.1)', '#0277BD'],
                ['قيد الانتظار', $pending, 'ti-loader', 'rgba(230,81,0,.1)', '#E65100'],
                ['مكتملة', $done, 'ti-circle-check', 'rgba(4,120,87,.1)', '#047857'],
                ['المستخدمون', $users, 'ti-users', 'rgba(106,27,154,.1)', '#6A1B9A'],
                ['المكاتب', $offices, 'ti-building', 'rgba(5,150,105,.1)', '#059669'],
            ];
        @endphp
        @foreach ($cards as [$label, $val, $icon, $bg, $color])
            <div class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xl" style="background:{{ $bg }}">
                        <i class="ti {{ $icon }}" style="color:{{ $color }}"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-black text-slate-900" dir="ltr">{{ number_format($val) }}</div>
                        <div class="text-[12.5px] text-slate-500">{{ $label }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-[rgba(5,150,105,.08)] px-5 py-4">
            <span class="text-[15px] font-black text-slate-900"><i class="ti ti-gauge text-[#059669]"></i> مؤشرات سريعة</span>
        </div>
        <div class="grid grid-cols-1 gap-5 p-6 sm:grid-cols-3">
            <div>
                <div class="mb-3 text-[12.5px] font-bold text-slate-500">الإيرادات المتوقعة</div>
                <div class="text-2xl font-black text-emerald-700" dir="ltr">{{ number_format($revenue, 2) }} ر.س</div>
            </div>
            <div>
                <div class="mb-3 text-[12.5px] font-bold text-slate-500">قيمة المحفظة</div>
                <div class="text-2xl font-black text-slate-900" dir="ltr">{{ number_format((float) ($req->get('balance') ?? 0), 2) }} ر.س</div>
            </div>
            <div>
                <div class="mb-3 text-[12.5px] font-bold text-slate-500">العقود</div>
                <div class="text-2xl font-black text-slate-900" dir="ltr">{{ (int) ($req->get('contracts') ?? 0) }}</div>
            </div>
        </div>

        <div class="border-t border-[rgba(5,150,105,.08)] px-6 py-5 text-center">
            <a href="{{ route('amrtm.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-[rgba(5,150,105,.2)] px-5 py-2.5 text-[13px] font-bold text-[#059669] no-underline transition hover:bg-[#059669]/5">
                <i class="ti ti-world"></i> زيارة الموقع العام
            </a>
            <a href="{{ route('amrtm.logout') }}" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-[13px] font-bold text-red-500 no-underline transition hover:bg-red-50" onclick="event.preventDefault();document.getElementById('dash-logout-form')?.submit();">
                <i class="ti ti-logout"></i> تسجيل الخروج
            </a>
        </div>
    </div>
@endsection