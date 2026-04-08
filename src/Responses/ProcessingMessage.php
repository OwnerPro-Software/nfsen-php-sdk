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

    /** @phpstan-param MessageData $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mensagem: self::toString($data['mensagem'] ?? $data['Mensagem'] ?? null),
            codigo: self::toString($data['codigo'] ?? $data['Codigo'] ?? null),
            descricao: self::toString($data['descricao'] ?? $data['Descricao'] ?? null),
            complemento: self::toString($data['complemento'] ?? $data['Complemento'] ?? null),
            parametros: $data['parametros'] ?? $data['Parametros'] ?? [],
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
        $items = $result['erros'] ?? (isset($result['erro']) && $result['erro'] !== [] ? [$result['erro']] : []);

        return self::fromArrayList($items);
    }
}
