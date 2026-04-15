<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * @api
 */
final readonly class DanfseTotaisTributos
{
    public function __construct(
        public string $federais,
        public string $estaduais,
        public string $municipais,
    ) {}
}
