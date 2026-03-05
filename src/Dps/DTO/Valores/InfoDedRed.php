<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
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
            ['percentual de dedução/redução (pDR)' => $pDR, 'valor de dedução/redução (vDR)' => $vDR, 'documentos' => $documentos],
            expected: 1,
            path: 'infDPS/valores/vDedRed',
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
