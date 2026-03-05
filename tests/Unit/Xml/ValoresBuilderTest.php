<?php

covers(
    \Pulsar\NfseNacional\Xml\Builders\ValoresBuilder::class,
    \Pulsar\NfseNacional\Xml\Builders\CreatesTextElements::class,
);

use Pulsar\NfseNacional\Dps\DTO\Valores\BeneficioMunicipal;
use Pulsar\NfseNacional\Dps\DTO\Valores\DescontoCondIncond;
use Pulsar\NfseNacional\Dps\DTO\Valores\ExigibilidadeSuspensa;
use Pulsar\NfseNacional\Dps\DTO\Valores\PisCofins;
use Pulsar\NfseNacional\Dps\DTO\Valores\TotTribPercentual;
use Pulsar\NfseNacional\Dps\DTO\Valores\TotTribValor;
use Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao;
use Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoFederal;
use Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal;
use Pulsar\NfseNacional\Dps\DTO\Valores\Valores;
use Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado;
use Pulsar\NfseNacional\Dps\Enums\Valores\CST;
use Pulsar\NfseNacional\Dps\Enums\Valores\TpImunidade;
use Pulsar\NfseNacional\Dps\Enums\Valores\TpRetISSQN;
use Pulsar\NfseNacional\Dps\Enums\Valores\TpRetPisCofins;
use Pulsar\NfseNacional\Dps\Enums\Valores\TpSusp;
use Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;
use Pulsar\NfseNacional\Xml\Builders\ValoresBuilder;

function makeTribMunMinimo(): TributacaoMunicipal
{
    return new TributacaoMunicipal(
        tribISSQN: TribISSQN::Tributavel,
        tpRetISSQN: TpRetISSQN::NaoRetido,
    );
}

function makeTribMinimo(): Tributacao
{
    return new Tributacao(tribMun: makeTribMunMinimo(), indTotTrib: '0');
}

function makeVServPrestMinimo(): ValorServicoPrestado
{
    return new ValorServicoPrestado(vServ: '100.00');
}

it('builds valores element with vServPrest', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(vServPrest: makeVServPrestMinimo(), trib: makeTribMinimo());

    $element = $builder->build($doc, $valores);
    $xml = $doc->saveXML($element);

    expect($xml)->toContain('<valores>');
    expect($xml)->toContain('<vServPrest>');
    expect($xml)->toContain('<vServ>100.00</vServ>');
    expect($xml)->toContain('<tribMun>');
    expect($xml)->toContain('<tribISSQN>1</tribISSQN>');
    expect($xml)->toContain('<tpRetISSQN>1</tpRetISSQN>');
    expect($xml)->toContain('<totTrib>');
    expect($xml)->toContain('<indTotTrib>0</indTotTrib>');
    expect($xml)->not->toContain('<vDescCondIncond>');
    expect($xml)->not->toContain('<vDedRed>');
    expect($xml)->not->toContain('<tribFed>');
});

it('includes vReceb in vServPrest when set', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: new ValorServicoPrestado(vServ: '100.00', vReceb: '80.00'),
        trib: makeTribMinimo(),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<vReceb>80.00</vReceb>')
        ->toContain('<vServ>100.00</vServ>');
});

it('builds vDescCondIncond block', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: makeTribMinimo(),
        vDescCondIncond: new DescontoCondIncond(vDescIncond: '10.00', vDescCond: '5.00'),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<vDescCondIncond>')
        ->toContain('<vDescIncond>10.00</vDescIncond>')
        ->toContain('<vDescCond>5.00</vDescCond>');
});

it('includes tribMun optional fields', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: new Tributacao(
            tribMun: new TributacaoMunicipal(
                tribISSQN: TribISSQN::Tributavel,
                tpRetISSQN: TpRetISSQN::NaoRetido,
                cPaisResult: '01058',
                tpImunidade: TpImunidade::PatrimonioRendaServicos,
                pAliq: '5.00',
            ),
            indTotTrib: '0',
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<cPaisResult>01058</cPaisResult>')
        ->toContain('<tpImunidade>1</tpImunidade>')
        ->toContain('<pAliq>5.00</pAliq>');
});

it('builds exigSusp in tribMun', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: new Tributacao(
            tribMun: new TributacaoMunicipal(
                tribISSQN: TribISSQN::Tributavel,
                tpRetISSQN: TpRetISSQN::NaoRetido,
                exigSusp: new ExigibilidadeSuspensa(
                    tpSusp: TpSusp::DecisaoJudicial,
                    nProcesso: '0001234-56.2026.8.26.0100',
                ),
            ),
            indTotTrib: '0',
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<exigSusp>')
        ->toContain('<tpSusp>1</tpSusp>')
        ->toContain('<nProcesso>0001234-56.2026.8.26.0100</nProcesso>');
});

