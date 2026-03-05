<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Serv;

enum MdPrestacao: string
{
    case Desconhecido = '0';
    case Transfronteirico = '1';
    case ConsumoBrasil = '2';
    case PresencaComercialExterior = '3';
    case MovimentoTemporarioPessoasFisicas = '4';
}
