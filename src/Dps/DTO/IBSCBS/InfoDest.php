<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\DTO\Shared\Endereco;
use Pulsar\NfseNacional\Dps\Enums\Shared\CNaoNIF;

/**
 * @phpstan-import-type EnderecoArray from Endereco
 *
 * @phpstan-type InfoDestArray array{xNome: string, CNPJ?: string, CPF?: string, NIF?: string, cNaoNIF?: string, end?: EnderecoArray, fone?: string, email?: string}
 */
final readonly class InfoDest
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xNome,
        public ?string $CNPJ = null,
        public ?string $CPF = null,
        public ?string $NIF = null,
        public ?CNaoNIF $cNaoNIF = null,
        public ?Endereco $end = null,
        public ?string $fone = null,
        public ?string $email = null,
    ) {
        self::validateChoice(
            ['CNPJ' => $CNPJ, 'CPF' => $CPF, 'NIF' => $NIF, 'código de não NIF (cNaoNIF)' => $cNaoNIF],
            expected: 1,
            path: 'infDPS/IBSCBS/dest',
        );
    }

    /** @phpstan-param InfoDestArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            xNome: $data['xNome'],
            CNPJ: $data['CNPJ'] ?? null,
            CPF: $data['CPF'] ?? null,
            NIF: $data['NIF'] ?? null,
            cNaoNIF: isset($data['cNaoNIF']) ? CNaoNIF::from($data['cNaoNIF']) : null,
            end: isset($data['end']) ? Endereco::fromArray($data['end'], path: 'infDPS/IBSCBS/dest/end') : null,
            fone: $data['fone'] ?? null,
            email: $data['email'] ?? null,
        );
    }
}
