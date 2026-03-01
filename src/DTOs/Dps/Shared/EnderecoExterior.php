<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Shared;

final readonly class EnderecoExterior
{
    public function __construct(
        public string $cPais,
        public string $cEndPost,
        public string $xCidade,
        public string $xEstProvReg,
    ) {}
}
