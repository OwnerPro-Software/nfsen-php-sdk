<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Servico;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type EnderecoExteriorObraArray from EnderecoExteriorObra
 *
 * @phpstan-type EnderecoObraArray array{xLgr: string, nro: string, xBairro: string, CEP?: string, endExt?: EnderecoExteriorObraArray, xCpl?: string}
 */
final readonly class EnderecoObra
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xLgr,
        public string $nro,
        public string $xBairro,
        public ?string $CEP = null,
        public ?EnderecoExteriorObra $endExt = null,
        public ?string $xCpl = null,
    ) {
        self::validateChoice(
            ['CEP' => $CEP, 'endExt' => $endExt],
            expected: 1,
            message: 'EnderecoObra requer exatamente um entre CEP ou endExt.',
        );
    }

    /** @phpstan-param EnderecoObraArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            xLgr: $data['xLgr'],
            nro: $data['nro'],
            xBairro: $data['xBairro'],
            CEP: $data['CEP'] ?? null,
            endExt: isset($data['endExt']) ? EnderecoExteriorObra::fromArray($data['endExt']) : null,
            xCpl: $data['xCpl'] ?? null,
        );
    }
}
