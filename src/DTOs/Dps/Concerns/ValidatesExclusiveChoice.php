<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Concerns;

use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

trait ValidatesExclusiveChoice
{
    /** @param array<string, mixed> $fields */
    private static function validateChoice(array $fields, int $expected, string $message): void
    {
        $count = count(array_filter($fields, fn (mixed $v): bool => $v !== null));

        if ($count !== $expected) {
            throw new InvalidDpsArgument($message);
        }
    }

    /** @param array<string, mixed> $fields */
    private static function validateAtMostOne(array $fields, string $message): void
    {
        if (count(array_filter($fields, fn (mixed $v): bool => $v !== null)) > 1) {
            throw new InvalidDpsArgument($message);
        }
    }
}
