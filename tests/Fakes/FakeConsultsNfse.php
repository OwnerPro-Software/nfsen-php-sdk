<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Tests\Fakes;

use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\EventsResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;

final class FakeConsultsNfse implements ConsultsNfse
{
    public int $nfseCalls = 0;

    public int $dpsCalls = 0;

    public int $danfseCalls = 0;

    public int $eventosCalls = 0;

    public ?int $eventosNSequencialRecebido = null;

    public int $verificarDpsCalls = 0;

    public function __construct(
        private readonly NfseResponse $nfseResponse = new NfseResponse(
            sucesso: true,
            chave: 'CHAVE_CONSULT',
            xml: '<nfse id="consult"/>',
        ),
        private readonly NfseResponse $dpsResponse = new NfseResponse(sucesso: true, chave: 'K'),
        private readonly DanfseResponse $danfseResponse = new DanfseResponse(sucesso: true, pdf: '%PDF-official'),
        private readonly EventsResponse $eventosResponse = new EventsResponse(sucesso: true),
        private readonly bool $verificarDpsResponse = true,
    ) {}

    public function nfse(string $chave): NfseResponse
    {
        $this->nfseCalls++;

        return $this->nfseResponse;
    }

    public function dps(string $id): NfseResponse
    {
        $this->dpsCalls++;

        return $this->dpsResponse;
    }

    public function danfse(string $chave): DanfseResponse
    {
        $this->danfseCalls++;

        return $this->danfseResponse;
    }

    public function eventos(
        string $chave,
        TipoEvento|int $tipoEvento = TipoEvento::CancelamentoPorIniciativaPrestador,
        int $nSequencial = 1,
    ): EventsResponse {
        $this->eventosCalls++;
        $this->eventosNSequencialRecebido = $nSequencial;

        return $this->eventosResponse;
    }

    public function verificarDps(string $id): bool
    {
        $this->verificarDpsCalls++;

        return $this->verificarDpsResponse;
    }
}
