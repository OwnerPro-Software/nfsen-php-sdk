<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;

final readonly class Tributacao
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public TributacaoMunicipal $tribMun,
        public ?TotTribValor $vTotTrib = null,
        public ?TotTribPercentual $pTotTrib = null,
        public ?string $indTotTrib = null,
        public ?string $pTotTribSN = null,
        public ?TributacaoFederal $tribFed = null,
    ) {
        self::validateChoice(
            ['vTotTrib' => $vTotTrib, 'pTotTrib' => $pTotTrib, 'indTotTrib' => $indTotTrib, 'pTotTribSN' => $pTotTribSN],
            expected: 1,
            message: 'totTrib requer exatamente um entre vTotTrib, pTotTrib, indTotTrib ou pTotTribSN.',
        );
    }
}
