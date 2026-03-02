<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

/**
 * @phpstan-type EnderecoExteriorObraArray array{cEndPost: string, xCidade: string, xEstProvReg: string}
 */
final readonly class EnderecoExteriorObra
{
    public function __construct(
        public string $cEndPost,
        public string $xCidade,
        public string $xEstProvReg,
    ) {}

    /** @phpstan-param EnderecoExteriorObraArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
