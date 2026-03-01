<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

final readonly class DescontoCondIncond
{
    public function __construct(
        public ?string $vDescIncond = null,
        public ?string $vDescCond = null,
    ) {}
}
