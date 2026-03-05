<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Tomador;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\DTO\Shared\Endereco;
use Pulsar\NfseNacional\Dps\Enums\Shared\CNaoNIF;

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
        public ?CNaoNIF $cNaoNIF = null,
        public ?string $CAEPF = null,
        public ?string $IM = null,
        public ?Endereco $end = null,
        public ?string $fone = null,
        public ?string $email = null,
        string $path = 'infDPS/toma',
    ) {
        self::validateChoice(
            ['CNPJ' => $CNPJ, 'CPF' => $CPF, 'NIF' => $NIF, 'código de não NIF (cNaoNIF)' => $cNaoNIF],
            expected: 1,
            path: $path,
        );
    }

    /** @phpstan-param TomadorArray $data */
    public static function fromArray(array $data, string $path = 'infDPS/toma'): self
    {
        return new self(
            xNome: $data['xNome'],
            CNPJ: $data['CNPJ'] ?? null,
            CPF: $data['CPF'] ?? null,
            NIF: $data['NIF'] ?? null,
            cNaoNIF: isset($data['cNaoNIF']) ? CNaoNIF::from($data['cNaoNIF']) : null,
            CAEPF: $data['CAEPF'] ?? null,
            IM: $data['IM'] ?? null,
            end: isset($data['end']) ? Endereco::fromArray($data['end'], path: $path.'/end') : null,
            fone: $data['fone'] ?? null,
            email: $data['email'] ?? null,
            path: $path,
        );
    }
}
