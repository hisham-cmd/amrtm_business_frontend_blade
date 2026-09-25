@extends('layouts.public')

@section('title', 'لوحة المستخدم | آمر تم')

@section('content')
    @include('partials.public.navbar', ['active' => 'dashboard'])

    <style>
        body { background-color: #F8FAF8; background-image: url('/images/bg-pattern.png'); background-size: cover; background-repeat: no-repeat; background-position: center top; background-attachment: fixed; }
        .bk { border-radius: 1.25rem; border: 1.5px solid rgba(5,150,105,.1); background: #fff; box-shadow: 0 4px 18px rgba(0,30,15,.06); }
        .bk-row { display: flex; align-items: center; gap: 1rem; border-bottom: 1px solid rgba(5,150,105,.06); padding: .85rem 1rem; }
        .bk-row:last-child { border-bottom: 0; }
        .st-badge { display: inline-block; border-radius: 20px; padding: 3px 12px; font-size: 11.5px; font-weight: 700; }
    </style>

    <div class="min-h-screen px-4 py-8 md:px-6">
        <div class="mx-auto w-full max-w-[1100px]">
            <!-- الترحيب -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900">مرحباً بك 👋</h1>
                    <p class="mt-1 text-sm text-slate-500">إليك ملخص حسابك</p>
                </div>
                <a href="{{ route('amrtm.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#059669] px-4 py-2 text-sm font-bold text-white no-underline transition hover:bg-[#047857]">
                    <i class="ti ti-plus"></i> طلب جديد
                </a>
            </div>

            <!-- بطاقات الإحصاءات -->
            @php
                $requestsArr = $requests instanceof \Illuminate\Support\Collection ? $requests : collect($requests ?? []);
                $total = $requestsArr->count();
                $done  = $requestsArr->filter(fn($r) => ($r['status'] ?? '') === 'done')->count();
                $pend  = $requestsArr->filter(fn($r) => in_array($r['status'] ?? '', ['pending', 'processing'] , true))->count();
                $balance = (float) (is_object($stats) ? ($stats->get('balance') ?? 0) : ($stats['balance'] ?? 0));
            @endphp

            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="bk flex items-center gap-4 p-5">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-xl" style="background:rgba(2,119,189,.1)"><i class="ti ti-file-text" style="color:#0277BD"></i></div>
                    <div><div class="text-2xl font-black text-slate-900">{{ $total }}</div><div class="text-[13px] text-slate-500">إجمالي الطلبات</div></div>
                </div>
                <div class="bk flex items-center gap-4 p-5">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-xl" style="background:rgba(230,81,0,.1)"><i class="ti ti-loader" style="color:#E65100"></i></div>
                    <div><div class="text-2xl font-black text-slate-900">{{ $pend }}</div><div class="text-[13px] text-slate-500">قيد الانتظار</div></div>
                </div>
                <div class="bk flex items-center gap-4 p-5">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-xl" style="background:rgba(4,120,87,.1)"><i class="ti ti-circle-check" style="color:#047857"></i></div>
                    <div><div class="text-2xl font-black text-slate-900">{{ $done }}</div><div class="text-[13px] text-slate-500">مكتملة</div></div>
                </div>
                <div class="bk flex items-center gap-4 p-5">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-xl" style="background:linear-gradient(135deg,#059669,#16a34a)"><i class="ti ti-wallet" style="color:#fff"></i></div>
                    <div><div class="text-2xl font-black text-slate-900" dir="ltr">{{ number_format($balance, 2) }}</div><div class="text-[13px] text-slate-500">رصيدي (ر.س)</div></div>
                </div>
            </div>

            <!-- الطلبات -->
            <div class="bk">
                <div class="flex items-center justify-between border-b border-[rgba(5,150,105,.08)] px-5 py-4">
                    <span class="text-[15px] font-black text-slate-900"><i class="ti ti-files text-[#059669]"></i> طلباتي</span>
                    <a href="{{ route('amrtm.index') }}" class="text-[12px] font-bold text-[#059669]">عرض الكل ←</a>
                </div>
                @if ($requestsArr->isEmpty())
                    <div class="p-10 text-center text-sm text-slate-400">
                        <i class="ti ti-inbox mb-2 block text-3xl opacity-40"></i>
                        لا توجد طلبات بعد
                    </div>
                @else
                    <div>
                        @foreach ($requestsArr as $req)
                            @php
                                $st = $req['status'] ?? 'pending';
                                $stMap = [
                                    'pending' => ['قيد الانتظار', '#E65100', 'rgba(230,81,0,.1)'],
                                    'processing' => ['جاري المعالجة', '#0277BD', 'rgba(2,119,189,.1)'],
                                    'in_progress' => ['قيد التنفيذ', '#F9A825', 'rgba(249,168,37,.1)'],
                                    'done' => ['تمت العملية', '#047857', 'rgba(4,120,87,.1)'],
                                    'rejected' => ['مرفوض', '#dc2626', 'rgba(220,38,38,.1)'],
                                ];
                                [$stAr, $stColor, $stBg] = $stMap[$st] ?? [$st, '#999', 'rgba(0,0,0,.05)'];
                                $svcName = $req['service_name'] ?? ($req['gov_service']['name_ar'] ?? 'خدمة');
                            @endphp
                            <div class="bk-row">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-[17px]" style="background:{{ $stBg }}">
                                    <i class="ti ti-file-text" style="color:{{ $stColor }}"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[13.5px] font-bold text-slate-900">{{ $svcName }}</div>
                                    <div class="text-[11px] text-slate-400" dir="ltr">{{ $req['ref_number'] ?? ('#' . ($req['id'] ?? '')) }}</div>
                                </div>
                                <span class="st-badge" style="color:{{ $stColor }};background:{{ $stBg }}">{{ $stAr }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection