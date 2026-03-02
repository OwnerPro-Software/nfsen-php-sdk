<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\DTOs\Dps\Tomador\Tomador;
use Pulsar\NfseNacional\Enums\Dps\Valores\TipoDedRed;

final readonly class DocDedRed
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public TipoDedRed $tpDedRed,
        public string $dtEmiDoc,
        public string $vDedutivelRedutivel,
        public string $vDeducaoReducao,
        public ?string $chNFSe = null,
        public ?string $chNFe = null,
        public ?DocOutNFSe $NFSeMun = null,
        public ?DocNFNFS $NFNFS = null,
        public ?string $nDocFisc = null,
        public ?string $nDoc = null,
        public ?string $xDescOutDed = null,
        public ?Tomador $fornec = null,
    ) {
        self::validateChoice(
            ['chNFSe' => $chNFSe, 'chNFe' => $chNFe, 'NFSeMun' => $NFSeMun, 'NFNFS' => $NFNFS, 'nDocFisc' => $nDocFisc, 'nDoc' => $nDoc],
            expected: 1,
            message: 'DocDedRed requer exatamente um tipo de documento.',
        );
    }
}
