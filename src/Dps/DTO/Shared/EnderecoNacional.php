<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Shared;

/**
 * @phpstan-type EnderecoNacionalArray array{cMun: string, CEP: string}
 */
final readonly class EnderecoNacional
{
    public function __construct(
        public string $cMun,
        public string $CEP,
    ) {}

    /** @phpstan-param EnderecoNacionalArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
