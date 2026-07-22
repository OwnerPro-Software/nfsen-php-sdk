<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Pipeline\Concerns;

use Closure;
use OwnerPro\Nfsen\Events\NfseFailed;
use Throwable;

trait DispatchesEvents
{
    private function dispatchEvent(object $event): void
    {
        if (function_exists('event')) {
            try {
                event($event);
            } catch (Throwable $e) {
                // report() também resolve do container: com o framework
                // instalado mas não bootado (standalone), lançaria — e evento
                // é best-effort, jamais pode derrubar a operação.
                if (function_exists('report')) {
                    try {
                        report($e);
                    } catch (Throwable) {
                        // sem handler disponível, a falha fica sem reporte
                    }
                }
            }
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    private function withFailureEvent(string $operacao, Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable $throwable) {
            $this->dispatchEvent(new NfseFailed($operacao, $throwable->getMessage()));
            throw $throwable;
        }
    }
}
