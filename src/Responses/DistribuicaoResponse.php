<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

use OwnerPro\Nfsen\Enums\StatusDistribuicao;

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
        /** @var string $statusRaw */
        $statusRaw = $result['StatusProcessamento'] ?? ''; // @pest-mutate-ignore EmptyStringToNotEmpty — any non-enum string produces null from tryFrom
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

        /** @var list<array{NSU?: int|null, ChaveAcesso?: string|null, TipoDocumento: string, TipoEvento?: string|null, ArquivoXml?: string|null, DataHoraGeracao?: string|null}> $loteDFe */
        $loteDFe = $result['LoteDFe'] ?? [];

        /** @var list<array{mensagem?: string, Mensagem?: string, codigo?: string, Codigo?: string, descricao?: string, Descricao?: string, complemento?: string, Complemento?: string, parametros?: list<string>, Parametros?: list<string>}> $alertas */
        $alertas = $result['Alertas'] ?? [];

        /** @var list<array{mensagem?: string, Mensagem?: string, codigo?: string, Codigo?: string, descricao?: string, Descricao?: string, complemento?: string, Complemento?: string, parametros?: list<string>, Parametros?: list<string>}> $erros */
        $erros = $result['Erros'] ?? [];

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

    private static function mapTipoAmbiente(mixed $value): ?int
    {
        return match ($value) {
            'PRODUCAO' => 1,
            'HOMOLOGACAO' => 2,
            default => null,
        };
    }
}
