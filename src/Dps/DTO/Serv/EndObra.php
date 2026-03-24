<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Serv;

use OwnerPro\Nfsen\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type EndExtArray from EndExt
 *
 * @phpstan-type EndObraArray array{xLgr: string, nro: string, xBairro: string, CEP?: string, endExt?: EndExtArray, xCpl?: string}
 */
final readonly class EndObra
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xLgr,
        public string $nro,
        public string $xBairro,
        public ?string $CEP = null,
        public ?EndExt $endExt = null,
        public ?string $xCpl = null,
        string $path = 'end',
    ) {
        self::validateChoice(
            ['CEP' => $CEP, 'endereço exterior (endExt)' => $endExt],
            expected: 1,
            path: $path,
        );
    }

    /** @phpstan-param EndObraArray $data */
    public static function fromArray(array $data, string $path = 'end'): self
    {
        return new self(
            xLgr: $data['xLgr'],
            nro: $data['nro'],
            xBairro: $data['xBairro'],
            CEP: $data['CEP'] ?? null,
            endExt: isset($data['endExt']) ? EndExt::fromArray($data['endExt']) : null,
            xCpl: $data['xCpl'] ?? null,
            path: $path,
        );
    }
}
