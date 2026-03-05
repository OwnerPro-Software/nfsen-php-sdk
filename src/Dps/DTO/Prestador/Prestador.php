<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Prestador;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\DTO\Shared\Endereco;
use Pulsar\NfseNacional\Dps\DTO\Shared\RegTrib;
use Pulsar\NfseNacional\Dps\Enums\Shared\CodNaoNIF;

/**
 * @phpstan-import-type RegTribArray from RegTrib
 * @phpstan-import-type EnderecoArray from Endereco
 *
 * @phpstan-type PrestadorArray array{regTrib: RegTribArray, CNPJ?: string, CPF?: string, NIF?: string, cNaoNIF?: string, CAEPF?: string, IM?: string, xNome?: string, end?: EnderecoArray, fone?: string, email?: string}
 */
final readonly class Prestador
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public RegTrib $regTrib,
        public ?string $CNPJ = null,
        public ?string $CPF = null,
        public ?string $NIF = null,
        public ?CodNaoNIF $cNaoNIF = null,
        public ?string $CAEPF = null,
        public ?string $IM = null,
        public ?string $xNome = null,
        public ?Endereco $end = null,
        public ?string $fone = null,
        public ?string $email = null,
    ) {
        self::validateChoice(
            ['CNPJ' => $CNPJ, 'CPF' => $CPF, 'NIF' => $NIF, 'código de não NIF (cNaoNIF)' => $cNaoNIF],
            expected: 1,
        );
    }

    /** @phpstan-param PrestadorArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            regTrib: RegTrib::fromArray($data['regTrib']),
            CNPJ: $data['CNPJ'] ?? null,
            CPF: $data['CPF'] ?? null,
            NIF: $data['NIF'] ?? null,
            cNaoNIF: isset($data['cNaoNIF']) ? CodNaoNIF::from($data['cNaoNIF']) : null,
            CAEPF: $data['CAEPF'] ?? null,
            IM: $data['IM'] ?? null,
            xNome: $data['xNome'] ?? null,
            end: isset($data['end']) ? Endereco::fromArray($data['end']) : null,
            fone: $data['fone'] ?? null,
            email: $data['email'] ?? null,
        );
    }
}
