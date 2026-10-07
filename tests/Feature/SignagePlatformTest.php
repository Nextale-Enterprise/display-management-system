<?php

namespace Tests\Feature;

use App\Jobs\TranscodeVideo;
use App\Models\Device;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\Playlist;
use App\Models\PlaylistItem;
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
        $operator = $this->makeUser('admin');
        $operatorToken = $this->token($operator);

        $orgA = $this->postJson('/api/organizations', ['name' => 'Cafe A'], $this->bearer($operatorToken))
            ->assertCreated()
            ->json();
        $orgB = $this->postJson('/api/organizations', ['name' => 'Cafe B'], $this->bearer($operatorToken))
            ->assertCreated()
            ->json();

        $this->makeDevice($operatorToken, $orgB['id'], 'Wall B');

        $merchant = $this->makeUser('merchant', [$orgA['id']]);
        $merchantToken = $this->token($merchant);

        $this->getJson('/api/devices?organization_id='.$orgB['id'], $this->bearer($merchantToken))
            ->assertOk()
            ->assertJsonMissing(['name' => 'Wall B']);

        $branchA = $this->postJson('/api/branches', [
            'name' => 'Lobby',
            'organization_id' => $orgA['id'],
        ], $this->bearer($operatorToken))->assertCreated()->json();
        $poster = $this->readyAsset($orgA['id'], 'Lobby art', 'lobby-a.jpg');
        $playlistA = $this->publishPlaylist($operatorToken, $orgA['id'], 'Lobby', $poster->id);

        $this->postJson('/api/devices', [
            'name' => 'Mine',
            'organization_id' => $orgB['id'],
            'branch_id' => $branchA['id'],
            'playlist_id' => $playlistA['id'],
        ], $this->bearer($merchantToken))
            ->assertCreated()
            ->assertJsonPath('organization_id', $orgA['id']);

        $this->getJson('/api/devices', $this->bearer($operatorToken))->assertStatus(422);
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

        $live = collect($this->getJson('/api/playlists?organization_id='.$org['id'], $this->bearer($token))->json())
            ->firstWhere('id', $playlist['id'])['live_items'][0];
        $this->assertSame('image', $live['type']);
        $this->assertNotEmpty($live['url']);

        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [['media_asset_id' => $asset->id, 'duration_ms' => 15000]],
        ], $this->bearer($token))->assertOk();

        $branch = $this->postJson('/api/branches', [
            'name' => 'Downtown',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $screen = $this->postJson('/api/devices', [
            'name' => 'Front',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $playlist['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->putJson('/api/devices/'.$screen['id'], [
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

    public function test_screen_assignment_requires_a_published_playlist(): void
    {
        [$token, $org] = $this->operatorContext();
        $asset = $this->readyAsset($org['id'], 'Poster');
        $playlist = $this->postJson('/api/playlists', [
            'name' => 'Morning',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $ready = $this->publishPlaylist($token, $org['id'], 'Ready', $asset->id);
        $branch = $this->postJson('/api/branches', [
            'name' => 'Downtown',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $screen = $this->postJson('/api/devices', [
            'name' => 'Front',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $ready['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->putJson('/api/devices/'.$screen['id'], [
            'name' => 'Front',
            'organization_id' => $org['id'],
            'playlist_id' => $playlist['id'],
        ], $this->bearer($token))->assertStatus(422);

        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [['media_asset_id' => $asset->id, 'duration_ms' => 8000]],
        ], $this->bearer($token))->assertOk();
        $this->postJson('/api/playlists/'.$playlist['id'].'/publish', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();

        $this->putJson('/api/devices/'.$screen['id'], [
            'name' => 'Front',
            'organization_id' => $org['id'],
            'playlist_id' => $playlist['id'],
        ], $this->bearer($token))->assertOk()->assertJsonPath('playlist_live', true);
    }

    public function test_device_can_pick_one_media_from_its_playlist(): void
    {
        [$token, $org] = $this->operatorContext();
        $poster = $this->readyAsset($org['id'], 'Poster');
        $clip = $this->readyAsset($org['id'], 'Clip', 'clip.jpg');
        $other = $this->readyAsset($org['id'], 'Other', 'other.jpg');
        $playlist = $this->postJson('/api/playlists', [
            'name' => 'Morning',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [
                ['media_asset_id' => $poster->id, 'duration_ms' => 8000],
                ['media_asset_id' => $clip->id, 'duration_ms' => 8000],
            ],
        ], $this->bearer($token))->assertOk();
        $this->postJson('/api/playlists/'.$playlist['id'].'/publish', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();

        $branch = $this->postJson('/api/branches', [
            'name' => 'Downtown',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $screen = $this->postJson('/api/devices', [
            'name' => 'Front',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $playlist['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->putJson('/api/devices/'.$screen['id'], [
            'name' => 'Front',
            'organization_id' => $org['id'],
            'playlist_id' => $playlist['id'],
            'screen_row' => 9,
        ], $this->bearer($token))->assertStatus(422);

        $this->putJson('/api/devices/'.$screen['id'], [
            'name' => 'Front',
            'organization_id' => $org['id'],
            'playlist_id' => $playlist['id'],
            'screen_row' => 1,
        ], $this->bearer($token))->assertOk()->assertJsonPath('screen_name', 'Screen 2');

        $paired = $this->postJson('/api/device/pair', [
            'pairing_code' => $screen['pairing_code'],
            'device_name' => 'Box',
        ])->assertOk()->json();

        $manifest = $this->getJson('/api/device/manifest', [
            'Authorization' => 'Bearer '.$paired['device_token'],
        ])->assertOk()->json();

        $this->assertCount(1, $manifest['items']);
        $this->assertSame('Clip', $manifest['items'][0]['name']);
    }

    public function test_playlist_reports_unpublished_changes(): void
    {
        [$token, $org] = $this->operatorContext();
        $asset = $this->readyAsset($org['id'], 'Poster');
        $playlist = $this->postJson('/api/playlists', [
            'name' => 'Morning',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $items = ['organization_id' => $org['id'], 'items' => [['media_asset_id' => $asset->id, 'duration_ms' => 8000]]];

        $this->putJson('/api/playlists/'.$playlist['id'].'/items', $items, $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('unpublished_changes', true)
            ->assertJsonPath('draft_item_count', 1)
            ->assertJsonPath('published_item_count', 0);

        $this->postJson('/api/playlists/'.$playlist['id'].'/publish', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk()->assertJsonPath('playlist.unpublished_changes', false);

        $items['items'][0]['duration_ms'] = 15000;
        $this->putJson('/api/playlists/'.$playlist['id'].'/items', $items, $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('unpublished_changes', true)
            ->assertJsonPath('published_item_count', 1);
    }

    public function test_removing_a_draft_item_keeps_the_published_file(): void
    {
        [$token, $org] = $this->operatorContext();
        $first = $this->readyAsset($org['id'], 'First', 'first.jpg');
        $second = $this->readyAsset($org['id'], 'Second', 'second.jpg');
        $playlist = $this->postJson('/api/playlists', [
            'name' => 'Loop',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [['media_asset_id' => $first->id, 'duration_ms' => 8000]],
        ], $this->bearer($token))->assertOk();
        $this->postJson('/api/playlists/'.$playlist['id'].'/publish', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();

        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [['media_asset_id' => $second->id, 'duration_ms' => 8000]],
        ], $this->bearer($token))->assertOk();

        $this->assertDatabaseHas('media_assets', ['id' => $first->id]);
        Storage::disk('media')->assertExists($first->path);
    }

    public function test_adding_a_file_to_a_playlist_appends_it(): void
    {
        [$token, $org] = $this->operatorContext();
        $playlist = $this->postJson('/api/playlists', [
            'name' => 'Loop',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->post('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'file' => UploadedFile::fake()->create('poster.jpg', 20, 'image/jpeg'),
        ], $this->bearer($token))
            ->assertCreated()
            ->assertJsonPath('items.0.name', 'poster')
            ->assertJsonPath('items.0.status', 'ready')
            ->assertJsonPath('items.0.duration_ms', 10000);

        Queue::fake();
        $this->post('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'file' => UploadedFile::fake()->create('clip.mp4', 20, 'video/mp4'),
        ], $this->bearer($token))
            ->assertCreated()
            ->assertJsonPath('items.1.name', 'clip')
            ->assertJsonPath('items.1.status', 'processing');
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
        $playlist = Playlist::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loop',
        ]);
        $item = PlaylistItem::query()->create([
            'playlist_id' => $playlist->id,
            'media_asset_id' => $asset->id,
            'position' => 0,
            'duration_ms' => 1000,
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
        $this->assertSame(1500, $item->refresh()->duration_ms);
        Storage::disk('media')->assertMissing('org/1/original.mp4');
    }

    public function test_claim_accept_creates_a_device_without_a_playlist(): void
    {
        [$token, $org] = $this->operatorContext();
        $branch = $this->postJson('/api/branches', [
            'name' => 'Downtown',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $other = $this->postJson('/api/organizations', ['name' => 'Other'], $this->bearer($token))
            ->assertCreated()->json();

        $this->postJson('/api/claims', [
            'code' => 'HEAD01',
            'role' => 'screen',
            'cms_id' => 'cms-1',
            'local_device_id' => 2,
        ])->assertStatus(422);

        $this->postJson('/api/claims', [
            'code' => 'HEAD01',
            'role' => 'head',
            'cms_id' => 'cms-1',
            'local_device_id' => 1,
        ])->assertCreated()->assertJsonPath('claimed', false);

        $this->postJson('/api/claims/HEAD01/accept', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertStatus(422);

        $foreign = $this->makeDevice($token, $other['id'], 'Elsewhere screen');
        $this->postJson('/api/claims/HEAD01/accept', [
            'organization_id' => $org['id'],
            'device_id' => $foreign['id'],
        ], $this->bearer($token))->assertStatus(422);

        $counter = $this->makeDevice($token, $org['id'], 'Front counter', $branch['id']);
        $accepted = $this->postJson('/api/claims/HEAD01/accept', [
            'organization_id' => $org['id'],
            'device_id' => $counter['id'],
        ], $this->bearer($token))->assertOk()->json();

        $this->assertSame('Front counter', $accepted['device']['name']);
        $this->assertSame($branch['id'], $accepted['device']['branch_id']);
        $this->assertSame($counter['playlist_id'], $accepted['device']['playlist_id']);
        $this->assertTrue($accepted['device']['linked']);
        $this->assertFalse($accepted['device']['paired']);
        $this->getJson('/api/claims/HEAD01')->assertOk()->assertJsonPath('claimed', true);

        $this->postJson('/api/claims/HEAD01/accept', [
            'organization_id' => $org['id'],
            'device_id' => $counter['id'],
        ], $this->bearer($token))->assertStatus(422);

        $this->postJson('/api/claims', [
            'code' => 'SCRN02',
            'role' => 'screen',
            'cms_id' => 'cms-1',
            'local_device_id' => 2,
        ])->assertCreated();

        $bar = $this->makeDevice($token, $org['id'], 'Bar screen', $branch['id']);
        $device = $this->postJson('/api/claims/SCRN02/accept', [
            'organization_id' => $org['id'],
            'device_id' => $bar['id'],
        ], $this->bearer($token))->assertOk()->json('device');
        $this->assertSame('Bar screen', $device['name']);
        $this->assertSame($bar['playlist_id'], $device['playlist_id']);

        $again = $this->makeDevice($token, $org['id'], 'Second counter', $branch['id']);
        $this->postJson('/api/claims', [
            'code' => 'AGAIN',
            'role' => 'screen',
            'cms_id' => 'cms-1',
            'local_device_id' => 4,
        ])->assertCreated();
        $this->postJson('/api/claims/AGAIN/accept', [
            'organization_id' => $org['id'],
            'device_id' => $counter['id'],
        ], $this->bearer($token))->assertStatus(422);
        $this->postJson('/api/claims/AGAIN/accept', [
            'organization_id' => $org['id'],
            'device_id' => $again['id'],
        ], $this->bearer($token))->assertOk();

        $this->postJson('/api/claims', [
            'code' => 'LATE03',
            'role' => 'screen',
            'cms_id' => 'cms-1',
            'local_device_id' => 3,
        ])->assertCreated()->assertJsonPath('expired', false);
        \App\Models\ClaimCode::query()->where('code', 'LATE03')->update(['expires_at' => now()->subMinute()]);
        $late = $this->makeDevice($token, $org['id'], 'Late screen', $branch['id']);
        $this->postJson('/api/claims/LATE03/accept', [
            'organization_id' => $org['id'],
            'device_id' => $late['id'],
        ], $this->bearer($token))->assertStatus(422);

        $this->deleteJson('/api/devices/'.$accepted['device']['id'], [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();
        $this->getJson('/api/claims/HEAD01')->assertOk()
            ->assertJsonPath('claimed', true)
            ->assertJsonPath('revoked', true);
        $this->postJson('/api/claims', [
            'code' => 'SCRN99',
            'role' => 'screen',
            'cms_id' => 'cms-1',
            'local_device_id' => 9,
        ])->assertStatus(422);
    }

    public function test_a_branch_can_have_only_one_head(): void
    {
        [$token, $org] = $this->operatorContext();
        $branch = $this->postJson('/api/branches', [
            'name' => 'Downtown',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $other = $this->postJson('/api/branches', [
            'name' => 'Airport',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->postJson('/api/claims', [
            'code' => 'HEAD11',
            'role' => 'head',
            'cms_id' => 'cms-head',
            'local_device_id' => 1,
        ])->assertCreated();
        $first = $this->makeDevice($token, $org['id'], 'Counter', $branch['id'], null, true);
        $this->assertTrue($first['is_head']);
        $this->postJson('/api/claims/HEAD11/accept', [
            'organization_id' => $org['id'],
            'device_id' => $first['id'],
        ], $this->bearer($token))->assertOk()->assertJsonPath('device.is_head', true);

        $this->postJson('/api/devices', [
            'name' => 'Window',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $first['playlist_id'],
            'is_head' => true,
        ], $this->bearer($token))->assertStatus(422);
        $second = $this->makeDevice($token, $org['id'], 'Window', $branch['id']);
        $this->assertFalse($second['is_head']);

        $this->postJson('/api/claims', [
            'code' => 'HEAD12',
            'role' => 'head',
            'cms_id' => 'cms-head-2',
            'local_device_id' => 1,
        ])->assertCreated();
        $this->postJson('/api/claims/HEAD12/accept', [
            'organization_id' => $org['id'],
            'device_id' => $second['id'],
        ], $this->bearer($token))->assertOk()->assertJsonPath('device.is_head', false);

        $this->postJson('/api/claims', [
            'code' => 'HEAD13',
            'role' => 'head',
            'cms_id' => 'cms-head-3',
            'local_device_id' => 1,
        ])->assertCreated();
        $gate = $this->makeDevice($token, $org['id'], 'Gate', $other['id'], null, true);
        $this->postJson('/api/claims/HEAD13/accept', [
            'organization_id' => $org['id'],
            'device_id' => $gate['id'],
        ], $this->bearer($token))->assertOk()->assertJsonPath('device.is_head', true);
    }

    public function test_claim_role_follows_the_branch_head(): void
    {
        [$token, $org] = $this->operatorContext();
        $branch = $this->postJson('/api/branches', [
            'name' => 'Downtown',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->postJson('/api/claims', [
            'code' => 'ROLE01',
            'role' => 'head',
            'cms_id' => 'cms-role-1',
            'local_device_id' => 1,
        ])->assertCreated();
        $first = $this->makeDevice($token, $org['id'], 'Counter', $branch['id'], null, true);
        $this->postJson('/api/claims/ROLE01/accept', [
            'organization_id' => $org['id'],
            'device_id' => $first['id'],
        ], $this->bearer($token))->assertOk();

        $this->getJson('/api/claims/ROLE01/role')->assertOk()
            ->assertJsonPath('is_head', true)
            ->assertJsonPath('head_device_id', $first['id'])
            ->assertJsonPath('branch_id', $branch['id']);

        $this->postJson('/api/claims', [
            'code' => 'ROLE02',
            'role' => 'head',
            'cms_id' => 'cms-role-2',
            'local_device_id' => 1,
        ])->assertCreated();
        $second = $this->makeDevice($token, $org['id'], 'Window', $branch['id']);
        $this->postJson('/api/claims/ROLE02/accept', [
            'organization_id' => $org['id'],
            'device_id' => $second['id'],
        ], $this->bearer($token))->assertOk();

        $this->putJson('/api/devices/'.$first['id'], [
            'name' => 'Counter',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'is_head' => false,
        ], $this->bearer($token))->assertOk();
        $this->putJson('/api/devices/'.$second['id'], [
            'name' => 'Window',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'is_head' => true,
        ], $this->bearer($token))->assertOk();

        $this->getJson('/api/claims/ROLE01/role')->assertOk()
            ->assertJsonPath('is_head', false)
            ->assertJsonPath('head_device_id', $second['id']);
        $this->getJson('/api/claims/ROLE02/role')->assertOk()
            ->assertJsonPath('is_head', true)
            ->assertJsonPath('head_device_id', $second['id']);

        $this->deleteJson('/api/devices/'.$second['id'], [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();
        $this->getJson('/api/claims/ROLE02/role')->assertNotFound();
    }

    public function test_devices_in_one_branch_can_share_or_split_playlists(): void
    {
        [$token, $org] = $this->operatorContext();
        $branch = $this->postJson('/api/branches', [
            'name' => 'Downtown',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $asset = $this->readyAsset($org['id'], 'Poster');
        $morning = $this->publishPlaylist($token, $org['id'], 'Morning', $asset->id);
        $evening = $this->publishPlaylist($token, $org['id'], 'Evening', $asset->id);

        $front = $this->postJson('/api/devices', [
            'name' => 'Front',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $morning['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $bar = $this->postJson('/api/devices', [
            'name' => 'Bar',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $evening['id'],
        ], $this->bearer($token))->assertCreated()->json();

        $this->putJson('/api/devices/'.$front['id'], [
            'name' => 'Front',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $morning['id'],
        ], $this->bearer($token))->assertOk();
        $this->putJson('/api/devices/'.$bar['id'], [
            'name' => 'Bar',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $evening['id'],
        ], $this->bearer($token))->assertOk()->assertJsonPath('playlist_id', $evening['id']);

        $this->putJson('/api/devices/'.$bar['id'], [
            'name' => 'Bar',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $morning['id'],
        ], $this->bearer($token))->assertOk()->assertJsonPath('playlist_id', $morning['id']);
    }

    public function test_only_superadmin_can_manage_users(): void
    {
        $admin = $this->makeUser('admin');
        $this->getJson('/api/users', $this->bearer($this->token($admin)))->assertForbidden();

        $superadmin = $this->makeUser('superadmin');
        $this->getJson('/api/users', $this->bearer($this->token($superadmin)))->assertOk();
    }

    private function operatorContext(): array
    {
        $operator = $this->makeUser('admin');
        $token = $this->token($operator);
        $org = $this->postJson('/api/organizations', ['name' => 'Cafe'], $this->bearer($token))
            ->assertCreated()
            ->json();

        return [$token, $org];
    }

    private function publishPlaylist(string $token, int $organizationId, string $name, int $assetId): array
    {
        $playlist = $this->postJson('/api/playlists', [
            'name' => $name,
            'organization_id' => $organizationId,
        ], $this->bearer($token))->assertCreated()->json();
        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $organizationId,
            'items' => [['media_asset_id' => $assetId, 'duration_ms' => 8000]],
        ], $this->bearer($token))->assertOk();
        $this->postJson('/api/playlists/'.$playlist['id'].'/publish', [
            'organization_id' => $organizationId,
        ], $this->bearer($token))->assertOk();

        return $playlist;
    }

    private function makeDevice(string $token, int $organizationId, string $name, ?int $branchId = null, ?int $playlistId = null, bool $isHead = false): array
    {
        if ($branchId === null) {
            $branchId = $this->postJson('/api/branches', [
                'name' => $name.' branch',
                'organization_id' => $organizationId,
            ], $this->bearer($token))->assertCreated()->json('id');
        }
        if ($playlistId === null) {
            $asset = $this->readyAsset($organizationId, $name, uniqid($name).'.jpg');
            $playlistId = $this->publishPlaylist($token, $organizationId, $name.' list', $asset->id)['id'];
        }

        return $this->postJson('/api/devices', [
            'name' => $name,
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'playlist_id' => $playlistId,
            'is_head' => $isHead,
        ], $this->bearer($token))->assertCreated()->json();
    }

    public function test_claimed_device_playback_follows_the_published_playlist(): void
    {
        [$token, $org] = $this->operatorContext();
        $branch = $this->postJson('/api/branches', [
            'name' => 'Downtown',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $asset = $this->readyAsset($org['id'], 'Clip', 'clip.mp4');
        MediaAsset::query()->whereKey($asset->id)->update(['type' => 'video', 'duration_ms' => 4000]);

        $this->postJson('/api/claims', [
            'code' => 'PLAY01',
            'role' => 'head',
            'cms_id' => 'cms-play',
            'local_device_id' => 7,
        ])->assertCreated();
        $playlist = $this->postJson('/api/playlists', [
            'name' => 'Wall',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [['media_asset_id' => $asset->id, 'duration_ms' => 4000]],
        ], $this->bearer($token))->assertOk();
        $this->postJson('/api/playlists/'.$playlist['id'].'/publish', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();
        $device = $this->postJson('/api/devices', [
            'name' => 'Head',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $playlist['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $this->postJson('/api/claims/PLAY01/accept', [
            'organization_id' => $org['id'],
            'device_id' => $device['id'],
        ], $this->bearer($token))->assertOk()->assertJsonPath('device.name', 'Head');

        $playback = $this->getJson('/api/claims/PLAY01/playback')->assertOk()->json();
        $this->assertTrue($playback['assigned']);
        $this->assertSame($playlist['id'], $playback['playlist_id']);
        $this->assertSame(1, $playback['generation']);
        $this->assertSame('video', $playback['items'][0]['type']);
        $this->assertSame(4000, $playback['items'][0]['duration_ms']);

        $file = $this->get('/api/claims/PLAY01/items/'.$playback['items'][0]['id'].'/file');
        $file->assertOk();
        $this->assertSame('image', $file->streamedContent());

        $second = $this->readyAsset($org['id'], 'Second', 'second.mp4');
        $poster = $this->readyAsset($org['id'], 'Poster', 'poster.jpg');
        MediaAsset::query()->whereKey($second->id)->update(['type' => 'video', 'duration_ms' => 8000]);
        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [
                ['media_asset_id' => $asset->id, 'row_index' => 0, 'column_index' => 0, 'duration_ms' => 4000],
                ['media_asset_id' => $second->id, 'row_index' => 0, 'column_index' => 1, 'duration_ms' => 1000],
                ['media_asset_id' => $poster->id, 'row_index' => 0, 'column_index' => 2, 'duration_ms' => 3000],
            ],
        ], $this->bearer($token))->assertOk();
        $this->postJson('/api/playlists/'.$playlist['id'].'/publish', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();
        $this->putJson('/api/devices/'.$device['id'], [
            'name' => 'Head',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $playlist['id'],
            'screen_row' => 0,
        ], $this->bearer($token))->assertOk();
        $picked = $this->getJson('/api/claims/PLAY01/playback')->assertOk()->json();
        $this->assertSame(0, $picked['screen_row']);
        $this->assertCount(3, $picked['items']);
        $this->assertSame($second->id, $picked['items'][1]['media_asset_id']);
        $this->assertSame('Second', $picked['items'][1]['name']);
        $this->assertSame(8000, $picked['items'][1]['duration_ms']);
        $this->assertSame(8000, $picked['items'][1]['file_duration_ms']);
        $this->assertSame('image', $picked['items'][2]['type']);
        $this->assertSame(3000, $picked['items'][2]['duration_ms']);
        $this->assertSame(3000, $picked['items'][2]['file_duration_ms']);

        $this->deleteJson('/api/devices/'.$device['id'], [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();
        $this->getJson('/api/claims/PLAY01/playback')->assertNotFound();
    }

    public function test_a_short_video_loops_to_the_column_and_a_missing_cell_holds(): void
    {
        [$token, $org] = $this->operatorContext();
        $branch = $this->postJson('/api/branches', [
            'name' => 'Downtown',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $short = $this->readyAsset($org['id'], 'Short', 'short.mp4');
        $long = $this->readyAsset($org['id'], 'Long', 'long.mp4');
        $poster = $this->readyAsset($org['id'], 'Poster', 'poster.jpg');
        MediaAsset::query()->whereKey($short->id)->update(['type' => 'video', 'duration_ms' => 4000]);
        MediaAsset::query()->whereKey($long->id)->update(['type' => 'video', 'duration_ms' => 8000]);
        $this->postJson('/api/claims', [
            'code' => 'ROW01',
            'role' => 'head',
            'cms_id' => 'cms-row',
            'local_device_id' => 4,
        ])->assertCreated();
        $playlist = $this->postJson('/api/playlists', [
            'name' => 'Wall',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $this->putJson('/api/playlists/'.$playlist['id'].'/items', [
            'organization_id' => $org['id'],
            'items' => [
                ['media_asset_id' => $short->id, 'row_index' => 0, 'column_index' => 0, 'duration_ms' => 4000],
                ['media_asset_id' => $long->id, 'row_index' => 1, 'column_index' => 0, 'duration_ms' => 8000],
                ['media_asset_id' => $poster->id, 'row_index' => 1, 'column_index' => 1, 'duration_ms' => 3000],
            ],
        ], $this->bearer($token))->assertOk();
        $this->postJson('/api/playlists/'.$playlist['id'].'/publish', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();
        $device = $this->postJson('/api/devices', [
            'name' => 'Screen one',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $playlist['id'],
            'screen_row' => 0,
        ], $this->bearer($token))->assertCreated()->json();
        $this->postJson('/api/claims/ROW01/accept', [
            'organization_id' => $org['id'],
            'device_id' => $device['id'],
        ], $this->bearer($token))->assertOk();

        $top = $this->getJson('/api/claims/ROW01/playback')->assertOk()->json();
        $this->assertCount(3, $top['items']);
        $this->assertSame($short->id, $top['media_asset_id']);
        $this->assertCount(2, $top['row_items']);
        $this->assertSame('video', $top['row_items'][0]['type']);
        $this->assertSame(8000, $top['row_items'][0]['duration_ms']);
        $this->assertSame(4000, $top['row_items'][0]['file_duration_ms']);
        $this->assertSame('hold', $top['row_items'][1]['type']);
        $this->assertSame(3000, $top['row_items'][1]['duration_ms']);

        $this->putJson('/api/devices/'.$device['id'], [
            'name' => 'Screen two',
            'organization_id' => $org['id'],
            'branch_id' => $branch['id'],
            'playlist_id' => $playlist['id'],
            'screen_row' => 1,
        ], $this->bearer($token))->assertOk();
        $bottom = $this->getJson('/api/claims/ROW01/playback')->assertOk()->json();
        $this->assertSame('video', $bottom['row_items'][0]['type']);
        $this->assertSame(8000, $bottom['row_items'][0]['file_duration_ms']);
        $this->assertSame('image', $bottom['row_items'][1]['type']);
        $this->assertSame(3000, $bottom['row_items'][1]['duration_ms']);
    }

    public function test_release_frees_the_device_for_another_tv(): void
    {
        [$token, $org] = $this->operatorContext();
        $slot = $this->makeDevice($token, $org['id'], 'Counter');
        $this->postJson('/api/devices/'.$slot['id'].'/release', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertStatus(422);

        $this->postJson('/api/claims', [
            'code' => 'OLD01',
            'role' => 'head',
            'cms_id' => 'cms-old',
            'local_device_id' => 1,
        ])->assertCreated();
        $this->postJson('/api/claims/OLD01/accept', [
            'organization_id' => $org['id'],
            'device_id' => $slot['id'],
        ], $this->bearer($token))->assertOk()->assertJsonPath('device.linked', true);

        $this->postJson('/api/devices/'.$slot['id'].'/release', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk()
            ->assertJsonPath('linked', false)
            ->assertJsonPath('name', 'Counter')
            ->assertJsonPath('playlist_id', $slot['playlist_id']);
        $this->getJson('/api/claims/OLD01')->assertOk()
            ->assertJsonPath('claimed', true)
            ->assertJsonPath('revoked', true);

        $this->postJson('/api/claims', [
            'code' => 'NEW01',
            'role' => 'head',
            'cms_id' => 'cms-new',
            'local_device_id' => 1,
        ])->assertCreated();
        $this->postJson('/api/claims/NEW01/accept', [
            'organization_id' => $org['id'],
            'device_id' => $slot['id'],
        ], $this->bearer($token))->assertOk()
            ->assertJsonPath('device.linked', true)
            ->assertJsonPath('device.name', 'Counter');
    }

    public function test_head_report_covers_the_branch_in_one_reply(): void
    {
        [$token, $org] = $this->operatorContext();
        $branch = $this->postJson('/api/branches', [
            'name' => 'Sea Park',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $other = $this->postJson('/api/branches', [
            'name' => 'Other',
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertCreated()->json();
        $head = $this->makeDevice($token, $org['id'], 'tvbox', $branch['id'], null, true);
        $screen = $this->makeDevice($token, $org['id'], 'tvbox2', $branch['id'], null, false);
        $foreign = $this->makeDevice($token, $org['id'], 'elsewhere', $other['id'], null, true);

        $this->postJson('/api/claims', [
            'code' => 'HEAD01',
            'role' => 'head',
            'cms_id' => 'cms-report',
            'local_device_id' => 1,
        ])->assertCreated();
        $this->postJson('/api/claims/HEAD01/accept', [
            'organization_id' => $org['id'],
            'device_id' => $head['id'],
        ], $this->bearer($token))->assertOk();
        $this->postJson('/api/claims', [
            'code' => 'SCRN02',
            'role' => 'screen',
            'cms_id' => 'cms-report',
            'local_device_id' => 2,
        ])->assertCreated();
        $this->postJson('/api/claims/SCRN02/accept', [
            'organization_id' => $org['id'],
            'device_id' => $screen['id'],
        ], $this->bearer($token))->assertOk();
        $this->postJson('/api/claims', [
            'code' => 'OTHR03',
            'role' => 'head',
            'cms_id' => 'cms-other',
            'local_device_id' => 1,
        ])->assertCreated();
        $this->postJson('/api/claims/OTHR03/accept', [
            'organization_id' => $org['id'],
            'device_id' => $foreign['id'],
        ], $this->bearer($token))->assertOk();

        $this->postJson('/api/claims/SCRN02/report', [
            'devices' => [],
        ])->assertStatus(422);

        $report = $this->postJson('/api/claims/HEAD01/report', [
            'devices' => [
                ['code' => 'HEAD01', 'playing' => true, 'item' => 'first.mp4', 'seen_seconds' => 3],
                ['code' => 'SCRN02', 'playing' => false, 'item' => null, 'seen_seconds' => 40],
                ['code' => 'OTHR03', 'playing' => true, 'item' => 'nope.mp4', 'seen_seconds' => 1],
                ['code' => 'MISSING', 'playing' => true, 'item' => 'gone.mp4', 'seen_seconds' => 1],
            ],
        ])->assertOk();
        $rows = collect($report->json('devices'))->keyBy('code');
        $this->assertEqualsCanonicalizing(['HEAD01', 'SCRN02'], $rows->keys()->all());
        $this->assertFalse($rows['HEAD01']['revoked']);
        $this->assertTrue($rows['HEAD01']['is_head']);
        $this->assertTrue($rows['HEAD01']['playback']['assigned']);
        $this->assertSame($head['playlist_id'], $rows['HEAD01']['playback']['playlist_id']);
        $this->assertGreaterThan(0, $rows['HEAD01']['playback']['generation']);
        $this->assertFalse($rows['SCRN02']['revoked']);
        $this->assertNotEmpty($rows['SCRN02']['playback']['generation']);

        $headRow = Device::query()->find($head['id']);
        $screenRow = Device::query()->find($screen['id']);
        $this->assertNotNull($headRow->last_seen_at);
        $this->assertTrue($headRow->reported_playing);
        $this->assertSame('first.mp4', $headRow->reported_item);
        $this->assertNotNull($screenRow->last_seen_at);
        $this->assertFalse($screenRow->reported_playing);
        $this->assertNull(Device::query()->find($foreign['id'])->last_seen_at);

        $seen = $screenRow->last_seen_at->copy();
        $this->travel(2)->seconds();
        $this->postJson('/api/devices/'.$screen['id'].'/release', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();
        $after = $this->postJson('/api/claims/HEAD01/report', [
            'devices' => [
                ['code' => 'HEAD01', 'playing' => true, 'item' => 'first.mp4', 'seen_seconds' => 2],
                ['code' => 'SCRN02', 'playing' => true, 'item' => 'still.mp4', 'seen_seconds' => 2],
            ],
        ])->assertOk();
        $revoked = collect($after->json('devices'))->firstWhere('code', 'SCRN02');
        $this->assertTrue($revoked['revoked']);
        $this->assertTrue(Device::query()->find($screen['id'])->last_seen_at->equalTo($seen));

        $this->postJson('/api/devices/'.$head['id'].'/release', [
            'organization_id' => $org['id'],
        ], $this->bearer($token))->assertOk();
        $this->postJson('/api/claims/HEAD01/report', [
            'devices' => [
                ['code' => 'HEAD01', 'playing' => true, 'item' => 'first.mp4', 'seen_seconds' => 1],
            ],
        ])->assertOk()
            ->assertJsonPath('devices.0.code', 'HEAD01')
            ->assertJsonPath('devices.0.revoked', true);
    }

    private function readyAsset(int $organizationId, string $name, string $filename = 'poster.jpg'): MediaAsset
    {
        $path = 'org/'.$organizationId.'/'.$filename;
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
