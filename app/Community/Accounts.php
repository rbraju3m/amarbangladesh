<?php

namespace App\Community;

use App\Models\Answer;
use App\Models\HelpfulMark;
use App\Models\Member;
use App\Models\MemberIdentity;
use App\Models\MemberNotification;
use App\Models\Post;
use App\Models\Report;
use App\Support\Sms\SmsSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signing community members in. Every method ends the same way: find or create the member behind
 * a (provider, identifier) pair and give this browser a token.
 */
final class Accounts
{
    public const CODE_TTL = 600;    // seconds a phone code stays valid

    public const CODE_TRIES = 5;    // wrong guesses before the code is thrown away

    public const CODE_RESEND = 60;  // seconds before another code can be sent to the same phone

    /** Sign-in methods that are configured, in the order the sheet shows them. */
    public static function providers(): array
    {
        return array_values(array_filter([
            config('services.google.client_id') ? 'google' : null,
            config('services.facebook.client_id') ? 'facebook' : null,
            config('services.sms.driver') ? 'phone' : null,
            'email',
        ]));
    }

    public static function find(string $provider, string $identifier): ?Member
    {
        return MemberIdentity::where(['provider' => $provider, 'identifier' => $identifier])->first()?->member;
    }

    /**
     * The member for this identity, created (with this name) the first time. `$email` is a verified
     * address for notifications (the identity itself for email sign-in); it fills an empty one.
     */
    public static function signIn(string $provider, string $identifier, ?string $name, array $attributes = [], ?string $email = null): Member
    {
        $email ??= $provider === 'email' ? $identifier : null;
        $member = self::find($provider, $identifier);
        if ($member) {
            if ($email && ! $member->email) {
                $member->update(['email' => $email]);
            }

            return $member;
        }

        return DB::transaction(function () use ($provider, $identifier, $name, $attributes, $email) {
            $member = Member::create(['code' => self::newCode(), 'name' => $name, 'email' => $email] + $attributes);
            $member->identities()->create(['provider' => $provider, 'identifier' => $identifier, 'verified_at' => now()]);

            return $member;
        });
    }

    /**
     * Moves what a device-only member (from before sign-in existed) wrote into the signed-in account,
     * then deletes the device-only member. Members that already have an account are never merged.
     */
    public static function absorb(Member $account, Member $legacy): void
    {
        if ($legacy->id === $account->id || $legacy->hasAccount()) {
            return;
        }

        DB::transaction(function () use ($account, $legacy) {
            Post::where('member_id', $legacy->id)->update(['member_id' => $account->id]);
            Answer::where('member_id', $legacy->id)->update(['member_id' => $account->id]);
            MemberNotification::where('member_id', $legacy->id)
                ->whereIn('answer_id', MemberNotification::where('member_id', $account->id)->pluck('answer_id'))->delete();
            MemberNotification::where('member_id', $legacy->id)->update(['member_id' => $account->id]);
            // Marks and reports are one per member per item: where both gave one, drop the duplicate
            // (and its count on the item); move the rest.
            foreach ([HelpfulMark::class => ['markable', 'helpful_count'], Report::class => ['reportable', 'reports_count']] as $model => [$prefix, $counter]) {
                $mine = $model::where('member_id', $account->id)->get()->map(fn ($r) => $r->{"{$prefix}_type"}.':'.$r->{"{$prefix}_id"})->flip();
                foreach ($model::where('member_id', $legacy->id)->get() as $row) {
                    if (isset($mine[$row->{"{$prefix}_type"}.':'.$row->{"{$prefix}_id"}])) {
                        Moderation::find($row->{"{$prefix}_type"}, $row->{"{$prefix}_id"})?->newQuery()
                            ->whereKey($row->{"{$prefix}_id"})->where($counter, '>', 0)->decrement($counter);
                        $row->delete();
                    } else {
                        $row->update(['member_id' => $account->id]);
                    }
                }
            }
            if (! $account->name && $legacy->name) {
                $account->update(['name' => $legacy->name]);
            }
            $legacy->delete();
        });
    }

    /**
     * Deletes an account: every way to sign in, the email and password, all devices, notifications,
     * helpful marks and reports (with the counts they added). Posts and answers are deleted too
     * when asked; otherwise they stay with the author shown as "মুছে ফেলা অ্যাকাউন্ট" and no profile.
     */
    public static function delete(Member $member, bool $withContent = false): void
    {
        DB::transaction(function () use ($member, $withContent) {
            if ($withContent) {
                foreach ([...$member->answers()->where('status', '!=', Post::DELETED)->get(), ...$member->posts()->where('status', '!=', Post::DELETED)->get()] as $item) {
                    Moderation::setStatus($item, Post::DELETED);
                }
            }
            foreach ([HelpfulMark::class => ['markable', 'helpful_count'], Report::class => ['reportable', 'reports_count']] as $model => [$prefix, $counter]) {
                foreach ($model::where('member_id', $member->id)->get() as $row) {
                    Moderation::find($row->{"{$prefix}_type"}, $row->{"{$prefix}_id"})?->newQuery()
                        ->whereKey($row->{"{$prefix}_id"})->where($counter, '>', 0)->decrement($counter);
                    $row->delete();
                }
            }
            $member->identities()->delete();
            $member->tokens()->delete();
            $member->notifications()->delete();
            $member->update([
                'name' => null, 'password' => null, 'email' => null, 'email_notifications' => false,
                'code' => 'x'.Str::lower(Str::random(11)), // the old profile URL stops working
                'deleted_at' => now(),
            ]);
        });
    }

    /** "01712345678", "+8801712345678", "8801712345678" → "+8801712345678"; null if not a Bangladeshi mobile. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', strtr((string) $phone, array_combine(['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'], range(0, 9))));
        $digits = preg_replace('/^(?:00)?880/', '0', $digits);

        return preg_match('/^01[3-9]\d{8}$/', $digits) ? '+88'.$digits : null;
    }

    /** Sends a 6-digit code. Returns false while the resend wait is still running. */
    public static function sendPhoneCode(string $phone, string $message): bool
    {
        if (! Cache::add("phone-code-wait:{$phone}", true, self::CODE_RESEND)) {
            return false;
        }
        $code = (string) random_int(100000, 999999);
        Cache::put("phone-code:{$phone}", ['hash' => hash('sha256', $code), 'tries' => 0], self::CODE_TTL);
        app(SmsSender::class)->send($phone, str_replace(':code', $code, $message));

        return true;
    }

    public static function checkPhoneCode(string $phone, string $code): bool
    {
        $key = "phone-code:{$phone}";
        $entry = Cache::get($key);
        if (! $entry) {
            return false;
        }
        if (hash_equals($entry['hash'], hash('sha256', preg_replace('/\D/', '', $code)))) {
            Cache::forget($key);

            return true;
        }
        ++$entry['tries'] >= self::CODE_TRIES ? Cache::forget($key) : Cache::put($key, $entry, self::CODE_TTL);

        return false;
    }

    private static function newCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (Member::where('code', $code)->exists());

        return $code;
    }
}
