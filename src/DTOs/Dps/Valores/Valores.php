<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

final readonly class Valores
{
    public function __construct(
        public ValorServicoPrestado $vServPrest,
        public Tributacao $trib,
        public ?DescontoCondIncond $vDescCondIncond = null,
        public ?InfoDedRed $vDedRed = null,
    ) {}
}
