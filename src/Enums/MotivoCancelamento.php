<?php

namespace Pulsar\NfseNacional\Enums;

enum MotivoCancelamento: string
{
    case ErroEmissao = 'e101101';
    case Outros      = 'e105102';
}
