<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Facades;

use Illuminate\Support\Facades\Facade;
use Pulsar\NfseNacional\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\NfseClient;

/**
 * @method static NfseResponse emitir(DpsData $data)
 * @method static NfseResponse cancelar(string $chave, MotivoCancelamento $motivo, string $descricao)
 * @method static ConsultaBuilder consultar()
 */
final class NfseNacional extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NfseClient::class;
    }

    public static function for(string $pfxContent, string $senha, string $prefeitura): NfseClient
    {
        return NfseClient::for($pfxContent, $senha, $prefeitura);
    }
}
