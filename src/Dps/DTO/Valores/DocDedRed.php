<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\DTO\Toma\Toma;
use Pulsar\NfseNacional\Dps\Enums\Valores\TpDedRed;

/**
 * @phpstan-import-type NFSeMunArray from NFSeMun
 * @phpstan-import-type NFNFSArray from NFNFS
 * @phpstan-import-type TomaArray from Toma
 *
 * @phpstan-type DocDedRedArray array{tpDedRed: string, dtEmiDoc: string, vDedutivelRedutivel: string, vDeducaoReducao: string, chNFSe?: string, chNFe?: string, NFSeMun?: NFSeMunArray, NFNFS?: NFNFSArray, nDocFisc?: string, nDoc?: string, xDescOutDed?: string, fornec?: TomaArray}
 */
final readonly class DocDedRed
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public TpDedRed $tpDedRed,
        public string $dtEmiDoc,
        public string $vDedutivelRedutivel,
        public string $vDeducaoReducao,
        public ?string $chNFSe = null,
        public ?string $chNFe = null,
        public ?NFSeMun $NFSeMun = null,
        public ?NFNFS $NFNFS = null,
        public ?string $nDocFisc = null,
        public ?string $nDoc = null,
        public ?string $xDescOutDed = null,
        public ?Toma $fornec = null,
    ) {
        self::validateChoice(
            ['chave NFSe (chNFSe)' => $chNFSe, 'chave NFe (chNFe)' => $chNFe, 'NFSe municipal (NFSeMun)' => $NFSeMun, 'NF/NFS (NFNFS)' => $NFNFS, 'número doc. fiscal (nDocFisc)' => $nDocFisc, 'número documento (nDoc)' => $nDoc],
            expected: 1,
            path: 'infDPS/valores/vDedRed/documentos',
        );
    }

    /** @phpstan-param DocDedRedArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tpDedRed: TpDedRed::from($data['tpDedRed']),
            dtEmiDoc: $data['dtEmiDoc'],
            vDedutivelRedutivel: $data['vDedutivelRedutivel'],
            vDeducaoReducao: $data['vDeducaoReducao'],
            chNFSe: $data['chNFSe'] ?? null,
            chNFe: $data['chNFe'] ?? null,
            NFSeMun: isset($data['NFSeMun']) ? NFSeMun::fromArray($data['NFSeMun']) : null,
            NFNFS: isset($data['NFNFS']) ? NFNFS::fromArray($data['NFNFS']) : null,
            nDocFisc: $data['nDocFisc'] ?? null,
            nDoc: $data['nDoc'] ?? null,
            xDescOutDed: $data['xDescOutDed'] ?? null,
            fornec: isset($data['fornec']) ? Toma::fromArray($data['fornec'], path: 'infDPS/valores/vDedRed/documentos/fornec') : null,
        );
    }
}
