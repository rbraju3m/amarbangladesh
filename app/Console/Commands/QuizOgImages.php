<?php

namespace App\Console\Commands;

use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Renders the 1200×630 link-preview images with headless Chrome, which shapes Bangla
 * correctly (PHP GD cannot). Run after changing location names, titles or illustrations.
 */
class QuizOgImages extends Command
{
    protected $signature = 'quiz:og-images {--chrome=google-chrome : Chrome/Chromium binary}';

    protected $description = 'Render Open Graph preview images for every location and the home page, in Bangla and English';

    public function handle(): int
    {
        $fonts = base_path('node_modules/@fontsource-variable/anek-bangla/files');
        if (! File::exists($fonts)) {
            $this->error('Run `npm install` first — the font files come from @fontsource-variable/anek-bangla.');

            return self::FAILURE;
        }

        $tmp = storage_path('app/og-tmp');
        File::ensureDirectoryExists($tmp);
        File::ensureDirectoryExists(public_path('images/og'));

        $base = [
            // Inlined: Chrome treats file:// fonts as cross-origin and silently falls back.
            'fontBn' => 'data:font/woff2;base64,'.base64_encode(File::get("{$fonts}/anek-bangla-bengali-wght-normal.woff2")),
            'fontLatin' => 'data:font/woff2;base64,'.base64_encode(File::get("{$fonts}/anek-bangla-latin-wght-normal.woff2")),
        ];

        // Bangla in images/og/, English in images/og/en/ (see Location::ogImageUrl).
        foreach (['bn' => '', 'en' => 'en/'] as $locale => $dir) {
            app()->setLocale($locale);
            File::ensureDirectoryExists(public_path("images/og/{$dir}"));

            $jobs = ['default' => $base + [
                'accent' => '#006a4e', 'illustration' => null, 'kicker' => __('মাত্র ১ মিনিটের খেলা'),
                'name' => $locale === 'en' ? "Where is your\nBangladesh?" : "তোমার বাংলাদেশ\nকোথায়?", 'nameSize' => 76,
                'title' => __('বাংলাদেশের কোন জায়গাটা তোমার মতো?'), 'cta' => __('খেলে দেখো →'),
            ]];

            foreach (Location::orderBy('sort_order')->get() as $location) {
                $illustration = public_path($location->illustration ?: "images/locations/{$location->slug}.svg");
                $name = $location->text('name');
                $jobs[$location->slug] = $base + [
                    'accent' => $location->accent_color,
                    'illustration' => File::exists($illustration) ? "file://{$illustration}" : null,
                    'kicker' => __('আমার বাংলাদেশ হলো'),
                    'name' => "{$name} {$location->emoji}",
                    'title' => $location->text('title'),
                    'nameSize' => mb_strlen($name) > ($locale === 'en' ? 9 : 7) ? 84 : 112,
                ];
            }

            foreach ($jobs as $slug => $data) {
                $html = "{$tmp}/{$locale}-{$slug}.html";
                File::put($html, view('og.image', $data)->render());
                $png = public_path("images/og/{$dir}{$slug}.png");

                $result = Process::timeout(60)->run([
                    $this->option('chrome'), '--headless=new', '--no-sandbox', '--disable-gpu', '--hide-scrollbars',
                    '--allow-file-access-from-files', '--window-size=1200,630', '--virtual-time-budget=2000',
                    "--screenshot={$png}", "file://{$html}",
                ]);

                if (! File::exists($png)) {
                    $this->error("Failed: {$dir}{$slug}\n".$result->errorOutput());

                    return self::FAILURE;
                }
                $this->line("✓ images/og/{$dir}{$slug}.png");
            }
        }

        File::deleteDirectory($tmp);

        return self::SUCCESS;
    }
}
