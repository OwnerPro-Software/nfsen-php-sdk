<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoObra;

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
}
