<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\IBSCBS;

/**
 * @phpstan-import-type GTribRegularArray from GTribRegular
 * @phpstan-import-type GDifArray from GDif
 *
 * @phpstan-type GIBSCBSArray array{CST: string, cClassTrib: string, cCredPres?: string, gTribRegular?: GTribRegularArray, gDif?: GDifArray}
 */
final readonly class GIBSCBS
{
    public function __construct(
        public string $CST,
        public string $cClassTrib,
        public ?string $cCredPres = null,
        public ?GTribRegular $gTribRegular = null,
        public ?GDif $gDif = null,
    ) {}

    /** @phpstan-param GIBSCBSArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            CST: $data['CST'],
            cClassTrib: $data['cClassTrib'],
            cCredPres: $data['cCredPres'] ?? null,
            gTribRegular: isset($data['gTribRegular']) ? GTribRegular::fromArray($data['gTribRegular']) : null,
            gDif: isset($data['gDif']) ? GDif::fromArray($data['gDif']) : null,
        );
    }
}
