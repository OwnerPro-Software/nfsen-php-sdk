<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Facades;

use Illuminate\Support\Facades\Facade;
use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Responses\NfseResponse;
use SensitiveParameter;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 *
 * @method static NfseResponse emitir(DpsData|DpsDataArray $data)
 * @method static NfseResponse emitirDecisaoJudicial(DpsData|DpsDataArray $data)
 * @method static NfseResponse cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao)
 * @method static NfseResponse substituir(string $chave, DpsData|DpsDataArray $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, ?string $descricao = null)
 * @method static ConsultsNfse consultar()
 */
final class Nfsen extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NfsenClient::class;
    }

    public static function for(#[SensitiveParameter] string $pfxContent, #[SensitiveParameter] string $senha, string $prefeitura, ?NfseAmbiente $ambiente = null): NfsenClient
    {
        return NfsenClient::for($pfxContent, $senha, $prefeitura, $ambiente);
    }
}
