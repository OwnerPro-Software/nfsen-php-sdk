<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use InvalidArgumentException;
use RuntimeException;

/**
 * @internal
 */
final class LogoLoader
{
    public static function toDataUri(string $path): string
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException('Arquivo de logo não encontrado ou ilegível: '.$path);
        }

        $mime = mime_content_type($path) ?: 'image/png'; // @pest-mutate-ignore TernaryNegated — fallback é o mesmo mime usado nos fixtures; mutação não observável.
        $contents = file_get_contents($path);

        // Defensivo: is_readable já passou, mas file_get_contents pode falhar
        // em condições extremas de filesystem (permissão alterada entre chamadas, disco corrompido).
        // @codeCoverageIgnoreStart
        if ($contents === false) { // @pest-mutate-ignore FalseToTrue,IdenticalToNotIdentical,IfNegated — defensivo; inatingível em cenário real após is_readable.
            throw new RuntimeException('Não foi possível ler o arquivo de logo: '.$path); // @pest-mutate-ignore ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — mensagem da exceção defensiva; nunca disparada em teste.
        }

        // @codeCoverageIgnoreEnd

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
