<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\InfDPS;

use Pulsar\NfseNacional\Dps\Enums\InfDPS\MotivoEmissaoTI;
use Pulsar\NfseNacional\Dps\Enums\InfDPS\TipoEmitente;
use Pulsar\NfseNacional\Enums\NfseAmbiente;

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
        public TipoEmitente $tpEmit,
        public string $cLocEmi,
        public ?MotivoEmissaoTI $cMotivoEmisTI = null,
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
            tpEmit: TipoEmitente::from($data['tpEmit']),
            cLocEmi: $data['cLocEmi'],
            cMotivoEmisTI: isset($data['cMotivoEmisTI']) ? MotivoEmissaoTI::from($data['cMotivoEmisTI']) : null,
            chNFSeRej: $data['chNFSeRej'] ?? null,
        );
    }
}
