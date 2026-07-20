<?php

namespace App\Console\Commands;

use App\Models\FoodLookupRequest;
use Illuminate\Console\Command;

class PruneExpiredFoodLookupsCommand extends Command
{
    protected $signature = 'meals:prune-expired-lookups';

    protected $description = 'Delete barcode/OCR food lookup requests past their expires_at';

    public function handle(): int
    {
        $deleted = FoodLookupRequest::query()
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Pruned {$deleted} expired food lookup request(s).");

        return self::SUCCESS;
    }
}
