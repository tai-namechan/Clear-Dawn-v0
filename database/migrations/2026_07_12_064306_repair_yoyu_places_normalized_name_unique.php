<?php

use App\Domain\Yoyu\Support\PlaceNameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair path for DBs that already ran the old PR-D1 unique on (user_id, name)
 * before that migration was rewritten to use normalized_name.
 *
 * Fresh installs that run the rewritten 2026_07_11_220001 are a no-op here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasIndex('yoyu_places', 'yoyu_places_user_id_name_unique')) {
            Schema::table('yoyu_places', function (Blueprint $table) {
                $table->dropUnique('yoyu_places_user_id_name_unique');
            });
        }

        if (! Schema::hasColumn('yoyu_places', 'normalized_name')) {
            Schema::table('yoyu_places', function (Blueprint $table) {
                $table->string('normalized_name', 255)->nullable()->after('name');
            });
        }

        $this->backfillAndDedupe();

        if (Schema::hasColumn('yoyu_places', 'normalized_name')) {
            Schema::table('yoyu_places', function (Blueprint $table) {
                $table->string('normalized_name', 255)->nullable(false)->change();
            });
        }

        if (! $this->hasIndex('yoyu_places', 'yoyu_places_user_id_normalized_name_unique')) {
            Schema::table('yoyu_places', function (Blueprint $table) {
                $table->unique(['user_id', 'normalized_name'], 'yoyu_places_user_id_normalized_name_unique');
            });
        }
    }

    public function down(): void
    {
        // Do not reintroduce the incorrect (user_id, name) unique.
        // Rolling back leaves normalized_name + correct unique in place from 220001,
        // or drops only this repair's additions when 220001 never added them.
        if (
            Schema::hasColumn('yoyu_places', 'normalized_name')
            && ! $this->hasIndex('yoyu_places', 'yoyu_places_user_id_normalized_name_unique')
        ) {
            return;
        }

        // Safe no-op for reverse when the rewritten 220001 owns the column/index.
    }

    private function backfillAndDedupe(): void
    {
        if (! Schema::hasColumn('yoyu_places', 'normalized_name')) {
            return;
        }

        $rows = DB::table('yoyu_places')
            ->orderBy('user_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'name', 'normalized_name']);

        /** @var array<string, true> $kept */
        $kept = [];
        $deleteIds = [];

        foreach ($rows as $row) {
            $key = is_string($row->normalized_name) && $row->normalized_name !== ''
                ? $row->normalized_name
                : PlaceNameNormalizer::normalize((string) $row->name);

            if ($key === '') {
                $deleteIds[] = $row->id;

                continue;
            }

            $composite = $row->user_id.'|'.$key;
            if (isset($kept[$composite])) {
                $deleteIds[] = $row->id;

                continue;
            }

            $kept[$composite] = true;
            DB::table('yoyu_places')->where('id', $row->id)->update([
                'normalized_name' => $key,
            ]);
        }

        if ($deleteIds !== []) {
            foreach (array_chunk($deleteIds, 500) as $chunk) {
                DB::table('yoyu_places')->whereIn('id', $chunk)->delete();
            }
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
