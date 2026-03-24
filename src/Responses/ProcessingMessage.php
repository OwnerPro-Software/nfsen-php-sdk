<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

/**
 * @phpstan-type MessageData array{
 *     mensagem?: string, Mensagem?: string,
 *     codigo?: string, Codigo?: string,
 *     descricao?: string, Descricao?: string,
 *     complemento?: string, Complemento?: string,
 * }
 */
final readonly class ProcessingMessage
{
    public function __construct(
        public ?string $mensagem = null,
        public ?string $codigo = null,
        public ?string $descricao = null,
        public ?string $complemento = null,
    ) {}

    /** @phpstan-param MessageData $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mensagem: $data['mensagem'] ?? $data['Mensagem'] ?? null,
            codigo: $data['codigo'] ?? $data['Codigo'] ?? null,
            descricao: $data['descricao'] ?? $data['Descricao'] ?? null,
            complemento: $data['complemento'] ?? $data['Complemento'] ?? null,
        );
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
