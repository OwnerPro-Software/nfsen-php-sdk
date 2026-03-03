<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

/**
 * @phpstan-import-type ValorServicoPrestadoArray from ValorServicoPrestado
 * @phpstan-import-type TributacaoArray from Tributacao
 * @phpstan-import-type DescontoCondIncondArray from DescontoCondIncond
 * @phpstan-import-type InfoDedRedArray from InfoDedRed
 *
 * @phpstan-type ValoresArray array{vServPrest: ValorServicoPrestadoArray, trib: TributacaoArray, vDescCondIncond?: DescontoCondIncondArray, vDedRed?: InfoDedRedArray}
 */
final readonly class Valores
{
    public function __construct(
        public ValorServicoPrestado $vServPrest,
        public Tributacao $trib,
        public ?DescontoCondIncond $vDescCondIncond = null,
        public ?InfoDedRed $vDedRed = null,
    ) {}

    /** @phpstan-param ValoresArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            vServPrest: ValorServicoPrestado::fromArray($data['vServPrest']),
            trib: Tributacao::fromArray($data['trib']),
            vDescCondIncond: isset($data['vDescCondIncond']) ? DescontoCondIncond::fromArray($data['vDescCondIncond']) : null,
            vDedRed: isset($data['vDedRed']) ? InfoDedRed::fromArray($data['vDedRed']) : null,
        );
    }
}
