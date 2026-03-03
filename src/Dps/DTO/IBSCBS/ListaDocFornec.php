<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\Enums\Shared\CodNaoNIF;

/**
 * @phpstan-type ListaDocFornecArray array{xNome: string, CNPJ?: string, CPF?: string, NIF?: string, cNaoNIF?: string}
 */
final readonly class ListaDocFornec
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xNome,
        public ?string $CNPJ = null,
        public ?string $CPF = null,
        public ?string $NIF = null,
        public ?CodNaoNIF $cNaoNIF = null,
    ) {
        self::validateChoice(
            ['CNPJ' => $CNPJ, 'CPF' => $CPF, 'NIF' => $NIF, 'cNaoNIF' => $cNaoNIF],
            expected: 1,
            message: 'ListaDocFornec requer exatamente um entre CNPJ, CPF, NIF ou cNaoNIF.',
        );
    }

    /** @phpstan-param ListaDocFornecArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            xNome: $data['xNome'],
            CNPJ: $data['CNPJ'] ?? null,
            CPF: $data['CPF'] ?? null,
            NIF: $data['NIF'] ?? null,
            cNaoNIF: isset($data['cNaoNIF']) ? CodNaoNIF::from($data['cNaoNIF']) : null,
        );
    }
}
