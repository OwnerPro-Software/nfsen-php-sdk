<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Support;

use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

/**
 * Fonte única da regra de formação do identificador da DPS (simpleType TSIdDPS,
 * `storage/schemes/tiposSimples_v1.01.xsd`):
 *
 * "DPS" + Cód.Mun (7) + Tipo de Inscrição Federal (1: CPF=1, CNPJ=2)
 *       + Inscrição Federal (14, zero-pad à esquerda) + Série DPS (5, zero-pad)
 *       + Núm. DPS (15, zero-pad)
 *
 * Útil para reconciliação pós-timeout: permite recalcular o ID de uma DPS já
 * enviada (ou não) sem reconstruir o XML, para uso em `consultar()->dps($id)`.
 *
 * @api
 */
final readonly class DpsId
{
    private const string PATTERN = '/^DPS\d{42}$/';

    /**
     * @param  bool  $allowEmptyInscricao  permite CNPJ e CPF ambos null, gerando
     *                                     inscrição zerada — válido APENAS para
     *                                     prestador estrangeiro (NIF/cNaoNIF), no
     *                                     caminho interno do DpsBuilder. Na chamada
     *                                     manual (reconciliação), informe o mesmo
     *                                     CNPJ ou CPF usado na emissão.
     */
    public static function generate(
        string $cLocEmi,
        ?string $cnpj,
        ?string $cpf,
        string $serie,
        string $nDps,
        bool $allowEmptyInscricao = false,
    ): string {
        if ($cnpj === null && $cpf === null && ! $allowEmptyInscricao) {
            throw new InvalidDpsArgument(
                'Informe o CNPJ ou o CPF do emitente para gerar o identificador da DPS. '
                .'Inscrição zerada só é válida para prestador estrangeiro (NIF/cNaoNIF) — use allowEmptyInscricao: true.',
            );
        }

        $id = 'DPS';
        $id .= substr($cLocEmi, 0, 7);
        $id .= $cnpj !== null ? '2' : '1';
        $id .= str_pad($cnpj ?? $cpf ?? '', 14, '0', STR_PAD_LEFT);
        $id .= str_pad($serie, 5, '0', STR_PAD_LEFT);
        $id .= str_pad($nDps, 15, '0', STR_PAD_LEFT);

        if (preg_match(self::PATTERN, $id) !== 1) {
            throw new InvalidDpsArgument(sprintf(
                'Identificador de DPS gerado é inválido: "%s". Esperado o padrão DPS[0-9]{42} (TSIdDPS).',
                $id,
            ));
        }

        return $id;
    }
}
