<?php

namespace App\Domain\Kioku\Capture\Adapters;

use App\Domain\Kioku\Capture\CaptureAdapter;
use App\Domain\Kioku\Capture\Dto\CaptureCommand;
use App\Domain\Kioku\Capture\Dto\CapturedRaw;
use InvalidArgumentException;
use RuntimeException;

final class CaptureAdapterRegistry
{
    /**
     * @param  list<CaptureAdapter>  $adapters
     */
    public function __construct(
        private readonly array $adapters,
    ) {}

    public function resolve(CaptureCommand $command): CaptureAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($command)) {
                return $adapter;
            }
        }

        throw new RuntimeException("No CaptureAdapter supports kind [{$command->kind}].");
    }

    public function toCapturedRaw(CaptureCommand $command): CapturedRaw
    {
        try {
            return $this->resolve($command)->toCapturedRaw($command);
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }
}
