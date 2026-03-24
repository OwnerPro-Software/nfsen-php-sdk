<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\IBSCBS;

/**
 * @phpstan-type DocOutroArray array{nDoc: string, xDoc: string}
 */
final readonly class DocOutro
{
    public function __construct(
        public string $nDoc,
        public string $xDoc,
    ) {}

    /** @phpstan-param DocOutroArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
