<?php

use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Dps\DTO\Tomador\Tomador;
use Pulsar\NfseNacional\Dps\DTO\Valores\DocDedRed;
use Pulsar\NfseNacional\Dps\DTO\Valores\DocNFNFS;
use Pulsar\NfseNacional\Dps\DTO\Valores\DocOutNFSe;
use Pulsar\NfseNacional\Dps\DTO\Valores\InfoDedRed;
use Pulsar\NfseNacional\Dps\DTO\Valores\Valores;
use Pulsar\NfseNacional\Dps\Enums\Valores\TipoDedRed;
use Pulsar\NfseNacional\Builders\Xml\Parts\ValoresBuilder;
use Pulsar\NfseNacional\Builders\Xml\DpsBuilder;

it('builds vDedRed with pDR choice', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: new \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado(vServ: '100.00'),
        trib: new \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao(
            tribMun: new \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal(
                tribISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN::Tributavel,
                tpRetISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
        vDedRed: new InfoDedRed(pDR: '10.00'),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<vDedRed>')
        ->toContain('<pDR>10.00</pDR>');
});

it('builds vDedRed with vDR choice', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: new \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado(vServ: '100.00'),
        trib: new \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao(
            tribMun: new \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal(
                tribISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN::Tributavel,
                tpRetISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
        vDedRed: new InfoDedRed(vDR: '50.00'),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<vDedRed>')
        ->toContain('<vDR>50.00</vDR>');
});

it('builds vDedRed with documentos containing chNFSe', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: new \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado(vServ: '100.00'),
        trib: new \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao(
            tribMun: new \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal(
                tribISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN::Tributavel,
                tpRetISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
        vDedRed: new InfoDedRed(documentos: [
            new DocDedRed(
                tpDedRed: TipoDedRed::Materiais,
                dtEmiDoc: '2026-01-15',
                vDedutivelRedutivel: '100.00',
                vDeducaoReducao: '50.00',
                chNFSe: '12345678901234567890123456789012345678901234567890',
            ),
        ]),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<vDedRed>')
        ->toContain('<documentos>')
        ->toContain('<docDedRed>')
        ->toContain('<chNFSe>12345678901234567890123456789012345678901234567890</chNFSe>')
        ->toContain('<tpDedRed>2</tpDedRed>')
        ->toContain('<dtEmiDoc>2026-01-15</dtEmiDoc>')
        ->toContain('<vDedutivelRedutivel>100.00</vDedutivelRedutivel>')
        ->toContain('<vDeducaoReducao>50.00</vDeducaoReducao>');
});

it('builds vDedRed with NFSeMun document', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: new \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado(vServ: '100.00'),
        trib: new \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao(
            tribMun: new \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal(
                tribISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN::Tributavel,
                tpRetISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
        vDedRed: new InfoDedRed(documentos: [
            new DocDedRed(
                tpDedRed: TipoDedRed::Servicos,
                dtEmiDoc: '2026-01-15',
                vDedutivelRedutivel: '200.00',
                vDeducaoReducao: '100.00',
                NFSeMun: new DocOutNFSe(cMunNFSeMun: '3501608', nNFSeMun: '000000000000001', cVerifNFSeMun: 'ABC123'),
            ),
        ]),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<NFSeMun>')
        ->toContain('<cMunNFSeMun>3501608</cMunNFSeMun>')
        ->toContain('<nNFSeMun>000000000000001</nNFSeMun>')
        ->toContain('<cVerifNFSeMun>ABC123</cVerifNFSeMun>');
});

it('builds vDedRed with NFNFS document', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: new \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado(vServ: '100.00'),
        trib: new \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao(
            tribMun: new \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal(
                tribISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN::Tributavel,
                tpRetISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
        vDedRed: new InfoDedRed(documentos: [
            new DocDedRed(
                tpDedRed: TipoDedRed::AlimentacaoBebidas,
                dtEmiDoc: '2026-01-15',
                vDedutivelRedutivel: '100.00',
                vDeducaoReducao: '50.00',
                NFNFS: new DocNFNFS(nNFS: '0000001', modNFS: '000000000000001', serieNFS: '1'),
            ),
        ]),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<NFNFS>')
        ->toContain('<nNFS>0000001</nNFS>')
        ->toContain('<modNFS>000000000000001</modNFS>')
        ->toContain('<serieNFS>1</serieNFS>');
});

it('builds vDedRed with fornec', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: new \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado(vServ: '100.00'),
        trib: new \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao(
            tribMun: new \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal(
                tribISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN::Tributavel,
                tpRetISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
        vDedRed: new InfoDedRed(documentos: [
            new DocDedRed(
                tpDedRed: TipoDedRed::SubempreitadaMaoDeObra,
                dtEmiDoc: '2026-01-15',
                vDedutivelRedutivel: '100.00',
                vDeducaoReducao: '50.00',
                nDoc: 'DOC-001',
                fornec: new Tomador(CNPJ: '98765432000111', xNome: 'Fornecedor Ltda'),
            ),
        ]),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<fornec>')
        ->toContain('<CNPJ>98765432000111</CNPJ>')
        ->toContain('<xNome>Fornecedor Ltda</xNome>');
});

it('builds vDedRed with chNFe document', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: new \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado(vServ: '100.00'),
        trib: new \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao(
            tribMun: new \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal(
                tribISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN::Tributavel,
                tpRetISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
        vDedRed: new InfoDedRed(documentos: [
            new DocDedRed(
                tpDedRed: TipoDedRed::Materiais,
                dtEmiDoc: '2026-01-15',
                vDedutivelRedutivel: '100.00',
                vDeducaoReducao: '50.00',
                chNFe: '12345678901234567890123456789012345678901234',
            ),
        ]),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));
    expect($xml)->toContain('<chNFe>12345678901234567890123456789012345678901234</chNFe>');
});

it('builds vDedRed with nDocFisc document', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: new \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado(vServ: '100.00'),
        trib: new \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao(
            tribMun: new \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal(
                tribISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN::Tributavel,
                tpRetISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
        vDedRed: new InfoDedRed(documentos: [
            new DocDedRed(
                tpDedRed: TipoDedRed::OutrasDeducoes,
                dtEmiDoc: '2026-01-15',
                vDedutivelRedutivel: '100.00',
                vDeducaoReducao: '50.00',
                nDocFisc: 'DOC-FISCAL-001',
                xDescOutDed: 'Descricao de outra deducao',
            ),
        ]),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<nDocFisc>DOC-FISCAL-001</nDocFisc>')
        ->toContain('<xDescOutDed>Descricao de outra deducao</xDescOutDed>');
});

it('builds DPS with vDedRed pDR that validates against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: makeServicoMinimo(),
        valores: new Valores(
            vServPrest: new \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado(vServ: '100.00'),
            trib: new \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao(
                tribMun: new \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal(
                    tribISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN::Tributavel,
                    tpRetISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetISSQN::NaoRetido,
                ),
                indTotTrib: '0',
            ),
            vDedRed: new InfoDedRed(pDR: '10.00'),
        ),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)->toContain('<vDedRed>');
});

it('builds DPS with vDedRed documentos that validates against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: makeServicoMinimo(),
        valores: new Valores(
            vServPrest: new \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado(vServ: '1000.00'),
            trib: new \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao(
                tribMun: new \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal(
                    tribISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN::Tributavel,
                    tpRetISSQN: \Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetISSQN::NaoRetido,
                ),
                indTotTrib: '0',
            ),
            vDedRed: new InfoDedRed(documentos: [
                new DocDedRed(
                    tpDedRed: TipoDedRed::Materiais,
                    dtEmiDoc: '2026-01-15',
                    vDedutivelRedutivel: '500.00',
                    vDeducaoReducao: '250.00',
                    chNFSe: '12345678901234567890123456789012345678901234567890',
                ),
            ]),
        ),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)->toContain('<vDedRed>');
});
