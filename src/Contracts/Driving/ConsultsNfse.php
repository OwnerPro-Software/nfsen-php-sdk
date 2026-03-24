<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\EventsResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;

interface ConsultsNfse
{
    public function nfse(string $chave): NfseResponse;

    public function dps(string $id): NfseResponse;

    public function danfse(string $chave): DanfseResponse;

    public function eventos(string $chave, TipoEvento|int $tipoEvento = TipoEvento::CancelamentoPorIniciativaPrestador, int $nSequencial = 1): EventsResponse;

    public function verificarDps(string $id): bool;
}
