<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Tomador;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\DTO\Shared\Endereco;
use Pulsar\NfseNacional\Enums\Dps\Shared\CodNaoNIF;

/**
 * @phpstan-import-type EnderecoArray from Endereco
 *
 * @phpstan-type TomadorArray array{xNome: string, CNPJ?: string, CPF?: string, NIF?: string, cNaoNIF?: string, CAEPF?: string, IM?: string, end?: EnderecoArray, fone?: string, email?: string}
 */
final readonly class Tomador
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xNome,
        public ?string $CNPJ = null,
        public ?string $CPF = null,
        public ?string $NIF = null,
        public ?CodNaoNIF $cNaoNIF = null,
        public ?string $CAEPF = null,
        public ?string $IM = null,
        public ?Endereco $end = null,
        public ?string $fone = null,
        public ?string $email = null,
    ) {
        self::validateChoice(
            ['CNPJ' => $CNPJ, 'CPF' => $CPF, 'NIF' => $NIF, 'cNaoNIF' => $cNaoNIF],
            expected: 1,
            message: 'Tomador requer exatamente um entre CNPJ, CPF, NIF ou cNaoNIF.',
        );
    }

    /** @phpstan-param TomadorArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            xNome: $data['xNome'],
            CNPJ: $data['CNPJ'] ?? null,
            CPF: $data['CPF'] ?? null,
            NIF: $data['NIF'] ?? null,
            cNaoNIF: isset($data['cNaoNIF']) ? CodNaoNIF::from($data['cNaoNIF']) : null,
            CAEPF: $data['CAEPF'] ?? null,
            IM: $data['IM'] ?? null,
            end: isset($data['end']) ? Endereco::fromArray($data['end']) : null,
            fone: $data['fone'] ?? null,
            email: $data['email'] ?? null,
        );
    }
}
