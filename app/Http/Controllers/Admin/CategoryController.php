<?php

namespace App\Http\Controllers\Admin;

use App\Community\Taxonomy;
use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Community topics (categories): add, rename, emoji, order, on/off. Never deleted: a topic that is
 * turned off disappears from Ask and the filters, while its posts and old links keep working.
 * Every change goes into the moderation log.
 */
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories', [
            'categories' => Category::orderBy('sort_order')->orderBy('id')->get(),
            'posts' => Post::where('status', '!=', Post::DELETED)->whereNotNull('category_id')->groupBy('category_id')
                ->selectRaw('category_id, COUNT(*) AS n')->pluck('n', 'category_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $request->validate([
            'slug' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
        ])['slug'] ?? null ?: Str::slug(($data['name_en'] ?? null) ?: $data['name_bn']);
        validator($data, ['slug' => ['required', Rule::unique('categories', 'slug')]], [
            'slug.required' => 'Give the topic a URL name (a-z, 0-9 and dashes), or an English name to make one from.',
            'slug.unique' => 'Another topic already uses that URL name.',
        ])->validate();
        $data['sort_order'] ??= (int) Category::max('sort_order') + 1;

        $category = Category::create($data);
        $this->log($request, 'topic_add', $category, ['slug' => $category->slug]);

        return back()->with('status', "Topic “{$category->name_en}” added.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort_order'] ??= $category->sort_order; // left empty: keep its place
        $category->fill($data + ['is_active' => $request->boolean('is_active')]);
        $changes = collect($category->getDirty())->except('updated_at')
            ->map(fn ($new, $field) => ['from' => $category->getOriginal($field), 'to' => $new])->all();
        if (! $changes) {
            return back()->with('status', 'Nothing changed.');
        }
        $category->save();

        $action = array_keys($changes) === ['is_active'] ? ($category->is_active ? 'topic_on' : 'topic_off') : 'topic_edit';
        $this->log($request, $action, $category, ['changes' => $changes]);

        return back()->with('status', "Topic “{$category->name_en}” ".match ($action) {
            'topic_on' => 'turned on.', 'topic_off' => 'turned off: hidden from Ask and filters, its posts stay.', default => 'saved.',
        });
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name_bn' => ['required', 'string', 'max:60'],
            'name_en' => ['nullable', 'string', 'max:60'],
            'emoji' => ['required', 'string', 'max:16'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);
    }

    /** Topics feed every cached list of them: clear those and write the log line. */
    private function log(Request $request, string $action, Category $category, array $meta): void
    {
        Taxonomy::forget();
        AdminAction::create([
            'user_id' => $request->user()->id, 'action' => $action, 'target_type' => 'category', 'target_id' => $category->id,
            'meta' => ['title' => "{$category->emoji} {$category->name_bn} / {$category->name_en}"] + $meta,
        ]);
    }
}
