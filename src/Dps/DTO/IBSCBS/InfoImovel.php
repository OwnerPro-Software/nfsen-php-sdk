<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoObra;

/**
 * @phpstan-import-type EnderecoObraArray from EnderecoObra
 *
 * @phpstan-type InfoImovelArray array{inscImobFisc?: string, cCIB?: string, end?: EnderecoObraArray}
 */
final readonly class InfoImovel
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public ?string $inscImobFisc = null,
        public ?string $cCIB = null,
        public ?EnderecoObra $end = null,
    ) {
        self::validateChoice(
            ['código CIB (cCIB)' => $cCIB, 'endereço (end)' => $end],
            expected: 1,
            path: 'infDPS/IBSCBS/imovel',
        );
    }

    /** @phpstan-param InfoImovelArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            inscImobFisc: $data['inscImobFisc'] ?? null,
            cCIB: $data['cCIB'] ?? null,
            end: isset($data['end']) ? EnderecoObra::fromArray($data['end'], path: 'infDPS/IBSCBS/imovel/end') : null,
        );
    }
}
