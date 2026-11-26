<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

use InvalidArgumentException;

/**
 * @api
 */
enum Leiaute: string
{
    case V101 = 'v1.01';
    case Nt009 = 'nt009';

    public static function fromConfig(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw new InvalidArgumentException(
                sprintf("Leiaute NFSe inválido: '%s'. Valores aceitos: 'v1.01', 'nt009'.", $value)
            );
    }

    public function schemasPath(string $basePath): string
    {
        return match ($this) {
            self::V101 => $basePath,
            self::Nt009 => $basePath.'/nt009',
        };
    }
}
