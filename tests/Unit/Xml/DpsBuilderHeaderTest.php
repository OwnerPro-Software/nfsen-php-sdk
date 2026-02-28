<?php

use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Xml\DpsBuilder;

it('builds xml with DPS root element', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<DPS ');
    expect($xml)->toContain('versao=');
    expect($xml)->toContain('xmlns="http://www.sped.fazenda.gov.br/nfse"');
})->with('dpsData');

it('builds xml with infDPS Id', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<infDPS Id="DPS');
})->with('dpsData');

it('includes tpAmb in infDPS', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<tpAmb>2</tpAmb>');
})->with('dpsData');

it('includes cMotivoEmisTI when set', function () {
    $infDps           = new stdClass();
    $infDps->tpamb    = 2;
    $infDps->dhemi    = '2026-02-27T10:00:00-03:00';
    $infDps->veraplic = '1.0';
    $infDps->serie    = '1';
    $infDps->ndps     = 1;
    $infDps->dcompet  = '2026-02-27';
    $infDps->tpemit   = 1;
    $infDps->clocemi  = '3501608';
    $infDps->cmotivoemisti = '1';

    $prestador        = new stdClass();
    $prestador->cnpj  = '12345678000195';
    $regTrib             = new stdClass();
    $regTrib->opsimpnac  = 1;
    $regTrib->regesptrib = 0;
    $prestador->regtrib  = $regTrib;

    $servico                         = new stdClass();
    $servico->locprest               = new stdClass();
    $servico->locprest->clocprestacao = '3501608';
    $servico->cserv                  = new stdClass();
    $servico->cserv->ctribnac        = '010101';
    $servico->cserv->xdescserv       = 'Serviço';

    $data = new DpsData($infDps, $prestador, new stdClass(), $servico, new stdClass());

    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<cMotivoEmisTI>1</cMotivoEmisTI>');
});

it('includes chNFSeRej when set', function () {
    $infDps           = new stdClass();
    $infDps->tpamb    = 2;
    $infDps->dhemi    = '2026-02-27T10:00:00-03:00';
    $infDps->veraplic = '1.0';
    $infDps->serie    = '1';
    $infDps->ndps     = 1;
    $infDps->dcompet  = '2026-02-27';
    $infDps->tpemit   = 1;
    $infDps->clocemi  = '3501608';
    $infDps->chnfserej = 'CHAVE_REJEITADA_123';

    $prestador        = new stdClass();
    $prestador->cnpj  = '12345678000195';
    $regTrib             = new stdClass();
    $regTrib->opsimpnac  = 1;
    $regTrib->regesptrib = 0;
    $prestador->regtrib  = $regTrib;

    $servico                         = new stdClass();
    $servico->locprest               = new stdClass();
    $servico->locprest->clocprestacao = '3501608';
    $servico->cserv                  = new stdClass();
    $servico->cserv->ctribnac        = '010101';
    $servico->cserv->xdescserv       = 'Serviço';

    $data = new DpsData($infDps, $prestador, new stdClass(), $servico, new stdClass());

    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<chNFSeRej>CHAVE_REJEITADA_123</chNFSeRej>');
});

it('includes toma element when tomador has data', function () {
    $infDps           = new stdClass();
    $infDps->tpamb    = 2;
    $infDps->dhemi    = '2026-02-27T10:00:00-03:00';
    $infDps->veraplic = '1.0';
    $infDps->serie    = '1';
    $infDps->ndps     = 1;
    $infDps->dcompet  = '2026-02-27';
    $infDps->tpemit   = 1;
    $infDps->clocemi  = '3501608';

    $prestador        = new stdClass();
    $prestador->cnpj  = '12345678000195';
    $regTrib             = new stdClass();
    $regTrib->opsimpnac  = 1;
    $regTrib->regesptrib = 0;
    $prestador->regtrib  = $regTrib;

    $tomador        = new stdClass();
    $tomador->cnpj  = '98765432000111';
    $tomador->xnome = 'Tomador Ltda';

    $servico                         = new stdClass();
    $servico->locprest               = new stdClass();
    $servico->locprest->clocprestacao = '3501608';
    $servico->cserv                  = new stdClass();
    $servico->cserv->ctribnac        = '010101';
    $servico->cserv->xdescserv       = 'Serviço';

    $data = new DpsData($infDps, $prestador, $tomador, $servico, new stdClass());

    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)
        ->toContain('<toma>')
        ->toContain('<CNPJ>98765432000111</CNPJ>')
        ->toContain('<xNome>Tomador Ltda</xNome>');
});

it('skips XSD validation when scheme file does not exist', function (DpsData $data) {
    $builder = new DpsBuilder('/nonexistent/path');
    $xml     = $builder->buildAndValidate($data);

    // Should not throw — just returns the XML
    expect($xml)->toContain('<DPS ');
})->with('dpsData');

it('throws NfseException on invalid XSD', function () {
    $infDps           = new stdClass();
    $infDps->tpamb    = 2;
    $infDps->dhemi    = '2026-02-27T10:00:00-03:00';
    $infDps->veraplic = '1.0';
    $infDps->serie    = '1';
    $infDps->ndps     = 1;
    $infDps->dcompet  = '2026-02-27';
    $infDps->tpemit   = 1;
    $infDps->clocemi  = '3501608';

    $prestador        = new stdClass();
    $prestador->cnpj  = '12345678000195';
    $regTrib             = new stdClass();
    $regTrib->opsimpnac  = 1;
    $regTrib->regesptrib = 0;
    $prestador->regtrib  = $regTrib;

    // serviço incomplete on purpose — no locprest
    $servico              = new stdClass();
    $servico->locprest    = new stdClass();
    $servico->locprest->clocprestacao = '3501608';
    $servico->cserv       = new stdClass();
    $servico->cserv->ctribnac  = 'INVALID_LONG_VALUE_THAT_WILL_FAIL_XSD_VALIDATION_BECAUSE_IT_EXCEEDS_MAX_LENGTH';
    $servico->cserv->xdescserv = 'Serviço';

    $data = new DpsData($infDps, $prestador, new stdClass(), $servico, new stdClass());

    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');

    expect(fn () => $builder->buildAndValidate($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'XML inválido');
});

it('generates correct Id for CNPJ prestador', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('Id="DPS350160821234567800019500001000000000000001"');
})->with('dpsData');

it('generates correct Id for CPF prestador', function () {
    $infDps           = new stdClass();
    $infDps->tpamb    = 2;
    $infDps->dhemi    = '2026-02-27T10:00:00-03:00';
    $infDps->veraplic = '1.0';
    $infDps->serie    = '1';
    $infDps->ndps     = 1;
    $infDps->dcompet  = '2026-02-27';
    $infDps->tpemit   = 1;
    $infDps->clocemi  = '3501608';

    $prestador        = new stdClass();
    $prestador->cpf   = '12345678901';
    $prestador->xnome = 'Pessoa Física';
    $regTrib             = new stdClass();
    $regTrib->opsimpnac  = 0;
    $regTrib->regesptrib = 0;
    $prestador->regtrib  = $regTrib;

    $servico                          = new stdClass();
    $servico->locprest                = new stdClass();
    $servico->locprest->clocprestacao = '3501608';
    $servico->cserv                   = new stdClass();
    $servico->cserv->ctribnac         = '010101';
    $servico->cserv->xdescserv        = 'Serviço';

    $data = new DpsData($infDps, $prestador, new stdClass(), $servico, new stdClass());

    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('Id="DPS350160810001234567890100001000000000000001"');
});
