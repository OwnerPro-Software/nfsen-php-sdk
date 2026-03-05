<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Serv;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type EndObraArray from EndObra
 *
 * @phpstan-type ObraArray array{inscImobFisc?: string, cObra?: string, cCIB?: string, end?: EndObraArray}
 */
final readonly class Obra
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public ?string $inscImobFisc = null,
        public ?string $cObra = null,
        public ?string $cCIB = null,
        public ?EndObra $end = null,
    ) {
        self::validateChoice(
            ['código da obra (cObra)' => $cObra, 'código CIB (cCIB)' => $cCIB, 'endereço (end)' => $end],
            expected: 1,
            path: 'infDPS/serv/obra',
        );
    }

    /** @phpstan-param ObraArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            inscImobFisc: $data['inscImobFisc'] ?? null,
            cObra: $data['cObra'] ?? null,
            cCIB: $data['cCIB'] ?? null,
            end: isset($data['end']) ? EndObra::fromArray($data['end'], path: 'infDPS/serv/obra/end') : null,
        );
    }
}
