<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\InfDPS;

use OwnerPro\Nfsen\Dps\Enums\InfDPS\CMotivoEmisTI;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\TpEmit;
use OwnerPro\Nfsen\Enums\NfseAmbiente;

/**
 * @phpstan-type InfDPSArray array{tpAmb: string, dhEmi: string, verAplic: string, serie: string, nDPS: string, dCompet: string, tpEmit: string, cLocEmi: string, cMotivoEmisTI?: string, chNFSeRej?: string}
 */
final readonly class InfDPS
{
    public function __construct(
        public NfseAmbiente $tpAmb,
        public string $dhEmi,
        public string $verAplic,
        public string $serie,
        public string $nDPS,
        public string $dCompet,
        public TpEmit $tpEmit,
        public string $cLocEmi,
        public ?CMotivoEmisTI $cMotivoEmisTI = null,
        public ?string $chNFSeRej = null,
    ) {}

    /** @phpstan-param InfDPSArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tpAmb: NfseAmbiente::from($data['tpAmb']),
            dhEmi: $data['dhEmi'],
            verAplic: $data['verAplic'],
            serie: $data['serie'],
            nDPS: (string) $data['nDPS'],
            dCompet: $data['dCompet'],
            tpEmit: TpEmit::from($data['tpEmit']),
            cLocEmi: $data['cLocEmi'],
            cMotivoEmisTI: isset($data['cMotivoEmisTI']) ? CMotivoEmisTI::from($data['cMotivoEmisTI']) : null,
            chNFSeRej: $data['chNFSeRej'] ?? null,
        );
    }
}
