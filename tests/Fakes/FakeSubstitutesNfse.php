<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Tests\Fakes;

use OwnerPro\Nfsen\Contracts\Driving\SubstitutesNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Responses\NfseResponse;

final class FakeSubstitutesNfse implements SubstitutesNfse
{
    public int $substituirCalls = 0;

    public function __construct(
        private readonly NfseResponse $response = new NfseResponse(
            sucesso: true,
            chave: 'CHAVE_SUBST',
            xml: '<nfse id="subst"/>',
        ),
    ) {}

    public function substituir(
        string $chave,
        DpsData|array $dps,
        CodigoJustificativaSubstituicao|string $codigoMotivo,
        ?string $descricao = null,
    ): NfseResponse {
        $this->substituirCalls++;

        return $this->response;
    }
}
