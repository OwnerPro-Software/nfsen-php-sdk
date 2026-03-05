<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\DTO\Serv\EndObra;

/**
 * @phpstan-import-type EndObraArray from EndObra
 *
 * @phpstan-type ImovelArray array{inscImobFisc?: string, cCIB?: string, end?: EndObraArray}
 */
final readonly class Imovel
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public ?string $inscImobFisc = null,
        public ?string $cCIB = null,
        public ?EndObra $end = null,
    ) {
        self::validateChoice(
            ['código CIB (cCIB)' => $cCIB, 'endereço (end)' => $end],
            expected: 1,
            path: 'infDPS/IBSCBS/imovel',
        );
    }

    /** @phpstan-param ImovelArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            inscImobFisc: $data['inscImobFisc'] ?? null,
            cCIB: $data['cCIB'] ?? null,
            end: isset($data['end']) ? EndObra::fromArray($data['end'], path: 'infDPS/IBSCBS/imovel/end') : null,
        );
    }
}
