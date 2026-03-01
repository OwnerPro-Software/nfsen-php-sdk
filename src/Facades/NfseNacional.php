<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Facades;

use Illuminate\Support\Facades\Facade;
use Pulsar\NfseNacional\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\DTOs\Dps\DpsData;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\NfseClient;

/**
 * @method static NfseResponse emitir(DpsData $data)
 * @method static NfseResponse cancelar(string $chave, CodigoJustificativaCancelamento $codigoMotivo, string $descricao, int $nPedRegEvento = 1)
 * @method static NfseResponse substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao $codigoMotivo, string $descricao = '', int $nPedRegEvento = 1)
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
