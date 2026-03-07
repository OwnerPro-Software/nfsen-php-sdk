<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Operations;

use Pulsar\NfseNacional\Contracts\Driving\EmitsNfse;
use Pulsar\NfseNacional\Contracts\Driving\SubstitutesNfse;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\Subst;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Events\NfseSubstituted;
use Pulsar\NfseNacional\Pipeline\Concerns\DispatchesEvents;
use Pulsar\NfseNacional\Pipeline\Concerns\ParsesEventResponse;
use Pulsar\NfseNacional\Pipeline\Concerns\ValidatesChaveAcesso;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Responses\ProcessingMessage;
use Pulsar\NfseNacional\Responses\SubstituicaoResponse;
use Pulsar\NfseNacional\Xml\Builders\SubstitutionBuilder;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 * @phpstan-import-type MessageData from ProcessingMessage
 */
final readonly class NfseSubstitutor implements SubstitutesNfse
{
    use DispatchesEvents;
    use ParsesEventResponse;
    use ValidatesChaveAcesso;

    public function __construct(
        private EmitsNfse $emitter,
        private NfseRequestPipeline $pipeline,
        private SubstitutionBuilder $substitutionBuilder,
        private NfseAmbiente $ambiente,
    ) {}

    /** @phpstan-param DpsData|DpsDataArray $dps */
    public function substituir(string $chave, DpsData|array $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): SubstituicaoResponse
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
                xMotivo: $descricao !== '' ? $descricao : null,
            ),
            toma: $dps->toma,
            interm: $dps->interm,
            IBSCBS: $dps->IBSCBS,
        );

        $emissaoResponse = $this->emitter->emitir($dps);

        if (! $emissaoResponse->sucesso) {
            return new SubstituicaoResponse(
                sucesso: false,
                emissao: $emissaoResponse,
                evento: null,
            );
        }

        /** @var string $chaveSubstituta */
        $chaveSubstituta = $emissaoResponse->chave;

        $operacao = 'substituir';
        $this->dispatchEvent(new NfseRequested($operacao, ['chave' => $chave]));

        $eventoResponse = $this->withFailureEvent($operacao, function () use ($chave, $chaveSubstituta, $codigoMotivo, $descricao, $operacao): NfseResponse {
            $identity = $this->pipeline->extractAuthorIdentity('substituir');

            $xml = $this->substitutionBuilder->buildAndValidate(
                tpAmb: $this->ambiente->value,
                verAplic: '1.0',
                dhEvento: date('c'),
                cnpjAutor: $identity['cnpj'],
                cpfAutor: $identity['cpf'],
                chNFSe: $chave,
                codigoMotivo: $codigoMotivo,
                chSubstituta: $chaveSubstituta,
                descricao: $descricao,
            );

            /**
             * @var array{
             *     erros?: list<MessageData>,
             *     erro?: MessageData,
             *     eventoXmlGZipB64?: string,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->pipeline->signCompressSend(
                $xml, 'infPedReg', 'pedRegEvento', 'pedidoRegistroEventoXmlGZipB64', 'substitute_nfse', ['chave' => $chave]
            );

            return $this->parseEventResponse($result, $chave, $operacao, new NfseSubstituted($chave, $chaveSubstituta));
        });

        return new SubstituicaoResponse(
            sucesso: $eventoResponse->sucesso,
            emissao: $emissaoResponse,
            evento: $eventoResponse,
        );
    }
}
