<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

interface ResolvesOperations
{
    /** @param array<string, int|string> $params */
    public function resolveOperation(string $codigoIbge, string $operacao, array $params = []): string;
}
