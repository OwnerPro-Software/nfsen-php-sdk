<?php

use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoDest;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoImovel;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoReeRepRes;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosDif;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosSitClas;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosTribRegular;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoValoresIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocDFe;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocFornec;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocOutro;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocReeRepRes;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\FinNFSe;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\IndDest;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\IndFinal;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TipoChaveDFe;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TpEnteGov;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TpOper;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TpReeRepRes;
use Pulsar\NfseNacional\Builders\Xml\Parts\IBSCBSBuilder;
use Pulsar\NfseNacional\Builders\Xml\DpsBuilder;

function makeMinimalIBSCBS(): InfoIBSCBS
{
    return new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(
                    CST: '100',
                    cClassTrib: '010101',
                ),
            ),
        ),
    );
}

it('builds minimal IBSCBS element', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $xml = $doc->saveXML($builder->build($doc, makeMinimalIBSCBS()));

    expect($xml)
        ->toContain('<IBSCBS>')
        ->toContain('<finNFSe>0</finNFSe>')
        ->toContain('<indFinal>0</indFinal>')
        ->toContain('<cIndOp>010101</cIndOp>')
        ->toContain('<indDest>0</indDest>')
        ->toContain('<trib>')
        ->toContain('<gIBSCBS>')
        ->toContain('<CST>100</CST>')
        ->toContain('<cClassTrib>010101</cClassTrib>');
});

it('builds IBSCBS with tpOper', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Sim,
        cIndOp: '020202',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
        ),
        tpOper: TpOper::FornecimentoPagamentoPosterior,
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)->toContain('<tpOper>1</tpOper>');
});

it('builds IBSCBS with gRefNFSe', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
        ),
        refNFSe: ['12345678901234567890123456789012345678901234567890'],
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<gRefNFSe>')
        ->toContain('<refNFSe>12345678901234567890123456789012345678901234567890</refNFSe>');
});

it('builds IBSCBS with dest', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::OutraPessoa,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
        ),
        dest: new InfoDest(xNome: 'Destinatário', CNPJ: '12345678000195'),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<dest>')
        ->toContain('<CNPJ>12345678000195</CNPJ>')
        ->toContain('<xNome>Destinatário</xNome>');
});

it('builds IBSCBS with imovel cCIB', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
        ),
        imovel: new InfoImovel(cCIB: '12345678'),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<imovel>')
        ->toContain('<cCIB>12345678</cCIB>');
});

it('builds IBSCBS with gTribRegular and gDif', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(
                    CST: '100',
                    cClassTrib: '010101',
                    cCredPres: '01',
                    gTribRegular: new InfoTributosTribRegular(CSTReg: '200', cClassTribReg: '020202'),
                    gDif: new InfoTributosDif(pDifUF: '10.00', pDifMun: '5.00', pDifCBS: '3.00'),
                ),
            ),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<cCredPres>01</cCredPres>')
        ->toContain('<gTribRegular>')
        ->toContain('<CSTReg>200</CSTReg>')
        ->toContain('<cClassTribReg>020202</cClassTribReg>')
        ->toContain('<gDif>')
        ->toContain('<pDifUF>10.00</pDifUF>')
        ->toContain('<pDifMun>5.00</pDifMun>')
        ->toContain('<pDifCBS>3.00</pDifCBS>');
});

it('builds IBSCBS with gReeRepRes', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
            gReeRepRes: new InfoReeRepRes(documentos: [
                new ListaDocReeRepRes(
                    dtEmiDoc: '2026-01-01',
                    dtCompDoc: '2026-01-01',
                    tpReeRepRes: TpReeRepRes::RepasseImoveis,
                    vlrReeRepRes: '500.00',
                    dFeNacional: new ListaDocDFe(
                        tipoChaveDFe: TipoChaveDFe::NFSe,
                        chaveDFe: '12345678901234567890123456789012345678901234567890',
                    ),
                    fornec: new ListaDocFornec(xNome: 'Fornecedor', CNPJ: '98765432000111'),
                ),
            ]),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<gReeRepRes>')
        ->toContain('<documentos>')
        ->toContain('<dFeNacional>')
        ->toContain('<tipoChaveDFe>1</tipoChaveDFe>')
        ->toContain('<chaveDFe>12345678901234567890123456789012345678901234567890</chaveDFe>')
        ->toContain('<fornec>')
        ->toContain('<CNPJ>98765432000111</CNPJ>')
        ->toContain('<tpReeRepRes>01</tpReeRepRes>')
        ->toContain('<vlrReeRepRes>500.00</vlrReeRepRes>');
});

