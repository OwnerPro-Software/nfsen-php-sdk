<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Shared;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type EnderecoNacionalArray from EnderecoNacional
 * @phpstan-import-type EnderecoExteriorArray from EnderecoExterior
 *
 * @phpstan-type EnderecoArray array{xLgr: string, nro: string, xBairro: string, endNac?: EnderecoNacionalArray, endExt?: EnderecoExteriorArray, xCpl?: string}
 */
final readonly class Endereco
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xLgr,
        public string $nro,
        public string $xBairro,
        public ?EnderecoNacional $endNac = null,
        public ?EnderecoExterior $endExt = null,
        public ?string $xCpl = null,
    ) {
        self::validateChoice(
            ['endereço nacional (endNac)' => $endNac, 'endereço exterior (endExt)' => $endExt],
            expected: 1,
        );
    }

    /** @phpstan-param EnderecoArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            xLgr: $data['xLgr'],
            nro: $data['nro'],
            xBairro: $data['xBairro'],
            endNac: isset($data['endNac']) ? EnderecoNacional::fromArray($data['endNac']) : null,
            endExt: isset($data['endExt']) ? EnderecoExterior::fromArray($data['endExt']) : null,
            xCpl: $data['xCpl'] ?? null,
        );
    }
}
