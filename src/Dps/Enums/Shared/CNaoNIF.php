<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Shared;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum CNaoNIF: string
{
    use HasLabelOf;

    case NaoInformado = '0';
    case Dispensado = '1';
    case NaoExigencia = '2';

    /**
     * Rótulos transcritos literalmente do `<xs:documentation>` de `TSCodNaoNIF` em
     * `storage/schemes/tiposSimples_v1.01.xsd`, que é a fonte de verdade — o texto sai
     * impresso na DANFSe no lugar da identificação do participante.
     */
    public function label(): string
    {
        return match ($this) {
            self::NaoInformado => 'Não informado na nota de origem',
            self::Dispensado => 'Dispensado do NIF',
            self::NaoExigencia => 'Não exigência do NIF',
        };
    }
}
