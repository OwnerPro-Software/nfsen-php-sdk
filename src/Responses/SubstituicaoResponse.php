<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Responses;

final readonly class SubstituicaoResponse
{
    public function __construct(
        public bool $sucesso,
        public NfseResponse $emissao,
        public ?NfseResponse $evento,
    ) {}
}
