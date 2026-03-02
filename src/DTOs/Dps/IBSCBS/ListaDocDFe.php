<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

use Pulsar\NfseNacional\Enums\Dps\IBSCBS\TipoChaveDFe;

/**
 * @phpstan-type ListaDocDFeArray array{tipoChaveDFe: string, chaveDFe: string, xTipoChaveDFe?: string}
 */
final readonly class ListaDocDFe
{
    public function __construct(
        public TipoChaveDFe $tipoChaveDFe,
        public string $chaveDFe,
        public ?string $xTipoChaveDFe = null,
    ) {}

    /** @phpstan-param ListaDocDFeArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tipoChaveDFe: TipoChaveDFe::from($data['tipoChaveDFe']),
            chaveDFe: $data['chaveDFe'],
            xTipoChaveDFe: $data['xTipoChaveDFe'] ?? null,
        );
    }
}
