<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

final readonly class InfoTributosSitClas
{
    public function __construct(
        public string $CST,
        public string $cClassTrib,
        public ?string $cCredPres = null,
        public ?InfoTributosTribRegular $gTribRegular = null,
        public ?InfoTributosDif $gDif = null,
    ) {}
}
