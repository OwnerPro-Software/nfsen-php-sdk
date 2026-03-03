<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

use Pulsar\NfseNacional\Enums\TipoEvento;
use Pulsar\NfseNacional\Responses\DanfseResponse;
use Pulsar\NfseNacional\Responses\EventosResponse;
use Pulsar\NfseNacional\Responses\NfseResponse;

interface ConsultsNfse
{
    public function nfse(string $chave): NfseResponse;

    public function dps(string $id): NfseResponse;

    public function danfse(string $chave): DanfseResponse;

    public function eventos(string $chave, TipoEvento|int $tipoEvento = TipoEvento::CancelamentoPorIniciativaPrestador, int $nSequencial = 1): EventosResponse;

    public function verificarDps(string $id): bool;
}
