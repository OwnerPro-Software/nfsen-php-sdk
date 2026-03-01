<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

final readonly class EnderecoExteriorObra
{
    public function __construct(
        public string $cEndPost,
        public string $xCidade,
        public string $xEstProvReg,
    ) {}
}
