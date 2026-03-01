<?php

use Pulsar\NfseNacional\Xml\Builders\ValoresBuilder;

function makeTribMinimo(): stdClass
{
    $trib = new stdClass;
    $trib->tribmun = new stdClass;
    $trib->tribmun->tribissqn = '1';
    $trib->tribmun->tpretissqn = '1';
    $trib->totaltrib = new stdClass;
    $trib->totaltrib->indtottrib = '0';

    return $trib;
}

function makeVServPrestMinimo(): stdClass
{
    $vservprest = new stdClass;
    $vservprest->vserv = '100.00';

    return $vservprest;
}

it('builds valores element with vServPrest', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vservprest = makeVServPrestMinimo();
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
    $valores->vservprest = new stdClass;
    $valores->vservprest->vreceb = '80.00';
    $valores->vservprest->vserv = '100.00';
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
    $valores->vservprest = makeVServPrestMinimo();
    $valores->vdesccondincond = new stdClass;
    $valores->vdesccondincond->vdescincond = '10.00';
    $valores->vdesccondincond->vdesccond = '5.00';
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
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->cpaisresult = '01058';
    $valores->trib->tribmun->tpimunidade = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->tribmun->paliq = '5.00';
    $valores->trib->totaltrib = new stdClass;
    $valores->trib->totaltrib->indtottrib = '0';

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
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->tribmun->exigsusp = new stdClass;
    $valores->trib->tribmun->exigsusp->tpsusp = '1';
    $valores->trib->tribmun->exigsusp->nprocesso = '0001234-56.2026.8.26.0100';
    $valores->trib->totaltrib = new stdClass;
    $valores->trib->totaltrib->indtottrib = '0';

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
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->tribmun->bm = new stdClass;
    $valores->trib->tribmun->bm->nbm = '12345';
    $valores->trib->tribmun->bm->vredbcbm = '50.00';
    $valores->trib->totaltrib = new stdClass;
    $valores->trib->totaltrib->indtottrib = '0';

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
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->tribmun->bm = new stdClass;
    $valores->trib->tribmun->bm->nbm = '12345';
    $valores->trib->tribmun->bm->predbcbm = '10.00';
    $valores->trib->totaltrib = new stdClass;
    $valores->trib->totaltrib->indtottrib = '0';

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
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->tribmun->bm = new stdClass;
    $valores->trib->tribmun->bm->nbm = '12345';
    $valores->trib->tribmun->bm->vredbcbm = '50.00';
    $valores->trib->tribmun->bm->predbcbm = '10.00';
    $valores->trib->totaltrib = new stdClass;
    $valores->trib->totaltrib->indtottrib = '0';

    expect(fn () => $builder->build($doc, $valores))
        ->toThrow(InvalidArgumentException::class, 'não ambos');
});

it('builds tribFed with pisCofins and retencoes', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->tribfed = new stdClass;
    $valores->trib->tribfed->piscofins = new stdClass;
    $valores->trib->tribfed->piscofins->cst = '01';
    $valores->trib->tribfed->piscofins->vbcpiscofins = '100.00';
    $valores->trib->tribfed->piscofins->paliqpis = '0.65';
    $valores->trib->tribfed->piscofins->paliqcofins = '3.00';
    $valores->trib->tribfed->piscofins->vpis = '0.65';
    $valores->trib->tribfed->piscofins->vcofins = '3.00';
    $valores->trib->tribfed->piscofins->tpretpiscofins = '1';
    $valores->trib->tribfed->vretcp = '11.00';
    $valores->trib->tribfed->vretirrf = '1.50';
    $valores->trib->tribfed->vretcsll = '1.00';
    $valores->trib->totaltrib = new stdClass;
    $valores->trib->totaltrib->indtottrib = '0';

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
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->totaltrib = new stdClass;
    $valores->trib->totaltrib->vtottrib = new stdClass;
    $valores->trib->totaltrib->vtottrib->vtottribfed = '10.00';
    $valores->trib->totaltrib->vtottrib->vtottribest = '5.00';
    $valores->trib->totaltrib->vtottrib->vtottribmun = '3.00';

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
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->totaltrib = new stdClass;
    $valores->trib->totaltrib->ptottrib = new stdClass;
    $valores->trib->totaltrib->ptottrib->ptottribfed = '10.00';
    $valores->trib->totaltrib->ptottrib->ptottribest = '5.00';
    $valores->trib->totaltrib->ptottrib->ptottribmun = '3.00';

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
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->totaltrib = new stdClass;
    $valores->trib->totaltrib->ptottribsn = '5.00';

    $xml = $doc->saveXML($builder->build($doc, $valores));

    expect($xml)->toContain('<pTotTribSN>5.00</pTotTribSN>');
});

it('throws when multiple totTrib choices are set', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->totaltrib = new stdClass;
    $valores->trib->totaltrib->indtottrib = '0';
    $valores->trib->totaltrib->ptottribsn = '5.00';

    expect(fn () => $builder->build($doc, $valores))
        ->toThrow(InvalidArgumentException::class, 'apenas um');
});

it('throws when no totTrib choice is set', function () {
    $builder = new ValoresBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass;
    $valores->vservprest = makeVServPrestMinimo();
    $valores->trib = new stdClass;
    $valores->trib->tribmun = new stdClass;
    $valores->trib->tribmun->tribissqn = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->totaltrib = new stdClass;

    expect(fn () => $builder->build($doc, $valores))
        ->toThrow(InvalidArgumentException::class, 'requer vTotTrib');
});
