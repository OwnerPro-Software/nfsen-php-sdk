<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Pipeline\Concerns;

use InvalidArgumentException;

trait ValidatesChaveAcesso
{
    private function validateChaveAcesso(string $chave): void
    {
        // /D: sem ele, `$` casa também antes de um \n final, e uma chave com quebra
        // de linha passaria daqui direto para a interpolação na URL.
        if (! preg_match('/^\d{50}$/D', $chave)) {
            throw new InvalidArgumentException(sprintf("chaveAcesso inválida: '%s'. Esperado: exatamente 50 dígitos numéricos.", $chave));
        }
    }
}
