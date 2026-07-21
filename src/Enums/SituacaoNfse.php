<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

/**
 * Situação da NFS-e (`infNFSe/cStat`, simpleType `TStat`).
 *
 * A NT 008, item 2.4.5, exige o campo "SITUAÇÃO DA NFS-E" no bloco de identificação
 * do DANFSe, com a descrição da opção — não o código.
 *
 * @api
 */
enum SituacaoNfse: string
{
    use HasLabelOf;

    case Gerada = '100';
    case DecisaoJudicial = '102';
    case Avulsa = '103';
    case Mei = '107';

    /** Rótulos transcritos do `<xs:documentation>` de `TStat` em `tiposSimples_v1.01.xsd`. */
    public function label(): string
    {
        return match ($this) {
            self::Gerada => 'NFS-e Gerada',
            self::DecisaoJudicial => 'NFS-e de Decisão Judicial',
            self::Avulsa => 'NFS-e Avulsa',
            self::Mei => 'NFS-e MEI',
        };
    }
}
