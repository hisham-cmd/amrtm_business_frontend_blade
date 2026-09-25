@extends('layouts.public')

@section('title', 'تفاصيل العقد | آمر تم')

@section('content')
    @include('partials.public.navbar', ['active' => 'contracts'])

    <div class="min-h-[calc(100vh-72px)] bg-emerald-50/40 py-6">
        <main class="mx-auto w-full max-w-4xl space-y-5 px-4 sm:px-6">

            <div class="rounded-2xl border border-emerald-200 bg-white p-10 text-center shadow-sm">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100">
                    <i class="ti ti-file-contract text-2xl text-emerald-800"></i>
                </div>
                <h1 class="text-xl font-extrabold text-emerald-950">تفاصيل العقد</h1>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-emerald-800">
                    يتم عرض تفاصيل العقد الكاملة عبر سيرفر الباك اند بعد تسجيل دخول المكتب.
                    هذه النسخة المسلَّمة تعرض الوثائق العامة فقط.
                </p>

                @if ($contract)
                    <div class="mt-6 grid grid-cols-1 gap-4 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-6 text-right sm:grid-cols-2">
                        <div class="rounded-xl bg-white p-4">
                            <div class="text-xs font-bold text-emerald-600">رقم العقد</div>
                            <div class="mt-1 text-sm font-extrabold text-emerald-950" dir="ltr">{{ $contract->number ?? '—' }}</div>
                        </div>
                        <div class="rounded-xl bg-white p-4">
                            <div class="text-xs font-bold text-emerald-600">النوع</div>
                            <div class="mt-1 text-sm font-extrabold text-emerald-950">{{ $contract->type_name ?? ($contract->type->name ?? '—') }}</div>
                        </div>
                        <div class="rounded-xl bg-white p-4">
                            <div class="text-xs font-bold text-emerald-600">الطرف الثاني</div>
                            <div class="mt-1 text-sm font-extrabold text-emerald-950">{{ $contract->party_name ?? '—' }}</div>
                        </div>
                        <div class="rounded-xl bg-white p-4">
                            <div class="text-xs font-bold text-emerald-600">القيمة</div>
                            <div class="mt-1 text-sm font-extrabold text-emerald-950" dir="ltr">{{ number_format((float) ($contract->price ?? 0), 2) }} ر.س</div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('amrtm.index') }}"
                        class="mt-6 inline-flex items-center gap-2 rounded-xl bg-emerald-900 px-6 py-2.5 text-sm font-extrabold text-white no-underline transition hover:bg-emerald-950">
                        <i class="ti ti-home"></i> العودة للرئيسية
                    </a>
                @endif
            </div>

        </main>
    </div>
@endsection