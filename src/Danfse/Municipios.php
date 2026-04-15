<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use RuntimeException;

/**
 * Lookup IBGE → "Nome - UF".
 *
 * Dados de storage/ibge-municipios.json (origem: kelvins/municipios-brasileiros, MIT).
 *
 * @internal Tabela estática carregada uma vez por processo.
 */
final class Municipios
{
    /** @var array<int,array{nome:string,uf:string}>|null */
    private static ?array $map = null;

    public static function lookup(string|int $cMun): string
    {
        self::$map ??= self::load(); // @pest-mutate-ignore CoalesceEqualToEqual — idempotente; remover o guard apenas recarrega o JSON a cada chamada.

        $entry = self::$map[(int) $cMun] ?? null; // @pest-mutate-ignore RemoveIntegerCast — PHP normaliza chaves numéricas string→int; cast reflete a invariante.

        return $entry !== null ? $entry['nome'].' - '.$entry['uf'] : '-';
    }

    /** @return array<int,array{nome:string,uf:string}> */
    private static function load(): array
    {
        $path = __DIR__.'/../../storage/ibge-municipios.json';
        $json = file_get_contents($path);

        // @codeCoverageIgnoreStart
        if ($json === false) { // @pest-mutate-ignore FalseToTrue,IdenticalToNotIdentical,IfNegated — defensivo; arquivo é parte do pacote.
            throw new RuntimeException('Não foi possível ler a tabela IBGE: '.$path); // @pest-mutate-ignore ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — mensagem da exceção defensiva.
        }

        // @codeCoverageIgnoreEnd

        /** @var array<int,array{nome:string,uf:string}> $decoded */
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
