<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Models\Memory;
use Illuminate\Support\Collection;

final class KiokuSearchService
{
    /**
     * @param  array{types?: list<string>, sources?: list<string>, tags?: list<string>, from?: string|null, to?: string|null, importance_min?: int|null}  $filters
     * @return Collection<int, Memory>
     */
    public function search(int $userId, ?string $query, array $filters = [], int $limit = 50): Collection
    {
        $builder = Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereNotIn('status', ['archived']);

        if ($query !== null && trim($query) !== '') {
            $terms = collect(preg_split('/[\s　]+/u', trim($query)) ?: [])
                ->map(fn ($term) => trim((string) $term))
                ->filter(fn ($term) => $term !== '')
                ->values();

            $builder->where(function ($q) use ($terms): void {
                foreach ($terms as $term) {
                    $like = '%'.addcslashes((string) $term, '%_\\').'%';
                    $q->orWhere(function ($inner) use ($like): void {
                        $inner->where('title', 'like', $like)
                            ->orWhere('summary', 'like', $like)
                            ->orWhere('raw_content', 'like', $like);
                    });
                }
            });
        }

        if (! empty($filters['types'])) {
            $builder->whereIn('memory_type', $filters['types']);
        }

        if (! empty($filters['sources'])) {
            $builder->whereIn('source_type', $filters['sources']);
        }

        if (! empty($filters['tags'])) {
            foreach ($filters['tags'] as $tag) {
                // Cross-db friendly (sqlite/MySQL). Prefer JSON_CONTAINS/GIN on Postgres later.
                $escaped = addcslashes((string) $tag, '%_\\');
                $builder->where('tags', 'like', '%"'.$escaped.'"%');
            }
        }

        if (! empty($filters['from'])) {
            $builder->whereDate('captured_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $builder->whereDate('captured_at', '<=', $filters['to']);
        }

        if (isset($filters['importance_min'])) {
            $builder->where('importance', '>=', (int) $filters['importance_min']);
        }

        return $builder
            ->orderByDesc('captured_at')
            ->limit($limit)
            ->get();
    }
}
