<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

/**
 * @phpstan-import-type InfoTributosIBSCBSArray from InfoTributosIBSCBS
 * @phpstan-import-type InfoReeRepResArray from InfoReeRepRes
 *
 * @phpstan-type InfoValoresIBSCBSArray array{trib: InfoTributosIBSCBSArray, gReeRepRes?: InfoReeRepResArray}
 */
final readonly class InfoValoresIBSCBS
{
    public function __construct(
        public InfoTributosIBSCBS $trib,
        public ?InfoReeRepRes $gReeRepRes = null,
    ) {}

    /** @phpstan-param InfoValoresIBSCBSArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            trib: InfoTributosIBSCBS::fromArray($data['trib']),
            gReeRepRes: isset($data['gReeRepRes']) ? InfoReeRepRes::fromArray($data['gReeRepRes']) : null,
        );
    }
}
