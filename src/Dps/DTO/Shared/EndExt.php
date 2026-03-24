<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Shared;

/**
 * @phpstan-type EndExtArray array{cPais: string, cEndPost: string, xCidade: string, xEstProvReg: string}
 */
final readonly class EndExt
{
    public function __construct(
        public string $cPais,
        public string $cEndPost,
        public string $xCidade,
        public string $xEstProvReg,
    ) {}

    /** @phpstan-param EndExtArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
