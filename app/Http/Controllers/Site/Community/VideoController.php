<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Moderation;
use App\Community\Videos;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Post;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    /** Starts an upload: the browser says how big the file is, then sends it in pieces. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['size' => ['required', 'integer', 'min:1']], ['size.*' => __('একটা ভিডিও বেছে নিন।')]);

        return response()->json(Videos::start($request->attributes->get('member'), (int) $data['size'])->present(), 201);
    }

    /**
     * One piece (raw bytes) at `?offset=`. 409 with the current state when the offset isn't where the
     * upload stands, so the browser continues from `received` (after a dropped connection, a retry…).
     */
    public function chunk(Request $request, Video $video): JsonResponse
    {
        $this->mine($request, $video);
        $offset = filter_var($request->query('offset'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($offset === false || ! Videos::append($video, $offset, $request->getContent())) {
            return response()->json($video->refresh()->present(), 409);
        }

        return response()->json($video->refresh()->present());
    }

    /** Where an upload or conversion stands (resuming an upload, waiting for a video to be ready). */
    public function show(Request $request, Video $video): JsonResponse
    {
        $this->mine($request, $video);

        return response()->json($video->present());
    }

    /**
     * The uploader drops a video before posting it (cancelled, or removed from the form), so it doesn't
     * count against their unposted uploads. A video already on a post or answer goes with an edit instead.
     */
    public function destroy(Request $request, Video $video): JsonResponse
    {
        $this->mine($request, $video);
        abort_if($video->videoable_id !== null, 409);
        Videos::delete(collect([$video]));

        return response()->json([], 204);
    }

    /**
     * The player for a video on a published post or answer, once it's converted (pages show "being made"
     * until then and ask here). Public: it only says what the page itself will show.
     */
    public function player(Video $video): JsonResponse
    {
        $item = $video->videoable_type ? Moderation::find($video->videoable_type, (int) $video->videoable_id) : null;
        abort_unless($item && $item->isPublished() && (! $item instanceof Answer || $item->post->isPublished()), 404);

        return response()->json([
            'status' => $video->status,
            // What an edit form starts from (the page's <template data-raw-video> was written before it was ready).
            'video' => $video->isReady() ? ['id' => $video->id, 'status' => $video->status, 'poster' => $video->posterUrl(), 'duration' => $video->present()['duration']] : null,
            'html' => $video->isReady() ? view('community.partials.video', [
                'video' => $video, 'owner' => "{$video->videoable_type}:{$item->id}", 'title' => $item instanceof Post ? $item->title : __('উত্তরের ভিডিও'),
            ])->render() : null,
        ])->header('Cache-Control', 'no-store');
    }

    /** Someone else's upload is "not found", not "forbidden": ids say nothing about who uploads. */
    private function mine(Request $request, Video $video): void
    {
        abort_unless($video->member_id === $request->attributes->get('member')->id, 404);
    }
}
