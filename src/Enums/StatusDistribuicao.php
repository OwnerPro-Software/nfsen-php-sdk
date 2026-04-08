<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

enum StatusDistribuicao: string
{
    case Rejeicao = 'REJEICAO';
    case NenhumDocumentoLocalizado = 'NENHUM_DOCUMENTO_LOCALIZADO';
    case DocumentosLocalizados = 'DOCUMENTOS_LOCALIZADOS';
}
