<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Shared;

/**
 * @phpstan-type EnderecoExteriorArray array{cPais: string, cEndPost: string, xCidade: string, xEstProvReg: string}
 */
final readonly class EnderecoExterior
{
    public function __construct(
        public string $cPais,
        public string $cEndPost,
        public string $xCidade,
        public string $xEstProvReg,
    ) {}

    /** @phpstan-param EnderecoExteriorArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
