<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

/**
 * @phpstan-import-type InfoTributosSitClasArray from InfoTributosSitClas
 *
 * @phpstan-type InfoTributosIBSCBSArray array{gIBSCBS: InfoTributosSitClasArray}
 */
final readonly class InfoTributosIBSCBS
{
    public function __construct(
        public InfoTributosSitClas $gIBSCBS,
    ) {}

    /** @phpstan-param InfoTributosIBSCBSArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            gIBSCBS: InfoTributosSitClas::fromArray($data['gIBSCBS']),
        );
    }
}
