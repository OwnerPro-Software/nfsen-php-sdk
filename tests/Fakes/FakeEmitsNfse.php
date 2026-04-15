<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Tests\Fakes;

use OwnerPro\Nfsen\Contracts\Driving\EmitsNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Responses\NfseResponse;

final class FakeEmitsNfse implements EmitsNfse
{
    public int $emitirCalls = 0;

    public int $emitirDecisaoJudicialCalls = 0;

    public function __construct(
        private readonly NfseResponse $emitirResponse = new NfseResponse(
            sucesso: true,
            chave: 'CHAVE_EMIT',
            xml: '<nfse id="emit"/>',
        ),
        private readonly NfseResponse $decisaoResponse = new NfseResponse(
            sucesso: true,
            chave: 'CHAVE_DECISAO',
            xml: '<nfse id="decisao"/>',
        ),
    ) {}

    public function emitir(DpsData|array $data): NfseResponse
    {
        $this->emitirCalls++;

        return $this->emitirResponse;
    }

    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse
    {
        $this->emitirDecisaoJudicialCalls++;

        return $this->decisaoResponse;
    }
}
