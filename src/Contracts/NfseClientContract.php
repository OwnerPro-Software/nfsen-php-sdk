<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts;

use Pulsar\NfseNacional\DTOs\NfseResponse;

interface NfseClientContract
{
    public function executeGet(string $url): NfseResponse;

    /**
     * Retorna JSON cru da API.
     *
     * @return array{
     *     erros?: list<array{descricao?: string, codigo?: string}>,
     *     erro?: string,
     *     danfseUrl?: string,
     *     eventos?: array<int, array<string, mixed>>,
     * }
     */
    public function executeGetRaw(string $url): array;
}
