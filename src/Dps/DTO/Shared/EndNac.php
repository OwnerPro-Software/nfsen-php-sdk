<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Shared;

/**
 * @phpstan-type EndNacArray array{cMun: string, CEP: string}
 */
final readonly class EndNac
{
    public function __construct(
        public string $cMun,
        public string $CEP,
    ) {}

    /** @phpstan-param EndNacArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
