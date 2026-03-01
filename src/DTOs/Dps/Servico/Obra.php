<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;

final readonly class Obra
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public ?string $inscImobFisc = null,
        public ?string $cObra = null,
        public ?string $cCIB = null,
        public ?EnderecoObra $end = null,
    ) {
        self::validateChoice(
            ['cObra' => $cObra, 'cCIB' => $cCIB, 'end' => $end],
            expected: 1,
            message: 'Obra requer exatamente um entre cObra, cCIB ou end.',
        );
    }
}
