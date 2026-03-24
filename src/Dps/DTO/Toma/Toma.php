<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Toma;

use OwnerPro\Nfsen\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use OwnerPro\Nfsen\Dps\DTO\Shared\End;
use OwnerPro\Nfsen\Dps\Enums\Shared\CNaoNIF;

/**
 * @phpstan-import-type EndArray from End
 *
 * @phpstan-type TomaArray array{xNome: string, CNPJ?: string, CPF?: string, NIF?: string, cNaoNIF?: string, CAEPF?: string, IM?: string, end?: EndArray, fone?: string, email?: string}
 */
final readonly class Toma
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
        public ?End $end = null,
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

    /** @phpstan-param TomaArray $data */
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
            end: isset($data['end']) ? End::fromArray($data['end'], path: $path.'/end') : null,
            fone: $data['fone'] ?? null,
            email: $data['email'] ?? null,
            path: $path,
        );
    }
}
