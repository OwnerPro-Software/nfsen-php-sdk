<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Serv;

use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

/**
 * @phpstan-type InfoComplArray array{idDocTec?: string, docRef?: string, xPed?: string, xItemPed?: list<string>, xInfComp?: string}
 */
final readonly class InfoCompl
{
    /** @param list<string>|null $xItemPed */
    public function __construct(
        public ?string $idDocTec = null,
        public ?string $docRef = null,
        public ?string $xPed = null,
        public ?array $xItemPed = null,
        public ?string $xInfComp = null,
    ) {
        if ($xItemPed !== null && $xItemPed === []) {
            throw new InvalidDpsArgument('xItemPed deve conter ao menos um item.');
        }

        if ($idDocTec === null && $docRef === null && $xPed === null && $xItemPed === null && $xInfComp === null) {
            throw new InvalidDpsArgument('infoCompl deve conter ao menos um campo preenchido.');
        }
    }

    /** @phpstan-param InfoComplArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
