@extends('layouts.public')

@section('title', 'إنشاء حساب جديد | آمر تم')

@section('content')
    @include('partials.public.navbar', ['active' => ''])

    <div class="mx-auto flex w-full max-w-[560px] flex-col px-4 py-12">
        <div class="rounded-2xl border border-[rgba(0,108,53,.1)] bg-white p-8 shadow-[0_20px_50px_-20px_rgba(0,50,25,.18)]">
            <!-- Header -->
            <div class="mb-7 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#006C35] to-[#00843D] text-white shadow-lg">
                    <i class="ti ti-user-plus text-2xl"></i>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-900">إنشاء حساب جديد</h1>
                <p class="mt-2 text-sm text-slate-500">انضم إلى منصة آمر تم لقطاع الأعمال</p>
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <i class="ti ti-alert-circle mr-1"></i> {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('amrtm.register.submit') }}" autocomplete="on">
                @csrf

                <div class="mb-4">
                    <label for="reg-name" class="mb-1.5 block text-[13px] font-bold text-slate-700">الاسم الكامل <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i class="ti ti-user pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="reg-name" name="name" value="{{ old('name') }}" required placeholder="الاسم كما في الهوية"
                            class="w-full rounded-xl border-[1.5px] border-slate-200 bg-white py-3 pl-4 pr-11 text-sm outline-none transition focus:border-[#006C35]/50 focus:ring-2 focus:ring-[#006C35]/15" />
                    </div>
                </div>

                <div class="mb-4">
                    <label for="reg-email" class="mb-1.5 block text-[13px] font-bold text-slate-700">البريد الإلكتروني <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i class="ti ti-mail pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="email" id="reg-email" name="email" value="{{ old('email') }}" required dir="ltr" placeholder="you@example.com"
                            class="w-full rounded-xl border-[1.5px] border-slate-200 bg-white py-3 pl-4 pr-11 text-sm outline-none transition focus:border-[#006C35]/50 focus:ring-2 focus:ring-[#006C35]/15" />
                    </div>
                </div>

                <div class="mb-4">
                    <label for="reg-phone" class="mb-1.5 block text-[13px] font-bold text-slate-700">رقم الجوال <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i class="ti ti-phone pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="tel" id="reg-phone" name="phone" value="{{ old('phone') }}" required dir="ltr" placeholder="05xxxxxxxx"
                            class="w-full rounded-xl border-[1.5px] border-slate-200 bg-white py-3 pl-4 pr-11 text-sm outline-none transition focus:border-[#006C35]/50 focus:ring-2 focus:ring-[#006C35]/15" />
                    </div>
                </div>

                <div class="mb-4">
                    <label for="reg-pass" class="mb-1.5 block text-[13px] font-bold text-slate-700">كلمة المرور <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i class="ti ti-lock pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="password" id="reg-pass" name="password" required dir="ltr" placeholder="8 أحرف على الأقل" minlength="8"
                            class="w-full rounded-xl border-[1.5px] border-slate-200 bg-white py-3 pl-4 pr-11 text-sm outline-none transition focus:border-[#006C35]/50 focus:ring-2 focus:ring-[#006C35]/15" />
                    </div>
                    @include('partials.public.password-requirements')
                </div>

                <div class="mb-5">
                    <label for="reg-pass2" class="mb-1.5 block text-[13px] font-bold text-slate-700">تأكيد كلمة المرور <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i class="ti ti-lock pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="password" id="reg-pass2" name="password_confirmation" required dir="ltr" placeholder="••••••••" minlength="8"
                            class="w-full rounded-xl border-[1.5px] border-slate-200 bg-white py-3 pl-4 pr-11 text-sm outline-none transition focus:border-[#006C35]/50 focus:ring-2 focus:ring-[#006C35]/15" />
                    </div>
                </div>

                <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#006C35] py-3.5 text-sm font-extrabold text-white transition hover:bg-[#00843D] hover:shadow-lg disabled:opacity-60">
                    <i class="ti ti-user-plus"></i> إنشاء الحساب
                </button>
            </form>

            <p class="mt-5 text-center text-sm text-slate-500">
                لديك حساب بالفعل؟
                <a href="{{ route('amrtm.login') }}" class="font-bold text-[#006C35] no-underline hover:text-[#00843D]">تسجيل الدخول</a>
            </p>
        </div>
    </div>
@endsection