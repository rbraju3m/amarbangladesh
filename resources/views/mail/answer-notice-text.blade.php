{{ __('আমার বাংলাদেশ') }}

{{ __('আপনার পোস্ট') }}: “{{ $post->title }}”

@foreach ($notifications as $notice)
@if ($notice->type === 'accepted')
✓ {{ __('আপনার উত্তরটি সমাধান হিসেবে বেছে নেওয়া হয়েছে') }}
@else
{{ $notice->answer->publicAuthor()?->displayName() ?? __('একজন (বেনামী)') }}:
@endif
{{ \Illuminate\Support\Str::limit($notice->answer->body, 200) }}

@endforeach
{{ $url }}

--
{{ __('এমন ইমেইল বন্ধ করুন') }}: {{ $unsubscribeUrl }}
