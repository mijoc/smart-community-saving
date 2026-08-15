<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LocationSeeder extends Seeder
{
    /**
     * Seed Rwanda administrative divisions:
     *   provinces -> districts -> sectors -> cells -> villages
     *
     * Source: database/data/rwanda_locations.sql (raw INSERT dump).
     * Placeholder rows like '-- Select X --' are skipped.
     */
    public function run(): void
    {
        // If already seeded, skip. (Fast & idempotent.)
        if (DB::table('villages')->count() > 0) {
            $this->command?->info('Locations already seeded — skipping.');
            return;
        }

        $sqlPath = database_path('data/rwanda_locations.sql');
        if (! is_file($sqlPath)) {
            $this->command?->warn("rwanda_locations.sql not found at {$sqlPath} — skipping location seed.");
            return;
        }

        $contents = file_get_contents($sqlPath);

        // --- Provinces (clean, hand-written; SQL dump had typos) ---
        $provinces = [
            ['code' => '1', 'name' => 'Kigali City'],
            ['code' => '2', 'name' => 'Southern Province'],
            ['code' => '3', 'name' => 'Western Province'],
            ['code' => '4', 'name' => 'Northern Province'],
            ['code' => '5', 'name' => 'Eastern Province'],
        ];
        DB::table('provinces')->insert(array_map(fn ($p) => $p + [
            'created_at' => now(), 'updated_at' => now(),
        ], $provinces));
        $this->command?->info('Seeded '.count($provinces).' provinces.');

        // --- Districts ---
        $rows = $this->extractRows($contents, 'districts');
        $this->bulkInsert('districts', $rows, function ($r) {
            // (districtcode, namedistrict, provincecode)
            return [
                'code'          => $r[0],
                'name'          => $r[1],
                'province_code' => $r[2],
            ];
        });

        // --- Sectors ---
        $rows = $this->extractRows($contents, 'sectors');
        $this->bulkInsert('sectors', $rows, function ($r) {
            // (sectorcode, namesector, districtcode)
            return [
                'code'          => $r[0],
                'name'          => $r[1],
                'district_code' => $r[2],
            ];
        });

        // --- Cells ---
        $rows = $this->extractRows($contents, 'cells');
        $this->bulkInsert('cells', $rows, function ($r) {
            // (codecell, nameCell, sectorcode)
            return [
                'code'        => $r[0],
                'name'        => $r[1],
                'sector_code' => $r[2],
            ];
        });

        // --- Villages (note: dump uses misspelled `vilages`) ---
        $rows = $this->extractRows($contents, 'vilages');
        $this->bulkInsert('villages', $rows, function ($r) {
            // (CodeVillage, VillageName, codecell)
            return [
                'code'      => $r[0],
                'name'      => $r[1],
                'cell_code' => $r[2],
            ];
        });
    }

    /**
     * Pull every (...) tuple from `INSERT INTO `<table>` (...) VALUES ...;` blocks.
     * Returns an array of arrays of column values (strings, unescaped).
     */
    protected function extractRows(string $sql, string $table): array
    {
        // Grab every INSERT block for this table, then collect all (...) tuples.
        $blockPattern  = "/INSERT\s+INTO\s+`?{$table}`?\s*\([^)]*\)\s*VALUES\s*(.*?);/is";
        $tuplePattern  = "/\(\s*('(?:[^'\\\\]|\\\\.)*'(?:\s*,\s*'(?:[^'\\\\]|\\\\.)*')*)\s*\)/";

        $rows = [];
        if (! preg_match_all($blockPattern, $sql, $blocks)) {
            return $rows;
        }

        foreach ($blocks[1] as $block) {
            if (! preg_match_all($tuplePattern, $block, $matches)) continue;
            foreach ($matches[1] as $values) {
                // Split on `,` between quoted strings.
                if (! preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $values, $cols)) continue;
                $cleaned = array_map(fn ($v) => stripcslashes($v), $cols[1]);
                // Skip placeholder/header rows.
                if (str_contains($cleaned[1] ?? '', '-- Select')) continue;
                $rows[] = $cleaned;
            }
        }
        return $rows;
    }

    protected function bulkInsert(string $table, array $rows, \Closure $map): void
    {
        if (! $rows) {
            $this->command?->warn("No rows extracted for {$table}.");
            return;
        }

        $now    = now();
        $chunks = array_chunk($rows, 500);
        $count  = 0;

        // Disable FK checks on SQLite while bulk-loading (cells/villages reference codes).
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') DB::statement('PRAGMA foreign_keys = OFF');

        foreach ($chunks as $chunk) {
            $payload = [];
            foreach ($chunk as $r) {
                $row = $map($r);
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                $payload[] = $row;
            }
            DB::table($table)->insertOrIgnore($payload);
            $count += count($payload);
        }

        if ($driver === 'sqlite') DB::statement('PRAGMA foreign_keys = ON');

        $this->command?->info("Seeded {$count} rows into {$table}.");
    }
}
