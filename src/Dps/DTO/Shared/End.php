<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Shared;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type EndNacArray from EndNac
 * @phpstan-import-type EndExtArray from EndExt
 *
 * @phpstan-type EndArray array{xLgr: string, nro: string, xBairro: string, endNac?: EndNacArray, endExt?: EndExtArray, xCpl?: string}
 */
final readonly class End
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xLgr,
        public string $nro,
        public string $xBairro,
        public ?EndNac $endNac = null,
        public ?EndExt $endExt = null,
        public ?string $xCpl = null,
        string $path = 'end',
    ) {
        self::validateChoice(
            ['endereço nacional (endNac)' => $endNac, 'endereço exterior (endExt)' => $endExt],
            expected: 1,
            path: $path,
        );
    }

    /** @phpstan-param EndArray $data */
    public static function fromArray(array $data, string $path = 'end'): self
    {
        return new self(
            xLgr: $data['xLgr'],
            nro: $data['nro'],
            xBairro: $data['xBairro'],
            endNac: isset($data['endNac']) ? EndNac::fromArray($data['endNac']) : null,
            endExt: isset($data['endExt']) ? EndExt::fromArray($data['endExt']) : null,
            xCpl: $data['xCpl'] ?? null,
            path: $path,
        );
    }
}
