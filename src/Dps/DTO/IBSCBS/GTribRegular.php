<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

/**
 * @phpstan-type GTribRegularArray array{CSTReg: string, cClassTribReg: string}
 */
final readonly class GTribRegular
{
    public function __construct(
        public string $CSTReg,
        public string $cClassTribReg,
    ) {}

    /** @phpstan-param GTribRegularArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
