<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

/**
 * @phpstan-import-type InfoTributosTribRegularArray from InfoTributosTribRegular
 * @phpstan-import-type InfoTributosDifArray from InfoTributosDif
 *
 * @phpstan-type InfoTributosSitClasArray array{CST: string, cClassTrib: string, cCredPres?: string, gTribRegular?: InfoTributosTribRegularArray, gDif?: InfoTributosDifArray}
 */
final readonly class InfoTributosSitClas
{
    public function __construct(
        public string $CST,
        public string $cClassTrib,
        public ?string $cCredPres = null,
        public ?InfoTributosTribRegular $gTribRegular = null,
        public ?InfoTributosDif $gDif = null,
    ) {}

    /** @phpstan-param InfoTributosSitClasArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            CST: $data['CST'],
            cClassTrib: $data['cClassTrib'],
            cCredPres: $data['cCredPres'] ?? null,
            gTribRegular: isset($data['gTribRegular']) ? InfoTributosTribRegular::fromArray($data['gTribRegular']) : null,
            gDif: isset($data['gDif']) ? InfoTributosDif::fromArray($data['gDif']) : null,
        );
    }
}
