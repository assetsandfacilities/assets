<?php

namespace App\Support;

use App\Models\FacilityItem;
use Illuminate\Http\Request;

/**
 * Shared translator between the reservation form's checkbox + quantity inputs
 * and what actually gets stored.
 *
 * Two things are written for every reservation / proposal:
 *   1. a structured array (JSON column) -> used by the FMO detail screens so
 *      quantities can be displayed properly;
 *   2. a plain readable summary string -> written to the pre-existing
 *      equipment_needed / resources_needed columns so every old screen,
 *      export and notification keeps working untouched.
 */
class FacilityRequirements
{
    /**
     * Expected request shape:
     *   requirements[<facility_item_id>][selected] = 1
     *   requirements[<facility_item_id>][quantity] = 5
     *   requirements_other = 1
     *   requirements_other_note = "free text"
     */
    public static function fromRequest(Request $request): array
    {
        $raw = $request->input('requirements', []);
        $selectedIds = [];
        $quantities = [];

        if (is_array($raw)) {
            foreach ($raw as $itemId => $payload) {
                if (!is_array($payload) || empty($payload['selected'])) {
                    continue;
                }
                $id = (int) $itemId;
                if ($id <= 0) {
                    continue;
                }
                $selectedIds[] = $id;
                $quantity = (int) ($payload['quantity'] ?? 1);
                $quantities[$id] = max(1, min($quantity, 100000));
            }
        }

        $lines = [];
        if ($selectedIds) {
            $catalog = FacilityItem::whereIn('id', $selectedIds)->ordered()->get();
            foreach ($catalog as $item) {
                $lines[] = [
                    'facility_item_id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'unit' => $item->unit,
                    'quantity' => $item->allows_quantity ? ($quantities[$item->id] ?? 1) : null,
                ];
            }
        }

        $otherNote = trim((string) $request->input('requirements_other_note', ''));
        $otherChecked = $request->boolean('requirements_other');
        if (!$otherChecked) {
            $otherNote = '';
        }

        return [
            'lines' => $lines,
            'other_note' => $otherNote !== '' ? $otherNote : null,
            'summary' => self::summarize($lines, $otherNote !== '' ? $otherNote : null),
        ];
    }

    /**
     * Human-readable one-liner kept in the legacy string columns, e.g.
     * "Table (5), Chairs (100), ITSO Services (2), Others: 2 standing fans".
     */
    public static function summarize(array $lines, ?string $otherNote = null): string
    {
        $parts = [];
        foreach ($lines as $line) {
            $name = $line['name'] ?? '';
            if ($name === '') {
                continue;
            }
            $quantity = $line['quantity'] ?? null;
            $parts[] = $quantity ? $name . ' (' . $quantity . ')' : $name;
        }
        if ($otherNote) {
            $parts[] = 'Others: ' . $otherNote;
        }

        return implode(', ', $parts);
    }

    /**
     * Reads a stored JSON payload back into an array. Older records saved
     * before this update have no JSON at all -- for those the legacy comma
     * separated string is split so the detail screens still show something
     * sensible instead of an empty table.
     */
    public static function decode(?string $json, ?string $legacySummary = null): array
    {
        if ($json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_array'));
            }
        }

        $legacySummary = trim((string) $legacySummary);
        if ($legacySummary === '') {
            return [];
        }

        return array_values(array_map(
            fn ($name) => ['facility_item_id' => null, 'name' => $name, 'type' => null, 'unit' => null, 'quantity' => null],
            array_filter(array_map('trim', explode(',', $legacySummary)))
        ));
    }
}
