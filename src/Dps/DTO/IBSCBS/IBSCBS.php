<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\IBSCBS;

use OwnerPro\Nfsen\Dps\Enums\IBSCBS\FinNFSe;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\IndDest;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\IndFinal;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\TpEnteGov;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\TpOper;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

/**
 * @phpstan-import-type ValoresArray from Valores
 * @phpstan-import-type DestArray from Dest
 * @phpstan-import-type ImovelArray from Imovel
 *
 * @phpstan-type IBSCBSArray array{finNFSe: string, cIndOp: string, indDest: string, valores: ValoresArray, indFinal?: string, tpOper?: string, refNFSe?: list<string>, tpEnteGov?: string, dest?: DestArray, imovel?: ImovelArray}
 */
final readonly class IBSCBS
{
    /** @param list<string>|null $refNFSe */
    public function __construct(
        public FinNFSe $finNFSe,
        public string $cIndOp,
        public IndDest $indDest,
        public Valores $valores,
        public ?IndFinal $indFinal = null,
        public ?TpOper $tpOper = null,
        public ?array $refNFSe = null,
        public ?TpEnteGov $tpEnteGov = null,
        public ?Dest $dest = null,
        public ?Imovel $imovel = null,
    ) {
        if ($refNFSe !== null && $refNFSe === []) {
            throw new InvalidDpsArgument('refNFSe deve conter ao menos um item.');
        }
    }

    /** @phpstan-param IBSCBSArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            finNFSe: FinNFSe::from($data['finNFSe']),
            cIndOp: $data['cIndOp'],
            indDest: IndDest::from($data['indDest']),
            valores: Valores::fromArray($data['valores']),
            indFinal: isset($data['indFinal']) ? IndFinal::from($data['indFinal']) : null,
            tpOper: isset($data['tpOper']) ? TpOper::from($data['tpOper']) : null,
            refNFSe: $data['refNFSe'] ?? null,
            tpEnteGov: isset($data['tpEnteGov']) ? TpEnteGov::from($data['tpEnteGov']) : null,
            dest: isset($data['dest']) ? Dest::fromArray($data['dest']) : null,
            imovel: isset($data['imovel']) ? Imovel::fromArray($data['imovel']) : null,
        );
    }
}
