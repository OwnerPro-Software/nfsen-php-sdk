<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Operations;

use Pulsar\NfseNacional\Contracts\Ports\Driving\EmitsNfse;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Responses\MensagemProcessamento;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Pipeline\Concerns\DispatchesEvents;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Builders\Xml\DpsBuilder;

/** @phpstan-import-type DpsDataArray from DpsData */
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
        return $this->doEmitir($data, 'emitir', 'emitir_nfse', 'dpsXmlGZipB64');
    }

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse
    {
        return $this->doEmitir($data, 'emitir_decisao_judicial', 'emitir_decisao_judicial', 'xmlGZipB64');
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
             *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
             *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
             *     chaveAcesso?: string,
             *     nfseXmlGZipB64?: string,
             *     idDps?: string,
             *     idDPS?: string,
             *     alertas?: list<array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string}>,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->pipeline->signCompressSend($xml, 'infDPS', 'DPS', $payloadKey, $operationKey);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $erros = MensagemProcessamento::fromApiResult($result);
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
                    erros: [new MensagemProcessamento(descricao: 'Resposta da API não contém chaveAcesso.')],
                );
            }

            $this->dispatchEvent(new NfseEmitted($chave));

            return new NfseResponse(
                sucesso: true,
                chave: $chave,
                xml: GzipCompressor::decompressB64($result['nfseXmlGZipB64'] ?? null),
                idDps: $result['idDps'] ?? null,
                alertas: MensagemProcessamento::fromArrayList($result['alertas'] ?? []),
                tipoAmbiente: $result['tipoAmbiente'] ?? null,
                versaoAplicativo: $result['versaoAplicativo'] ?? null,
                dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
            );
        });
    }
}
