<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

use Pulsar\NfseNacional\Dps\Enums\IBSCBS\FinNFSe;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\IndDest;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\IndFinal;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TpEnteGov;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TpOper;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

/**
 * @phpstan-import-type InfoValoresIBSCBSArray from InfoValoresIBSCBS
 * @phpstan-import-type InfoDestArray from InfoDest
 * @phpstan-import-type InfoImovelArray from InfoImovel
 *
 * @phpstan-type InfoIBSCBSArray array{finNFSe: string, indFinal: string, cIndOp: string, indDest: string, valores: InfoValoresIBSCBSArray, tpOper?: string, refNFSe?: list<string>, tpEnteGov?: string, dest?: InfoDestArray, imovel?: InfoImovelArray}
 */
final readonly class InfoIBSCBS
{
    /** @param list<string>|null $refNFSe */
    public function __construct(
        public FinNFSe $finNFSe,
        public IndFinal $indFinal,
        public string $cIndOp,
        public IndDest $indDest,
        public InfoValoresIBSCBS $valores,
        public ?TpOper $tpOper = null,
        public ?array $refNFSe = null,
        public ?TpEnteGov $tpEnteGov = null,
        public ?InfoDest $dest = null,
        public ?InfoImovel $imovel = null,
    ) {
        if ($refNFSe !== null && $refNFSe === []) {
            throw new InvalidDpsArgument('refNFSe deve conter ao menos um item.');
        }
    }

    /** @phpstan-param InfoIBSCBSArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            finNFSe: FinNFSe::from($data['finNFSe']),
            indFinal: IndFinal::from($data['indFinal']),
            cIndOp: $data['cIndOp'],
            indDest: IndDest::from($data['indDest']),
            valores: InfoValoresIBSCBS::fromArray($data['valores']),
            tpOper: isset($data['tpOper']) ? TpOper::from($data['tpOper']) : null,
            refNFSe: $data['refNFSe'] ?? null,
            tpEnteGov: isset($data['tpEnteGov']) ? TpEnteGov::from($data['tpEnteGov']) : null,
            dest: isset($data['dest']) ? InfoDest::fromArray($data['dest']) : null,
            imovel: isset($data['imovel']) ? InfoImovel::fromArray($data['imovel']) : null,
        );
    }
}
