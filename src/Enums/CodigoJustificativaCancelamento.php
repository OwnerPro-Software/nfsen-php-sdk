<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

enum CodigoJustificativaCancelamento: string
{
    case ErroEmissao = '1';
    case ServicoNaoPrestado = '2';
    case Outros = '9';
}
