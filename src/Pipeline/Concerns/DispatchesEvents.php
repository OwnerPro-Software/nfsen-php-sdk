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
                if (function_exists('report')) {
                    report($e);
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
