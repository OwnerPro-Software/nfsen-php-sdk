<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

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
}
