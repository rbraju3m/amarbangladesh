<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Photos;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    /** One photo, uploaded as soon as it's picked; the post or answer sends its id back when saved. */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file']], ['photo.required' => __('একটা ছবি বেছে নিন।'), 'photo.file' => __('একটা ছবি বেছে নিন।')]);

        return response()->json(Photos::store($request->attributes->get('member'), $request->file('photo'))->present(), 201);
    }
}
