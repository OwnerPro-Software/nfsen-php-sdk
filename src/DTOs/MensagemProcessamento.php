<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs;

final readonly class MensagemProcessamento
{
    public function __construct(
        public ?string $mensagem = null,
        public ?string $codigo = null,
        public ?string $descricao = null,
        public ?string $complemento = null,
    ) {}

    /** @param array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mensagem: $data['mensagem'] ?? null,
            codigo: $data['codigo'] ?? null,
            descricao: $data['descricao'] ?? null,
            complemento: $data['complemento'] ?? null,
        );
    }

    /**
     * @param  list<array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string}>  $items
     * @return list<self>
     */
    public static function fromArrayList(array $items): array
    {
        return array_map(self::fromArray(...), $items);
    }

    /**
     * Normaliza as duas formas de erro da API (singular `erro` e plural `erros`) em uma lista tipada.
     *
     * @param  array{erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>, erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string}}  $result
     * @return list<self>
     */
    public static function fromApiResult(array $result): array
    {
        $items = $result['erros'] ?? (isset($result['erro']) ? [$result['erro']] : []);

        return self::fromArrayList($items);
    }
}
