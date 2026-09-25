@extends('layouts.public')

@section('title', 'تتبع الطلب | آمر تم')

@section('content')
    @include('partials.public.navbar', ['active' => 'dashboard'])

    <style>
        body { background-color: #F8FAF8; background-image: url('/images/bg-pattern.png'); background-size: cover; background-repeat: no-repeat; background-position: center top; background-attachment: fixed; }
        .tl { display: flex; gap: 1rem; position: relative; }
        .tl-dot { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .tl-dot.done { background: rgba(4,120,87,.1); color: #047857; }
        .tl-dot.current { background: #047857; color: #fff; box-shadow: 0 0 0 5px rgba(4,120,87,.15); }
        .tl-dot.pending { background: #f1f5f9; color: #94a3b8; }
        .tl-line { width: 2px; flex: 1; min-height: 34px; background: #e2e8f0; margin: 6px 0; }
        .tl-line.is-done { background: #047857; }
    </style>

    @php
        $r = $serviceRequest instanceof \Illuminate\Support\Collection ? $serviceRequest : collect($serviceRequest ?? []);
        $status = $r->get('status', 'pending');
        $stageOrder = ['submitted', 'assigned', 'processing', 'done'];
        $idx = array_search($status, $stageOrder);
        $idx = $idx === false ? 0 : $idx;
        $stages = [
            'submitted' => ['تم استلام طلبك', 'ti-file-check'],
            'assigned'  => ['تم الإسناد للمكتب', 'ti-building'],
            'processing'=> ['قيد التنفيذ', 'ti-settings'],
            'done'      => ['تمت العملية', 'ti-circle-check'],
        ];
    @endphp

    <div class="min-h-screen px-4 py-8 md:px-6">
        <div class="mx-auto w-full max-w-[900px]">
            <!-- Hero -->
            <div class="mb-6 rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-[13px] font-bold text-slate-500">تتبع حالة الطلب</div>
                        <h1 class="text-xl font-extrabold text-slate-900">طلب #{{ $r->get('ref_number', $r->get('id', '—')) }}</h1>
                        <p class="mt-1 text-sm text-slate-400">{{ $r->get('service_name', $r->get('gov_service.name_ar', 'خدمة')) }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#059669]/10 px-4 py-2 text-[13px] font-bold text-[#059669]">
                        <i class="ti ti-clock-hour-4"></i> {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </span>
                </div>
            </div>

            <!-- Timeline -->
            <div class="rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-6">
                <div class="mb-4 flex items-center gap-2">
                    <i class="ti ti-route text-[#059669]"></i>
                    <span class="text-base font-extrabold text-slate-900">مسار الطلب</span>
                </div>
                <div class="flex flex-col">
                    @foreach ($stages as $key => [$label, $icon])
                        @php
                            $sIdx = array_search($key, $stageOrder);
                            $state = $sIdx < $idx ? 'done' : ($sIdx === $idx ? 'current' : 'pending');
                            $isLast = $sIdx === count($stageOrder) - 1;
                            $time = $sIdx === 0 && $r->get('created_at') ? $r->get('created_at') : null;
                        @endphp
                        <div class="tl">
                            <div>
                                <div class="tl-dot {{ $state }}"><i class="ti {{ $icon }}"></i></div>
                                @if (!$isLast)
                                    <div class="tl-line {{ $state === 'done' ? 'is-done' : '' }}"></div>
                                @endif
                            </div>
                            <div class="pb-7">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold text-slate-900">{{ $label }}</span>
                                    <span class="rounded-full px-2.5 py-0.5 text-[10.5px] font-bold {{ $state === 'done' ? 'bg-emerald-50 text-emerald-700' : ($state === 'current' ? 'bg-[#059669]/10 text-[#059669]' : 'bg-slate-100 text-slate-400') }}">
                                        {{ $state === 'done' ? 'مكتمل' : ($state === 'current' ? 'حالياً' : 'في الانتظار') }}
                                    </span>
                                </div>
                                @if ($time)
                                    <p class="mt-1 text-xs text-slate-400" dir="ltr">{{ $time }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- بيانات الطلب -->
            <div class="mt-5 rounded-2xl border border-[rgba(5,150,105,.1)] bg-white p-6">
                <div class="mb-4 text-base font-extrabold text-slate-900"><i class="ti ti-file-text text-[#059669]"></i> بيانات الطلب</div>
                <div class="space-y-3 text-[13.5px]">
                    <div class="flex justify-between border-b border-[rgba(5,150,105,.06)] pb-3">
                        <span class="text-slate-400">رقم المرجع</span>
                        <strong class="text-slate-900" dir="ltr">{{ $r->get('ref_number', '—') }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-[rgba(5,150,105,.06)] pb-3">
                        <span class="text-slate-400">التكلفة</span>
                        <strong class="text-[#059669]" dir="ltr">{{ number_format((float) ($r->get('price') ?? 0), 2) }} ر.س</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">تاريخ التقديم</span>
                        <strong class="text-slate-900" dir="ltr">{{ $r->get('created_at', '—') }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection