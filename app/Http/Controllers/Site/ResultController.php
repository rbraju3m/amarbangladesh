<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Location;
use App\Models\QuizResult;
use App\Quiz\QuizConfig;
use App\Quiz\ResultPresenter;
use App\Quiz\Scorer;
use App\Support\Bangla;
use App\Support\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ResultController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1', 'max:30'],
            'answers.*' => ['integer'],
            'visitor_id' => ['nullable', 'uuid'],
            'ref' => ['nullable', 'string', 'max:12'],
        ]);

        $config = QuizConfig::load();
        try {
            $score = (new Scorer($config))->score($data['answers']);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'উত্তরগুলো ঠিকমতো পাওয়া যায়নি। আবার চেষ্টা করো।'], 422);
        }

        $referrer = isset($data['ref']) ? QuizResult::where('code', $data['ref'])->first() : null;
        $location = Location::where('slug', $score->locationSlug)->firstOrFail();
        $token = Str::random(40);

        $result = QuizResult::create([
            'code' => QuizResult::newCode(),
            'visitor_id' => $data['visitor_id'] ?? null,
            'location_id' => $location->id,
            'match_pct' => $score->matchPct,
            'second_location_id' => $config->locations[$score->secondSlug]['id'],
            'second_match_pct' => $score->secondPct,
            'trait_vector' => $score->vector,
            'trait_scores' => $score->traitScores,
            'answer_ids' => array_map('intval', $data['answers']),
            'reason_bn' => implode(' আর ', $score->reasons).' — '.$location->reason_tail_bn,
            'reason_en' => ResultPresenter::englishReason($score->reasonsEn, $location->reason_tail_en ?: $location->reason_tail_bn),
            'owner_token_hash' => hash('sha256', $token),
            'referrer_result_id' => $referrer?->id,
            'friend_match_pct' => $referrer ? Scorer::friendMatch($referrer->trait_vector, $score->vector, $referrer->location_id === $location->id) : null,
            'scoring_version' => $config->version(),
        ]);

        AnalyticsEvent::create([
            'visitor_id' => $result->visitor_id,
            'name' => 'quiz_completed',
            'quiz_result_id' => $result->id,
            'referrer_result_id' => $referrer?->id,
            'device' => Device::fromUserAgent($request->userAgent()),
            'meta' => ['location' => $location->slug],
        ]);

        return response()->json(['result' => ResultPresenter::present($result), 'owner_token' => $token], 201);
    }

    /** Lets the owner put an optional first name on their card and share preview. */
    public function updateName(Request $request, QuizResult $result): JsonResponse
    {
        $data = $request->validate([
            'owner_token' => ['required', 'string', 'size:40'],
            'name' => ['nullable', 'string', 'max:40'],
        ]);

        abort_unless(hash_equals($result->owner_token_hash, hash('sha256', $data['owner_token'])), 403);

        $result->update(['display_name' => Bangla::cleanName($data['name'] ?? null)]);

        return response()->json(['result' => ResultPresenter::present($result)]);
    }
}
