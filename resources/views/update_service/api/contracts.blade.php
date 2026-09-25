@extends('layouts.public')

@section('title', 'العقود | آمر تم')

@section('content')
    @include('partials.public.navbar', ['active' => 'contracts'])

    <style>
        body { background-color: #F8FAF8; background-image: url('/images/bg-pattern.png'); background-size: cover; background-repeat: no-repeat; background-position: center top; background-attachment: fixed; }
        .bk { border-radius: 1.25rem; border: 1.5px solid rgba(5,150,105,.1); background: #fff; box-shadow: 0 4px 18px rgba(0,30,15,.06); }
    </style>

    <div class="min-h-screen px-4 py-8 md:px-6">
        <div class="mx-auto w-full max-w-[900px]">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-extrabold text-slate-900">العقود</h1>
                    <p class="mt-1 text-sm text-slate-500">عقود المنصة النظامية حسب النشاط</p>
                </div>
                <a href="{{ route('amrtm.create-contract') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#059669] px-4 py-2 text-sm font-bold text-white no-underline transition hover:bg-[#047857]">
                    <i class="ti ti-plus"></i> إنشاء عقد
                </a>
            </div>

            <!-- الإحصائيات -->
            @php
        $statsArr = $stats instanceof \Illuminate\Support\Collection ? $stats->all() : (array) ($stats ?? []);
        $balance = (float) ($statsArr['balance'] ?? 0);
    @endphp
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="bk p-5">
                    <div class="mb-1 text-[11px] font-bold uppercase text-slate-400">قيمة المحفظة</div>
                    <div class="text-xl font-black text-emerald-700" dir="ltr">{{ number_format($balance, 2) }} ر.س</div>
                </div>
                <div class="bk p-5">
                    <div class="mb-1 text-[11px] font-bold uppercase text-slate-400">طلباتي</div>
                    <div class="text-xl font-black text-slate-900">{{ count($requests ?? []) }}</div>
                </div>
                <div class="bk p-5">
                    <div class="mb-1 text-[11px] font-bold uppercase text-slate-400">عقودي</div>
                    <div class="text-xl font-black text-slate-900">{{ count($contracts ?? []) }}</div>
                </div>
            </div>

            <!-- قائمة العقود -->
            <div class="bk">
                <div class="flex items-center justify-between border-b border-[rgba(5,150,105,.08)] px-5 py-4">
                    <span class="text-[15px] font-black text-slate-900"><i class="ti ti-file-contract text-[#059669]"></i> عقودي</span>
                </div>
                @if (empty($contracts) || count($contracts) === 0)
                    <div class="p-12 text-center">
                        <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50">
                            <i class="ti ti-file-off text-2xl text-emerald-300"></i>
                        </div>
                        <p class="text-sm font-bold text-slate-500">لا توجد عقود بعد</p>
                        <p class="mt-1 text-[12.5px] text-slate-400">أنشئ عقدك الأول من زر «إنشاء عقد»</p>
                        <a href="{{ route('amrtm.create-contract') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-900 px-5 py-2.5 text-[13px] font-bold text-white no-underline transition hover:bg-emerald-950">
                            <i class="ti ti-file-plus"></i> إنشاء عقد
                        </a>
                    </div>
                @else
                    <div>
                        @foreach ($contracts as $c)
                            @php $c = (array) $c; @endphp
                            <div class="flex items-center gap-4 border-b border-[rgba(5,150,105,.06)] px-5 py-4 last:border-b-0">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800">
                                    <i class="ti ti-file-contract"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[13.5px] font-bold text-slate-900">{{ $c['type_name'] ?? 'عقد' }}</div>
                                    <div class="text-[11px] text-slate-400" dir="ltr">{{ $c['number'] ?? '' }}</div>
                                </div>
                                <span class="text-[12.5px] font-extrabold text-emerald-700" dir="ltr">{{ number_format((float) ($c['price'] ?? 0), 2) }} ر.س</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection