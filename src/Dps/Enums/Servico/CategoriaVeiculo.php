<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Servico;

enum CategoriaVeiculo: string
{
    case NaoInformado = '00';
    case AutomovelCaminhonete = '01';
    case CaminhaoLeveOnibus = '02';
    case AutomovelSemireboque = '03';
    case CaminhaoTrator = '04';
    case AutomovelReboque = '05';
    case CaminhaoReboque = '06';
    case CaminhaoTratorSemireboque = '07';
    case Motocicletas = '08';
    case VeiculoEspecial = '09';
    case VeiculoIsento = '10';
}
