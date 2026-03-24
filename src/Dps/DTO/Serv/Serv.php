<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Serv;

use OwnerPro\Nfsen\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type CServArray from CServ
 * @phpstan-import-type ComExtArray from ComExt
 * @phpstan-import-type ObraArray from Obra
 * @phpstan-import-type AtvEventoArray from AtvEvento
 * @phpstan-import-type InfoComplArray from InfoCompl
 *
 * @phpstan-type ServArray array{cServ: CServArray, cLocPrestacao?: string, cPaisPrestacao?: string, comExt?: ComExtArray, obra?: ObraArray, atvEvento?: AtvEventoArray, infoCompl?: InfoComplArray}
 */
final readonly class Serv
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public CServ $cServ,
        public ?string $cLocPrestacao = null,
        public ?string $cPaisPrestacao = null,
        public ?ComExt $comExt = null,
        public ?Obra $obra = null,
        public ?AtvEvento $atvEvento = null,
        public ?InfoCompl $infoCompl = null,
    ) {
        self::validateChoice(
            ['código do local de prestação (cLocPrestacao)' => $cLocPrestacao, 'código do país de prestação (cPaisPrestacao)' => $cPaisPrestacao],
            expected: 1,
            path: 'infDPS/serv/locPrest',
        );
    }

    /** @phpstan-param ServArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            cServ: CServ::fromArray($data['cServ']),
            cLocPrestacao: $data['cLocPrestacao'] ?? null,
            cPaisPrestacao: $data['cPaisPrestacao'] ?? null,
            comExt: isset($data['comExt']) ? ComExt::fromArray($data['comExt']) : null,
            obra: isset($data['obra']) ? Obra::fromArray($data['obra']) : null,
            atvEvento: isset($data['atvEvento']) ? AtvEvento::fromArray($data['atvEvento']) : null,
            infoCompl: isset($data['infoCompl']) ? InfoCompl::fromArray($data['infoCompl']) : null,
        );
    }
}
