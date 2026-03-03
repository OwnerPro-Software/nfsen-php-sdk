<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Facades;

use Illuminate\Support\Facades\Facade;
use Pulsar\NfseNacional\Builders\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\NfseClient;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 *
 * @method static NfseResponse emitir(DpsData|DpsDataArray $data)
 * @method static NfseResponse emitirDecisaoJudicial(DpsData|DpsDataArray $data)
 * @method static NfseResponse cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao, int $nPedRegEvento = 1)
 * @method static NfseResponse substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = '', int $nPedRegEvento = 1)
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
