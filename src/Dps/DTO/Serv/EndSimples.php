<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Serv;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type EndExtArray from EndExt
 *
 * @phpstan-type EndSimplesArray array{xLgr: string, nro: string, xBairro: string, CEP?: string, endExt?: EndExtArray, xCpl?: string}
 */
final readonly class EndSimples
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xLgr,
        public string $nro,
        public string $xBairro,
        public ?string $CEP = null,
        public ?EndExt $endExt = null,
        public ?string $xCpl = null,
    ) {
        self::validateChoice(
            ['CEP' => $CEP, 'endereço exterior (endExt)' => $endExt],
            expected: 1,
            path: 'infDPS/serv/atvEvento/end',
        );
    }

    /** @phpstan-param EndSimplesArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            xLgr: $data['xLgr'],
            nro: $data['nro'],
            xBairro: $data['xBairro'],
            CEP: $data['CEP'] ?? null,
            endExt: isset($data['endExt']) ? EndExt::fromArray($data['endExt']) : null,
            xCpl: $data['xCpl'] ?? null,
        );
    }
}
