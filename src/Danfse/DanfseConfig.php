<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

/**
 * Configuração opcional da geração do DANFSE.
 *
 * - `logoDataUri` tem precedência sobre `logoPath` quando ambos são informados.
 * - `logoPath: false` suprime o logo completamente (ignora `logoDataUri`).
 * - `logoPath: null` usa o logo padrão do pacote.
 *
 * Portado de andrevabo/danfse-nacional (MIT).
 *
 * @api
 */
final readonly class DanfseConfig
{
    public ?string $logoDataUri;

    public function __construct(
        ?string $logoDataUri = null,
        string|false|null $logoPath = null,
        public ?MunicipalityBranding $municipality = null,
    ) {
        if ($logoPath === false) {
            $this->logoDataUri = null;

            return;
        }

        $this->logoDataUri = $logoDataUri
            ?? ($logoPath !== null ? LogoLoader::toDataUri($logoPath) : $this->defaultLogoDataUri());
    }

    private function defaultLogoDataUri(): ?string
    {
        $path = __DIR__.'/../../storage/danfse/logo-nfse.png';

        return is_readable($path) ? LogoLoader::toDataUri($path) : null;
    }
}
