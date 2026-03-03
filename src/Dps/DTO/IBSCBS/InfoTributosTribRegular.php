<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

/**
 * @phpstan-type InfoTributosTribRegularArray array{CSTReg: string, cClassTribReg: string}
 */
final readonly class InfoTributosTribRegular
{
    public function __construct(
        public string $CSTReg,
        public string $cClassTribReg,
    ) {}

    /** @phpstan-param InfoTributosTribRegularArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
