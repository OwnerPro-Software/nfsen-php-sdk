<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Servico;

use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

/**
 * @phpstan-type InfoComplementarArray array{idDocTec?: string, docRef?: string, xPed?: string, xItemPed?: list<string>, xInfComp?: string}
 */
final readonly class InfoComplementar
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

    /** @phpstan-param InfoComplementarArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
