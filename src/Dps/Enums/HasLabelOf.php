<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums;

/**
 * Fornece `labelOf(?string)` para backed string enums que implementem `label(): string`.
 *
 * @internal
 */
trait HasLabelOf
{
    public static function labelOf(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? '-';
    }
}
