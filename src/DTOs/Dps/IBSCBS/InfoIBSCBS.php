<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

use Pulsar\NfseNacional\Enums\Dps\IBSCBS\FinNFSe;
use Pulsar\NfseNacional\Enums\Dps\IBSCBS\IndDest;
use Pulsar\NfseNacional\Enums\Dps\IBSCBS\IndFinal;
use Pulsar\NfseNacional\Enums\Dps\IBSCBS\TpEnteGov;
use Pulsar\NfseNacional\Enums\Dps\IBSCBS\TpOper;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

final readonly class InfoIBSCBS
{
    /** @param list<string>|null $refNFSe */
    public function __construct(
        public FinNFSe $finNFSe,
        public IndFinal $indFinal,
        public string $cIndOp,
        public IndDest $indDest,
        public InfoValoresIBSCBS $valores,
        public ?TpOper $tpOper = null,
        public ?array $refNFSe = null,
        public ?TpEnteGov $tpEnteGov = null,
        public ?InfoDest $dest = null,
        public ?InfoImovel $imovel = null,
    ) {
        if ($refNFSe !== null && $refNFSe === []) {
            throw new InvalidDpsArgument('refNFSe deve conter ao menos um item.');
        }
    }
}
