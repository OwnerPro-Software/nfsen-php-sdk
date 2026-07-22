<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

use OwnerPro\Nfsen\Enums\StatusDistribuicao;

/**
 * @api
 */
final readonly class DistribuicaoResponse
{
    /**
     * @param  list<DocumentoFiscal>  $lote
     * @param  list<ProcessingMessage>  $alertas
     * @param  list<ProcessingMessage>  $erros
     */
    public function __construct(
        public bool $sucesso,
        public StatusDistribuicao $statusProcessamento,
        public array $lote,
        public array $alertas,
        public array $erros,
        public ?int $tipoAmbiente,
        public ?string $versaoAplicativo,
        public ?string $dataHoraProcessamento,
    ) {}

    /** @param array<string, mixed> $result */
    public static function fromApiResult(array $result): self
    {
        // O swagger declara StatusProcessamento como enum de string; valor de
        // outro tipo é a mesma resposta fora do contrato que o branch
        // INVALID_RESPONSE existe para nomear — não um TypeError no tryFrom.
        $statusBruto = $result['StatusProcessamento'] ?? null;
        $statusRaw = is_string($statusBruto) ? $statusBruto : ''; // @pest-mutate-ignore EmptyStringToNotEmpty — any non-enum string produces null from tryFrom
        $status = StatusDistribuicao::tryFrom($statusRaw);

        if ($status === null) {
            $rawKeys = implode(', ', array_keys($result));
            $rawJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return new self(
                sucesso: false,
                statusProcessamento: StatusDistribuicao::Rejeicao,
                lote: [],
                alertas: [],
                erros: [new ProcessingMessage(
                    mensagem: 'Resposta inválida da API',
                    codigo: 'INVALID_RESPONSE',
                    descricao: sprintf('Campo StatusProcessamento ausente ou inválido. Keys: [%s]', $rawKeys),
                    complemento: $rawJson !== false ? $rawJson : null, // @pest-mutate-ignore FalseToTrue — json_encode on a plain array never returns false
                )],
                tipoAmbiente: null,
                versaoAplicativo: null,
                dataHoraProcessamento: null,
            );
        }

        // O swagger declara `LoteDFe` como array de `DistribuicaoNSU`, e
        // `Alertas`/`Erros` como arrays de mensagem; estas guardas são
        // tolerância para a resposta que não o seguir, no mesmo espírito de
        // DocumentoFiscal::fromArray(). Item escalar não traz nsu nem chave:
        // não há o que preservar dele, e passá-lo adiante custaria a página
        // inteira num TypeError.
        $loteBruto = $result['LoteDFe'] ?? [];
        /** @var list<array<string, mixed>> $loteDFe */
        $loteDFe = is_array($loteBruto) ? array_values(array_filter($loteBruto, is_array(...))) : [];

        $alertasBruto = $result['Alertas'] ?? [];
        /** @var list<array{mensagem?: string, Mensagem?: string, codigo?: string, Codigo?: string, descricao?: string, Descricao?: string, complemento?: string, Complemento?: string, parametros?: list<string>, Parametros?: list<string>}> $alertas */
        $alertas = is_array($alertasBruto) ? array_values(array_filter($alertasBruto, is_array(...))) : [];

        $errosBruto = $result['Erros'] ?? [];
        /** @var list<array{mensagem?: string, Mensagem?: string, codigo?: string, Codigo?: string, descricao?: string, Descricao?: string, complemento?: string, Complemento?: string, parametros?: list<string>, Parametros?: list<string>}> $erros */
        $erros = is_array($errosBruto) ? array_values(array_filter($errosBruto, is_array(...))) : [];

        return new self(
            sucesso: $status === StatusDistribuicao::DocumentosLocalizados,
            statusProcessamento: $status,
            lote: array_map(DocumentoFiscal::fromArray(...), $loteDFe),
            alertas: ProcessingMessage::fromArrayList($alertas),
            erros: ProcessingMessage::fromArrayList($erros),
            tipoAmbiente: self::mapTipoAmbiente($result['TipoAmbiente'] ?? null),
            versaoAplicativo: is_string($result['VersaoAplicativo'] ?? null) ? $result['VersaoAplicativo'] : null,
            dataHoraProcessamento: is_string($result['DataHoraProcessamento'] ?? null) ? $result['DataHoraProcessamento'] : null,
        );
    }

    public static function fromHttpResponse(HttpResponse $response): self
    {
        if ($response->statusCode >= 300) {
            return self::fromNon2xxResponse($response);
        }

        if ($response->json === []) {
            return new self(
                sucesso: false,
                statusProcessamento: StatusDistribuicao::Rejeicao,
                lote: [],
                alertas: [],
                erros: [new ProcessingMessage(
                    mensagem: 'Resposta vazia da API',
                    codigo: 'EMPTY_RESPONSE',
                    descricao: sprintf('A API retornou HTTP %d com corpo vazio.', $response->statusCode),
                )],
                tipoAmbiente: null,
                versaoAplicativo: null,
                dataHoraProcessamento: null,
            );
        }

        return self::fromApiResult($response->json);
    }

    private static function fromNon2xxResponse(HttpResponse $response): self
    {
        if ($response->json !== [] && isset($response->json['StatusProcessamento'])) {
            return self::fromApiResult($response->json);
        }

        $body = $response->body;

        return new self(
            sucesso: false,
            statusProcessamento: StatusDistribuicao::Rejeicao,
            lote: [],
            alertas: [],
            erros: [new ProcessingMessage(
                mensagem: sprintf('HTTP error: %d', $response->statusCode),
                codigo: sprintf('HTTP_%d', $response->statusCode),
                descricao: sprintf('A API retornou HTTP %d.', $response->statusCode),
                complemento: $body !== '' ? $body : null,
            )],
            tipoAmbiente: null,
            versaoAplicativo: null,
            dataHoraProcessamento: null,
        );
    }

    private static function mapTipoAmbiente(mixed $value): ?int
    {
        return match ($value) {
            'PRODUCAO' => 1,
            'HOMOLOGACAO' => 2,
            default => null,
        };
    }
}
