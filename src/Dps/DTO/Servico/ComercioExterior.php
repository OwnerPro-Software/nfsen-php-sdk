<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Servico;

use Pulsar\NfseNacional\Dps\Enums\Servico\Mdic;
use Pulsar\NfseNacional\Dps\Enums\Servico\MdPrestacao;
use Pulsar\NfseNacional\Dps\Enums\Servico\MecAFComexP;
use Pulsar\NfseNacional\Dps\Enums\Servico\MecAFComexT;
use Pulsar\NfseNacional\Dps\Enums\Servico\MovTempBens;
use Pulsar\NfseNacional\Dps\Enums\Servico\VincPrest;

/**
 * @phpstan-type ComercioExteriorArray array{mdPrestacao: string, vincPrest: string, tpMoeda: string, vServMoeda: string, mecAFComexP: string, mecAFComexT: string, movTempBens: string, mdic: string, nDI?: string, nRE?: string}
 */
final readonly class ComercioExterior
{
    public function __construct(
        public MdPrestacao $mdPrestacao,
        public VincPrest $vincPrest,
        public string $tpMoeda,
        public string $vServMoeda,
        public MecAFComexP $mecAFComexP,
        public MecAFComexT $mecAFComexT,
        public MovTempBens $movTempBens,
        public Mdic $mdic,
        public ?string $nDI = null,
        public ?string $nRE = null,
    ) {}

    /** @phpstan-param ComercioExteriorArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mdPrestacao: MdPrestacao::from($data['mdPrestacao']),
            vincPrest: VincPrest::from($data['vincPrest']),
            tpMoeda: $data['tpMoeda'],
            vServMoeda: $data['vServMoeda'],
            mecAFComexP: MecAFComexP::from($data['mecAFComexP']),
            mecAFComexT: MecAFComexT::from($data['mecAFComexT']),
            movTempBens: MovTempBens::from($data['movTempBens']),
            mdic: Mdic::from($data['mdic']),
            nDI: $data['nDI'] ?? null,
            nRE: $data['nRE'] ?? null,
        );
    }
}
