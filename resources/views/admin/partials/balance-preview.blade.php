{{-- Live balance preview (resources/js/admin/balance-preview.js). Hidden without JS; the Balance page is the fallback. --}}
<script type="application/json" id="balance-data">@json($balance)</script>
<section class="panel @container" data-balance-preview hidden>
    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="font-bold">Live balance</h2>
            <p class="text-xs text-ink-2">Share of all answer combinations each place wins with your unsaved changes. The tick marks the saved value; the shaded band is {{ $balance['min'] }}–{{ $balance['max'] }}%.</p>
        </div>
        <div data-balance-status></div>
    </div>
    <div class="space-y-2" data-balance-rows></div>
</section>
