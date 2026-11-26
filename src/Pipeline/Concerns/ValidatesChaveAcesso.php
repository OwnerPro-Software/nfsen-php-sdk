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
        if (! preg_match('/^\d{9}[0-9A-Z]{14}\d{27}$/D', $chave)) {
            throw new InvalidArgumentException(sprintf(
                "chaveAcesso inválida: '%s'. Esperado: 50 posições numéricas, admitidas letras maiúsculas apenas na inscrição federal (posições 10 a 23).",
                $chave,
            ));
        }
    }
}
