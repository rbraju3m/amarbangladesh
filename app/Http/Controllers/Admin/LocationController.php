<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\PersonalityTrait;
use App\Quiz\QuizConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Locations are edited, not created or deleted, from the panel: stored results point at them,
 * and each new place also needs an illustration, preview image and map position.
 */
class LocationController extends Controller
{
    public function index(): View
    {
        return view('admin.locations.index', [
            'locations' => Location::withCount('results')->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(Location $location): View
    {
        return view('admin.locations.form', [
            'location' => $location,
            'traits' => PersonalityTrait::orderBy('sort_order')->get(),
            'balance' => BalanceController::previewData(['kind' => 'location', 'slug' => $location->slug]),
        ]);
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $traitKeys = PersonalityTrait::pluck('key')->all();

        $data = $request->validate([
            'name_bn' => ['required', 'string', 'max:60'],
            'name_en' => ['required', 'string', 'max:60'],
            'emoji' => ['required', 'string', 'max:16'],
            'title_bn' => ['required', 'string', 'max:100'],
            'tagline_bn' => ['required', 'string', 'max:255'],
            'description_bn' => ['required', 'string', 'max:2000'],
            'reason_tail_bn' => ['required', 'string', 'max:255'],
            'badges' => ['required', 'string', 'max:500'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'illustration' => ['nullable', 'string', 'max:255'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
            'profile' => ['required', 'array'],
            'profile.*' => ['required', 'integer', 'between:0,10'],
        ]);

        $data['badges'] = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['badges']))));
        $data['profile'] = array_map('intval', array_intersect_key($data['profile'], array_flip($traitKeys)));
        $data['is_active'] = $request->boolean('is_active');

        $location->update($data);
        QuizConfig::forget();

        return redirect()->route('admin.locations.edit', $location)
            ->with('status', 'Saved. If you changed the name, title or picture, run `php artisan quiz:og-images`.');
    }
}
