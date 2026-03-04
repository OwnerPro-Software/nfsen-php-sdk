<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Facades;

use Illuminate\Support\Facades\Facade;
use Pulsar\NfseNacional\Contracts\Driving\ConsultsNfse;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\NfseClient;
use Pulsar\NfseNacional\Responses\NfseResponse;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 *
 * @method static NfseResponse emitir(DpsData|DpsDataArray $data)
 * @method static NfseResponse emitirDecisaoJudicial(DpsData|DpsDataArray $data)
 * @method static NfseResponse cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao, int $nPedRegEvento = 1)
 * @method static NfseResponse substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = '', int $nPedRegEvento = 1)
 * @method static ConsultsNfse consultar()
 */
final class NfseNacional extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NfseClient::class;
    }

    public static function for(string $pfxContent, string $senha, string $prefeitura, ?NfseAmbiente $ambiente = null): NfseClient
    {
        return NfseClient::for($pfxContent, $senha, $prefeitura, $ambiente);
    }
}
