<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driven;

interface ExtractsAuthorIdentity
{
    /** @return array{cnpj: ?string, cpf: ?string} */
    public function extract(): array;
}
