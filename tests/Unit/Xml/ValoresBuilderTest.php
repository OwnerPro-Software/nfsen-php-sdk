<?php

use Pulsar\NfseNacional\Xml\Builders\ValoresBuilder;

function makeTribMinimo(): stdClass
{
    $trib = new stdClass;
    $trib->tribMun = new stdClass;
    $trib->tribMun->tribISSQN = '1';
    $trib->tribMun->tpRetISSQN = '1';
    $trib->totTrib = new stdClass;
    $trib->totTrib->indTotTrib = '0';

    return $trib;
}

function makeVServPrestMinimo(): stdClass
{
    $vServPrest = new stdClass;
    $vServPrest->vServ = '100.00';

    return $vServPrest;
}

it('builds valores element with vServPrest', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = makeTribMinimo();

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
});

it('includes vReceb in vServPrest when set', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vServPrest = new stdClass;
    $valores->vServPrest->vReceb = '80.00';
    $valores->vServPrest->vServ = '100.00';
    $valores->trib = makeTribMinimo();

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<vReceb>80.00</vReceb>')
        ->toContain('<vServ>100.00</vServ>');
});

it('builds vDescCondIncond block', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->vDescCondIncond = new stdClass;
    $valores->vDescCondIncond->vDescIncond = '10.00';
    $valores->vDescCondIncond->vDescCond = '5.00';
    $valores->trib = makeTribMinimo();

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<vDescCondIncond>')
        ->toContain('<vDescIncond>10.00</vDescIncond>')
        ->toContain('<vDescCond>5.00</vDescCond>');
});

it('includes tribMun optional fields', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->cPaisResult = '01058';
    $valores->trib->tribMun->tpImunidade = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->tribMun->pAliq = '5.00';
    $valores->trib->totTrib = new stdClass;
    $valores->trib->totTrib->indTotTrib = '0';

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<cPaisResult>01058</cPaisResult>')
        ->toContain('<tpImunidade>1</tpImunidade>')
        ->toContain('<pAliq>5.00</pAliq>');
});

it('builds exigSusp in tribMun', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->tribMun->exigSusp = new stdClass;
    $valores->trib->tribMun->exigSusp->tpSusp = '1';
    $valores->trib->tribMun->exigSusp->nProcesso = '0001234-56.2026.8.26.0100';
    $valores->trib->totTrib = new stdClass;
    $valores->trib->totTrib->indTotTrib = '0';

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<exigSusp>')
        ->toContain('<tpSusp>1</tpSusp>')
        ->toContain('<nProcesso>0001234-56.2026.8.26.0100</nProcesso>');
});

it('builds BM in tribMun with vRedBCBM', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->tribMun->BM = new stdClass;
    $valores->trib->tribMun->BM->nBM = '12345';
    $valores->trib->tribMun->BM->vRedBCBM = '50.00';
    $valores->trib->totTrib = new stdClass;
    $valores->trib->totTrib->indTotTrib = '0';

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

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->tribMun->BM = new stdClass;
    $valores->trib->tribMun->BM->nBM = '12345';
    $valores->trib->tribMun->BM->pRedBCBM = '10.00';
    $valores->trib->totTrib = new stdClass;
    $valores->trib->totTrib->indTotTrib = '0';

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<BM>')
        ->toContain('<nBM>12345</nBM>')
        ->toContain('<pRedBCBM>10.00</pRedBCBM>')
        ->not->toContain('<vRedBCBM>');
});

it('throws when both vRedBCBM and pRedBCBM are set in BM', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->tribMun->BM = new stdClass;
    $valores->trib->tribMun->BM->nBM = '12345';
    $valores->trib->tribMun->BM->vRedBCBM = '50.00';
    $valores->trib->tribMun->BM->pRedBCBM = '10.00';
    $valores->trib->totTrib = new stdClass;
    $valores->trib->totTrib->indTotTrib = '0';

    expect(fn () => $builder->build($doc, $valores))
        ->toThrow(InvalidArgumentException::class, 'não ambos');
});

it('builds tribFed with pisCofins and retencoes', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->tribFed = new stdClass;
    $valores->trib->tribFed->pisCofins = new stdClass;
    $valores->trib->tribFed->pisCofins->CST = '01';
    $valores->trib->tribFed->pisCofins->vBCPisCofins = '100.00';
    $valores->trib->tribFed->pisCofins->pAliqPis = '0.65';
    $valores->trib->tribFed->pisCofins->pAliqCofins = '3.00';
    $valores->trib->tribFed->pisCofins->vPis = '0.65';
    $valores->trib->tribFed->pisCofins->vCofins = '3.00';
    $valores->trib->tribFed->pisCofins->tpRetPisCofins = '1';
    $valores->trib->tribFed->vRetCP = '11.00';
    $valores->trib->tribFed->vRetIRRF = '1.50';
    $valores->trib->tribFed->vRetCSLL = '1.00';
    $valores->trib->totTrib = new stdClass;
    $valores->trib->totTrib->indTotTrib = '0';

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)
        ->toContain('<tribFed>')
        ->toContain('<pisCofins>')
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

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->totTrib = new stdClass;
    $valores->trib->totTrib->vTotTrib = new stdClass;
    $valores->trib->totTrib->vTotTrib->vTotTribFed = '10.00';
    $valores->trib->totTrib->vTotTrib->vTotTribEst = '5.00';
    $valores->trib->totTrib->vTotTrib->vTotTribMun = '3.00';

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

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->totTrib = new stdClass;
    $valores->trib->totTrib->pTotTrib = new stdClass;
    $valores->trib->totTrib->pTotTrib->pTotTribFed = '10.00';
    $valores->trib->totTrib->pTotTrib->pTotTribEst = '5.00';
    $valores->trib->totTrib->pTotTrib->pTotTribMun = '3.00';

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

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->totTrib = new stdClass;
    $valores->trib->totTrib->pTotTribSN = '5.00';

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)->toContain('<pTotTribSN>5.00</pTotTribSN>');
});

it('throws when multiple totTrib choices are set', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->totTrib = new stdClass;
    $valores->trib->totTrib->indTotTrib = '0';
    $valores->trib->totTrib->pTotTribSN = '5.00';

    expect(fn () => $builder->build($doc, $valores))
        ->toThrow(InvalidArgumentException::class, 'apenas um');
});

it('throws when no totTrib choice is set', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vServPrest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribMun = new stdClass;
    $valores->trib->tribMun->tribISSQN = '1';
    $valores->trib->tribMun->tpRetISSQN = '1';
    $valores->trib->totTrib = new stdClass;

    expect(fn () => $builder->build($doc, $valores))
        ->toThrow(InvalidArgumentException::class, 'requer vTotTrib');
});
