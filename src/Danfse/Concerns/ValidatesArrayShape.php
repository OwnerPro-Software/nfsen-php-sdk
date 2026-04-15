<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Concerns;

use InvalidArgumentException;

trait ValidatesArrayShape
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowed
     */
    private static function rejectUnknownKeys(array $data, array $allowed, string $context): void
    {
        $unknown = array_values(array_diff(array_keys($data), $allowed)); // @pest-mutate-ignore UnwrapArrayValues — array_values preserva contrato list<string>; saída de implode idêntica.

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                sprintf('%s: chave(s) desconhecida(s): %s', $context, implode(', ', $unknown)),
            );
        }
    }
}
