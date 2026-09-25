@extends('layouts.public')

@section('title', 'الدفع الإلكتروني الآمن | آمر تم')

@section('content')
    @include('partials.public.navbar', ['active' => 'dashboard'])

    <style>
        body { background-color: #F8FAF8; background-image: url('/images/bg-pattern.png'); background-size: cover; background-repeat: no-repeat; background-position: center top; background-attachment: fixed; }
        .pay-card { border-radius: 1.5rem; border: 1.5px solid rgba(5,150,105,.1); background: #fff; box-shadow: 0 20px 50px -20px rgba(0,50,25,.18); }
        .pay-method { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 12px 8px; font-size: 11px; font-weight: 700; color: #64748b; cursor: pointer; transition: all .25s; background: #fff; }
        .pay-method.on { border-color: #059669; background: rgba(5,150,105,.06); color: #059669; }
    </style>

    <div class="flex min-h-screen items-center justify-center bg-surface px-4 py-12">
        <div class="w-full max-w-md">
            <div class="pay-card overflow-hidden">
                <!-- رأس البوابة -->
                <div class="border-b border-slate-100 px-6 pt-6 pb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br from-[#00843D] to-[#004D28] text-lg text-white">
                                <i class="ti ti-shield-lock"></i>
                            </div>
                            <div>
                                <div class="text-base font-extrabold text-slate-900">الدفع الإلكتروني الآمن</div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-[11px] text-slate-400">
                                    <span>قم بالدفع عبر HyperPay</span>
                                    <span class="inline-block h-1 w-1 rounded-full bg-slate-300"></span>
                                    <span class="text-emerald-700">ر.س (SAR)</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-[10.5px] font-bold text-emerald-800">
                            <i class="ti ti-lock"></i> SSL آمن
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-extrabold text-emerald-800">MADA</span>
                        <span class="rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-extrabold text-slate-600">VISA</span>
                        <span class="rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-extrabold text-slate-600">Mastercard</span>
                        <span class="rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-extrabold text-slate-600">Apple Pay</span>
                    </div>
                </div>

                <!-- المبلغ -->
                <div class="px-6 py-5">
                    <div class="rounded-2xl bg-gradient-to-br from-[#00843D] via-[#005C2A] to-[#004D28] p-5 text-white">
                        <div class="text-[11.5px] font-semibold text-white/75">المبلغ المطلوب دفعه</div>
                        <div class="mt-2 text-4xl font-black tabular-nums" dir="ltr">450.00</div>
                        <div class="mt-0.5 text-[11px] text-white/60">ريال سعودي</div>
                    </div>

                    <!-- طريقة الدفع -->
                    <div class="mb-4 mt-5">
                        <div class="mb-2 text-[12.5px] font-bold text-slate-700"><i class="ti ti-wallet text-emerald-700"></i> طريقة الدفع</div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div class="pay-method on"><i class="ti ti-credit-card text-xl"></i> مدى</div>
                            <div class="pay-method"><i class="ti ti-brand-visa text-xl"></i> Visa</div>
                            <div class="pay-method"><i class="ti ti-brand-mastercard text-xl"></i> Mastercard</div>
                            <div class="pay-method"><i class="ti ti-brand-apple text-xl"></i> Apple Pay</div>
                        </div>
                    </div>

                    <p class="rounded-xl border border-emerald-100 bg-emerald-50/70 px-3.5 py-2.5 text-[11.5px] leading-6 text-emerald-900">
                        <i class="ti ti-info-circle ml-1"></i>
                        يتم إتمام عملية الدفع الفعلية على سيرفر الباك اند عبر بوابة HyperPay الآمنة.
                    </p>

                    <a href="{{ route('amrtm.index') }}"
                        class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-[#006C35] px-6 py-3 text-sm font-extrabold text-white no-underline transition hover:bg-[#00843D]">
                        <i class="ti ti-home"></i> العودة للرئيسية
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection