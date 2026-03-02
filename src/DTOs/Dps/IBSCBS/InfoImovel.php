<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoObra;

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
            ['cCIB' => $cCIB, 'end' => $end],
            expected: 1,
            message: 'InfoImovel requer exatamente um entre cCIB ou end.',
        );
    }

    /** @phpstan-param InfoImovelArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            inscImobFisc: $data['inscImobFisc'] ?? null,
            cCIB: $data['cCIB'] ?? null,
            end: isset($data['end']) ? EnderecoObra::fromArray($data['end']) : null,
        );
    }
}
