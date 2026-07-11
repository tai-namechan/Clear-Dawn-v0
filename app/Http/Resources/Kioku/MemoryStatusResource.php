<?php

namespace App\Http\Resources\Kioku;

use App\Domain\Kioku\Models\Memory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Lightweight status map for polling. Never exposes raw_content or other fields.
 *
 * @property-read Collection<int, Memory> $resource
 */
class MemoryStatusResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @param  Collection<int, Memory>  $found
     * @param  list<string>  $requestedIds
     */
    public function __construct(
        Collection $found,
        private readonly array $requestedIds,
    ) {
        parent::__construct($found);
    }

    /**
     * @return array{data: array<string, string>, missing_ids: list<string>}
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, Memory> $found */
        $found = $this->resource;

        /** @var array<string, string> $data */
        $data = $found
            ->mapWithKeys(fn (Memory $memory): array => [(string) $memory->id => (string) $memory->status])
            ->all();

        $missingIds = array_values(array_filter(
            $this->requestedIds,
            fn (string $id): bool => ! array_key_exists($id, $data),
        ));

        return [
            'data' => $data,
            'missing_ids' => $missingIds,
        ];
    }
}
