<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Events;

use Throwable;

/**
 * @api
 */
final readonly class NfseFailed
{
    /**
     * @param  Throwable|null  $throwable  a falha inteira, não só a mensagem: em
     *                                     `IndeterminateResultException` é por onde
     *                                     o listener alcança `statusCode`, `body` e
     *                                     `raw` sem depender do `catch` do chamador
     */
    public function __construct(
        public string $operacao,
        public string $mensagem,
        public ?Throwable $throwable = null,
    ) {}
}