it('builds BM in tribMun with vRedBCBM', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: new Tributacao(
            tribMun: new TributacaoMunicipal(
                tribISSQN: TribISSQN::Tributavel,
                tpRetISSQN: TpRetISSQN::NaoRetido,
                BM: new BeneficioMunicipal(nBM: '12345', vRedBCBM: '50.00'),
            ),
            indTotTrib: '0',
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<BM>')
        ->toContain('<nBM>12345</nBM>')
        ->toContain('<vRedBCBM>50.00</vRedBCBM>')
        ->not->toContain('<pRedBCBM>');
});

it('builds BM in tribMun with pRedBCBM', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: new Tributacao(
            tribMun: new TributacaoMunicipal(
                tribISSQN: TribISSQN::Tributavel,
                tpRetISSQN: TpRetISSQN::NaoRetido,
                BM: new BeneficioMunicipal(nBM: '12345', pRedBCBM: '10.00'),
            ),
            indTotTrib: '0',
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<BM>')
        ->toContain('<nBM>12345</nBM>')
        ->toContain('<pRedBCBM>10.00</pRedBCBM>')
        ->not->toContain('<vRedBCBM>');
});

it('throws when both vRedBCBM and pRedBCBM are set in BM', function () {
    expect(fn () => new BeneficioMunicipal(nBM: '12345', vRedBCBM: '50.00', pRedBCBM: '10.00'))
        ->toThrow(InvalidDpsArgument::class, 'não ambos');
});

it('builds tribFed with piscofins and retencoes', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: new Tributacao(
            tribMun: makeTribMunMinimo(),
            indTotTrib: '0',
            tribFed: new TributacaoFederal(
                piscofins: new PisCofins(
                    CST: CST::AliqBasica,
                    vBCPisCofins: '100.00',
                    pAliqPis: '0.65',
                    pAliqCofins: '3.00',
                    vPis: '0.65',
                    vCofins: '3.00',
                    tpRetPisCofins: TpRetPisCofins::PisCofinsRetidos,
                ),
                vRetCP: '11.00',
                vRetIRRF: '1.50',
                vRetCSLL: '1.00',
            ),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<tribFed>')
        ->toContain('<piscofins>')
        ->toContain('<CST>01</CST>')
        ->toContain('<vBCPisCofins>100.00</vBCPisCofins>')
        ->toContain('<pAliqPis>0.65</pAliqPis>')
        ->toContain('<pAliqCofins>3.00</pAliqCofins>')
        ->toContain('<vPis>0.65</vPis>')
        ->toContain('<vCofins>3.00</vCofins>')
        ->toContain('<tpRetPisCofins>1</tpRetPisCofins>')
        ->toContain('<vRetCP>11.00</vRetCP>')
        ->toContain('<vRetIRRF>1.50</vRetIRRF>')
        ->toContain('<vRetCSLL>1.00</vRetCSLL>');
});

it('builds totTrib with vTotTrib choice', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: new Tributacao(
            tribMun: makeTribMunMinimo(),
            vTotTrib: new TotTribValor(vTotTribFed: '10.00', vTotTribEst: '5.00', vTotTribMun: '3.00'),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<vTotTrib>')
        ->toContain('<vTotTribFed>10.00</vTotTribFed>')
        ->toContain('<vTotTribEst>5.00</vTotTribEst>')
        ->toContain('<vTotTribMun>3.00</vTotTribMun>');
});

it('builds totTrib with pTotTrib choice', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: new Tributacao(
            tribMun: makeTribMunMinimo(),
            pTotTrib: new TotTribPercentual(pTotTribFed: '10.00', pTotTribEst: '5.00', pTotTribMun: '3.00'),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<pTotTrib>')
        ->toContain('<pTotTribFed>10.00</pTotTribFed>')
        ->toContain('<pTotTribEst>5.00</pTotTribEst>')
        ->toContain('<pTotTribMun>3.00</pTotTribMun>');
});

it('builds totTrib with pTotTribSN choice', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: new Tributacao(tribMun: makeTribMunMinimo(), pTotTribSN: '5.00'),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)->toContain('<pTotTribSN>5.00</pTotTribSN>');
});

it('throws when multiple totTrib choices are set', function () {
    expect(fn () => new Tributacao(
        tribMun: makeTribMunMinimo(),
        indTotTrib: '0',
        pTotTribSN: '5.00',
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when no totTrib choice is set', function () {
    expect(fn () => new Tributacao(tribMun: makeTribMunMinimo()))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('builds tribFed without piscofins', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: new Tributacao(
            tribMun: makeTribMunMinimo(),
            indTotTrib: '0',
            tribFed: new TributacaoFederal(vRetCP: '11.00'),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<tribFed>')
        ->toContain('<vRetCP>11.00</vRetCP>')
        ->not->toContain('<piscofins>');
});

it('builds piscofins without tpRetPisCofins', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new Valores(
        vServPrest: makeVServPrestMinimo(),
        trib: new Tributacao(
            tribMun: makeTribMunMinimo(),
            indTotTrib: '0',
            tribFed: new TributacaoFederal(
                piscofins: new PisCofins(CST: CST::AliqBasica),
            ),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<piscofins>')
        ->toContain('<CST>01</CST>')
        ->not->toContain('<tpRetPisCofins>');
});
