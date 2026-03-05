<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Serv;

enum MecAFComexT: string
{
    case Desconhecido = '00';
    case Nenhum = '01';
    case AdmPublicaReprInternacional = '02';
    case AlugueisArrendamento = '03';
    case ArrendamentoAeronave = '04';
    case ComissaoAgentesExternos = '05';
    case DespesasArmazenagemTransporte = '06';
    case EventosFIFASubsidiaria = '07';
    case EventosFIFA = '08';
    case FretesArrendamentos = '09';
    case MaterialAeronautico = '10';
    case PromocaoBensExterior = '11';
    case PromocaoDestinosTuristicos = '12';
    case PromocaoBrasilExterior = '13';
    case PromocaoServicosExterior = '14';
    case RECINE = '15';
    case RECOPA = '16';
    case RegistroMarcasPatentes = '17';
    case REICOMP = '18';
    case REIDI = '19';
    case REPENEC = '20';
    case REPES = '21';
    case RETAERO = '22';
    case RETID = '23';
    case RoyaltiesAssistenciaTecnica = '24';
    case ServicosAvaliacaoOMC = '25';
    case ZPE = '26';
}
