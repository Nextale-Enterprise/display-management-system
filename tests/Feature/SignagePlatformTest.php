<?php

namespace Tests\Feature;

use App\Jobs\TranscodeVideo;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Transcode\VideoTranscoder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignagePlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('media');
    }

    public function test_merchant_is_locked_to_their_organization(): void
    {
        $operator = $this->makeUser('operator');
        $operatorToken = $this->token($operator);

        $orgA = $this->postJson('/api/organizations', ['name' => 'Cafe A'], $this->bearer($operatorToken))
            ->assertCreated()
            ->json();
        $orgB = $this->postJson('/api/organizations', ['name' => 'Cafe B'], $this->bearer($operatorToken))
            ->assertCreated()
            ->json();

        $this->postJson('/api/screens', [
            'name' => 'Wall B',
            'organization_id' => $orgB['id'],
        ], $this->bearer($operatorToken))->assertCreated();

        $merchant = $this->makeUser('merchant', [$orgA['id']]);
        $merchantToken = $this->token($merchant);

        $this->getJson('/api/screens?organization_id='.$orgB['id'], $this->bearer($merchantToken))
            ->assertOk()
            ->assertJsonMissing(['name' => 'Wall B']);

        $this->postJson('/api/screens', [
            'name' => 'Mine',
            'organization_id' => $orgB['id'],
        ], $this->bearer($merchantToken))
            ->assertCreated()
            ->assertJsonPath('organization_id', $orgA['id']);

        $this->getJson('/api/screens', $this->bearer($operatorToken))->assertStatus(422);
        $this->postJson('/api/organizations', ['name' => 'Nope'], $this->bearer($merchantToken))->assertForbidden();
    }

    public function test_publish_keeps_the_snapshot_when_the_draft_changes(): void
    {
        [$token, $org] = $this->operatorContext();
        $asset = $this->readyAsset($org['id'], 'Poster');

        $playlist = $this->postJson('/api/playlists', [
            'name' => 'Morning',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [['media_asset_id' => $asset->id, 'duration_ms' => 8000]],
        ], $this->bearer($token))->assertOk();

        $this->postJson('/api/playlists/'.$playlist['id'].'/publish', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk()->assertJsonPath('generation', 1);

        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [['media_asset_id' => $asset->id, 'duration_ms' => 15000]],
        ], $this->bearer($token))->assertOk();

        $screen = $this->postJson('/api/screens', [
            'name' => 'Front',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->putJson('/api/screens/'.$screen['id'], [
            'name' => 'Front',
            'organization_id' => $org['id'],
            'playlist_id' => $playlist['id'],
        ], $this->bearer($token))->assertOk();

        $paired = $this->postJson('/api/device/pair', [
            'pairing_code' => $screen['pairing_code'],
            'device_name' => 'Box',
        ])->assertOk()->json();

        $manifest = $this->getJson('/api/device/manifest', [
            'Authorization' => 'Bearer '.$paired['device_token'],
        ])->assertOk()->json();

        $this->assertSame(1, $manifest['generation']);
        $this->assertSame(8000, $manifest['items'][0]['duration_ms']);

        $this->postJson('/api/device/heartbeat', [], [
            'Authorization' => 'Bearer '.$paired['device_token'],
        ])->assertOk();

        $this->getJson('/api/device/manifest', [
            'Authorization' => 'Bearer '.$paired['device_token'],
        ])->assertOk()->assertJsonPath('generation', 1);
    }

    public function test_publish_rejects_media_that_is_not_ready(): void
    {
        [$token, $org] = $this->operatorContext();
        $asset = MediaAsset::query()->create([
            'organization_id' => $org['id'],
            'name' => 'Clip',
            'type' => 'video',
            'path' => 'org/x/clip.mp4',
            'status' => 'processing',
        ]);
        $playlist = $this->postJson('/api/playlists', [
            'name' => 'Loop',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [['media_asset_id' => $asset->id, 'duration_ms' => 8000]],
        ], $this->bearer($token))->assertOk();

        $this->postJson('/api/playlists/'.$playlist['id'].'/publish', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_image_upload_is_ready_and_video_upload_queues_transcode(): void
    {
        [$token, $org] = $this->operatorContext();

        $this->post('/api/media', [
            'name' => 'Poster',
            'organization_id' => $org['id'],
            'file' => UploadedFile::fake()->create('poster.jpg', 20, 'image/jpeg'),
        ], $this->bearer($token))->assertCreated()->assertJsonPath('status', 'ready');

        Queue::fake();
        $this->post('/api/media', [
            'name' => 'Clip',
            'organization_id' => $org['id'],
            'file' => UploadedFile::fake()->create('clip.mp4', 20, 'video/mp4'),
        ], $this->bearer($token))->assertCreated()->assertJsonPath('status', 'processing');
        Queue::assertPushed(TranscodeVideo::class);
    }

    public function test_transcode_job_marks_the_asset_ready(): void
    {
        $org = Organization::query()->create(['name' => 'Cafe']);
        Storage::disk('media')->put('org/1/original.mp4', 'original');
        $asset = MediaAsset::query()->create([
            'organization_id' => $org->id,
            'name' => 'Clip',
            'type' => 'video',
            'path' => 'org/1/original.mp4',
            'status' => 'processing',
        ]);

        $this->app->instance(VideoTranscoder::class, new class implements VideoTranscoder
        {
            public function transcode(string $sourcePath, string $destPath): int
            {
                file_put_contents($destPath, 'transcoded');

                return 1500;
            }
        });

        (new TranscodeVideo($asset->id))->handle($this->app->make(VideoTranscoder::class));

        $asset->refresh();
        $this->assertSame('ready', $asset->status);
        $this->assertSame(1500, $asset->duration_ms);
        Storage::disk('media')->assertMissing('org/1/original.mp4');
    }

    private function operatorContext(): array
    {
        $operator = $this->makeUser('operator');
        $token = $this->token($operator);
        $org = $this->postJson('/api/organizations', ['name' => 'Cafe'], $this->bearer($token))
            ->assertCreated()
            ->json();

        return [$token, $org];
    }

    private function readyAsset(int $organizationId, string $name): MediaAsset
    {
        $path = 'org/'.$organizationId.'/poster.jpg';
        Storage::disk('media')->put($path, 'image');

        return MediaAsset::query()->create([
            'organization_id' => $organizationId,
            'name' => $name,
            'type' => 'image',
            'path' => $path,
            'status' => 'ready',
            'duration_ms' => 10000,
        ]);
    }

    private function makeUser(string $role, array $organizationIds = []): User
    {
        $user = User::factory()->create([
            'role_id' => Role::query()->where('slug', $role)->value('id'),
        ]);
        if ($organizationIds !== []) {
            $user->organizations()->sync($organizationIds);
        }

        return $user;
    }

    private function token(User $user): string
    {
        return $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('token');
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