it('builds IBSCBS with dest CPF, fone and email', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::OutraPessoa,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
        ),
        dest: new InfoDest(
            xNome: 'Pessoa Física',
            CPF: '12345678901',
            end: new \Pulsar\NfseNacional\Dps\DTO\Shared\Endereco(
                xLgr: 'Rua Teste',
                nro: '100',
                xBairro: 'Centro',
                endNac: new \Pulsar\NfseNacional\Dps\DTO\Shared\EnderecoNacional(cMun: '3501608', CEP: '01001000'),
            ),
            fone: '11999998888',
            email: 'dest@test.com',
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<CPF>12345678901</CPF>')
        ->toContain('<fone>11999998888</fone>')
        ->toContain('<email>dest@test.com</email>')
        ->toContain('<end>')
        ->toContain('<endNac>');
});

it('builds IBSCBS with dest NIF', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::OutraPessoa,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
        ),
        dest: new InfoDest(xNome: 'Estrangeiro', NIF: 'NIF12345'),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));
    expect($xml)->toContain('<NIF>NIF12345</NIF>');
});

it('builds IBSCBS with dest cNaoNIF', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::OutraPessoa,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
        ),
        dest: new InfoDest(xNome: 'Sem NIF', cNaoNIF: \Pulsar\NfseNacional\Dps\Enums\Shared\CodNaoNIF::Dispensado),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));
    expect($xml)->toContain('<cNaoNIF>1</cNaoNIF>');
});

it('builds IBSCBS with imovel end using CEP', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
        ),
        imovel: new InfoImovel(
            inscImobFisc: '12345',
            end: new \Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoObra(
                xLgr: 'Rua Imovel', nro: '50', xBairro: 'Centro',
                CEP: '01001000', xCpl: 'Apto 1',
            ),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<imovel>')
        ->toContain('<inscImobFisc>12345</inscImobFisc>')
        ->toContain('<end>')
        ->toContain('<CEP>01001000</CEP>')
        ->toContain('<xLgr>Rua Imovel</xLgr>')
        ->toContain('<xCpl>Apto 1</xCpl>');
});

it('builds IBSCBS with imovel end using endExt', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
        ),
        imovel: new InfoImovel(
            end: new \Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoObra(
                xLgr: '5th Avenue', nro: '200', xBairro: 'Manhattan',
                endExt: new \Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoExteriorObra(
                    cEndPost: '10001', xCidade: 'New York', xEstProvReg: 'NY',
                ),
            ),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<imovel>')
        ->toContain('<endExt>')
        ->toContain('<cEndPost>10001</cEndPost>')
        ->toContain('<xCidade>New York</xCidade>')
        ->toContain('<xEstProvReg>NY</xEstProvReg>');
});

it('builds IBSCBS with docFiscalOutro', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
            gReeRepRes: new InfoReeRepRes(documentos: [
                new ListaDocReeRepRes(
                    dtEmiDoc: '2026-01-01',
                    dtCompDoc: '2026-01-01',
                    tpReeRepRes: TpReeRepRes::Outros,
                    vlrReeRepRes: '200.00',
                    docFiscalOutro: new \Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocFiscalOutro(
                        cMunDocFiscal: '3501608', nDocFiscal: 'NF-001', xDocFiscal: 'Nota fiscal municipal',
                    ),
                    xTpReeRepRes: 'Outro tipo de reembolso de teste',
                ),
            ]),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<docFiscalOutro>')
        ->toContain('<cMunDocFiscal>3501608</cMunDocFiscal>')
        ->toContain('<nDocFiscal>NF-001</nDocFiscal>')
        ->toContain('<xDocFiscal>Nota fiscal municipal</xDocFiscal>')
        ->toContain('<xTpReeRepRes>Outro tipo de reembolso de teste</xTpReeRepRes>');
});

it('builds IBSCBS with xTipoChaveDFe', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
            gReeRepRes: new InfoReeRepRes(documentos: [
                new ListaDocReeRepRes(
                    dtEmiDoc: '2026-01-01',
                    dtCompDoc: '2026-01-01',
                    tpReeRepRes: TpReeRepRes::RepasseImoveis,
                    vlrReeRepRes: '100.00',
                    dFeNacional: new ListaDocDFe(
                        tipoChaveDFe: TipoChaveDFe::Outro,
                        chaveDFe: '12345',
                        xTipoChaveDFe: 'Outro documento',
                    ),
                    fornec: new ListaDocFornec(xNome: 'Fornec CPF', CPF: '12345678901'),
                ),
            ]),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<xTipoChaveDFe>Outro documento</xTipoChaveDFe>')
        ->toContain('<CPF>12345678901</CPF>');
});

