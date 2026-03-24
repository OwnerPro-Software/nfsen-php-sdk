<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Prest;

use OwnerPro\Nfsen\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use OwnerPro\Nfsen\Dps\DTO\Shared\End;
use OwnerPro\Nfsen\Dps\DTO\Shared\RegTrib;
use OwnerPro\Nfsen\Dps\Enums\Shared\CNaoNIF;

/**
 * @phpstan-import-type RegTribArray from RegTrib
 * @phpstan-import-type EndArray from End
 *
 * @phpstan-type PrestArray array{regTrib: RegTribArray, CNPJ?: string, CPF?: string, NIF?: string, cNaoNIF?: string, CAEPF?: string, IM?: string, xNome?: string, end?: EndArray, fone?: string, email?: string}
 */
final readonly class Prest
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public RegTrib $regTrib,
        public ?string $CNPJ = null,
        public ?string $CPF = null,
        public ?string $NIF = null,
        public ?CNaoNIF $cNaoNIF = null,
        public ?string $CAEPF = null,
        public ?string $IM = null,
        public ?string $xNome = null,
        public ?End $end = null,
        public ?string $fone = null,
        public ?string $email = null,
    ) {
        self::validateChoice(
            ['CNPJ' => $CNPJ, 'CPF' => $CPF, 'NIF' => $NIF, 'código de não NIF (cNaoNIF)' => $cNaoNIF],
            expected: 1,
            path: 'infDPS/prest',
        );
    }

    /** @phpstan-param PrestArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            regTrib: RegTrib::fromArray($data['regTrib']),
            CNPJ: $data['CNPJ'] ?? null,
            CPF: $data['CPF'] ?? null,
            NIF: $data['NIF'] ?? null,
            cNaoNIF: isset($data['cNaoNIF']) ? CNaoNIF::from($data['cNaoNIF']) : null,
            CAEPF: $data['CAEPF'] ?? null,
            IM: $data['IM'] ?? null,
            xNome: $data['xNome'] ?? null,
            end: isset($data['end']) ? End::fromArray($data['end'], path: 'infDPS/prest/end') : null,
            fone: $data['fone'] ?? null,
            email: $data['email'] ?? null,
        );
    }
}
