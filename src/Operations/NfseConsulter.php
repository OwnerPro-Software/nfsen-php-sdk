<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use OwnerPro\Nfsen\Contracts\Driven\ResolvesOperations;
use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
use OwnerPro\Nfsen\Contracts\Driving\ExecutesNfseRequests;
use OwnerPro\Nfsen\Enums\SituacaoCancelamento;
use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Enums\TipoEventoDistribuicao;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Pipeline\Concerns\ValidatesChaveAcesso;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\EventoConsultado;
use OwnerPro\Nfsen\Responses\EventsResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;

/**
 * @internal
 *
 * @phpstan-import-type MessageData from ProcessingMessage
 */
final readonly class NfseConsulter implements ConsultsNfse
{
    use ValidatesChaveAcesso;

    public function __construct(
        private ExecutesNfseRequests $client,
        private string $seFinBaseUrl,
        private string $adnBaseUrl,
        private ResolvesOperations $resolver,
        private string $codigoIbge,
        private DistributesNfse $distributor,
    ) {}

    public function nfse(string $chave): NfseResponse
    {
        $this->validateChaveAcesso($chave);
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'query_nfse', ['chave' => $chave]);

        return $this->client->executeAndDecompress($this->buildUrl($this->seFinBaseUrl, $path));
    }

    public function dps(string $id): NfseResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'query_dps', ['id' => $id]);

        // DpsGetResponse (SefinNacional-swagger.json) declara chaveAcesso
        // required no 200: sem ela, um "sucesso" com chave null encerraria a
        // reconciliação pós-timeout — que é o fluxo que dps() existe para
        // servir — sem identificador algum da nota. O 404 não é afetado:
        // executeRaw retorna antes da checagem.
        $response = $this->client->executeRaw($this->buildUrl($this->seFinBaseUrl, $path), ['chaveAcesso']);

        /**
         * @var array{
         *     erros?: list<MessageData>,
         *     erro?: MessageData|list<MessageData>,
         *     chaveAcesso?: string,
         *     idDps?: string,
         *     tipoAmbiente?: int,
         *     versaoAplicativo?: string,
         *     dataHoraProcessamento?: string,
         * } $result
         */
        $result = $response->json;

        $tipoAmbiente = $result['tipoAmbiente'] ?? null;
        $versaoAplicativo = $result['versaoAplicativo'] ?? null;
        $dataHoraProcessamento = $result['dataHoraProcessamento'] ?? null;

        if ($response->statusCode === 404) {
            return new NfseResponse(
                sucesso: false,
                erros: $this->notFoundErrors(
                    new ProcessingMessage(
                        mensagem: 'DPS não encontrada',
                        codigo: NfseResponse::DPS_NOT_FOUND,
                        descricao: 'A SEFIN respondeu 404: não existe DPS com o identificador informado.',
                    ),
                    $result,
                ),
                tipoAmbiente: $tipoAmbiente,
                versaoAplicativo: $versaoAplicativo,
                dataHoraProcessamento: $dataHoraProcessamento,
                raw: $result,
            );
        }

        if (ProcessingMessage::hasApiError($result)) {
            return new NfseResponse(
                sucesso: false,
                erros: ProcessingMessage::fromApiResult($result),
                tipoAmbiente: $tipoAmbiente,
                versaoAplicativo: $versaoAplicativo,
                dataHoraProcessamento: $dataHoraProcessamento,
                raw: $result,
            );
        }

        return new NfseResponse(
            sucesso: true,
            // executeRaw('chaveAcesso') garante string não-vazia neste ponto.
            chave: $result['chaveAcesso'] ?? null,
            idDps: $result['idDps'] ?? null,
            tipoAmbiente: $tipoAmbiente,
            versaoAplicativo: $versaoAplicativo,
            dataHoraProcessamento: $dataHoraProcessamento,
            raw: $result,
        );
    }

    public function danfse(string $chave): DanfseResponse
    {
        $this->validateChaveAcesso($chave);
        $baseUrl = $this->adnBaseUrl ?: $this->seFinBaseUrl;
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'query_danfse', ['chave' => $chave]);

        try {
            $pdf = $this->client->executeAndDownload($this->buildUrl($baseUrl, $path));

            if ($pdf === '') {
                return new DanfseResponse(
                    sucesso: false,
                    erros: [
                        new ProcessingMessage(
                            mensagem: 'Resposta vazia',
                            codigo: 'EMPTY_RESPONSE',
                            descricao: 'O servidor retornou uma resposta vazia para o DANFSe.',
                        ),
                        $this->apiSobrestadaWarning(),
                    ],
                );
            }

            return new DanfseResponse(sucesso: true, pdf: $pdf);
        } catch (HttpException $httpException) {
            return new DanfseResponse(
                sucesso: false,
                erros: [...$this->parseHttpError($httpException), $this->apiSobrestadaWarning()],
            );
        }
    }

    /**
     * Aviso anexado a toda falha de `danfse()`.
     *
     * Sem ele o consumidor recebe um 404 ou um corpo vazio e vai depurar rede,
     * certificado ou chave de acesso — quando a causa é a API ter sido desligada e o
     * documento agora ser gerado localmente por `danfse()->toPdf()`.
     */
    private function apiSobrestadaWarning(): ProcessingMessage
    {
        return new ProcessingMessage(
            mensagem: 'A API remota de DANFSe foi sobrestada',
            codigo: DanfseResponse::API_SOBRESTADA,
            descricao: 'A Nota Técnica nº 008, de 05/05/2026, suspendeu a API de geração do DANFSe ' // @pest-mutate-ignore ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — texto informativo em três partes; os testes asseguram os trechos que importam ('01/07/2026' e 'toPdf').
                .'em 01/07/2026. Gere o documento localmente a partir do XML da NFS-e: '
                .'`$client->danfse()->toPdf($xml)`.',
        );
    }

    public function eventos(string $chave, TipoEvento|int $tipoEvento = TipoEvento::Cancelamento, int $nSequencial = 1): EventsResponse
    {
        $this->validateChaveAcesso($chave);

        if (is_int($tipoEvento)) {
            $tipoEvento = TipoEvento::from($tipoEvento);
        }

        $path = $this->resolver->resolveOperation($this->codigoIbge, 'query_events', [
            'chave' => $chave,
            'tipoEvento' => $tipoEvento->value,
            'nSequencial' => $nSequencial,
        ]);

        $response = $this->client->executeRaw($this->buildUrl($this->seFinBaseUrl, $path), ['eventoXmlGZipB64'], ['eventos']);

        /**
         * @var array{
         *     erros?: list<MessageData>,
         *     erro?: MessageData|list<MessageData>,
         *     eventos?: mixed,
         *     eventoXmlGZipB64?: string,
         *     tipoAmbiente?: int,
         *     versaoAplicativo?: string,
         *     dataHoraProcessamento?: string,
         * } $result
         */
        $result = $response->json;

        $tipoAmbiente = $result['tipoAmbiente'] ?? null;
        $versaoAplicativo = $result['versaoAplicativo'] ?? null;
        $dataHoraProcessamento = $result['dataHoraProcessamento'] ?? null;

        if ($response->statusCode === 404) {
            return new EventsResponse(
                sucesso: false,
                erros: $this->notFoundErrors(
                    new ProcessingMessage(
                        mensagem: 'Evento não encontrado',
                        codigo: EventsResponse::EVENT_NOT_FOUND,
                        descricao: 'A SEFIN respondeu 404: não existe evento com os parâmetros informados.',
                    ),
                    $result,
                ),
                tipoAmbiente: $tipoAmbiente,
                versaoAplicativo: $versaoAplicativo,
                dataHoraProcessamento: $dataHoraProcessamento,
                raw: $result,
            );
        }

        if (ProcessingMessage::hasApiError($result)) {
            return new EventsResponse(
                sucesso: false,
                erros: ProcessingMessage::fromApiResult($result),
                tipoAmbiente: $tipoAmbiente,
                versaoAplicativo: $versaoAplicativo,
                dataHoraProcessamento: $dataHoraProcessamento,
                raw: $result,
            );
        }

        $eventos = $this->parseEventos($result);

        return new EventsResponse(
            sucesso: true,
            xml: $eventos[0]->xml,
            tipoAmbiente: $tipoAmbiente,
            versaoAplicativo: $versaoAplicativo,
            dataHoraProcessamento: $dataHoraProcessamento,
            raw: $result,
            eventos: $eventos,
        );
    }

    /**
     * O SefinNacional 1.6.0 devolve `eventos[]` com o XML em `arquivoXml`; o
     * `eventoXmlGZipB64` de topo é o que o swagger declara para esta rota, e é o que
     * as prefeituras de implementação própria (`storage/prefeituras.json`) podem
     * devolver. `executeRaw` já garante que um dos dois veio preenchido na forma
     * que se lê aqui: lista não-vazia, ou texto não-vazio.
     *
     * @param  array<string, mixed>  $result
     * @return non-empty-list<EventoConsultado>
     */
    private function parseEventos(array $result): array
    {
        $eventos = $result['eventos'] ?? null;

        if (is_array($eventos) && $eventos !== []) {
            return array_values(array_map(EventoConsultado::fromArray(...), $eventos));
        }

        return [EventoConsultado::fromArray(['arquivoXml' => $result['eventoXmlGZipB64'] ?? null])];
    }

    public function situacaoCancelamento(string $chave): SituacaoCancelamento
    {
        $events = $this->distributor->eventos($chave);

        if ($events->statusProcessamento === StatusDistribuicao::Rejeicao) {
            throw new NfseException('O ADN rejeitou a consulta de eventos da NFS-e: '.($events->erros[0]->descricao ?? 'motivo não informado.'));
        }

        $status = SituacaoCancelamento::SemPedido;
        $highestNsu = PHP_INT_MIN;

        foreach ($events->lote as $documento) {
            $fromEvent = $this->analysisStatusOf($documento->tipoEvento);
            $nsu = $documento->nsu ?? PHP_INT_MIN;

            // Vence o maior NSU: um pedido novo depois de um indeferimento reabre a
            // análise, e o ADN não promete ordem no lote.
            if ($fromEvent instanceof SituacaoCancelamento && $nsu >= $highestNsu) {
                $highestNsu = $nsu;
                $status = $fromEvent;
            }
        }

        return $status;
    }

    private function analysisStatusOf(?TipoEventoDistribuicao $tipoEvento): ?SituacaoCancelamento
    {
        return match ($tipoEvento) {
            TipoEventoDistribuicao::SolicitacaoCancelamentoAnaliseFiscal => SituacaoCancelamento::EmAnalise,
            TipoEventoDistribuicao::CancelamentoDeferidoAnaliseFiscal => SituacaoCancelamento::Deferido,
            TipoEventoDistribuicao::CancelamentoIndeferidoAnaliseFiscal => SituacaoCancelamento::Indeferido,
            default => null,
        };
    }

    public function verificarDps(string $id): bool
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'verify_dps', ['id' => $id]);

        // executeHead só retorna 200 ou 404; qualquer outro status lança lá.
        return $this->client->executeHead($this->buildUrl($this->seFinBaseUrl, $path)) === 200;
    }

    /**
     * Erro dedicado de ausência comprovada (HTTP 404) seguido dos erros
     * originais da SEFIN, quando o corpo do 404 os traz.
     *
     * @param  array{erros?: list<MessageData>, erro?: MessageData|list<MessageData>}  $result
     * @return list<ProcessingMessage>
     */
    private function notFoundErrors(ProcessingMessage $marker, array $result): array
    {
        return [$marker, ...ProcessingMessage::fromApiResult($result)];
    }

    /** @return list<ProcessingMessage> */
    private function parseHttpError(HttpException $e): array
    {
        $body = $e->getResponseBody();

        /** @var array{erros?: list<MessageData>, erro?: MessageData|list<MessageData>}|null $decoded */
        $decoded = json_decode($body, true);

        if (is_array($decoded) && ProcessingMessage::hasApiError($decoded)) {
            return ProcessingMessage::fromApiResult($decoded);
        }

        return [new ProcessingMessage(
            mensagem: 'HTTP error: '.$e->getCode(),
            codigo: (string) $e->getCode(),
            descricao: $body,
        )];
    }

    /**
     * O path nunca é vazio aqui: toda operação destas classes passa parâmetro, e
     * `resolveOperation()` rejeita template sem placeholder quando há parâmetros.
     * O caso de path vazio (emissão) é tratado em NfseRequestPipeline.
     */
    private function buildUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
