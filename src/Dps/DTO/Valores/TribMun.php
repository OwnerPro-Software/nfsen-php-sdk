<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Valores;

use OwnerPro\Nfsen\Dps\Enums\Valores\TpImunidade;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetISSQN;
use OwnerPro\Nfsen\Dps\Enums\Valores\TribISSQN;

/**
 * @phpstan-import-type ExigSuspArray from ExigSusp
 * @phpstan-import-type BMArray from BM
 *
 * @phpstan-type TribMunArray array{tribISSQN: string, tpRetISSQN: string, cPaisResult?: string, tpImunidade?: string, exigSusp?: ExigSuspArray, BM?: BMArray, pAliq?: string}
 */
final readonly class TribMun
{
    public function __construct(
        public TribISSQN $tribISSQN,
        public TpRetISSQN $tpRetISSQN,
        public ?string $cPaisResult = null,
        public ?TpImunidade $tpImunidade = null,
        public ?ExigSusp $exigSusp = null,
        public ?BM $BM = null,
        public ?string $pAliq = null,
    ) {}

    /** @phpstan-param TribMunArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tribISSQN: TribISSQN::from($data['tribISSQN']),
            tpRetISSQN: TpRetISSQN::from($data['tpRetISSQN']),
            cPaisResult: $data['cPaisResult'] ?? null,
            tpImunidade: isset($data['tpImunidade']) ? TpImunidade::from($data['tpImunidade']) : null,
            exigSusp: isset($data['exigSusp']) ? ExigSusp::fromArray($data['exigSusp']) : null,
            BM: isset($data['BM']) ? BM::fromArray($data['BM']) : null,
            pAliq: $data['pAliq'] ?? null,
        );
    }
}
