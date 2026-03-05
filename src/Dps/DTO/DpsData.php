<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO;

use Pulsar\NfseNacional\Dps\DTO\IBSCBS\IBSCBS;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\InfDPS;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\Subst;
use Pulsar\NfseNacional\Dps\DTO\Prest\Prest;
use Pulsar\NfseNacional\Dps\DTO\Serv\Serv;
use Pulsar\NfseNacional\Dps\DTO\Toma\Toma;
use Pulsar\NfseNacional\Dps\DTO\Valores\Valores;

/**
 * @phpstan-import-type InfDPSArray from InfDPS
 * @phpstan-import-type PrestArray from Prest
 * @phpstan-import-type ServArray from Serv
 * @phpstan-import-type ValoresArray from Valores
 * @phpstan-import-type SubstArray from Subst
 * @phpstan-import-type TomaArray from Toma
 * @phpstan-import-type IBSCBSArray from IBSCBS
 *
 * @phpstan-type DpsDataArray array{infDPS: InfDPSArray, prest: PrestArray, serv: ServArray, valores: ValoresArray, subst?: SubstArray, toma?: TomaArray, interm?: TomaArray, IBSCBS?: IBSCBSArray}
 */
final readonly class DpsData
{
    public function __construct(
        public InfDPS $infDPS,
        public Prest $prest,
        public Serv $serv,
        public Valores $valores,
        public ?Subst $subst = null,
        public ?Toma $toma = null,
        public ?Toma $interm = null,
        public ?IBSCBS $IBSCBS = null,
    ) {}

    /** @phpstan-param DpsDataArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            infDPS: InfDPS::fromArray($data['infDPS']),
            prest: Prest::fromArray($data['prest']),
            serv: Serv::fromArray($data['serv']),
            valores: Valores::fromArray($data['valores']),
            subst: isset($data['subst']) ? Subst::fromArray($data['subst']) : null,
            toma: isset($data['toma']) ? Toma::fromArray($data['toma'], path: 'infDPS/toma') : null,
            interm: isset($data['interm']) ? Toma::fromArray($data['interm'], path: 'infDPS/interm') : null,
            IBSCBS: isset($data['IBSCBS']) ? IBSCBS::fromArray($data['IBSCBS']) : null,
        );
    }
}
