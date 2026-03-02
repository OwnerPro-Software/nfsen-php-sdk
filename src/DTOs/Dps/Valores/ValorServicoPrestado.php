<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

/**
 * @phpstan-type ValorServicoPrestadoArray array{vServ: string, vReceb?: string}
 */
final readonly class ValorServicoPrestado
{
    public function __construct(
        public string $vServ,
        public ?string $vReceb = null,
    ) {}

    /** @phpstan-param ValorServicoPrestadoArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
