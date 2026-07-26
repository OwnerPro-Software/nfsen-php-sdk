<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Tests\Fakes;

use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Responses\DistribuicaoResponse;

final class FakeDistributesNfse implements DistributesNfse
{
    public int $eventosCalls = 0;

    public ?string $chaveRecebida = null;

    public function __construct(
        private readonly DistribuicaoResponse $eventosResponse = new DistribuicaoResponse(
            sucesso: false,
            statusProcessamento: StatusDistribuicao::NenhumDocumentoLocalizado,
            lote: [],
            alertas: [],
            erros: [],
            tipoAmbiente: 2,
            versaoAplicativo: '1.0.0',
            dataHoraProcessamento: null,
        ),
    ) {}

    public function documentos(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse
    {
        return $this->eventosResponse;
    }

    public function documento(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse
    {
        return $this->eventosResponse;
    }

    public function eventos(string $chave): DistribuicaoResponse
    {
        $this->eventosCalls++;
        $this->chaveRecebida = $chave;

        return $this->eventosResponse;
    }
}
