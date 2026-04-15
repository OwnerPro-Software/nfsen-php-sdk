<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

/**
 * Identificação do município emissor no cabeçalho do DANFSE.
 *
 * Portado de andrevabo/danfse-nacional (MIT).
 *
 * @api
 */
final readonly class MunicipalityBranding
{
    public ?string $logoDataUri;

    public function __construct(
        public string $name,
        public string $department = '',
        public string $email = '',
        ?string $logoDataUri = null,
        ?string $logoPath = null,
    ) {
        $this->logoDataUri = $logoDataUri
            ?? ($logoPath !== null ? LogoLoader::toDataUri($logoPath) : null);
    }
}
