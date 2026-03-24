<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use OwnerPro\Nfsen\Contracts\Driving\EmitsNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Events\NfseEmitted;
use OwnerPro\Nfsen\Events\NfseRejected;
use OwnerPro\Nfsen\Events\NfseRequested;
use OwnerPro\Nfsen\Pipeline\Concerns\DispatchesEvents;
use OwnerPro\Nfsen\Pipeline\NfseRequestPipeline;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;
use OwnerPro\Nfsen\Support\GzipCompressor;
use OwnerPro\Nfsen\Xml\DpsBuilder;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 * @phpstan-import-type MessageData from ProcessingMessage
 */
final readonly class NfseEmitter implements EmitsNfse
{
    use DispatchesEvents;

    public function __construct(
        private NfseRequestPipeline $pipeline,
        private DpsBuilder $dpsBuilder,
    ) {}

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitir(DpsData|array $data): NfseResponse
    {
        return $this->doEmitir($data, 'emitir', 'emit_nfse', 'dpsXmlGZipB64');
    }

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse
    {
        return $this->doEmitir($data, 'emitir_decisao_judicial', 'emit_court_order', 'xmlGZipB64');
    }

    /** @phpstan-param DpsData|DpsDataArray $data */
    private function doEmitir(DpsData|array $data, string $operacao, string $operationKey, string $payloadKey): NfseResponse
    {
        if (is_array($data)) {
            $data = DpsData::fromArray($data);
        }

        $this->dispatchEvent(new NfseRequested($operacao, []));

        return $this->withFailureEvent($operacao, function () use ($data, $operacao, $operationKey, $payloadKey): NfseResponse {
            $xml = $this->dpsBuilder->buildAndValidate($data);

            /**
             * @var array{
             *     erros?: list<MessageData>,
             *     erro?: MessageData,
             *     chaveAcesso?: string,
             *     nfseXmlGZipB64?: string,
             *     idDps?: string,
             *     idDPS?: string,
             *     alertas?: list<MessageData>,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->pipeline->signCompressSend($xml, 'infDPS', 'DPS', $payloadKey, $operationKey);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $erros = ProcessingMessage::fromApiResult($result);
                $codigo = $erros[0]->codigo ?? 'UNKNOWN';
                $this->dispatchEvent(new NfseRejected($operacao, $codigo));

                return new NfseResponse(
                    sucesso: false,
                    idDps: $result['idDPS'] ?? $result['idDps'] ?? null,
                    erros: $erros,
                    tipoAmbiente: $result['tipoAmbiente'] ?? null,
                    versaoAplicativo: $result['versaoAplicativo'] ?? null,
                    dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
                );
            }

            $chave = $result['chaveAcesso'] ?? null;

            if ($chave === null) {
                $this->dispatchEvent(new NfseRejected($operacao, 'SEM_CHAVE'));

                return new NfseResponse(
                    sucesso: false,
                    erros: [new ProcessingMessage(descricao: 'Resposta da API não contém chaveAcesso.')],
                );
            }

            $this->dispatchEvent(new NfseEmitted($chave));

            return new NfseResponse(
                sucesso: true,
                chave: $chave,
                xml: GzipCompressor::decompressB64($result['nfseXmlGZipB64'] ?? null),
                idDps: $result['idDps'] ?? null,
                alertas: ProcessingMessage::fromArrayList($result['alertas'] ?? []),
                tipoAmbiente: $result['tipoAmbiente'] ?? null,
                versaoAplicativo: $result['versaoAplicativo'] ?? null,
                dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
            );
        });
    }
}
