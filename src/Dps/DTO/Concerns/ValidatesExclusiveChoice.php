<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Concerns;

use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

trait ValidatesExclusiveChoice
{
    /** @param array<string, mixed> $fields */
    private static function validateChoice(array $fields, int $expected): void
    {
        $filled = array_keys(array_filter($fields, fn (mixed $v): bool => $v !== null));
        $count = count($filled);

        if ($count === $expected) {
            return;
        }

        $options = implode(', ', array_keys($fields));
        $rule = "Somente {$expected} dos seguintes campos deve ser informado: {$options}.";

        $detail = $count === 0
            ? ' Nenhum foi informado.'
            : ' Informados: '.implode(', ', $filled).'.';

        throw new InvalidDpsArgument($rule.$detail);
    }

    /** @param array<string, mixed> $fields */
    private static function validateAtMostOne(array $fields, string $message): void
    {
        if (count(array_filter($fields, fn (mixed $v): bool => $v !== null)) > 1) {
            throw new InvalidDpsArgument($message);
        }
    }
}
