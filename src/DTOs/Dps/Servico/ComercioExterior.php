<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

use Pulsar\NfseNacional\Enums\Dps\Servico\MDIC;
use Pulsar\NfseNacional\Enums\Dps\Servico\MecAFComexP;
use Pulsar\NfseNacional\Enums\Dps\Servico\MecAFComexT;
use Pulsar\NfseNacional\Enums\Dps\Servico\ModoPrestacao;
use Pulsar\NfseNacional\Enums\Dps\Servico\MovTempBens;
use Pulsar\NfseNacional\Enums\Dps\Servico\VinculoPrestacao;

/**
 * @phpstan-type ComercioExteriorArray array{mdPrestacao: string, vincPrest: string, tpMoeda: string, vServMoeda: string, mecAFComexP: string, mecAFComexT: string, movTempBens: string, mdic: string, nDI?: string, nRE?: string}
 */
final readonly class ComercioExterior
{
    public function __construct(
        public ModoPrestacao $mdPrestacao,
        public VinculoPrestacao $vincPrest,
        public string $tpMoeda,
        public string $vServMoeda,
        public MecAFComexP $mecAFComexP,
        public MecAFComexT $mecAFComexT,
        public MovTempBens $movTempBens,
        public MDIC $mdic,
        public ?string $nDI = null,
        public ?string $nRE = null,
    ) {}

    /** @phpstan-param ComercioExteriorArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mdPrestacao: ModoPrestacao::from($data['mdPrestacao']),
            vincPrest: VinculoPrestacao::from($data['vincPrest']),
            tpMoeda: $data['tpMoeda'],
            vServMoeda: $data['vServMoeda'],
            mecAFComexP: MecAFComexP::from($data['mecAFComexP']),
            mecAFComexT: MecAFComexT::from($data['mecAFComexT']),
            movTempBens: MovTempBens::from($data['movTempBens']),
            mdic: MDIC::from($data['mdic']),
            nDI: $data['nDI'] ?? null,
            nRE: $data['nRE'] ?? null,
        );
    }
}
