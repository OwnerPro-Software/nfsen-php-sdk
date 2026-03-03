<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

/**
 * @phpstan-type DescontoCondIncondArray array{vDescIncond?: string, vDescCond?: string}
 */
final readonly class DescontoCondIncond
{
    public function __construct(
        public ?string $vDescIncond = null,
        public ?string $vDescCond = null,
    ) {}

    /** @phpstan-param DescontoCondIncondArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
