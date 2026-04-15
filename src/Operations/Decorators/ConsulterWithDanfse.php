<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations\Decorators;

use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Operations\Decorators\Concerns\AttachesDanfsePdf;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\EventsResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;

final readonly class ConsulterWithDanfse implements ConsultsNfse
{
    use AttachesDanfsePdf;

    public function __construct(
        private ConsultsNfse $inner,
        private RendersDanfse $renderer,
    ) {}

    public function nfse(string $chave): NfseResponse
    {
        return $this->attachPdf($this->inner->nfse($chave));
    }

    // dps() consulta o DPS (documento pré-autorização) — não é uma NFS-e autorizada,
    // logo não há PDF a anexar. Idem danfse() que retorna o PDF oficial do ADN.
    public function dps(string $id): NfseResponse
    {
        return $this->inner->dps($id);
    }

    public function danfse(string $chave): DanfseResponse
    {
        return $this->inner->danfse($chave);
    }

    public function eventos(
        string $chave,
        TipoEvento|int $tipoEvento = TipoEvento::CancelamentoPorIniciativaPrestador,
        int $nSequencial = 1,
    ): EventsResponse {
        return $this->inner->eventos($chave, $tipoEvento, $nSequencial);
    }

    public function verificarDps(string $id): bool
    {
        return $this->inner->verificarDps($id);
    }
}
