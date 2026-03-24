<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

interface ExtractsAuthorIdentity
{
    /** @return array{cnpj: ?string, cpf: ?string} */
    public function extract(): array;
}
