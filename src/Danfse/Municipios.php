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

    /**
     * @return array<int,array{nome:string,uf:string}>
     *
     * @codeCoverageIgnore
     *
     * Infraestrutura de boot: `$map` estático é populado uma vez por processo. Quando outro
     * teste roda antes de `MunicipiosTest` no mesmo processo (via `DanfseDataBuilder` → `lookup()`),
     * o cache fica quente e `load()` nunca mais é invocado dentro de um teste com `covers(Municipios::class)`.
     * Comportamento do load é verificado indiretamente pelos tests de `lookup()` (dados corretos → JSON carregado OK).
     */
    private static function load(): array
    {
        $path = __DIR__.'/../../storage/ibge-municipios.json';
        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException('Não foi possível ler a tabela IBGE: '.$path);
        }

        /** @var array<int,array{nome:string,uf:string}> $decoded */
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
