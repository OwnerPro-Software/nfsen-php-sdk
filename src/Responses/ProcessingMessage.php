<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

/**
 * @phpstan-type MessageData array{
 *     mensagem?: string, Mensagem?: string,
 *     codigo?: string, Codigo?: string,
 *     descricao?: string, Descricao?: string,
 *     complemento?: string, Complemento?: string,
 *     parametros?: list<string>, Parametros?: list<string>,
 * }
 *
 * @api
 */
final readonly class ProcessingMessage
{
    public function __construct(
        public ?string $mensagem = null,
        public ?string $codigo = null,
        public ?string $descricao = null,
        public ?string $complemento = null,
        /** @var list<string> */
        public array $parametros = [],
    ) {}

    public static function xmlIlegivel(string $recuperarVia, string $causa): self
    {
        return new self(
            mensagem: 'XML não pôde ser lido',
            codigo: 'XML_ILEGIVEL',
            descricao: sprintf(
                'A operação foi confirmada, mas o XML compactado veio corrompido e não pôde ser descomprimido. Recupere o documento por %s.',
                $recuperarVia,
            ),
            complemento: $causa,
        );
    }

    /** @phpstan-param MessageData $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mensagem: self::toString($data['mensagem'] ?? $data['Mensagem'] ?? null),
            codigo: self::toString($data['codigo'] ?? $data['Codigo'] ?? null),
            descricao: self::toString($data['descricao'] ?? $data['Descricao'] ?? null),
            complemento: self::toString($data['complemento'] ?? $data['Complemento'] ?? null),
            // `Parametros` é o único declarado (ADN). A variante minúscula é
            // tolerância: a SEFIN nomeia todo o resto da mensagem em minúscula, logo
            // seria esse o casing dela. Auditoria de 2026-07-21 confirmou que nenhum
            // dos dois swaggers declara `parametros` — não remova achando que é typo.
            parametros: self::toList($data['parametros'] ?? $data['Parametros'] ?? null),
        );
    }

    private static function toString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : null; // @pest-mutate-ignore FalseToTrue — json_encode on API data never returns false in practice
    }

    /**
     * Normaliza `parametros` numa lista de strings. Como nenhum swagger declara o
     * campo, sua forma não tem contrato: um escalar vira lista vazia e itens não-string
     * saem, em vez de estourar TypeError no construtor tipado — mesma tolerância que
     * {@see self::extractErrors()} aplica ao envelope de erro.
     *
     * @return list<string>
     */
    private static function toList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @phpstan-param  list<MessageData>  $items
     *
     * @return list<self>
     */
    public static function fromArrayList(array $items): array
    {
        return array_map(self::fromArray(...), $items);
    }

    /**
     * Normaliza as duas formas de erro da API (singular `erro` e plural `erros`) em uma lista tipada.
     *
     * @phpstan-param  array{erros?: list<MessageData>, erro?: MessageData}  $result
     *
     * @return list<self>
     */
    public static function fromApiResult(array $result): array
    {
        return self::fromArrayList(self::extractErrors($result));
    }

    /**
     * A resposta carrega erro da SEFIN? Único critério de rejeição aceito pelo SDK.
     *
     * Existe para que a classificação (rejeitado vs. processado) e a extração das
     * mensagens não possam divergir: as duas derivam de {@see self::extractErrors()}.
     * Testar a presença da chave `erro` por conta própria é o que fazia um corpo
     * `{"erro": [], "chaveAcesso": "..."}` — forma que a API realmente produz —
     * virar rejeição sem mensagem alguma, descartando a chave de uma nota autorizada.
     *
     * @phpstan-param  array{erros?: list<MessageData>, erro?: MessageData}  $result
     */
    public static function hasApiError(array $result): bool
    {
        return self::extractErrors($result) !== [];
    }

    /**
     * @phpstan-param  array{erros?: list<MessageData>, erro?: MessageData}  $result
     *
     * @return list<MessageData>
     */
    private static function extractErrors(array $result): array
    {
        $erros = self::onlyMessages($result['erros'] ?? []);

        if ($erros !== []) {
            return $erros;
        }

        // `erro` singular só é envelope da SEFIN se for uma mensagem (array). Um
        // escalar — como o `{"erro": "Bad Gateway"}` que um proxy/WAF devolve num
        // 5xx — não prova rejeição da SEFIN: aceitá-lo classificava a falha do
        // gateway como rejeição definitiva (convite a reemitir a nota que a SEFIN
        // pode ter gravado) e ainda estourava TypeError em fromArray(), fora do
        // contrato de exceções tipadas. Mesma régua de tolerância do
        // DistribuicaoResponse: item que não é mensagem sai da classificação.
        $erro = $result['erro'] ?? null;

        return is_array($erro) && $erro !== [] ? [$erro] : [];
    }

    /**
     * @return list<MessageData>
     */
    private static function onlyMessages(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        /** @var list<MessageData> */
        return array_values(array_filter($items, is_array(...)));
    }
}
