<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

/**
 * @phpstan-type CodigoServicoArray array{cTribNac: string, xDescServ: string, cNBS: string, cTribMun?: string, cIntContrib?: string}
 */
final readonly class CodigoServico
{
    public function __construct(
        public string $cTribNac,
        public string $xDescServ,
        public string $cNBS,
        public ?string $cTribMun = null,
        public ?string $cIntContrib = null,
    ) {}

    /** @phpstan-param CodigoServicoArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
