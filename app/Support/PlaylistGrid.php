<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PlaylistGrid
{
    /**
     * @param  Collection<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function timeline(Collection $items, int $row): array
    {
        $cells = $items->map(fn ($item) => self::cell($item))->filter(fn ($item) => $item->type !== '');
        if ($cells->isEmpty()) {
            return [];
        }

        $columns = $cells->pluck('column_index')->unique()->sort()->values();
        $played = false;
        $timeline = [];
        foreach ($columns as $column) {
            $inColumn = $cells->filter(fn ($item) => $item->column_index === (int) $column)->values();
            $slot = self::slot($inColumn);
            $cell = $inColumn->first(fn ($item) => $item->row_index === $row);
            if ($cell) {
                $timeline[] = [
                    'id' => $cell->id,
                    'media_asset_id' => $cell->media_asset_id,
                    'name' => $cell->name,
                    'type' => $cell->type,
                    'duration_ms' => $slot,
                    'file_duration_ms' => $cell->type === 'video' ? max(1, $cell->file_ms) : $slot,
                ];
                $played = true;
                continue;
            }
            $timeline[] = [
                'id' => 0,
                'media_asset_id' => 0,
                'name' => '',
                'type' => $played ? 'hold' : 'gap',
                'duration_ms' => $slot,
                'file_duration_ms' => 0,
            ];
        }

        $hasPlayable = collect($timeline)->contains(fn ($item) => in_array($item['type'], ['video', 'image'], true));

        return $hasPlayable ? $timeline : [];
    }

    /**
     * Every real file, at its own length. The installed boxes share this list
     * and pin one file. The row timeline is separate.
     *
     * @param  Collection<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function library(Collection $items): array
    {
        return $items->map(function ($item) {
            $cell = self::cell($item);
            if ($cell->type === '') {
                return null;
            }
            $duration = $cell->type === 'video' ? max(1, $cell->file_ms) : max(1000, $cell->duration_ms);

            return [
                'id' => $cell->id,
                'media_asset_id' => $cell->media_asset_id,
                'name' => $cell->name,
                'type' => $cell->type,
                'row_index' => $cell->row_index,
                'column_index' => $cell->column_index,
                'duration_ms' => $duration,
                'file_duration_ms' => $duration,
            ];
        })->filter()->values()->all();
    }

    /**
     * Every screen's timeline, tagged with its row, so the branch shares one clock.
     *
     * @param  Collection<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function wall(Collection $items): array
    {
        $wall = [];
        foreach (self::rows($items) as $row) {
            foreach (self::timeline($items, $row) as $item) {
                $item['row_index'] = $row;
                $wall[] = $item;
            }
        }

        return $wall;
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return array<int, int>
     */
    public static function rows(Collection $items): array
    {
        return $items->pluck('row_index')
            ->map(fn ($row) => (int) $row)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private static function cell(mixed $item): object
    {
        $type = (string) ($item->type ?? $item->mediaAsset?->type ?? '');
        $duration = (int) ($item->duration_ms ?? 0);
        $file = $type === 'video'
            ? (int) ($item->mediaAsset?->duration_ms ?: $duration)
            : $duration;

        return (object) [
            'id' => (int) $item->id,
            'media_asset_id' => (int) ($item->media_asset_id ?? 0),
            'name' => (string) ($item->name ?? $item->mediaAsset?->name ?? ''),
            'type' => $type,
            'row_index' => (int) ($item->row_index ?? 0),
            'column_index' => (int) ($item->column_index ?? 0),
            'duration_ms' => $duration,
            'file_ms' => max(0, $file),
        ];
    }

    private static function slot(Collection $column): int
    {
        $slot = 1000;
        foreach ($column as $item) {
            if ($item->type === 'video') {
                $slot = max($slot, max(1, $item->file_ms));
                continue;
            }
            $slot = max($slot, max(1000, $item->duration_ms));
        }

        return $slot;
    }
}
