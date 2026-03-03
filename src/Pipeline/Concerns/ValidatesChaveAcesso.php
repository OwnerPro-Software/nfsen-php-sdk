<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Pipeline\Concerns;

use InvalidArgumentException;

trait ValidatesChaveAcesso
{
    private function validateChaveAcesso(string $chave): void
    {
        if (! preg_match('/^\d{50}$/', $chave)) {
            throw new InvalidArgumentException(sprintf("chaveAcesso inválida: '%s'. Esperado: exatamente 50 dígitos numéricos.", $chave));
        }
    }
}
