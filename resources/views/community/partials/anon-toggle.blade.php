{{-- "Post anonymously": a switch bound to the form's `anon` state; sent as anonymous=1. --}}
<label class="flex cursor-pointer items-start gap-3 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60 {{ $class ?? '' }}">
    <input type="checkbox" name="anonymous" value="1" x-model="anon" class="peer sr-only" :disabled="($data.photoCount ?? 0) > 0">
    <span class="switch mt-0.5" aria-hidden="true"></span>
    <span class="text-sm">
        <span class="font-semibold">🕶️ {{ $label }}</span>
        <span class="block text-xs text-ink-2">{{ __('সবাই দেখবে “বেনামী”। আপনার নাম শুধু মডারেটররা দেখতে পারবেন।') }}</span>
        <span x-show="($data.photoCount ?? 0) > 0" x-cloak class="block text-xs font-semibold text-ink-2">{{ __('ছবি থাকলে বেনামী করা যায় না।') }}</span>
    </span>
</label>
