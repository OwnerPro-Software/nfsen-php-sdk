<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\IBSCBS;

use OwnerPro\Nfsen\Dps\Enums\IBSCBS\TipoChaveDFe;

/**
 * @phpstan-type DFeNacionalArray array{tipoChaveDFe: string, chaveDFe: string, xTipoChaveDFe?: string}
 */
final readonly class DFeNacional
{
    public function __construct(
        public TipoChaveDFe $tipoChaveDFe,
        public string $chaveDFe,
        public ?string $xTipoChaveDFe = null,
    ) {}

    /** @phpstan-param DFeNacionalArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tipoChaveDFe: TipoChaveDFe::from($data['tipoChaveDFe']),
            chaveDFe: $data['chaveDFe'],
            xTipoChaveDFe: $data['xTipoChaveDFe'] ?? null,
        );
    }
}
