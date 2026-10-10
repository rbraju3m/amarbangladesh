{{-- Sign-in sheet. Opens whenever a signed-out reader tries to write; the action continues after sign-in. --}}
@php($providers = \App\Community\Accounts::providers())
<div x-show="loginOpen" x-cloak x-modal="loginOpen" class="fixed inset-0 z-50 flex items-end justify-center md:items-center md:p-6" role="dialog" aria-modal="true" aria-labelledby="login-title" @keydown.escape.window="loginOpen && closeLogin()">
    <div class="absolute inset-0 bg-black/50" x-show="loginOpen" x-transition.opacity @click="closeLogin()"></div>
    <div x-show="loginOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full md:translate-y-8 md:opacity-0"
        tabindex="-1" class="relative max-h-[92dvh] w-full max-w-md overflow-y-auto overscroll-contain rounded-t-[2rem] bg-paper px-5 outline-none pt-3 pb-[max(1.5rem,env(safe-area-inset-bottom))] md:rounded-[2rem] md:pt-6 md:shadow-2xl">
        <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-line md:hidden"></div>
        <button type="button" class="absolute top-4 right-4 flex size-9 items-center justify-center rounded-full border border-line text-ink-2" @click="closeLogin()" aria-label="{{ __('বন্ধ করুন') }}">✕</button>

        <div class="pr-10">
            <button type="button" x-show="loginStep !== 'choose'" @click="loginStep = 'choose'; formError = ''" class="mb-2 text-sm font-semibold text-ink-2">‹ {{ __('অন্যভাবে লগইন') }}</button>
            <h2 id="login-title" class="text-xl font-bold">{{ __('লগইন করুন') }}</h2>
            <p class="mt-1 text-sm text-ink-2">{{ __('প্রশ্ন, উত্তর আর মতামত দিতে একটা অ্যাকাউন্ট লাগে। পড়তে লাগে না।') }}</p>
        </div>

        {{-- Step: choose a method --}}
        <div x-show="loginStep === 'choose'" class="mt-5 grid gap-2.5">
            @if (in_array('google', $providers, true))
                <a href="{{ route('auth.social', ['google'] + (\App\Support\Lang::isEnglish() ? ['lang' => 'en'] : []), false) }}" @click="rememberReturn($el)" class="login-btn">
                    <svg class="size-5" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                    {{ __('Google দিয়ে লগইন') }}
                </a>
            @endif
            @if (in_array('facebook', $providers, true))
                <a href="{{ route('auth.social', ['facebook'] + (\App\Support\Lang::isEnglish() ? ['lang' => 'en'] : []), false) }}" @click="rememberReturn($el)" class="login-btn !border-[#1877F2] !bg-[#1877F2] !text-white">
                    <span class="size-5">@include('partials.icon', ['name' => 'facebook'])</span>
                    {{ __('Facebook দিয়ে লগইন') }}
                </a>
            @endif
            @if (in_array('phone', $providers, true))
                <button type="button" class="login-btn" @click="loginStep = 'phone'; $nextTick(() => document.getElementById('login-phone')?.focus())">📱 {{ __('মোবাইল নম্বর দিয়ে') }}</button>
            @endif
            <button type="button" class="login-btn" @click="loginStep = 'email-login'; $nextTick(() => document.getElementById('login-email')?.focus())">✉️ {{ __('ইমেইল দিয়ে') }}</button>
            <p class="mt-2 text-center text-xs leading-relaxed text-ink-2">{{ __('চাইলে পরে পোস্ট “বেনামী” হিসেবেও দিতে পারবেন।') }}
                <a href="{{ lroute('privacy', [], false) }}" class="font-semibold underline hover:text-ink">{{ __('আমরা কী রাখি →') }}</a></p>
        </div>

        {{-- Step: phone number --}}
        <form x-show="loginStep === 'phone'" x-cloak class="mt-5" @submit.prevent="sendCode($el)">
            <label for="login-phone" class="text-sm font-semibold">{{ __('মোবাইল নম্বর') }}</label>
            <input id="login-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required class="field mt-1 !text-lg tracking-wide" placeholder="01712345678" x-model="loginPhone">
            <p class="mt-1 text-xs text-ink-2">{{ __('এই নম্বরে ৬ সংখ্যার একটা কোড যাবে।') }}</p>
            <p role="alert" x-show="formError" class="mt-2 text-sm font-semibold text-danger" x-text="formError"></p>
            <button class="btn-primary mt-4" :disabled="authBusy" x-text="authBusy ? '…' : @js(__('কোড পাঠান'))"></button>
        </form>

        {{-- Step: code (and a name for first-timers; optional, asked again when posting) --}}
        <form x-show="loginStep === 'code'" x-cloak class="mt-5" @submit.prevent="verifyCode($el)">
            <p class="text-sm">{{ __('কোড পাঠানো হয়েছে এই নম্বরে:') }} <b x-text="loginPhone"></b></p>
            <label for="login-code" class="sr-only">{{ __('কোড') }}</label>
            <input id="login-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required class="field mt-2 text-center !text-2xl font-bold tracking-[0.5em]" placeholder="••••••">
            <label for="login-phone-name" class="mt-3 block text-sm font-semibold">{{ __('আপনার নাম') }} <span class="font-medium text-ink-2">({{ __('নতুন হলে') }})</span></label>
            <input id="login-phone-name" name="name" maxlength="20" autocomplete="name" class="field mt-1" placeholder="{{ __('যেমন: রাশেদ') }}">
            <p role="alert" x-show="formError" class="mt-2 text-sm font-semibold text-danger" x-text="formError"></p>
            <button class="btn-primary mt-4" :disabled="authBusy" x-text="authBusy ? '…' : @js(__('লগইন করুন'))"></button>
            <button type="button" class="mt-3 w-full text-sm font-semibold text-ink-2" @click="loginStep = 'phone'">{{ __('কোড আসেনি? আবার পাঠান') }}</button>
        </form>

        {{-- Step: email sign-in / sign-up --}}
        <form x-show="loginStep === 'email-login' || loginStep === 'email-register'" x-cloak class="mt-5 grid gap-3" @submit.prevent="emailAuth($el)">
            <div class="flex rounded-full bg-paper-2 p-1 text-sm font-semibold" role="tablist">
                <button type="button" role="tab" class="flex-1 rounded-full py-2" :class="loginStep === 'email-login' ? 'bg-card shadow-sm' : 'text-ink-2'" :aria-selected="loginStep === 'email-login'" @click="loginStep = 'email-login'; formError = ''">{{ __('আগে থেকে আছে') }}</button>
                <button type="button" role="tab" class="flex-1 rounded-full py-2" :class="loginStep === 'email-register' ? 'bg-card shadow-sm' : 'text-ink-2'" :aria-selected="loginStep === 'email-register'" @click="loginStep = 'email-register'; formError = ''">{{ __('নতুন অ্যাকাউন্ট') }}</button>
            </div>
            <template x-if="loginStep === 'email-register'">
                <div>
                    <label for="login-name" class="text-sm font-semibold">{{ __('আপনার নাম') }} <span class="font-medium text-ink-2">({{ __('সবাই দেখবে') }})</span></label>
                    <input id="login-name" name="name" maxlength="20" autocomplete="name" required class="field mt-1" placeholder="{{ __('যেমন: রাশেদ') }}">
                </div>
            </template>
            <div>
                <label for="login-email" class="text-sm font-semibold">{{ __('ইমেইল') }}</label>
                <input id="login-email" name="email" type="email" autocomplete="email" required class="field mt-1" x-model="loginEmail">
            </div>
            <div>
                <label for="login-password" class="text-sm font-semibold">{{ __('পাসওয়ার্ড') }} <span x-show="loginStep === 'email-register'" class="font-medium text-ink-2">({{ __('অন্তত ৮ অক্ষর') }})</span></label>
                <input id="login-password" name="password" type="password" minlength="8" required class="field mt-1" :autocomplete="loginStep === 'email-register' ? 'new-password' : 'current-password'">
            </div>
            <p role="alert" x-show="formError" class="text-sm font-semibold text-danger" x-text="formError"></p>
            <button class="btn-primary" :disabled="authBusy" x-text="authBusy ? '…' : (loginStep === 'email-register' ? @js(__('অ্যাকাউন্ট খুলুন')) : @js(__('লগইন করুন')))"></button>
            <button type="button" x-show="loginStep === 'email-login'" class="text-sm font-semibold text-ink-2" @click="loginStep = 'email-forgot'; formError = ''">{{ __('পাসওয়ার্ড ভুলে গেছেন?') }}</button>
        </form>

        {{-- Step: forgot password --}}
        <form x-show="loginStep === 'email-forgot'" x-cloak class="mt-5" @submit.prevent="forgotPassword($el)">
            <label for="login-forgot" class="text-sm font-semibold">{{ __('অ্যাকাউন্টের ইমেইল') }}</label>
            <input id="login-forgot" name="email" type="email" autocomplete="email" required class="field mt-1" x-model="loginEmail">
            <p role="alert" x-show="formError" class="mt-2 text-sm font-semibold text-danger" x-text="formError"></p>
            <button class="btn-primary mt-4" :disabled="authBusy">{{ __('পাসওয়ার্ড বদলের লিংক পাঠান') }}</button>
        </form>
        <div x-show="loginStep === 'forgot-sent'" x-cloak class="mt-6 text-center">
            <p class="text-4xl" aria-hidden="true">📬</p>
            <p class="mt-2 font-bold">{{ __('ইমেইল দেখুন') }}</p>
            <p class="mt-1 text-sm text-ink-2">{{ __('এই ইমেইলে অ্যাকাউন্ট থাকলে একটা লিংক গেছে। ১ ঘণ্টা কাজ করবে।') }}</p>
        </div>
    </div>
</div>
