<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use InvalidArgumentException;
use OwnerPro\Nfsen\Danfse\Concerns\ValidatesArrayShape;

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
    use ValidatesArrayShape;

    // 'enabled' é aceito como no-op — é o gate de NfsenClient::for() lendo config Laravel
    // e chega até aqui quando o array é repassado. Dentro de fromArray não tem efeito.
    private const array ALLOWED_KEYS = ['enabled', 'logo_path', 'logo_data_uri', 'municipality']; // @pest-mutate-ignore RemoveArrayItem — whitelist schema; definição em const (linha não coberta por line coverage do phpunit).

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

    /**
     * Assimetria intencional vs `MunicipalityBranding::fromArray()`:
     *
     * - `DanfseConfig::fromArray(['municipality' => ['name' => '']])` → silenciosamente
     *   descarta o bloco (retorna `municipality = null`). Defesa em profundidade para
     *   config Laravel parcial onde env `NFSE_DANFSE_MUN_NAME` está vazia.
     * - `MunicipalityBranding::fromArray(['name' => ''])` → lança `InvalidArgumentException`.
     *   Chamadas diretas por código devem passar `name` válido.
     *
     * A chave `enabled` é aceita no shape mas ignorada: só tem efeito no gate upstream
     * em `NfsenClient::for()` lendo config Laravel (`NfsenClient::isDanfseEnabled()`).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        self::rejectUnknownKeys($data, self::ALLOWED_KEYS, 'danfse');

        $logoPath = $data['logo_path'] ?? null;
        if ($logoPath !== null && $logoPath !== false && ! is_string($logoPath)) {
            throw new InvalidArgumentException('danfse.logo_path: esperado string|false|null');
        }

        $logoDataUri = $data['logo_data_uri'] ?? null;
        if ($logoDataUri !== null && ! is_string($logoDataUri)) {
            throw new InvalidArgumentException('danfse.logo_data_uri: esperado string|null');
        }

        $municipality = self::buildMunicipality($data['municipality'] ?? null);

        return new self(
            logoDataUri: $logoDataUri,
            logoPath: $logoPath,
            municipality: $municipality,
        );
    }

    private static function buildMunicipality(mixed $raw): ?MunicipalityBranding
    {
        if ($raw === null) {
            return null;
        }

        if (! is_array($raw)) {
            throw new InvalidArgumentException('danfse.municipality: esperado array|null');
        }

        /** @var array<string, mixed> $raw */

        // Defesa em profundidade: config Laravel parcial (name null/'') vira ausência.
        $name = $raw['name'] ?? null;
        if ($name === null || $name === '') {
            return null;
        }

        return MunicipalityBranding::fromArray($raw);
    }

    private function defaultLogoDataUri(): ?string
    {
        $path = __DIR__.'/../../storage/danfse/logo-nfse.png';

        return is_readable($path) ? LogoLoader::toDataUri($path) : null;
    }
}
