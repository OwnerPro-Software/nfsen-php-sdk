<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use OwnerPro\Nfsen\Contracts\Driving\EmitsNfse;
use OwnerPro\Nfsen\Contracts\Driving\SubstitutesNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Dps\DTO\InfDPS\Subst;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Events\NfseSubstituted;
use OwnerPro\Nfsen\Pipeline\Concerns\DispatchesEvents;
use OwnerPro\Nfsen\Pipeline\Concerns\ValidatesChaveAcesso;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
final readonly class NfseSubstitutor implements SubstitutesNfse
{
    use DispatchesEvents;
    use ValidatesChaveAcesso;

    public function __construct(
        private EmitsNfse $emitter,
    ) {}

    /** @phpstan-param DpsData|DpsDataArray $dps */
    public function substituir(string $chave, DpsData|array $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, ?string $descricao = null): NfseResponse
    {
        $this->validateChaveAcesso($chave);

        if (is_string($codigoMotivo)) {
            $codigoMotivo = CodigoJustificativaSubstituicao::from($codigoMotivo);
        }

        if (is_array($dps)) {
            $dps = DpsData::fromArray($dps);
        }

        $dps = new DpsData(
            infDPS: $dps->infDPS,
            prest: $dps->prest,
            serv: $dps->serv,
            valores: $dps->valores,
            subst: new Subst(
                chSubstda: $chave,
                cMotivo: $codigoMotivo,
                xMotivo: $descricao,
            ),
            toma: $dps->toma,
            interm: $dps->interm,
            IBSCBS: $dps->IBSCBS,
        );

        $response = $this->emitter->emitir($dps);

        if ($response->sucesso) {
            /** @var string $chaveSubstituta */
            $chaveSubstituta = $response->chave;
            $this->dispatchEvent(new NfseSubstituted($chave, $chaveSubstituta));
        }

        return $response;
    }
}
