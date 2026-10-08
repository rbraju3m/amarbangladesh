{{-- "Post anonymously": a switch bound to the form's `anon` state; sent as anonymous=1. --}}
<label class="flex cursor-pointer items-start gap-3 {{ $class ?? '' }}">
    <input type="checkbox" name="anonymous" value="1" x-model="anon" class="peer sr-only">
    <span class="switch mt-0.5" aria-hidden="true"></span>
    <span class="text-sm">
        <span class="font-semibold">🕶️ {{ $label }}</span>
        <span class="block text-xs text-ink-2">{{ __('সবাই দেখবে “বেনামী”। আপনার নাম শুধু মডারেটররা দেখতে পারবেন।') }}</span>
    </span>
</label>
