<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Accounts;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveMember;
use App\Models\Member;
use App\Support\Bangla;
use App\Support\Lang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Phone, email and session endpoints. Every sign-in answers with the member and a new browser token. */
class AuthController extends Controller
{
    public function emailRegister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], self::messages());

        $email = Str::lower($data['email']);
        if (Accounts::find('email', $email)) {
            throw ValidationException::withMessages(['email' => __('এই ইমেইলে আগেই অ্যাকাউন্ট আছে। লগইন করুন।')]);
        }
        $name = Bangla::cleanName($data['name']) ?? throw ValidationException::withMessages(['name' => __('নামটা ঠিকমতো লিখুন।')]);

        return $this->signedIn(Accounts::signIn('email', $email, $name, ['password' => $data['password']]), 201);
    }

    public function emailLogin(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc'], 'password' => ['required', 'string']], self::messages());
        $member = Accounts::find('email', Str::lower($data['email']));

        if (! $member || ! $member->password || ! Hash::check($data['password'], $member->password)) {
            throw ValidationException::withMessages(['email' => __('ইমেইল বা পাসওয়ার্ড মেলেনি।')]);
        }

        return $this->signedIn($member);
    }

    /** Always 204, so the form can't be used to find out which emails have accounts. */
    public function emailForgot(Request $request): Response
    {
        $data = $request->validate(['email' => ['required', 'email:rfc']], self::messages());
        $member = Accounts::find('email', $email = Str::lower($data['email']));

        if ($member) {
            $token = Str::random(48);
            Cache::put('member-reset:'.hash('sha256', $token), $member->id, 3600);
            $link = url(Lang::path('/reset-password')).'#t='.$token;
            $body = __('পাসওয়ার্ড বদলাতে এই লিংকে যান (১ ঘণ্টা কাজ করবে):')."\n\n".$link."\n\n".__('আপনি না চাইলে এই ইমেইলটা উপেক্ষা করুন।');
            Mail::raw($body, fn ($m) => $m->to($email)->subject(__('আমার বাংলাদেশ: পাসওয়ার্ড বদলান')));
        }

        return response()->noContent();
    }

    public function emailReset(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'max:100']], self::messages());
        $member = Member::find(Cache::pull('member-reset:'.hash('sha256', $data['token'])));

        if (! $member) {
            throw ValidationException::withMessages(['token' => __('লিংকটার মেয়াদ শেষ। আবার “পাসওয়ার্ড ভুলে গেছি” চাপুন।')]);
        }
        $member->update(['password' => $data['password']]);
        $member->tokens()->delete(); // sign out every other device

        return $this->signedIn($member);
    }

    public function phoneSend(Request $request): Response
    {
        abort_unless(in_array('phone', Accounts::providers(), true), 404);
        $phone = Accounts::normalizePhone($request->input('phone'));
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => __('বাংলাদেশি মোবাইল নম্বর লিখুন, যেমন ০১৭১২৩৪৫৬৭৮।')]);
        }
        if (! Accounts::sendPhoneCode($phone, __('আমার বাংলাদেশ কোড: :code (১০ মিনিট কাজ করবে)'))) {
            abort(429, __('কোড পাঠানো হয়েছে। এক মিনিট পরে আবার চাইতে পারবেন।'));
        }

        return response()->noContent();
    }

    public function phoneVerify(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string'], 'code' => ['required', 'string', 'max:12'], 'name' => ['nullable', 'string', 'max:40']]);
        $phone = Accounts::normalizePhone($data['phone']);

        if (! $phone || ! Accounts::checkPhoneCode($phone, $data['code'])) {
            throw ValidationException::withMessages(['code' => __('কোডটা মেলেনি বা মেয়াদ শেষ।')]);
        }

        return $this->signedIn(Accounts::signIn('phone', $phone, Bangla::cleanName($data['name'] ?? null)));
    }

    /** After signing in, a browser that still holds a device-only member brings its posts along. */
    public function link(Request $request): JsonResponse
    {
        $request->validate(['legacy_token' => ['required', 'string']]);
        $account = $request->attributes->get('member');
        $legacy = Member::fromToken($request->input('legacy_token'));

        if ($legacy && $account->hasAccount()) {
            Accounts::absorb($account, $legacy);
            Member::revokeToken($request->input('legacy_token'));
        }

        return response()->json(['member' => MemberController::present($account->fresh())]);
    }

    public function logout(Request $request): Response
    {
        Member::revokeToken($request->header(ResolveMember::HEADER));

        return response()->noContent();
    }

    private function signedIn(Member $member, int $status = 200): JsonResponse
    {
        abort_if($member->isBlocked(), 403, __('এই অ্যাকাউন্ট থেকে লেখা বন্ধ করা হয়েছে।'));

        return response()->json(['member' => MemberController::present($member), 'token' => $member->issueToken()], $status);
    }

    private static function messages(): array
    {
        return [
            'name.required' => __('আপনার নাম লিখুন।'),
            'email.required' => __('ইমেইল লিখুন।'),
            'email.email' => __('ইমেইলটা ঠিক নেই।'),
            'password.required' => __('পাসওয়ার্ড লিখুন।'),
            'password.min' => __('পাসওয়ার্ড অন্তত ৮ অক্ষরের হতে হবে।'),
        ];
    }
}
