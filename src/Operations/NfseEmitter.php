<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use OwnerPro\Nfsen\Contracts\Driving\EmitsNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Events\NfseEmitted;
use OwnerPro\Nfsen\Events\NfseRejected;
use OwnerPro\Nfsen\Events\NfseRequested;
use OwnerPro\Nfsen\Exceptions\NfseException;
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
        if (is_array($data)) {
            $data = DpsData::fromArray($data);
        }

        $operacao = 'emitir';
        $this->dispatchEvent(new NfseRequested($operacao, []));

        return $this->withFailureEvent($operacao, function () use ($data, $operacao): NfseResponse {
            $xml = $this->dpsBuilder->buildAndValidate($data);

            $this->pipeline->validateIdentityAgainst($data);

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
            $result = $this->pipeline->signCompressSend($xml, 'infDPS', 'DPS', 'dpsXmlGZipB64', 'emit_nfse');

            if (ProcessingMessage::hasApiError($result)) {
                $erros = ProcessingMessage::fromApiResult($result);
                $firstError = $erros[0] ?? null;
                $this->dispatchEvent(new NfseRejected(
                    $operacao,
                    $firstError->codigo ?? 'UNKNOWN',
                    $firstError->descricao ?? $firstError->mensagem ?? null,
                    $firstError->complemento ?? null,
                ));

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
                    // Sem chave, o idDps é o único identificador que resta para
                    // reconciliar via consultar()->dps(); aceita as duas grafias
                    // porque esta resposta não casa com nenhum dos dois envelopes.
                    idDps: $result['idDps'] ?? $result['idDPS'] ?? null,
                    erros: [new ProcessingMessage(descricao: 'Resposta da API não contém chaveAcesso.')],
                    tipoAmbiente: $result['tipoAmbiente'] ?? null,
                    versaoAplicativo: $result['versaoAplicativo'] ?? null,
                    dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
                );
            }

            $this->dispatchEvent(new NfseEmitted($chave));

            $alertas = ProcessingMessage::fromArrayList($result['alertas'] ?? []);
            try {
                $xml = GzipCompressor::decompressB64($result['nfseXmlGZipB64'] ?? null);
            } catch (NfseException $nfseException) {
                $xml = null;
                $alertas[] = ProcessingMessage::xmlIlegivel('consultar()->nfse($chave)', $nfseException->getMessage());
            }

            return new NfseResponse(
                sucesso: true,
                chave: $chave,
                xml: $xml,
                idDps: $result['idDps'] ?? null,
                alertas: $alertas,
                tipoAmbiente: $result['tipoAmbiente'] ?? null,
                versaoAplicativo: $result['versaoAplicativo'] ?? null,
                dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
            );
        });
    }

    /**
     * @phpstan-param DpsData|DpsDataArray $data
     *
     * @throws NfseException sempre: a operação não tem implementação possível a
     *                       partir de uma DPS. Lançada na entrada, antes de
     *                       qualquer evento ou requisição.
     */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse
    {
        throw new NfseException(
            'emitirDecisaoJudicial() não é suportado por este SDK. O endpoint decisao-judicial/nfse '
            .'recebe o documento NFS-e completo — NFSeBypassPostRequest.xmlGZipB64 é "Documento XML da NFSe" '
            .'(SefinNacional-swagger.json) —, não uma DPS: em TCInfNFSe a DPS é apenas o último filho, ao lado '
            .'de nNFSe, nDFSe, cStat, dhProc e dos valores já apurados, e ambGer admite somente 1-Prefeitura '
            .'ou 2-Sistema Nacional. Emitir por decisão judicial cabe a quem gera a NFS-e.'
        );
    }
}