it('builds IBSCBS with fornec NIF and cNaoNIF', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    // Test NIF fornec
    $ibscbs1 = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular, indFinal: IndFinal::Nao, cIndOp: '010101', indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101')),
            gReeRepRes: new InfoReeRepRes(documentos: [
                new ListaDocReeRepRes(
                    dtEmiDoc: '2026-01-01', dtCompDoc: '2026-01-01',
                    tpReeRepRes: TpReeRepRes::RepasseImoveis, vlrReeRepRes: '100.00',
                    docOutro: new ListaDocOutro(nDoc: 'D1', xDoc: 'Doc'),
                    fornec: new ListaDocFornec(xNome: 'NIF Fornec', NIF: 'NIF999'),
                ),
            ]),
        ),
    );
    $xml1 = $doc->saveXML($builder->build($doc, $ibscbs1));
    expect($xml1)->toContain('<NIF>NIF999</NIF>');

    // Test cNaoNIF fornec
    $doc2 = new DOMDocument('1.0', 'UTF-8');
    $ibscbs2 = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular, indFinal: IndFinal::Nao, cIndOp: '010101', indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101')),
            gReeRepRes: new InfoReeRepRes(documentos: [
                new ListaDocReeRepRes(
                    dtEmiDoc: '2026-01-01', dtCompDoc: '2026-01-01',
                    tpReeRepRes: TpReeRepRes::RepasseImoveis, vlrReeRepRes: '100.00',
                    docOutro: new ListaDocOutro(nDoc: 'D2', xDoc: 'Doc2'),
                    fornec: new ListaDocFornec(xNome: 'cNaoNIF Fornec', cNaoNIF: \Pulsar\NfseNacional\Dps\Enums\Shared\CodNaoNIF::NaoInformado),
                ),
            ]),
        ),
    );
    $xml2 = $doc2->saveXML($builder->build($doc2, $ibscbs2));
    expect($xml2)->toContain('<cNaoNIF>0</cNaoNIF>');
});

it('builds IBSCBS with multiple documents in gReeRepRes', function () {
    $builder = new IBSCBSBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $ibscbs = new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Nao,
        cIndOp: '010101',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
            gReeRepRes: new InfoReeRepRes(documentos: [
                new ListaDocReeRepRes(
                    dtEmiDoc: '2026-01-01',
                    dtCompDoc: '2026-01-01',
                    tpReeRepRes: TpReeRepRes::RepasseImoveis,
                    vlrReeRepRes: '300.00',
                    dFeNacional: new ListaDocDFe(
                        tipoChaveDFe: TipoChaveDFe::NFSe,
                        chaveDFe: '12345678901234567890123456789012345678901234567890',
                    ),
                ),
                new ListaDocReeRepRes(
                    dtEmiDoc: '2026-02-01',
                    dtCompDoc: '2026-02-01',
                    tpReeRepRes: TpReeRepRes::RepasseAgenciaTurismo,
                    vlrReeRepRes: '200.00',
                    docOutro: new ListaDocOutro(nDoc: 'DOC002', xDoc: 'Segundo documento'),
                ),
            ]),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $ibscbs));

    expect($xml)
        ->toContain('<vlrReeRepRes>300.00</vlrReeRepRes>')
        ->toContain('<vlrReeRepRes>200.00</vlrReeRepRes>')
        ->toContain('<tpReeRepRes>01</tpReeRepRes>')
        ->toContain('<tpReeRepRes>02</tpReeRepRes>');
});

it('builds DPS with minimal IBSCBS that validates against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
        IBSCBS: makeMinimalIBSCBS(),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)->toContain('<IBSCBS>');
});

it('builds DPS with full IBSCBS that validates against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
        IBSCBS: new InfoIBSCBS(
            finNFSe: FinNFSe::Regular,
            indFinal: IndFinal::Sim,
            cIndOp: '010101',
            indDest: IndDest::OutraPessoa,
            valores: new InfoValoresIBSCBS(
                trib: new InfoTributosIBSCBS(
                    gIBSCBS: new InfoTributosSitClas(
                        CST: '100',
                        cClassTrib: '010101',
                        cCredPres: '01',
                        gTribRegular: new InfoTributosTribRegular(CSTReg: '200', cClassTribReg: '020202'),
                        gDif: new InfoTributosDif(pDifUF: '10.00', pDifMun: '5.00', pDifCBS: '3.00'),
                    ),
                ),
                gReeRepRes: new InfoReeRepRes(documentos: [
                    new ListaDocReeRepRes(
                        dtEmiDoc: '2026-01-01',
                        dtCompDoc: '2026-01-01',
                        tpReeRepRes: TpReeRepRes::RepasseImoveis,
                        vlrReeRepRes: '500.00',
                        docOutro: new ListaDocOutro(nDoc: 'DOC001', xDoc: 'Documento de teste'),
                    ),
                ]),
            ),
            tpOper: TpOper::FornecimentoRecebimentoConcomitantes,
            tpEnteGov: TpEnteGov::Municipio,
            dest: new InfoDest(xNome: 'Destinatário Teste', CNPJ: '98765432000111'),
        ),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)
        ->toContain('<IBSCBS>')
        ->toContain('<dest>')
        ->toContain('<gReeRepRes>')
        ->toContain('<gTribRegular>')
        ->toContain('<gDif>');
});
