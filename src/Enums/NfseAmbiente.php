<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums;

enum NfseAmbiente: int
{
    case PRODUCAO    = 1;
    case HOMOLOGACAO = 2;

    public static function fromConfig(int|string $v): self
    {
        if (is_int($v) || ctype_digit($v)) {
            return self::from((int) $v);
        }

        return match(strtolower($v)) {
            'producao', 'production'     => self::PRODUCAO,
            'homologacao', 'homologation' => self::HOMOLOGACAO,
            default => throw new \InvalidArgumentException(
                sprintf("Ambiente NFSe inválido: '%s'. Valores aceitos: 1, 2, 'producao', 'homologacao'.", $v)
            ),
        };
    }
}
