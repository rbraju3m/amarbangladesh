{{-- Answer notification email: plain, short, readable in any mail app (inline styles only). --}}
@php($answers = $notifications->where('type', 'answer'))
<!DOCTYPE html>
<html lang="{{ \App\Support\Lang::current() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#fbf8f1;font-family:'Hind Siliguri','Noto Sans Bengali',Arial,sans-serif;color:#14211b;">
<div style="max-width:520px;margin:0 auto;padding:24px 16px;">
    <p style="margin:0 0 16px;font-weight:bold;color:#006a4e;">{{ __('আমার বাংলাদেশ') }}</p>
    <div style="background:#ffffff;border:1px solid #e4ddcc;border-radius:16px;padding:20px;">
        <p style="margin:0 0 4px;font-size:14px;color:#4b5a52;">{{ __('আপনার পোস্ট') }}</p>
        <p style="margin:0 0 16px;font-size:18px;font-weight:bold;">“{{ $post->title }}”</p>

        @foreach ($notifications as $notice)
            <div style="border-left:3px solid {{ $notice->type === 'accepted' ? '#006a4e' : '#e4ddcc' }};padding:2px 0 2px 12px;margin:0 0 14px;">
                <p style="margin:0 0 4px;font-weight:bold;">
                    @if ($notice->type === 'accepted')
                        ✓ {{ __('আপনার উত্তরটি সমাধান হিসেবে বেছে নেওয়া হয়েছে') }}
                    @else
                        {{ $notice->answer->publicAuthor()?->displayName() ?? __('একজন (বেনামী)') }}
                    @endif
                </p>
                <p style="margin:0;color:#14211b;line-height:1.6;">{{ \Illuminate\Support\Str::limit($notice->answer->body, 200) }}</p>
            </div>
        @endforeach

        <a href="{{ $url }}" style="display:inline-block;margin-top:6px;background:#006a4e;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:12px;">
            {{ $answers->count() > 1 ? __('উত্তরগুলো দেখুন') : __('উত্তরটা দেখুন') }}
        </a>
        @if ($answers->isNotEmpty())
            <p style="margin:16px 0 0;font-size:14px;color:#4b5a52;">{{ __('কাজে লাগলে “✓ এটাই সমাধান” দিন, যিনি উত্তর দিলেন তিনি খুশি হবেন।') }}</p>
        @endif
    </div>
    <p style="margin:16px 0 0;font-size:12px;color:#4b5a52;line-height:1.6;">
        {{ __('আপনি আমার বাংলাদেশে লগইন করেছেন বলে এই ইমেইল পাচ্ছেন।') }}
        <a href="{{ $unsubscribeUrl }}" style="color:#4b5a52;">{{ __('এমন ইমেইল বন্ধ করুন') }}</a> ·
        <a href="{{ $settingsUrl }}" style="color:#4b5a52;">{{ __('নোটিফিকেশন') }}</a> ·
        <a href="{{ $privacyUrl }}" style="color:#4b5a52;">{{ __('গোপনীয়তা') }}</a>
    </p>
</div>
</body>
</html>
