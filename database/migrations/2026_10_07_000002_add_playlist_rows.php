<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playlist_items', function (Blueprint $table) {
            $table->unsignedInteger('row_index')->default(0)->after('position');
            $table->unsignedInteger('column_index')->default(0)->after('row_index');
        });
        Schema::table('publication_items', function (Blueprint $table) {
            $table->unsignedInteger('row_index')->default(0)->after('position');
            $table->unsignedInteger('column_index')->default(0)->after('row_index');
        });
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedInteger('screen_row')->nullable()->after('media_asset_id');
        });

        $playlistIds = DB::table('playlist_items')->distinct()->pluck('playlist_id')
            ->merge(DB::table('publications')->distinct()->pluck('playlist_id'))
            ->unique();

        foreach ($playlistIds as $playlistId) {
            $devices = DB::table('devices')->where('playlist_id', $playlistId)->get();
            $picked = $devices->contains(fn ($device) => $device->media_asset_id !== null);
            $asRows = $devices->isEmpty() || $picked;

            $this->place('playlist_items', 'playlist_id', (int) $playlistId, $asRows);
            $publications = DB::table('publications')->where('playlist_id', $playlistId)->pluck('id');
            foreach ($publications as $publicationId) {
                $this->place('publication_items', 'publication_id', (int) $publicationId, $asRows);
            }

            foreach ($devices as $device) {
                $row = 0;
                if ($asRows && $device->media_asset_id) {
                    $row = DB::table('playlist_items')
                        ->where('playlist_id', $playlistId)
                        ->where('media_asset_id', $device->media_asset_id)
                        ->orderBy('row_index')
                        ->value('row_index');
                    if ($row === null) {
                        $latest = DB::table('publications')
                            ->where('playlist_id', $playlistId)
                            ->orderByDesc('generation')
                            ->value('id');
                        $row = $latest
                            ? DB::table('publication_items')
                                ->where('publication_id', $latest)
                                ->where('media_asset_id', $device->media_asset_id)
                                ->value('row_index')
                            : null;
                    }
                    $row = (int) ($row ?? 0);
                }
                DB::table('devices')->where('id', $device->id)->update(['screen_row' => $row]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('screen_row');
        });
        Schema::table('publication_items', function (Blueprint $table) {
            $table->dropColumn(['row_index', 'column_index']);
        });
        Schema::table('playlist_items', function (Blueprint $table) {
            $table->dropColumn(['row_index', 'column_index']);
        });
    }

    private function place(string $table, string $ownerColumn, int $ownerId, bool $asRows): void
    {
        $ids = DB::table($table)
            ->where($ownerColumn, $ownerId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');
        foreach ($ids as $index => $id) {
            DB::table($table)->where('id', $id)->update([
                'row_index' => $asRows ? $index : 0,
                'column_index' => $asRows ? 0 : $index,
                'position' => ($asRows ? $index * 1000 : $index),
            ]);
        }
    }
};
