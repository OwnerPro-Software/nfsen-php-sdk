<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Serv;

use OwnerPro\Nfsen\Dps\Enums\Serv\Mdic;
use OwnerPro\Nfsen\Dps\Enums\Serv\MdPrestacao;
use OwnerPro\Nfsen\Dps\Enums\Serv\MecAFComexP;
use OwnerPro\Nfsen\Dps\Enums\Serv\MecAFComexT;
use OwnerPro\Nfsen\Dps\Enums\Serv\MovTempBens;
use OwnerPro\Nfsen\Dps\Enums\Serv\VincPrest;

/**
 * @phpstan-type ComExtArray array{mdPrestacao: string, vincPrest: string, tpMoeda: string, vServMoeda: string, mecAFComexP: string, mecAFComexT: string, movTempBens: string, mdic: string, nDI?: string, nRE?: string}
 */
final readonly class ComExt
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

    /** @phpstan-param ComExtArray $data */
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
