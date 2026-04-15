<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use InvalidArgumentException;
use OwnerPro\Nfsen\Danfse\Concerns\ValidatesArrayShape;

/**
 * Identificação do município emissor no cabeçalho do DANFSE.
 *
 * Portado de andrevabo/danfse-nacional (MIT).
 *
 * @api
 */
final readonly class MunicipalityBranding
{
    use ValidatesArrayShape;

    private const array ALLOWED_KEYS = ['name', 'department', 'email', 'logo_path', 'logo_data_uri']; // @pest-mutate-ignore RemoveArrayItem — whitelist schema; definição em const (linha não coberta por line coverage do phpunit).

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

    /**
     * Constrói MunicipalityBranding a partir de array.
     *
     * Pré-condição: `name` é string não-vazia. O caso de bloco ausente ou `name`
     * nulo/vazio é filtrado upstream por `DanfseConfig::buildMunicipality()`.
     * Chamadas diretas devem fornecer `name` válido.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        self::rejectUnknownKeys($data, self::ALLOWED_KEYS, 'danfse.municipality');

        if (! array_key_exists('name', $data)) {
            throw new InvalidArgumentException('danfse.municipality.name: obrigatório');
        }

        $name = $data['name'];
        if (! is_string($name)) {
            throw new InvalidArgumentException('danfse.municipality.name: esperado string');
        }

        if ($name === '') {
            throw new InvalidArgumentException('danfse.municipality.name: não pode ser vazio');
        }

        $department = $data['department'] ?? '';
        if (! is_string($department)) {
            throw new InvalidArgumentException('danfse.municipality.department: esperado string');
        }

        $email = $data['email'] ?? '';
        if (! is_string($email)) {
            throw new InvalidArgumentException('danfse.municipality.email: esperado string');
        }

        $logoPath = $data['logo_path'] ?? null;
        if ($logoPath !== null && ! is_string($logoPath)) {
            throw new InvalidArgumentException('danfse.municipality.logo_path: esperado string|null');
        }

        $logoDataUri = $data['logo_data_uri'] ?? null;
        if ($logoDataUri !== null && ! is_string($logoDataUri)) {
            throw new InvalidArgumentException('danfse.municipality.logo_data_uri: esperado string|null');
        }

        return new self(
            name: $name,
            department: $department,
            email: $email,
            logoDataUri: $logoDataUri,
            logoPath: $logoPath,
        );
    }
}
