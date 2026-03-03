<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO;

use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\InfDPS;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\SubstituicaoData;
use Pulsar\NfseNacional\Dps\DTO\Prestador\Prestador;
use Pulsar\NfseNacional\Dps\DTO\Servico\Servico;
use Pulsar\NfseNacional\Dps\DTO\Tomador\Tomador;
use Pulsar\NfseNacional\Dps\DTO\Valores\Valores;

/**
 * @phpstan-import-type InfDPSArray from InfDPS
 * @phpstan-import-type PrestadorArray from Prestador
 * @phpstan-import-type ServicoArray from Servico
 * @phpstan-import-type ValoresArray from Valores
 * @phpstan-import-type SubstituicaoDataArray from SubstituicaoData
 * @phpstan-import-type TomadorArray from Tomador
 * @phpstan-import-type InfoIBSCBSArray from InfoIBSCBS
 *
 * @phpstan-type DpsDataArray array{infDPS: InfDPSArray, prest: PrestadorArray, serv: ServicoArray, valores: ValoresArray, subst?: SubstituicaoDataArray, toma?: TomadorArray, interm?: TomadorArray, IBSCBS?: InfoIBSCBSArray}
 */
final readonly class DpsData
{
    public function __construct(
        public InfDPS $infDPS,
        public Prestador $prest,
        public Servico $serv,
        public Valores $valores,
        public ?SubstituicaoData $subst = null,
        public ?Tomador $toma = null,
        public ?Tomador $interm = null,
        public ?InfoIBSCBS $IBSCBS = null,
    ) {}

    /** @phpstan-param DpsDataArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            infDPS: InfDPS::fromArray($data['infDPS']),
            prest: Prestador::fromArray($data['prest']),
            serv: Servico::fromArray($data['serv']),
            valores: Valores::fromArray($data['valores']),
            subst: isset($data['subst']) ? SubstituicaoData::fromArray($data['subst']) : null,
            toma: isset($data['toma']) ? Tomador::fromArray($data['toma']) : null,
            interm: isset($data['interm']) ? Tomador::fromArray($data['interm']) : null,
            IBSCBS: isset($data['IBSCBS']) ? InfoIBSCBS::fromArray($data['IBSCBS']) : null,
        );
    }
}
