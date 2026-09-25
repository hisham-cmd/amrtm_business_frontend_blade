@extends('layouts.dashboard')

@section('title', 'لوحة المستخدم | آمر تم')
@section('page-title', $pageTitle ?? 'لوحة تحكم المستخدم')
@section('page-sub', 'نظرة عامة على طلباتك وملفك')

@section('dashboard-content')
    @php
        $requestsArr = collect($requestsList ?? $requests ?? []);
        $total = $requestsArr->count();
        $done  = $requestsArr->filter(fn($r) => ($r['status'] ?? '') === 'done')->count();
        $pend  = $requestsArr->filter(fn($r) => in_array($r['status'] ?? '', ['pending', 'processing'], true))->count();
        $balance = (float) (is_object($stats) ? ($stats->get('balance') ?? 0) : ($stats['balance'] ?? 0));
        $uname = $user->name ?? 'مستخدم';
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">مرحباً، {{ mb_substr($uname, 0, 1) }} 👋</h1>
            <p class="mt-1 text-sm text-slate-500">إليك ملخص حسابك</p>
        </div>
        <a href="{{ route('amrtm.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#059669] px-4 py-2 text-sm font-bold text-white no-underline transition hover:bg-[#047857]">
            <i class="ti ti-plus"></i> طلب جديد
        </a>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xl" style="background:rgba(2,119,189,.1)"><i class="ti ti-file-text" style="color:#0277BD"></i></div>
                <div><div class="text-2xl font-black text-slate-900">{{ $total }}</div><div class="text-[13px] text-slate-500">إجمالي الطلبات</div></div>
            </div>
        </div>
        <div class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xl" style="background:rgba(230,81,0,.1)"><i class="ti ti-loader" style="color:#E65100"></i></div>
                <div><div class="text-2xl font-black text-slate-900">{{ $pend }}</div><div class="text-[13px] text-slate-500">قيد الانتظار</div></div>
            </div>
        </div>
        <div class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xl" style="background:rgba(4,120,87,.1)"><i class="ti ti-circle-check" style="color:#047857"></i></div>
                <div><div class="text-2xl font-black text-slate-900">{{ $done }}</div><div class="text-[13px] text-slate-500">مكتملة</div></div>
            </div>
        </div>
        <div class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xl" style="background:linear-gradient(135deg,#059669,#16a34a)"><i class="ti ti-wallet" style="color:#fff"></i></div>
                <div><div class="text-2xl font-black text-slate-900" dir="ltr">{{ number_format($balance, 2) }}</div><div class="text-[13px] text-slate-500">رصيدي (ر.س)</div></div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-[rgba(5,150,105,.08)] px-5 py-4">
            <span class="text-[15px] font-black text-slate-900"><i class="ti ti-files text-[#059669]"></i> آخر الطلبات</span>
            <span class="text-[12px] font-bold text-[#059669]">الكل ←</span>
        </div>
        @if ($requestsArr->isEmpty())
            <div class="p-10 text-center text-sm text-slate-400">
                <i class="ti ti-inbox mb-2 block text-3xl opacity-40"></i>
                لا توجد طلبات بعد
            </div>
        @else
            @foreach ($requestsArr->take(6) as $req)
                @php
                    $st = $req['status'] ?? 'pending';
                    $map = [
                        'pending' => ['قيد الانتظار', '#E65100', 'rgba(230,81,0,.1)'],
                        'processing' => ['جاري المعالجة', '#0277BD', 'rgba(2,119,189,.1)'],
                        'in_progress' => ['قيد التنفيذ', '#F9A825', 'rgba(249,168,37,.1)'],
                        'done' => ['تمت العملية', '#047857', 'rgba(4,120,87,.1)'],
                        'rejected' => ['مرفوض', '#dc2626', 'rgba(220,38,38,.1)'],
                    ];
                    [$ar, $c, $bg] = $map[$st] ?? [$st, '#999', 'rgba(0,0,0,.05)'];
                @endphp
                <div class="flex items-center gap-4 border-b border-[rgba(5,150,105,.06)] px-5 py-4 last:border-b-0">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-[17px]" style="background:{{ $bg }}">
                        <i class="ti ti-file-text" style="color:{{ $c }}"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[13.5px] font-bold text-slate-900">{{ $req['service_name'] ?? 'خدمة' }}</div>
                        <div class="text-[11px] text-slate-400" dir="ltr">{{ $req['ref_number'] ?? ('#' . ($req['id'] ?? '')) }}</div>
                    </div>
                    <span class="rounded-full px-3 py-1 text-[11.5px] font-bold" style="color:{{ $c }};background:{{ $bg }}">{{ $ar }}</span>
                </div>
            @endforeach
        @endif
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('amrtm.payment.checkout') }}" class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-5 shadow-sm no-underline transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800"><i class="ti ti-wallet"></i></div>
            <div class="mt-3 text-[13.5px] font-extrabold text-slate-900">شحن رصيدي</div>
            <div class="text-[11.5px] text-slate-400">دفع الخدمات بسهولة</div>
        </a>
        <a href="{{ route('amrtm.contracts.my') }}" class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-5 shadow-sm no-underline transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800"><i class="ti ti-file-contract"></i></div>
            <div class="mt-3 text-[13.5px] font-extrabold text-slate-900">عقودي</div>
            <div class="text-[11.5px] text-slate-400">إدارة العقود والاتفاقيات</div>
        </a>
        <a href="{{ route('amrtm.index') }}" class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-5 shadow-sm no-underline transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800"><i class="ti ti-building"></i></div>
            <div class="mt-3 text-[13.5px] font-extrabold text-slate-900">مكاتب ومستشارون</div>
            <div class="text-[11.5px] text-slate-400">استعرض الجهات والخدمات</div>
        </a>
    </div>
@endsection