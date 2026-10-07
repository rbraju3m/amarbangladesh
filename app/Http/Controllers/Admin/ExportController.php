<?php

namespace App\Http\Controllers\Admin;

use App\Analytics\Funnel;
use App\Http\Controllers\Controller;
use App\Models\QuizResult;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV downloads for the dashboard's date range: every play, or the daily funnel. */
class ExportController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        $days = array_key_exists($request->query('days'), DashboardController::RANGES) ? $request->query('days') : '7';
        $since = $days === '1' ? now()->startOfDay() : now()->subDays((int) $days);
        $file = "amarbangladesh-{$type}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($type, $since) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM, so Excel opens the Bangla names as UTF-8

            if ($type === 'daily') {
                fputcsv($out, ['date', 'visitors', 'completed', 'shared']);
                foreach ((new Funnel($since))->trend() as $row) {
                    fputcsv($out, array_values($row));
                }
            } else {
                fputcsv($out, ['time', 'code', 'url', 'place', 'match_pct', 'second_place', 'second_pct', 'name', 'via_friend', 'friend_match_pct']);
                QuizResult::with(['location', 'secondLocation'])->where('created_at', '>=', $since)->orderBy('id')
                    ->lazy()->each(fn (QuizResult $r) => fputcsv($out, [
                        $r->created_at->toDateTimeString(), $r->code, route('results.show', $r), $r->location?->name_en, $r->match_pct,
                        $r->secondLocation?->name_en, $r->second_match_pct, $r->display_name, $r->referrer_result_id ? 'yes' : 'no', $r->friend_match_pct,
                    ]));
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
