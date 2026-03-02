<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

/**
 * @phpstan-import-type DocDedRedArray from DocDedRed
 *
 * @phpstan-type InfoDedRedArray array{pDR?: string, vDR?: string, documentos?: list<DocDedRedArray>}
 */
final readonly class InfoDedRed
{
    use ValidatesExclusiveChoice;

    /** @param list<DocDedRed>|null $documentos */
    public function __construct(
        public ?string $pDR = null,
        public ?string $vDR = null,
        public ?array $documentos = null,
    ) {
        if ($documentos !== null && $documentos === []) {
            throw new InvalidDpsArgument('documentos deve conter ao menos um item.');
        }

        self::validateChoice(
            ['pDR' => $pDR, 'vDR' => $vDR, 'documentos' => $documentos],
            expected: 1,
            message: 'InfoDedRed requer exatamente um entre pDR, vDR ou documentos.',
        );
    }

    /** @phpstan-param InfoDedRedArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            pDR: $data['pDR'] ?? null,
            vDR: $data['vDR'] ?? null,
            documentos: isset($data['documentos']) ? array_map(DocDedRed::fromArray(...), $data['documentos']) : null,
        );
    }
}
