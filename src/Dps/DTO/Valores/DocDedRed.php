<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\DTO\Tomador\Tomador;
use Pulsar\NfseNacional\Enums\Dps\Valores\TipoDedRed;

/**
 * @phpstan-import-type DocOutNFSeArray from DocOutNFSe
 * @phpstan-import-type DocNFNFSArray from DocNFNFS
 * @phpstan-import-type TomadorArray from Tomador
 *
 * @phpstan-type DocDedRedArray array{tpDedRed: string, dtEmiDoc: string, vDedutivelRedutivel: string, vDeducaoReducao: string, chNFSe?: string, chNFe?: string, NFSeMun?: DocOutNFSeArray, NFNFS?: DocNFNFSArray, nDocFisc?: string, nDoc?: string, xDescOutDed?: string, fornec?: TomadorArray}
 */
final readonly class DocDedRed
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public TipoDedRed $tpDedRed,
        public string $dtEmiDoc,
        public string $vDedutivelRedutivel,
        public string $vDeducaoReducao,
        public ?string $chNFSe = null,
        public ?string $chNFe = null,
        public ?DocOutNFSe $NFSeMun = null,
        public ?DocNFNFS $NFNFS = null,
        public ?string $nDocFisc = null,
        public ?string $nDoc = null,
        public ?string $xDescOutDed = null,
        public ?Tomador $fornec = null,
    ) {
        self::validateChoice(
            ['chNFSe' => $chNFSe, 'chNFe' => $chNFe, 'NFSeMun' => $NFSeMun, 'NFNFS' => $NFNFS, 'nDocFisc' => $nDocFisc, 'nDoc' => $nDoc],
            expected: 1,
            message: 'DocDedRed requer exatamente um tipo de documento.',
        );
    }

    /** @phpstan-param DocDedRedArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tpDedRed: TipoDedRed::from($data['tpDedRed']),
            dtEmiDoc: $data['dtEmiDoc'],
            vDedutivelRedutivel: $data['vDedutivelRedutivel'],
            vDeducaoReducao: $data['vDeducaoReducao'],
            chNFSe: $data['chNFSe'] ?? null,
            chNFe: $data['chNFe'] ?? null,
            NFSeMun: isset($data['NFSeMun']) ? DocOutNFSe::fromArray($data['NFSeMun']) : null,
            NFNFS: isset($data['NFNFS']) ? DocNFNFS::fromArray($data['NFNFS']) : null,
            nDocFisc: $data['nDocFisc'] ?? null,
            nDoc: $data['nDoc'] ?? null,
            xDescOutDed: $data['xDescOutDed'] ?? null,
            fornec: isset($data['fornec']) ? Tomador::fromArray($data['fornec']) : null,
        );
    }
}
