<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\Moderation;
use App\Community\Photos;
use App\Models\AnalyticsEvent;
use App\Models\Answer;
use App\Models\Photo;
use App\Models\Post;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withoutMiddleware(ThrottleRequests::class); // these tests upload and post more than a person would
    }

    private function token(string $name = 'রাশেদ'): string
    {
        return Accounts::signIn('email', Str::random(10).'@example.test', $name)->issueToken();
    }

    private function as(string $token): static
    {
        return $this->withHeader('X-Member-Token', $token);
    }

    /** Uploads a photo and returns its id. */
    private function upload(string $token, ?UploadedFile $file = null): int
    {
        return $this->as($token)->post('/api/photos', ['photo' => $file ?? UploadedFile::fake()->image('a.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertCreated()->json('id');
    }

    /** A JPEG with an EXIF block saying "rotate 90° clockwise" (orientation 6) and a camera note. */
    private function sidewaysJpeg(): UploadedFile
    {
        $image = imagecreatetruecolor(400, 200);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();
        $ifd = "\x00\x01".pack('nnNnn', 0x0112, 3, 1, 6, 0)."\x00\x00\x00\x00"; // one entry: Orientation = 6
        $exif = "Exif\x00\x00"."MM\x00\x2a\x00\x00\x00\x08".$ifd.'SECRET-CAMERA-NOTE';
        $path = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
        file_put_contents($path, "\xFF\xD8\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));

        return new UploadedFile($path, 'camera.jpg', 'image/jpeg', null, true);
    }

    private function files(Photo $photo): array
    {
        return array_map(fn ($size) => "{$photo->path}-{$size}.webp", array_keys(Photos::SIZES));
    }

    public function test_an_upload_is_re_encoded_as_webp_in_two_sizes_without_metadata(): void
    {
        $token = $this->token();
        $data = $this->as($token)->post('/api/photos', ['photo' => UploadedFile::fake()->image('big.jpg', 3000, 2000)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonStructure(['id', 'thumb', 'full', 'width', 'height'])->json();

        $photo = Photo::find($data['id']);
        $this->assertSame([1600, 1067], [$photo->width, $photo->height]);
        $this->assertNull($photo->photoable_id);
        foreach ($this->files($photo) as $file) {
            Storage::disk('public')->assertExists($file);
            $bytes = Storage::disk('public')->get($file);
            $this->assertSame(['RIFF', 'WEBP'], [substr($bytes, 0, 4), substr($bytes, 8, 4)]);
        }
        $this->assertSame([480, 320], array_slice(getimagesizefromstring(Storage::disk('public')->get("{$photo->path}-thumb.webp")), 0, 2));
        $this->assertStringEndsWith('-full.webp', $data['full']);
        $this->assertStringContainsString('/storage/photos/', $data['full']);
    }

    public function test_a_sideways_camera_photo_is_turned_upright_and_its_exif_is_gone(): void
    {
        $photo = Photo::find($this->upload($this->token(), $this->sidewaysJpeg()));

        $this->assertSame([200, 400], [$photo->width, $photo->height]);
        foreach ($this->files($photo) as $file) {
            $bytes = Storage::disk('public')->get($file);
            $this->assertStringNotContainsString('SECRET-CAMERA-NOTE', $bytes);
            $this->assertStringNotContainsString('Exif', $bytes);
        }
    }

    public function test_only_real_images_are_accepted_and_uploads_need_an_account(): void
    {
        $token = $this->token();
        $refuse = fn ($file) => $this->as($token)->post('/api/photos', ['photo' => $file], ['Accept' => 'application/json'])->assertJsonValidationErrors('photo');

        $refuse(UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "not an image";'));
        $refuse(UploadedFile::fake()->image('anim.gif', 50, 50));
        $refuse(UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'));
        $this->assertSame(0, Photo::count());

        $this->flushHeaders()->post('/api/photos', ['photo' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_one_member_cannot_pile_up_unattached_uploads(): void
    {
        $token = $this->token();
        foreach (range(1, Photos::MAX_UNATTACHED) as $i) {
            $this->upload($token, UploadedFile::fake()->image("p{$i}.jpg", 20, 20));
        }
        $this->as($token)->post('/api/photos', ['photo' => UploadedFile::fake()->image('one-more.jpg', 20, 20)], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('photo');
    }

    public function test_a_post_gets_its_photos_in_order(): void
    {
        $token = $this->token();
        [$a, $b] = [$this->upload($token), $this->upload($token)];

        $id = $this->as($token)->postJson('/api/posts', ['title' => 'এই গাছটার নাম কী কেউ জানেন?', 'photos' => [$b, $a]])->assertCreated()->json('post.id');

        $this->assertSame([$b, $a], Post::find($id)->photos->pluck('id')->all());
        $this->assertSame(2, AnalyticsEvent::where('name', 'post_created')->first()->meta['photos']);
    }

    public function test_photos_that_are_not_yours_or_too_many_or_anonymous_are_refused_and_nothing_is_posted(): void
    {
        $token = $this->token();
        $mine = array_map(fn () => $this->upload($token), range(1, 5));
        $theirs = $this->upload($this->token('মিতু'));
        $ask = fn (array $data) => $this->as($token)->postJson('/api/posts', $data + ['title' => 'এই গাছটার নাম কী কেউ জানেন?'])->assertJsonValidationErrors('photos');

        $ask(['photos' => [$mine[0], $theirs]]);
        $ask(['photos' => $mine]);
        $ask(['photos' => [$mine[0]], 'anonymous' => true]);
        $ask(['photos' => ['abc']]);

        $this->assertSame(0, Post::count());
        $this->assertSame(0, Photo::whereNotNull('photoable_id')->count());
        // Already attached elsewhere: not reusable.
        $other = $this->as($token)->postJson('/api/posts', ['title' => 'প্রথম পোস্ট, ছবিসহ দিলাম', 'photos' => [$mine[0]]])->assertCreated()->json('post.id');
        $ask(['photos' => [$mine[0]]]);
        $this->assertSame(Post::find($other)->id, Photo::find($mine[0])->photoable_id);
    }

    public function test_answers_can_have_photos_but_replies_cannot(): void
    {
        $asker = $this->token();
        $post = Post::find($this->as($asker)->postJson('/api/posts', ['title' => 'এই গাছটার নাম কী কেউ জানেন?'])->json('post.id'));
        $token = $this->token('মিতু');
        $photo = $this->upload($token);

        $answer = $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['body' => 'এটা কদম গাছ, ছবি দেখুন', 'photos' => [$photo]])->assertCreated()->json('answer.id');
        $this->assertSame([$photo], Answer::find($answer)->photos->pluck('id')->all());

        $this->as($asker)->postJson("/api/posts/{$post->id}/answers", ['body' => 'ধন্যবাদ!', 'parent' => $answer, 'photos' => [$this->upload($asker)]])
            ->assertJsonValidationErrors('photos');
        $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['body' => 'বেনামে ছবি', 'anonymous' => true, 'photos' => [$this->upload($token)]])
            ->assertJsonValidationErrors('photos');
    }

    public function test_editing_reorders_adds_and_removes_photos(): void
    {
        $token = $this->token();
        [$a, $b, $c] = [$this->upload($token), $this->upload($token), $this->upload($token)];
        $post = Post::find($this->as($token)->postJson('/api/posts', ['title' => 'এই গাছটার নাম কী কেউ জানেন?', 'photos' => [$a, $b]])->json('post.id'));
        $removed = Photo::find($a);

        // Title unchanged, photos not sent: nothing changes, not even "edited".
        $this->as($token)->patchJson("/api/posts/{$post->id}", ['title' => $post->title])->assertOk();
        $this->assertNull($post->fresh()->edited_at);
        $this->assertSame([$a, $b], $post->photos()->pluck('id')->all());

        $data = $this->as($token)->patchJson("/api/posts/{$post->id}", ['title' => $post->title, 'photos' => [$c, $b]])->assertOk()->json();
        $this->assertSame([$c, $b], array_column($data['photos'], 'id'));
        $this->assertNotNull($post->fresh()->edited_at);
        $this->assertNull(Photo::find($a));
        foreach ($this->files($removed) as $file) {
            Storage::disk('public')->assertMissing($file);
        }

        $this->as($token)->patchJson("/api/posts/{$post->id}", ['title' => $post->title, 'photos' => []])->assertOk();
        $this->assertSame(0, $post->photos()->count());
    }

    public function test_deleting_takes_the_files_but_an_admin_removal_keeps_them_a_while(): void
    {
        $token = $this->token();
        $mine = Post::find($this->as($token)->postJson('/api/posts', ['title' => 'নিজে মুছে দেব এই পোস্টটা', 'photos' => [$this->upload($token)]])->json('post.id'));
        $removed = Post::find($this->as($token)->postJson('/api/posts', ['title' => 'অ্যাডমিন সরিয়ে দেবেন এটা', 'photos' => [$this->upload($token)]])->json('post.id'));
        [$minePhoto, $removedPhoto] = [$mine->photos->first(), $removed->photos->first()];

        $this->as($token)->deleteJson("/api/posts/{$mine->id}")->assertNoContent();
        Storage::disk('public')->assertMissing($this->files($minePhoto)[0]);
        $this->assertSame(0, $mine->photos()->count());

        Moderation::setStatus($removed, Post::REMOVED);
        Photos::prune();
        Storage::disk('public')->assertExists($this->files($removedPhoto)[0]); // could be restored

        $this->travel(Photos::REMOVED_DAYS + 1)->days();
        $this->artisan('community:prune-photos')->assertSuccessful();
        Storage::disk('public')->assertMissing($this->files($removedPhoto)[0]);
        $this->assertSame(0, Photo::count());
    }

    public function test_unattached_uploads_are_pruned_after_a_day(): void
    {
        $token = $this->token();
        $old = Photo::find($this->upload($token));
        $this->travel(Photos::UNATTACHED_HOURS + 1)->hours();
        $fresh = $this->upload($token);

        $this->assertSame(1, Photos::prune());
        $this->assertNull(Photo::find($old->id));
        Storage::disk('public')->assertMissing($this->files($old)[0]);
        $this->assertNotNull(Photo::find($fresh));

        // A photo whose post is gone altogether (e.g. purged demo content) goes too.
        Photo::find($fresh)->update(['photoable_type' => 'post', 'photoable_id' => 999999]);
        $this->assertSame(1, Photos::prune());
    }

    public function test_deleting_an_account_with_its_posts_deletes_the_photos(): void
    {
        $token = $this->token();
        $this->as($token)->postJson('/api/posts', ['title' => 'এই গাছটার নাম কী কেউ জানেন?', 'photos' => [$this->upload($token)]])->assertCreated();
        $photo = Photo::first();
        $member = $photo->member;

        $this->as($token)->deleteJson('/api/members/me', ['confirm' => $member->code, 'content' => true])->assertSuccessful();

        $this->assertSame(0, Photo::count());
        Storage::disk('public')->assertMissing($this->files($photo)[0]);
    }
}
