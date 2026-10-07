<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\QuizResult;
use App\Support\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * First-party, cookieless analytics. Accepts small batches sent with navigator.sendBeacon.
 */
class EventController extends Controller
{
    public function store(Request $request): Response
    {
        // sendBeacon posts text/plain to avoid a CORS preflight; decode it ourselves.
        if (! $request->isJson() && $request->getContent()) {
            $request->merge((array) json_decode($request->getContent(), true));
        }

        $data = $request->validate([
            'visitor_id' => ['nullable', 'uuid'],
            'ref' => ['nullable', 'string', 'max:12'],
            'events' => ['required', 'array', 'max:20'],
            'events.*.name' => ['required', Rule::in(AnalyticsEvent::CLIENT_EVENTS)],
            'events.*.result' => ['nullable', 'string', 'max:12'],
            'events.*.meta' => ['nullable', 'array', 'max:5'],
        ]);

        $codes = collect($data['events'])->pluck('result')->push($data['ref'] ?? null)->filter()->unique();
        $ids = QuizResult::whereIn('code', $codes)->pluck('id', 'code');
        $device = Device::fromUserAgent($request->userAgent());

        foreach ($data['events'] as $event) {
            AnalyticsEvent::create([
                'visitor_id' => $data['visitor_id'] ?? null,
                'name' => $event['name'],
                'quiz_result_id' => $ids[$event['result'] ?? ''] ?? null,
                'referrer_result_id' => $ids[$data['ref'] ?? ''] ?? null,
                'device' => $device,
                'meta' => $this->cleanMeta($event['meta'] ?? null),
            ]);
        }

        return response()->noContent();
    }

    /** Keep only short scalar values under short keys. */
    private function cleanMeta(?array $meta): ?array
    {
        $clean = [];
        foreach ($meta ?? [] as $key => $value) {
            if (is_string($key) && strlen($key) <= 20 && is_scalar($value)) {
                $clean[$key] = is_string($value) ? mb_substr($value, 0, 40) : $value;
            }
        }

        return $clean ?: null;
    }
}
