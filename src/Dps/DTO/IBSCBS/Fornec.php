<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\Enums\Shared\CNaoNIF;

/**
 * @phpstan-type FornecArray array{xNome: string, CNPJ?: string, CPF?: string, NIF?: string, cNaoNIF?: string}
 */
final readonly class Fornec
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xNome,
        public ?string $CNPJ = null,
        public ?string $CPF = null,
        public ?string $NIF = null,
        public ?CNaoNIF $cNaoNIF = null,
    ) {
        self::validateChoice(
            ['CNPJ' => $CNPJ, 'CPF' => $CPF, 'NIF' => $NIF, 'código de não NIF (cNaoNIF)' => $cNaoNIF],
            expected: 1,
            path: 'infDPS/IBSCBS/infReeRepRes/documentos/fornec',
        );
    }

    /** @phpstan-param FornecArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            xNome: $data['xNome'],
            CNPJ: $data['CNPJ'] ?? null,
            CPF: $data['CPF'] ?? null,
            NIF: $data['NIF'] ?? null,
            cNaoNIF: isset($data['cNaoNIF']) ? CNaoNIF::from($data['cNaoNIF']) : null,
        );
    }
}
