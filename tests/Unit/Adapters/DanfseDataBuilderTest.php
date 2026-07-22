<?php

use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Danfse\ParticipanteBuilder;
use OwnerPro\Nfsen\Enums\MarcaDagua;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Exceptions\XmlParseException;

covers(DanfseDataBuilder::class, ParticipanteBuilder::class);

beforeEach(function () {
    $this->builder = new DanfseDataBuilder;
    $this->xml = (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-autorizada.xml');
    // Traz o grupo IBSCBS dos dois lados e um destinatário distinto do tomador — o que
    // a nfse-autorizada, anterior à reforma, não tem.
    $this->ibscbs = (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-ibscbs.xml');
});

it('builds NfseData from authorized XML', function () {
    $data = $this->builder->build($this->xml);

    expect($data)->toBeInstanceOf(NfseData::class);
    expect($data->chaveAcesso)->toBe('33033021211222333000181000000000001026010000010000');
    expect($data->numeroNfse)->toBe('10');
});

it('extracts emitente fields completely', function () {
    $data = $this->builder->build($this->xml);

    expect($data->emitente->nome)->toBe('EMPRESA EXEMPLO DESENVOLVIMENTO LTDA');
    expect($data->emitente->cnpjCpf)->toBe('11.222.333/0001-81');
    expect($data->emitente->im)->toBe('987654');
    expect($data->emitente->telefone)->toBe('(21) 3000-1234');
    // A NT 008 amarra o bloco do prestador a infDPS/prest/, e a fixture traz e-mails
    // diferentes nos dois nós — é este par que revela de qual deles o campo sai.
    expect($data->emitente->email)->toBe('financeiro@empresaexemplo.com.br');
    expect($data->emitente->endereco)->toBe('Rua Visconde do Rio Branco, 100, Centro');
    expect($data->emitente->municipio)->toBe('Niterói / RJ');
    expect($data->emitente->cep)->toBe('24.020-005');
    expect($data->emitente->simplesNacional)->toBe('Não Optante');
});

it('resolves the prestador city from the IBGE code, not from the header text', function () {
    // NT 008: o município do bloco PRESTADOR sai de cMun, via tabela do IBGE. O
    // xLocEmi descreve o local de emissão e alimenta o cabeçalho, não este campo.
    $xml = preg_replace('|<xLocEmi>[^<]+</xLocEmi>|', '<xLocEmi>OUTRA CIDADE</xLocEmi>', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->municipio)->toBe('Niterói / RJ');
});

it('shows emitente city alone when UF is missing', function () {
    // Sem cMun em lugar nenhum, cai no texto do portal: UF ausente não deve
    // descartar xLocEmi — mostrar a cidade isolada é mais útil que '-'.
    $xml = preg_replace('|<cMun>3303302</cMun>|', '<cMun></cMun>', $this->xml, 1);
    $xml = preg_replace('|<UF>RJ</UF>|', '<UF></UF>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->municipio)->toBe('Niterói');
});

it('shows dash for emitente municipio when both xLocEmi and UF are missing', function () {
    $xml = preg_replace('|<cMun>3303302</cMun>|', '<cMun></cMun>', $this->xml, 1);
    $xml = preg_replace('|<xLocEmi>[^<]+</xLocEmi>|', '<xLocEmi></xLocEmi>', (string) $xml);
    $xml = preg_replace('|<UF>RJ</UF>|', '<UF></UF>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->municipio)->toBe('-');
});

it('shows dash for emitente municipio when xLocEmi is missing but UF is present', function () {
    // Sem xLocEmi não dá para compor "Cidade - UF"; devolver '-' em vez de " - RJ".
    $xml = preg_replace('|<cMun>3303302</cMun>|', '<cMun></cMun>', $this->xml, 1);
    $xml = preg_replace('|<xLocEmi>[^<]+</xLocEmi>|', '<xLocEmi></xLocEmi>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->municipio)->toBe('-');
});

it('preserves emitente email case', function () {
    // Portal nacional preserva o case do XML. Lowercasing perdia info (ex.: WEB@JONATHANMARTINS.COM.BR).
    $xml = str_replace('<email>financeiro@empresaexemplo.com.br</email>', '<email>Financeiro@EMPRESAEXEMPLO.com.br</email>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->emitente->email)->toBe('Financeiro@EMPRESAEXEMPLO.com.br');
});

it('extracts tomador fields completely', function () {
    $data = $this->builder->build($this->xml);

    expect($data->tomador->nome)->toBe('CLIENTE FICTICIO COMERCIO S.A.');
    expect($data->tomador->cnpjCpf)->toBe('91.712.343/0001-34');
    expect($data->tomador->im)->toBe('654321');
    expect($data->tomador->telefone)->toBe('(11) 98765-4321');
    expect($data->tomador->email)->toBe('contato@clienteficticio.com.br');
    expect($data->tomador->endereco)->toBe('Avenida Paulista, 1000, Bela Vista');
    expect($data->tomador->municipio)->toBe('São Paulo / SP');
    expect($data->tomador->cep)->toBe('01.310-100');
});

it('preserves tomador email case', function () {
    $xml = str_replace('<email>contato@clienteficticio.com.br</email>', '<email>CONTATO@clienteficticio.com.br</email>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->tomador->email)->toBe('CONTATO@clienteficticio.com.br');
});

// NT 008, item 2.4.5, nota 12: "Os campos sem informações no XML devem ser
// preenchidos com um traço (-)". O e-mail era o único campo do bloco a sair em
// branco, e é a nota que o obriga a acompanhar os vizinhos.
it('returns dash for every email the XML leaves out', function () {
    $xml = preg_replace('|<email>[^<]*</email>|', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->email)->toBe('-');
    expect($data->tomador->email)->toBe('-');
    expect($data->intermediario?->email)->toBe('-');
});

it('returns dash for emitente IM when empty', function () {
    $xml = str_replace('<IM>987654</IM>', '<IM></IM>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->emitente->im)->toBe('-');
});

it('returns dash for tomador IM when empty', function () {
    $xml = str_replace('<IM>654321</IM>', '<IM></IM>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->tomador->im)->toBe('-');
});

it('extracts intermediario fields completely', function () {
    $data = $this->builder->build($this->xml);

    expect($data->intermediario?->nome)->toBe('INTERMEDIARIO FICTICIO LTDA');
    expect($data->intermediario?->im)->toBe('123456');
    expect($data->intermediario?->telefone)->toBe('(11) 3333-4444');
    expect($data->intermediario?->email)->toBe('contato@intermediarioficticio.com.br');
    expect($data->intermediario?->endereco)->toBe('Rua Santa Conceição, 333, Guarulhos');
    expect($data->intermediario?->cep)->toBe('07.095-130');
});

it('preserves intermediario email case', function () {
    $xml = str_replace('<email>contato@intermediarioficticio.com.br</email>', '<email>Contato@INTERMEDIARIOFICTICIO.com.br</email>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->intermediario?->email)->toBe('Contato@INTERMEDIARIOFICTICIO.com.br');
});

it('returns dash for intermediario IM when empty', function () {
    $xml = str_replace('<IM>123456</IM>', '<IM></IM>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->intermediario?->im)->toBe('-');
});

it('sets intermediario to null when absent', function () {
    $xml = preg_replace('|<interm>.*?</interm>|s', '', $this->xml);
    $data = $this->builder->build((string) $xml);
    expect($data->intermediario)->toBeNull();
});

it('extracts servico fields', function () {
    $data = $this->builder->build($this->xml);

    expect($data->servico->codigoTribNacional)->toBe('01.07.00');
    expect($data->servico->codigoTribMunicipal)->toBe('007');
    expect($data->servico->localPrestacao)->toBe('Niterói / RJ');
    expect($data->servico->descricao)->toBe('Desenvolvimento de sistema de gestão empresarial - Contrato #2026-001');
    // xTribNac/xTribMun têm 73 chars; truncados em 60 com retrocesso ao último espaço (word-boundary).
    expect($data->servico->descTribNacional)->toBe('Desenvolvimento e licenciamento de programas de computador...');
    expect($data->servico->descTribMunicipal)->toBe('Desenvolvimento e licenciamento de programas de computador...');
});

it('trims whitespace from xTribNac before truncation', function () {
    // Adiciona espaços no início do xTribNac para testar trim() antes do limit()
    $xml = str_replace(
        '<xTribNac>Desenvolvimento e licenciamento de programas de computador customizáveis.</xTribNac>',
        '<xTribNac>   ABC</xTribNac>',
        $this->xml,
    );
    $data = $this->builder->build($xml);

    // Com trim: resultado é 'ABC' (3 chars < 60, sem truncar)
    expect($data->servico->descTribNacional)->toBe('ABC');
});

it('masks codigoNbs the way the notice writes it', function () {
    // Portal nacional prefixa "NBS: <cNBS>" nas informações complementares quando o código está presente.
    // O XSD ordena cServ como cTribNac, cTribMun, xDescServ, cNBS: inserir antes de
    // <xDescServ> produzia um XML que a API nunca emite, e o teste afirmava comportamento
    // sobre um documento inválido.
    $xml = str_replace(
        ' - Contrato #2026-001</xDescServ>',
        ' - Contrato #2026-001</xDescServ><cNBS>111032200</cNBS>',
        $this->xml,
    );
    $data = $this->builder->build($xml);

    // Tabela do item 2.4.5: o campo sai em n.nnnn.nn.nn.
    expect($data->servico->codigoNbs)->toBe('1.1103.22.00');
});

it('returns dash for codigoNbs when cNBS is absent', function () {
    $data = $this->builder->build($this->xml);

    expect($data->servico->codigoNbs)->toBe('-');
});

it('returns dash for descTribMunicipal when xTribMun is absent', function () {
    // Sem xTribMun, descTribMunicipal deve ser '-' (não string vazia) para template
    // exibir só o código (ou "-") sem gerar "- " espúrio.
    $xml = preg_replace('|<xTribMun>[^<]*</xTribMun>|', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->servico->descTribMunicipal)->toBe('-');
});

it('returns dash for descTribNacional when xTribNac is absent', function () {
    $xml = preg_replace('|<xTribNac>[^<]*</xTribNac>|', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->servico->descTribNacional)->toBe('-');
});

it('falls back to xLocPrestacao when cLocPrestacao IBGE is absent', function () {
    // Sem cLocPrestacao, preservar string textual do portal (não silenciar).
    $xml = str_replace('<cLocPrestacao>3303302</cLocPrestacao>', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->servico->localPrestacao)->toBe('Niterói');
});

it('falls back to xLocPrestacao when cLocPrestacao IBGE is invalid', function () {
    // Código IBGE inválido (Municipios::lookup retorna '-'): preferir texto do portal.
    $xml = str_replace('<cLocPrestacao>3303302</cLocPrestacao>', '<cLocPrestacao>9999999</cLocPrestacao>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->servico->localPrestacao)->toBe('Niterói');
});

it('falls back to xLocIncid when cLocIncid IBGE is absent', function () {
    $xml = str_replace('<cLocIncid>3303302</cLocIncid>', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->tribMun->municipioIncidencia)->toBe('Niterói');
});

it('returns dash for localPrestacao when neither IBGE nor text is available', function () {
    $xml = str_replace('<cLocPrestacao>3303302</cLocPrestacao>', '', $this->xml);
    $xml = preg_replace('|<xLocPrestacao>[^<]+</xLocPrestacao>|', '<xLocPrestacao></xLocPrestacao>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->servico->localPrestacao)->toBe('-');
});

it('returns dash for codigoTribMunicipal when empty', function () {
    $xml = str_replace('<cTribMun>007</cTribMun>', '<cTribMun></cTribMun>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->servico->codigoTribMunicipal)->toBe('-');
});

it('extracts tribMun fields including percent formatting', function () {
    $data = $this->builder->build($this->xml);

    expect($data->tribMun->tributacaoIssqn)->toBe('Operação Tributável');
    expect($data->tribMun->municipioIncidencia)->toBe('Niterói / RJ');
    expect($data->tribMun->bcIssqn)->toBe('R$ 1.350,00');
    expect($data->tribMun->aliquota)->toBe('2,00%');
    expect($data->tribMun->issqnApurado)->toBe('R$ 27,00');
});

it('returns dash for tribMun optional fields when absent', function () {
    $xml = preg_replace('|<pAliqAplic>[^<]+</pAliqAplic>|', '<pAliqAplic></pAliqAplic>', $this->xml);
    $xml = preg_replace('|<pAliq>[^<]+</pAliq>|', '<pAliq></pAliq>', (string) $xml);
    $xml = preg_replace('|<vBC>[^<]+</vBC>|', '<vBC></vBC>', (string) $xml);
    $xml = preg_replace('|<vISSQN>[^<]+</vISSQN>|', '<vISSQN></vISSQN>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->tribMun->aliquota)->toBe('-');
    expect($data->tribMun->bcIssqn)->toBe('-');
    expect($data->tribMun->issqnApurado)->toBe('-');
});

it('prefers pAliqAplic over the emitter-declared tribMun/pAliq', function () {
    // pAliqAplic é a alíquota que o fisco aplicou; tribMun/pAliq é a declarada pelo
    // emitente. Divergindo, a DANFSE tem de exibir a apurada.
    $xml = str_replace('<pAliqAplic>2.00</pAliqAplic>', '<pAliqAplic>3.50</pAliqAplic>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->tribMun->aliquota)->toBe('3,50%');
});

it('falls back to tribMun/pAliq when pAliqAplic is absent', function () {
    // Município não parametrizado: o fisco não devolve pAliqAplic e a alíquota
    // declarada pelo emitente é a única disponível.
    $xml = preg_replace('|<pAliqAplic>[^<]+</pAliqAplic>|', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->tribMun->aliquota)->toBe('2,00%');
});

// NT 008, item 2.3.1 e nota 4 do item 2.4.5. Imunidade e exportação também não
// recolhem ISSQN, mas a NT reserva campo no bloco para as duas — colapsá-las apagaria
// o tipo de imunidade e o país do resultado, que é o dado que as distingue.
it('marks only Não Incidência as outside the ISSQN', function (string $tribISSQN, bool $sujeita) {
    $xml = str_replace('<tribISSQN>1</tribISSQN>', "<tribISSQN>$tribISSQN</tribISSQN>", $this->xml);
    $data = $this->builder->build($xml);

    expect($data->tribMun->sujeitaAoIssqn)->toBe($sujeita);
})->with([
    'operação tributável' => ['1', true],
    'imunidade' => ['2', true],
    'exportação de serviço' => ['3', true],
    'não incidência' => ['4', false],
]);

it('extracts tribFed fields', function () {
    $data = $this->builder->build($this->xml);

    expect($data->tribFed->irrf)->toBe('R$ 22,50');
    expect($data->tribFed->cp)->toBe('R$ 15,00');
    expect($data->tribFed->csll)->toBe('R$ 15,00');
    expect($data->tribFed->pis)->toBe('R$ 9,75');
    expect($data->tribFed->cofins)->toBe('R$ 45,00');
});

// NT 008, item 2.4.5, nota 6: a linha de PIS/COFINS "será impressa para as NFS-e
// emitidas com data de competência até o final do ano-calendário de 2026".
it('keeps the PIS/COFINS line through the last competência of 2026', function (string $dCompet) {
    $xml = str_replace('<dCompet>2026-01-15</dCompet>', "<dCompet>$dCompet</dCompet>", $this->xml);
    $data = $this->builder->build($xml);

    expect($data->tribFed->exibePisCofins)->toBeTrue();
})->with(['2025-06-30', '2026-01-15', '2026-12-31']);

it('drops the PIS/COFINS line from the first competência of 2027', function (string $dCompet) {
    $xml = str_replace('<dCompet>2026-01-15</dCompet>', "<dCompet>$dCompet</dCompet>", $this->xml);
    $data = $this->builder->build($xml);

    expect($data->tribFed->exibePisCofins)->toBeFalse();
})->with(['2027-01-01', '2030-08-09']);

// Suprimir tributo declarado por causa de uma data ilegível perderia mais do que
// imprimir uma linha a mais — e dCompet é obrigatório no XSD.
it('keeps the PIS/COFINS line when the competência cannot be read', function () {
    $xml = str_replace('<dCompet>2026-01-15</dCompet>', '<dCompet></dCompet>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->tribFed->exibePisCofins)->toBeTrue();
});

it('builds successfully when tribFed block is absent', function () {
    // tribFed é minOccurs=0 no XSD. XMLs reais (Simples Nacional) não incluem o bloco.
    $xml = preg_replace('|<tribFed>.*?</tribFed>|s', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->tribFed->irrf)->toBe('-');
    expect($data->tribFed->cp)->toBe('-');
    expect($data->tribFed->csll)->toBe('-');
    expect($data->tribFed->pis)->toBe('-');
    expect($data->tribFed->cofins)->toBe('-');
});

it('builds successfully when piscofins block is absent within tribFed', function () {
    // piscofins é minOccurs=0 dentro de tribFed. Sem ele, PIS/COFINS devem ser '-'.
    $xml = preg_replace('|<piscofins>.*?</piscofins>|s', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->tribFed->pis)->toBe('-');
    expect($data->tribFed->cofins)->toBe('-');
});

it('builds successfully when pTotTrib subchild is absent in totTrib', function () {
    // Em Simples Nacional só existe pTotTribSN, sem pTotTrib/pTotTribFed.
    $xml = preg_replace('|<pTotTrib>.*?</pTotTrib>|s', '<pTotTribSN>2.01</pTotTribSN>', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->totaisTributos->federais)->toBe('-');
    expect($data->totaisTributos->estaduais)->toBe('-');
    expect($data->totaisTributos->municipais)->toBe('-');
});

it('builds successfully when locPrest has no cPaisPrestacao child', function () {
    // cPaisPrestacao é opcional; XMLs domésticos não incluem.
    $xml = preg_replace('|<cPaisPrestacao>[^<]*</cPaisPrestacao>|', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->servico->paisPrestacao)->toBe('-');
});

it('returns dash for tribFed fields when empty', function () {
    $xml = preg_replace('|<vRetIRRF>[^<]+</vRetIRRF>|', '<vRetIRRF></vRetIRRF>', $this->xml);
    $xml = preg_replace('|<vRetCP>[^<]+</vRetCP>|', '<vRetCP></vRetCP>', (string) $xml);
    $xml = preg_replace('|<vRetCSLL>[^<]+</vRetCSLL>|', '<vRetCSLL></vRetCSLL>', (string) $xml);
    $xml = preg_replace('|<vPis>[^<]+</vPis>|', '<vPis></vPis>', (string) $xml);
    $xml = preg_replace('|<vCofins>[^<]+</vCofins>|', '<vCofins></vCofins>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->tribFed->irrf)->toBe('-');
    expect($data->tribFed->cp)->toBe('-');
    expect($data->tribFed->csll)->toBe('-');
    expect($data->tribFed->pis)->toBe('-');
    expect($data->tribFed->cofins)->toBe('-');
});

it('extracts totais fields', function () {
    $data = $this->builder->build($this->xml);

    expect($data->totais->valorServico)->toBe('R$ 1.500,00');
    expect($data->totais->descontoCondicionado)->toBe('R$ 50,00');
    expect($data->totais->descontoIncondicionado)->toBe('R$ 100,00');
    // Sem vTotalRet no XML, a soma de reserva: IRRF 22,50 + CP 15,00 + CSLL 15,00,
    // mais o ISSQN retido de 27,00 (tpRetISSQN = 2).
    expect($data->totais->totalRetencoes)->toBe('R$ 79,50');
    expect($data->totais->valorLiquido)->toBe('R$ 1.292,75');
});

it('prefers the total of retentions the tax authority already computed', function () {
    // vTotalRet é minOccurs=0, mas quando vem é ele que a NT manda imprimir — refazer
    // a conta por cima do fisco só produziria divergência.
    $xml = str_replace('<vLiq>', '<vTotalRet>81.00</vTotalRet><vLiq>', $this->xml);

    $data = $this->builder->build($xml);

    expect($data->totais->totalRetencoes)->toBe('R$ 81,00');
});

it('leaves the ISSQN out of the retentions total when it was not withheld', function () {
    // tpRetISSQN = 1 é "Não Retido": há ISSQN apurado, mas ele não é retenção.
    $xml = str_replace('<tpRetISSQN>2</tpRetISSQN>', '<tpRetISSQN>1</tpRetISSQN>', $this->xml);

    $data = $this->builder->build($xml);

    expect($data->totais->totalRetencoes)->toBe('R$ 52,50');
});

it('returns dash for descontos when absent', function () {
    $xml = preg_replace('|<vDescCond>[^<]+</vDescCond>|', '<vDescCond></vDescCond>', $this->xml);
    $xml = preg_replace('|<vDescIncond>[^<]+</vDescIncond>|', '<vDescIncond></vDescIncond>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->totais->descontoCondicionado)->toBe('-');
    expect($data->totais->descontoIncondicionado)->toBe('-');
});

it('returns dash for descontos when vDescCondIncond block is omitted', function () {
    // vDescCondIncond é minOccurs=0 no TCInfoValores.
    $xml = preg_replace('|<vDescCondIncond>.*?</vDescCondIncond>|s', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->totais->descontoCondicionado)->toBe('-');
    expect($data->totais->descontoIncondicionado)->toBe('-');
});

it('returns a dash when the NFS-e has no retentions at all', function () {
    $xml = preg_replace('|<vRetIRRF>[^<]+</vRetIRRF>|', '<vRetIRRF></vRetIRRF>', $this->xml);
    $xml = preg_replace('|<vRetCP>[^<]+</vRetCP>|', '<vRetCP></vRetCP>', (string) $xml);
    $xml = preg_replace('|<vRetCSLL>[^<]+</vRetCSLL>|', '<vRetCSLL></vRetCSLL>', (string) $xml);
    $xml = str_replace('<tpRetISSQN>2</tpRetISSQN>', '<tpRetISSQN>1</tpRetISSQN>', (string) $xml);
    $data = $this->builder->build($xml);

    expect($data->totais->totalRetencoes)->toBe('-');
});

it('extracts totaisTributos percentages', function () {
    $data = $this->builder->build($this->xml);

    expect($data->totaisTributos->federais)->toBe('4,50%');
    expect($data->totaisTributos->estaduais)->toBe('0,10%');
    expect($data->totaisTributos->municipais)->toBe('2,00%');
});

it('returns dash for totaisTributos when absent', function () {
    $xml = preg_replace('|<pTotTribFed>[^<]+</pTotTribFed>|', '<pTotTribFed></pTotTribFed>', $this->xml);
    $xml = preg_replace('|<pTotTribEst>[^<]+</pTotTribEst>|', '<pTotTribEst></pTotTribEst>', (string) $xml);
    $xml = preg_replace('|<pTotTribMun>[^<]+</pTotTribMun>|', '<pTotTribMun></pTotTribMun>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->totaisTributos->federais)->toBe('-');
    expect($data->totaisTributos->estaduais)->toBe('-');
    expect($data->totaisTributos->municipais)->toBe('-');
});

it('dashes the nota 10 line for the choice branches the NT gives no position to', function (string $ramo) {
    // Conformidade deliberada, não lacuna. A nota 10 fixa a linha em três posições
    // — Federais / Estaduais / Municipais — e a tabela do item 2.4.5 só as alimenta
    // de vTotTrib ou pTotTrib. `pTotTribSN` é percentual único do Simples Nacional,
    // que não se decompõe nas três esferas, e `indTotTrib` declara que não se
    // informa total algum; nenhum dos dois aparece na NT 008. Sobra o traço da
    // nota 12. Ver DanfseDataBuilder::buildTotaisTributos().
    $xml = (string) preg_replace('#<pTotTrib>.*?</pTotTrib>#s', $ramo, $this->xml);
    $data = $this->builder->build($xml);

    expect($data->totaisTributos->federais)->toBe('-')
        ->and($data->totaisTributos->estaduais)->toBe('-')
        ->and($data->totaisTributos->municipais)->toBe('-')
        ->and($data->totaisTributos->linhaNt008())
        ->toBe('Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: - ; Estaduais: - ; Municipais: -');
})->with([
    'pTotTribSN' => ['<pTotTribSN>6.00</pTotTribSN>'],
    'indTotTrib' => ['<indTotTrib>0</indTotTrib>'],
]);

it('extracts informacoesComplementares', function () {
    $data = $this->builder->build($this->xml);

    expect($data->informacoesComplementares)->toContain('Referente ao contrato de prestação de serviços');
});

it('formats dates', function () {
    $data = $this->builder->build($this->xml);

    expect($data->competencia)->toBe('15/01/2026');
    expect($data->emissaoNfse)->toBe('15/01/2026 14:30:00');
    expect($data->emissaoDps)->toBe('15/01/2026 14:00:00');
});

it('extracts numeroDps and serieDps', function () {
    $data = $this->builder->build($this->xml);

    expect($data->numeroDps)->toBe('5');
    expect($data->serieDps)->toBe('20261');
});

it('detects ambiente Producao', function () {
    $data = $this->builder->build($this->xml);
    expect($data->ambiente)->toBe(NfseAmbiente::PRODUCAO);
});

it('detects ambiente Homologacao from fixture', function () {
    $xml = (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-homologacao.xml');
    $data = $this->builder->build($xml);
    expect($data->ambiente)->toBe(NfseAmbiente::HOMOLOGACAO);
});

it('falls back to HOMOLOGACAO when tpAmb is invalid', function () {
    // Fail-safe visual: XML suspeito renderiza com watermark "SEM VALIDADE JURÍDICA".
    $xml = str_replace('<tpAmb>1</tpAmb>', '<tpAmb>99</tpAmb>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->ambiente)->toBe(NfseAmbiente::HOMOLOGACAO);
});

it('throws for empty XML', function () {
    expect(fn () => $this->builder->build(''))
        ->toThrow(XmlParseException::class, 'XML vazio.');
});

it('throws for whitespace-only XML', function () {
    expect(fn () => $this->builder->build("   \n\t"))
        ->toThrow(XmlParseException::class, 'XML vazio.');
});

it('throws for malformed XML', function () {
    expect(fn () => $this->builder->build('<not-xml'))
        ->toThrow(XmlParseException::class, 'XML malformado');
});

it('throws for XML without NFSe namespace', function () {
    expect(fn () => $this->builder->build('<?xml version="1.0"?><foo><bar/></foo>'))
        ->toThrow(XmlParseException::class, 'XML não está no namespace NFS-e');
});

it('throws for XML in NFSe namespace but missing infNFSe', function () {
    $xml = '<?xml version="1.0"?><NFSe xmlns="http://www.sped.fazenda.gov.br/nfse"><outro/></NFSe>';
    expect(fn () => $this->builder->build($xml))
        ->toThrow(XmlParseException::class, 'XML não contém infNFSe.');
});

it('throws for XML missing DPS/infDPS block', function () {
    $xml = '<?xml version="1.0"?><NFSe xmlns="http://www.sped.fazenda.gov.br/nfse">'
        .'<infNFSe Id="NFS123"><outro/></infNFSe></NFSe>';
    expect(fn () => $this->builder->build($xml))
        ->toThrow(XmlParseException::class, 'XML não contém DPS/infDPS.');
});

it('throws a typed error naming the missing required group', function (string $group, string $expectedPath) {
    // Antes, um XML truncado emitia "Attempt to read property on null" e estourava
    // com TypeError — que escapava de toHtml(), sem o catch que toPdf() tem.
    $xml = (string) preg_replace('|<'.$group.'>.*?</'.$group.'>|s', '', $this->xml);

    expect(fn () => $this->builder->build($xml))
        ->toThrow(XmlParseException::class, $expectedPath);
})->with([
    'prest' => ['prest', 'infDPS/prest'],
    'serv' => ['serv', 'infDPS/serv'],
    'emit' => ['emit', 'infNFSe/emit'],
]);

it('throws a typed error when infDPS carries no required group at all', function (string $infDpsXml) {
    // O grupo faltante mais externo é o que deve ser reportado, tanto quando infDPS
    // traz outros campos quanto quando está completamente vazio.
    $xml = '<?xml version="1.0"?><NFSe xmlns="http://www.sped.fazenda.gov.br/nfse">'
        .'<infNFSe Id="NFS123"><DPS>'.$infDpsXml.'</DPS></infNFSe></NFSe>';

    expect(fn () => $this->builder->build($xml))
        ->toThrow(XmlParseException::class, 'infDPS/prest');
})->with([
    'infDPS com conteúdo, sem os grupos' => ['<infDPS><tpAmb>1</tpAmb></infDPS>'],
    'infDPS totalmente vazio' => ['<infDPS/>'],
]);

// NT 008, item 2.4.5, nota 2: sem dados de tomador o bloco traz "apenas" a frase de
// não identificado. Um participante de traços diria que os campos existem e vieram
// vazios, que é outra coisa — daí o nulo.
it('reports no tomador at all when the toma block is absent', function () {
    $xml = preg_replace('|<toma>.*?</toma>|s', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->tomador)->toBeNull();
});

it('still builds the tomador block when toma carries data', function () {
    $data = $this->builder->build($this->xml);

    expect($data->tomador)->not->toBeNull()
        ->and($data->tomador?->nome)->toBe('CLIENTE FICTICIO COMERCIO S.A.');
});

it('builds tomador gracefully when end block is absent', function () {
    // Tomador com CNPJ/xNome mas sem <end> — endereço opcional no XSD (minOccurs=0).
    // SimpleXML retorna null para filhos de elemento vazio; builder não deve crashar.
    $xml = preg_replace('|<end>.*?</end>|s', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->tomador->nome)->toBe('CLIENTE FICTICIO COMERCIO S.A.');
    expect($data->tomador->cnpjCpf)->toBe('91.712.343/0001-34');
    expect($data->tomador->endereco)->toBe('-');
    expect($data->tomador->municipio)->toBe('-');
    expect($data->tomador->cep)->toBe('-');
});

it('builds intermediario gracefully when end block is absent', function () {
    // Intermediário com CNPJ/xNome mas sem <end>.
    $xml = preg_replace('|(<interm>.*?)<end>.*?</end>(.*?</interm>)|s', '$1$2', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->intermediario?->nome)->toBe('INTERMEDIARIO FICTICIO LTDA');
    expect($data->intermediario?->endereco)->toBe('-');
    expect($data->intermediario?->municipio)->toBe('-');
    expect($data->intermediario?->cep)->toBe('-');
});

it('handles emitente without any identification at all', function () {
    // Precisa zerar prest e emit: a identificação sai de prest, com emit de reserva.
    $xml = preg_replace('|<CNPJ>[^<]+</CNPJ>|', '', $this->xml, 2);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->cnpjCpf)->toBe('-');
});

it('prints a foreign NIF raw instead of stripping its non-digits', function () {
    // TSNIF é texto livre de até 40 caracteres: prefixo de país e letras fazem parte do
    // identificador. Mascarar como CNPJ/CPF apagava caracteres sem aviso — 'ES-B12345678'
    // saía '12345678' num documento fiscal.
    $xml = preg_replace('|(<toma>\s*)<CNPJ>[^<]+</CNPJ>|', '$1<NIF>ES-B12345678</NIF>', $this->xml, 1);
    $data = $this->builder->build((string) $xml);

    expect($data->tomador->cnpjCpf)->toBe('ES-B12345678');
});

it('reads the prestador NIF from prest, even with the emit CNPJ the XSD requires', function () {
    // A tabela do item 2.4.5 amarra o campo a infDPS/prest/. TCEmitente exige CNPJ ou CPF,
    // então o cadastro sempre traz um dos dois — e consultá-lo antes fazia o CNPJ vencer o
    // NIF que a DPS declarou, identificando como brasileiro todo prestador estrangeiro.
    $xml = preg_replace('|(<prest>\s*)<CNPJ>[^<]+</CNPJ>|', '$1<NIF>PT501234567</NIF>', $this->xml, 1);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->cnpjCpf)->toBe('PT501234567');
});

it('explains why the identification is missing when cNaoNIF is present', function () {
    $xml = preg_replace('|(<toma>\s*)<CNPJ>[^<]+</CNPJ>|', '$1<cNaoNIF>1</cNaoNIF>', $this->xml, 1);
    $data = $this->builder->build((string) $xml);

    expect($data->tomador->cnpjCpf)->toBe('Dispensado do NIF');
});

it('explains a missing prestador identification via prest cNaoNIF', function () {
    $xml = preg_replace('|(<prest>\s*)<CNPJ>[^<]+</CNPJ>|', '$1<cNaoNIF>2</cNaoNIF>', $this->xml, 1);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->cnpjCpf)->toBe('Não exigência do NIF');
});

it('falls back to the emit registry when prest carries no identification at all', function () {
    // O <xs:choice> de TCInfoPrestador é obrigatório: só XML fora do schema chega aqui, e
    // o cadastro do emitente é melhor que um traço.
    $xml = preg_replace('|(<prest>\s*)<CNPJ>[^<]+</CNPJ>|', '$1', $this->xml, 1);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->cnpjCpf)->toBe('11.222.333/0001-81');
});

it('prints a foreign NIF raw for the intermediario as well', function () {
    $xml = preg_replace('|(<interm>\s*)<CNPJ>[^<]+</CNPJ>|', '$1<NIF>IE1234567AB</NIF>', $this->xml, 1);
    $data = $this->builder->build((string) $xml);

    expect($data->intermediario?->cnpjCpf)->toBe('IE1234567AB');
});

it('returns dash for emitente fields when text nodes are empty', function () {
    // Mantém estrutura de enderNac mas zera os valores dos text nodes do emitente
    $xml = str_replace('<xNome>EMPRESA EXEMPLO DESENVOLVIMENTO LTDA</xNome>', '<xNome></xNome>', $this->xml);
    $xml = str_replace('<xLgr>Rua Visconde do Rio Branco</xLgr>', '<xLgr></xLgr>', $xml);
    $xml = str_replace('<nro>100</nro>', '<nro></nro>', $xml);
    $xml = str_replace('<xBairro>Centro</xBairro>', '<xBairro></xBairro>', $xml);
    // Zera nos dois nós: cada campo do bloco sai de prest e cai em emit quando vazio.
    $xml = preg_replace('|<fone>2130001234</fone>|', '<fone></fone>', $xml);
    $xml = str_replace('<email>financeiro@empresaexemplo.com.br</email>', '<email></email>', (string) $xml);
    $xml = str_replace('<email>financeiro@example.org</email>', '<email></email>', (string) $xml);
    $xml = str_replace('<CEP>24020005</CEP>', '<CEP></CEP>', (string) $xml);
    $xml = preg_replace('|<cMun>3303302</cMun>|', '<cMun></cMun>', (string) $xml, 1);
    $xml = preg_replace('|<xLocEmi>[^<]+</xLocEmi>|', '<xLocEmi></xLocEmi>', (string) $xml);
    $xml = str_replace('<UF>RJ</UF>', '<UF></UF>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->nome)->toBe('-');
    expect($data->emitente->telefone)->toBe('-');
    expect($data->emitente->email)->toBe('-');
    expect($data->emitente->endereco)->toBe('-');
    expect($data->emitente->cep)->toBe('-');
    expect($data->emitente->municipio)->toBe('-');
});

it('falls back to CPF when CNPJ is empty in emitente', function () {
    // Substitui CNPJ por CPF no emitente
    $xml = str_replace('<CNPJ>11222333000181</CNPJ>', '<CNPJ></CNPJ><CPF>12345678901</CPF>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->emitente->cnpjCpf)->toBe('123.456.789-01');
});

it('trims leading and trailing whitespace from text nodes', function () {
    // Insere whitespace no xNome do emitente
    $xml = str_replace(
        '<xNome>EMPRESA EXEMPLO DESENVOLVIMENTO LTDA</xNome>',
        "<xNome>  \n  EMPRESA EXEMPLO DESENVOLVIMENTO LTDA  \t  </xNome>",
        $this->xml,
    );
    $data = $this->builder->build($xml);

    expect($data->emitente->nome)->toBe('EMPRESA EXEMPLO DESENVOLVIMENTO LTDA');
});

it('trims whitespace from emitente CNPJ before formatting', function () {
    // Whitespace no CNPJ deve ser removido antes de passar ao formatter
    $xml = str_replace(
        '<CNPJ>11222333000181</CNPJ>',
        '<CNPJ>  11222333000181  </CNPJ>',
        $this->xml,
    );
    $data = $this->builder->build($xml);

    expect($data->emitente->cnpjCpf)->toBe('11.222.333/0001-81');
});

it('returns empty chaveAcesso when Id attribute is missing', function () {
    // Remove o atributo Id do infNFSe
    $xml = preg_replace('|<infNFSe Id="[^"]+">|', '<infNFSe>', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->chaveAcesso)->toBe('');
});

it('preserves Id when it does not start with NFS prefix', function () {
    // Substitui prefixo NFS por outro
    $xml = str_replace('Id="NFS3303302', 'Id="XXX3303302', $this->xml);
    $data = $this->builder->build($xml);

    // Sem prefixo NFS, a chave mantém os 3 primeiros chars
    expect($data->chaveAcesso)->toBe('XXX33033021211222333000181000000000001026010000010000');
});

it('trims whitespace from address parts when joining', function () {
    // Insere whitespace nos quatro componentes de endereço (xLgr, nro, xCpl, xBairro).
    $xml = str_replace('<xLgr>Rua Visconde do Rio Branco</xLgr>', '<xLgr>  Rua Visconde do Rio Branco  </xLgr>', $this->xml);
    $xml = str_replace('<nro>100</nro>', '<nro>  100  </nro>', $xml);
    $xml = str_replace('<xBairro>Centro</xBairro>', '<xCpl>  Sala 1201  </xCpl><xBairro>  Centro  </xBairro>', $xml);
    $data = $this->builder->build($xml);

    expect($data->emitente->endereco)->toBe('Rua Visconde do Rio Branco, 100, Sala 1201, Centro');
});

it('treats whitespace-only document as empty and falls through to next in firstNonEmpty', function () {
    // Substitui o CNPJ do emitente por whitespace (deve ser tratado como vazio após trim),
    // e adiciona um CPF que deve ser escolhido no lugar.
    $xml = str_replace('<CNPJ>11222333000181</CNPJ>', '<CNPJ>   </CNPJ><CPF>12345678901</CPF>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->emitente->cnpjCpf)->toBe('123.456.789-01');
});

// A NT 008 (seções 2.1.3, 2.1.4 e 2.1.6) define o endereço do DANFSe como
// "logradouro, número, complemento e bairro". O xCpl é minOccurs=0 no XSD, então a
// fixture não o traz — mas quando a NFS-e o carrega, ele tem de sair impresso, entre
// o número e o bairro. Omiti-lo entrega endereço incompleto no documento fiscal.
it('prints the address complement between number and neighbourhood', function () {
    $xml = str_replace(
        '<xBairro>Centro</xBairro>',
        '<xCpl>Sala 1201</xCpl><xBairro>Centro</xBairro>',
        $this->xml,
    );
    $data = $this->builder->build($xml);

    expect($data->emitente->endereco)->toBe('Rua Visconde do Rio Branco, 100, Sala 1201, Centro');
});

it('prints the address complement for tomador and intermediario too', function () {
    $xml = str_replace(
        '<xBairro>Bela Vista</xBairro>',
        '<xCpl>Conjunto 42</xCpl><xBairro>Bela Vista</xBairro>',
        $this->xml,
    );
    $xml = str_replace(
        '<xBairro>Guarulhos</xBairro>',
        '<xCpl>Bloco B</xCpl><xBairro>Guarulhos</xBairro>',
        $xml,
    );
    $data = $this->builder->build($xml);

    expect($data->tomador->endereco)->toBe('Avenida Paulista, 1000, Conjunto 42, Bela Vista');
    expect($data->intermediario?->endereco)->toBe('Rua Santa Conceição, 333, Bloco B, Guarulhos');
});

it('omits the complement from the address when the NFS-e does not carry one', function () {
    // xCpl é opcional: ausente, não pode deixar separador órfão no endereço.
    $data = $this->builder->build($this->xml);

    expect($data->emitente->endereco)->toBe('Rua Visconde do Rio Branco, 100, Centro');
});

// NT 008, item 2.4.5, campo "DESCRIÇÃO DO CÓDIGO DE TRIBUTAÇÃO NACIONAL / MUNICIPAL":
// conteúdo é `xTribNac + xTribMun` com a regra `SE xTribMun <> "" ENTAO Descrição
// Municipal SENAO Descrição Nacional`. É um campo só; o template imprimia os dois.
it('prints the municipal tax description when the NFS-e carries xTribMun', function () {
    $data = $this->builder->build($this->xml);

    expect($data->servico->descricaoTributacao)
        ->toBe('Desenvolvimento e licenciamento de programas de computador customizáveis.');
});

it('falls back to the national tax description when xTribMun is empty', function () {
    $xml = preg_replace('|<xTribMun>[^<]*</xTribMun>|', '<xTribMun></xTribMun>', $this->xml);
    $xml = preg_replace('|<xTribNac>[^<]*</xTribNac>|', '<xTribNac>Descrição nacional</xTribNac>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->servico->descricaoTributacao)->toBe('Descrição nacional');
});

it('shows a dash for the tax description when neither xTribMun nor xTribNac is present', function () {
    $xml = preg_replace('|<xTribMun>[^<]*</xTribMun>|', '<xTribMun></xTribMun>', $this->xml);
    $xml = preg_replace('|<xTribNac>[^<]*</xTribNac>|', '<xTribNac></xTribNac>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->servico->descricaoTributacao)->toBe('-');
});

// A NT 008, item 2.4.5, amarra o bloco PRESTADOR / FORNECEDOR a infDPS/prest/. O
// builder lia de infNFSe/emit/ — nó válido, nó errado, e nada falhava porque os
// dois costumam carregar a mesma empresa. Aqui os dois nós divergem de propósito,
// que é a única forma de provar de qual deles cada campo sai.
it('reads the prestador block from prest, not from emit', function () {
    $xml = str_replace(
        '<prest>
                    <CNPJ>11222333000181</CNPJ>',
        '<prest>
                    <CNPJ>11222333000181</CNPJ>
                    <IM>111222</IM>
                    <xNome>NOME DECLARADO NA DPS LTDA</xNome>
                    <end>
                        <endNac>
                            <cMun>3550308</cMun>
                            <CEP>01310100</CEP>
                        </endNac>
                        <xLgr>Rua Declarada</xLgr>
                        <nro>77</nro>
                        <xBairro>Bairro Declarado</xBairro>
                    </end>',
        $this->xml,
    );
    $data = $this->builder->build($xml);

    expect($data->emitente->nome)->toBe('NOME DECLARADO NA DPS LTDA');
    expect($data->emitente->im)->toBe('111222');
    expect($data->emitente->endereco)->toBe('Rua Declarada, 77, Bairro Declarado');
    expect($data->emitente->municipio)->toBe('São Paulo / SP');
    expect($data->emitente->cep)->toBe('01.310-100');
});

it('falls back to emit for the prestador fields the DPS omits', function () {
    // Em TCInfoPrestador xNome, end, IM e fone são minOccurs=0; em TCEmitente xNome e
    // enderNac são obrigatórios. Trocar de nó sem fallback apagaria do documento o
    // cadastro que só o fisco tem.
    $data = $this->builder->build($this->xml);

    expect($data->emitente->nome)->toBe('EMPRESA EXEMPLO DESENVOLVIMENTO LTDA');
    expect($data->emitente->im)->toBe('987654');
    expect($data->emitente->endereco)->toBe('Rua Visconde do Rio Branco, 100, Centro');
});

it('composes city and UF from the portal text when no IBGE code is available', function () {
    // Último recurso do município do prestador: nem prest/end nem emit/enderNac têm
    // cMun utilizável, então resta o texto do portal com a UF.
    $xml = preg_replace('|<cMun>3303302</cMun>|', '<cMun></cMun>', $this->xml, 1);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->municipio)->toBe('Niterói / RJ');
});

// NT 008, item 2.4.5: os campos SITUAÇÃO DA NFS-E (cStat), FINALIDADE (finNFSe) e
// EMITENTE DA NFS-e (tpEmit) exigem a *descrição* da opção do leiaute, não o código.
// O template trazia "Prestador do Serviço" fixo — uma NFS-e emitida pelo tomador
// imprimia mesmo assim que o emitente era o prestador.
it('describes the NFS-e situation, purpose and issuer instead of their codes', function () {
    $data = $this->builder->build($this->xml);

    expect($data->situacao)->toBe('NFS-e Gerada');
    expect($data->emitidaPor)->toBe('Prestador');
    // A fixture não traz o bloco IBSCBS, que é opcional no XSD.
    expect($data->finalidade)->toBe('-');
});

it('describes an NFS-e issued by the tomador as such', function () {
    $xml = str_replace('<tpEmit>1</tpEmit>', '<tpEmit>2</tpEmit>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->emitidaPor)->toBe('Tomador');
});

it('describes a court-ordered NFS-e by its own status', function () {
    $xml = str_replace('<cStat>100</cStat>', '<cStat>102</cStat>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->situacao)->toBe('NFS-e de Decisão Judicial');
});

it('shows a dash for a status code the layout does not define', function () {
    // Rótulo inventado num documento fiscal é pior que campo vazio.
    $xml = str_replace('<cStat>100</cStat>', '<cStat>999</cStat>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->situacao)->toBe('-');
});

// NT 008, item 2.1.5: o DANFSe tem um bloco DESTINATÁRIO DA OPERAÇÃO, lido de
// infDPS/IBSCBS/dest. O SDK não o coletava — e ausência não deixa rastro: não há
// caminho errado para detectar, o bloco simplesmente não saía no documento.
it('reads the destinatário block from IBSCBS/dest', function () {
    $data = $this->builder->build($this->ibscbs);

    expect($data->destinatario?->nome)->toBe('DESTINATARIO FICTICIO S.A.');
    expect($data->destinatario?->cnpjCpf)->toBe('60.316.817/0001-03');
    expect($data->destinatario?->telefone)->toBe('(21) 2222-3333');
    expect($data->destinatario?->email)->toBe('contato@destinatarioficticio.com.br');
    expect($data->destinatario?->endereco)->toBe('Avenida Rio Branco, 50, Centro');
    expect($data->destinatario?->municipio)->toBe('Rio de Janeiro / RJ');
    expect($data->destinatario?->cep)->toBe('20.040-030');
});

it('leaves the destinatário null when the NFS-e predates the tax reform', function () {
    // IBSCBS e dest são minOccurs=0: NFS-e anterior à reforma não os traz, e o
    // template imprime "NÃO IDENTIFICADO" em vez de um bloco vazio.
    $data = $this->builder->build($this->xml);

    expect($data->destinatario)->toBeNull();
});

it('shows a dash for the destinatário municipal registration the layout omits', function () {
    // TCRTCInfoDest não declara IM, e a NT 2.1.5 não lista o campo para este bloco.
    $data = $this->builder->build($this->ibscbs);

    expect($data->destinatario?->im)->toBe('-');
});

// NT 008, item 2.4.5, notas 2 e 3: são dois casos com frases distintas. `indDest`
// (minOccurs=1 em IBSCBS) é quem os separa — sem lê-lo, uma NFS-e cujo destinatário
// é o próprio tomador saía como "não identificado", que diz outra coisa.
it('marks the destinatário as the tomador when indDest says so', function () {
    $xml = str_replace('<indDest>1</indDest>', '<indDest>0</indDest>', $this->ibscbs);
    $data = $this->builder->build($xml);

    expect($data->destinatarioEhTomador)->toBeTrue();
});

it('does not claim the destinatário is the tomador when indDest says otherwise', function () {
    $data = $this->builder->build($this->ibscbs);

    expect($data->destinatarioEhTomador)->toBeFalse();
});

it('does not claim the destinatário is the tomador when there is no IBSCBS block', function () {
    $data = $this->builder->build($this->xml);

    expect($data->destinatarioEhTomador)->toBeFalse();
});

// NT 008, item 2.4.5, bloco TRIBUTAÇÃO MUNICIPAL (ISSQN): seis campos que o SDK
// não coletava — imunidade, suspensão da exigibilidade e seu processo, benefício
// municipal, cálculo do BM e total de deduções/reduções.
it('reads the ISSQN immunity, suspension and municipal benefit fields', function () {
    $xml = str_replace(
        '<tribISSQN>1</tribISSQN>',
        '<tribISSQN>1</tribISSQN><tpImunidade>2</tpImunidade>',
        $this->xml,
    );
    $xml = str_replace(
        '</tribMun>',
        '<exigSusp><tpSusp>1</tpSusp><nProcesso>0012345-67.2026.8.19.0002</nProcesso></exigSusp></tribMun>',
        $xml,
    );
    $data = $this->builder->build($xml);

    expect($data->tribMun->tipoImunidade)->toBe('Templos de qualquer culto (CF88, Art...');
    expect($data->tribMun->suspensaoExigibilidade)->toBe('Exigibilidade Suspensa por Decisão...');
    expect($data->tribMun->numeroProcessoSuspensao)->toBe('0012345-67.2026.8.19.0002');
});

it('takes the BM calculation and total deductions the tax authority computed', function () {
    $xml = str_replace(
        '<vLiq>',
        '<tpBM>1</tpBM><vCalcBM>150.00</vCalcBM><vCalcDR>75.50</vCalcDR><vLiq>',
        $this->xml,
    );
    $data = $this->builder->build($xml);

    expect($data->tribMun->beneficioMunicipal)->toBe('Isenção');
    expect($data->tribMun->calculoBM)->toBe('R$ 150,00');
    expect($data->tribMun->totalDeducoesReducoes)->toBe('R$ 75,50');
});

it('adds the reimbursement to the deductions the tax authority computed', function () {
    // A NT escreve o campo como "vDR | vCalcDR + vCalcReeRepRes": o segundo caminho
    // é uma soma, e a fixture já traz vCalcReeRepRes = 50,00 em IBSCBS/valores.
    $xml = str_replace('<vLiq>', '<vCalcDR>200.00</vCalcDR><vLiq>', $this->ibscbs);
    $data = $this->builder->build($xml);

    expect($data->tribMun->totalDeducoesReducoes)->toBe('R$ 250,00');
});

it('shows the reimbursement alone when no vCalcDR accompanies it', function () {
    $data = $this->builder->build($this->ibscbs);

    expect($data->tribMun->totalDeducoesReducoes)->toBe('R$ 50,00');
});

it('keeps the deduction declared in the DPS out of the reimbursement sum', function () {
    // O vDR declarado vale sozinho: está do outro lado da barra, não é parcela.
    $xml = str_replace('</valores>', '<vDedRed><vDR>40.00</vDR></vDedRed></valores>', $this->ibscbs);
    $data = $this->builder->build($xml);

    expect($data->tribMun->totalDeducoesReducoes)->toBe('R$ 40,00');
});

it('falls back to the values declared in the DPS for BM and deductions', function () {
    // A NT dá dois caminhos ao mesmo campo: o apurado em infNFSe/valores e o
    // declarado na DPS. Sem o apurado, o declarado tem de aparecer.
    $xml = str_replace(
        '</tribMun>',
        '<BM><nBM>123</nBM><vRedBCBM>90.00</vRedBCBM></BM></tribMun>',
        $this->xml,
    );
    $xml = str_replace('</valores>', '<vDedRed><vDR>40.00</vDR></vDedRed></valores>', $xml);
    $data = $this->builder->build($xml);

    expect($data->tribMun->calculoBM)->toBe('R$ 90,00');
    expect($data->tribMun->totalDeducoesReducoes)->toBe('R$ 40,00');
});

it('dashes the ISSQN fields the NFS-e does not carry', function () {
    $data = $this->builder->build($this->xml);

    expect($data->tribMun->tipoImunidade)->toBe('-');
    expect($data->tribMun->suspensaoExigibilidade)->toBe('-');
    expect($data->tribMun->numeroProcessoSuspensao)->toBe('-');
    expect($data->tribMun->beneficioMunicipal)->toBe('-');
    expect($data->tribMun->calculoBM)->toBe('-');
    expect($data->tribMun->totalDeducoesReducoes)->toBe('-');
});

// NT 008, item 2.4.5, nota 5: as duas linhas de imunidade/benefício podem ser
// suprimidas quando NENHUM campo da linha tem dado. Sem isso, a esmagadora maioria
// das NFS-e — sem imunidade nem benefício — imprime oito traços.
function semDadosSuprimiveis(string $xml): string
{
    $xml = (string) preg_replace('|<regEspTrib>[^<]*</regEspTrib>|', '', $xml);

    return (string) preg_replace('|<vDescCondIncond>.*?</vDescCondIncond>|s', '', $xml);
}

it('hides the suppressible ISSQN rows when no field in them has data', function () {
    $data = $this->builder->build(semDadosSuprimiveis($this->xml));

    expect($data->tribMun->exibeRegimeEImunidade)->toBeFalse();
    expect($data->tribMun->exibeBeneficioEDeducoes)->toBeFalse();
});

it('keeps the rows when the NFS-e carries data for them', function () {
    // A fixture traz regEspTrib e vDescIncond — uma linha cada.
    $data = $this->builder->build($this->xml);

    expect($data->tribMun->exibeRegimeEImunidade)->toBeTrue();
    expect($data->tribMun->exibeBeneficioEDeducoes)->toBeTrue();
});

it('keeps the row when a single field in it has data', function () {
    // A nota condiciona a supressão a "não existam dados em todos os campos da
    // mesma linha" — um só campo preenchido já obriga a linha inteira.
    $xml = str_replace(
        '</tribMun>',
        '<exigSusp><nProcesso>123</nProcesso></exigSusp></tribMun>',
        semDadosSuprimiveis($this->xml),
    );
    $data = $this->builder->build($xml);

    expect($data->tribMun->exibeRegimeEImunidade)->toBeTrue();
    expect($data->tribMun->exibeBeneficioEDeducoes)->toBeFalse();
});

it('keeps the benefit row when only the unconditional discount is present', function () {
    $xml = str_replace(
        '</valores>',
        '<vDescCondIncond><vDescIncond>10.00</vDescIncond></vDescCondIncond></valores>',
        semDadosSuprimiveis($this->xml),
    );
    $data = $this->builder->build($xml);

    expect($data->tribMun->exibeRegimeEImunidade)->toBeFalse();
    expect($data->tribMun->exibeBeneficioEDeducoes)->toBeTrue();
});

// NT 008, item 2.1.10: bloco TRIBUTAÇÃO IBS / CBS. As alíquotas e valores apurados
// vivem em infNFSe/IBSCBS (lado do fisco); CST, classificação e indicador de
// operação vêm do que a DPS declarou em infDPS/IBSCBS.
it('reads the IBS/CBS block from both the declared and the assessed sides', function () {
    $data = $this->builder->build($this->ibscbs);

    expect($data->tribIbsCbs->cstClassTrib)->toBe('000 / 000001');
    expect($data->tribIbsCbs->indicadorOperacao)->toBe('110001 / 3303302 / Niterói / RJ');
    expect($data->tribIbsCbs->baseCalculo)->toBe('R$ 1.350,00');
    expect($data->tribIbsCbs->aliquotaIbs)->toBe('10,00% / 2,00%');
    expect($data->tribIbsCbs->reducaoAliquotas)->toBe('10,00% / 10,00% / 10,00%');
    expect($data->tribIbsCbs->aliquotaEfetivaEstadual)->toBe('9,00%');
    expect($data->tribIbsCbs->aliquotaEfetivaMunicipal)->toBe('1,80%');
    expect($data->tribIbsCbs->valorApuradoEstadual)->toBe('R$ 121,50');
    expect($data->tribIbsCbs->valorApuradoMunicipal)->toBe('R$ 24,30');
    expect($data->tribIbsCbs->valorTotalIbs)->toBe('R$ 145,80');
    expect($data->tribIbsCbs->aliquotaCbs)->toBe('8,80%');
    expect($data->tribIbsCbs->aliquotaEfetivaCbs)->toBe('7,92%');
    expect($data->tribIbsCbs->valorTotalCbs)->toBe('R$ 106,92');
    expect($data->totais->totalIbsCbs)->toBe('R$ 252,72');
    expect($data->totais->valorLiquidoComIbsCbs)->toBe('R$ 1.638,65');
});

// A NT define este campo como o somatório de cinco origens espalhadas pelo leiaute.
it('sums the five sources the notice gives for the IBS/CBS exclusions', function () {
    $data = $this->builder->build($this->ibscbs);

    // vDescIncond 100,00 + vCalcReeRepRes 50,00 + vISSQN 27,00 + vPis 9,75 + vCofins 45,00
    expect($data->tribIbsCbs->exclusoesReducoes)->toBe('R$ 231,75');
});

it('reads the finalidade from the IBSCBS group', function () {
    $data = $this->builder->build($this->ibscbs);

    expect($data->finalidade)->toBe('NFS-e regular');
});

it('dashes the whole IBS/CBS block on an NFS-e predating the tax reform', function () {
    // O grupo inteiro é minOccurs=0. Sem `?->` em cada nível, cada acesso emitia
    // warning do PHP na maioria das notas — que não têm IBSCBS.
    $data = $this->builder->build($this->xml);

    expect($data->tribIbsCbs->cstClassTrib)->toBe('-');
    expect($data->tribIbsCbs->baseCalculo)->toBe('-');
    expect($data->tribIbsCbs->valorTotalIbs)->toBe('-');
    expect($data->totais->valorLiquidoComIbsCbs)->toBe('-');
    // Campos percentuais vazios não podem virar um '%' solto no documento.
    expect($data->tribIbsCbs->reducaoAliquotas)->toBe('-');
    expect($data->tribIbsCbs->aliquotaIbs)->toBe('-');
    expect($data->tribIbsCbs->aliquotaCbs)->toBe('-');
});

it('describes the withheld social contributions and the generating environment', function () {
    $xml = str_replace('<ambGer>1</ambGer>', '<ambGer>2</ambGer>', $this->xml);
    $xml = str_replace('</piscofins>', '<tpRetPisCofins>3</tpRetPisCofins></piscofins>', $xml);
    $data = $this->builder->build($xml);

    expect($data->ambienteGerador)->toBe('Sistema Nacional da NFS-e');
    expect($data->tribFed->descricaoContribuicoesRetidas)->toBe('PIS/COFINS/CSLL Retidos');
});

// NT 008: "CÓDIGO IBGE / CEP" é um campo concatenado, e o município de incidência
// do ISSQN é "Município / UF / País". O SDK lia as duas origens mas imprimia só
// metade de cada — passava no inventário por caminho, e saía incompleto no PDF.
it('concatenates the IBGE code with the CEP, as the notice prints it', function () {
    $data = $this->builder->build($this->xml);

    expect($data->emitente->codigoIbge)->toBe('3303302');
    expect($data->tomador->codigoIbge)->toBe('3550308');
});

it('appends the country to the ISSQN incidence municipality', function () {
    $xml = str_replace('</tribMun>', '<cPaisResult>BR</cPaisResult></tribMun>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->tribMun->municipioIncidencia)->toBe('Niterói / RJ / BR');
});

it('omits the country when the NFS-e does not carry one', function () {
    // Serviço prestado no país não traz cPaisResult; o campo não pode sair com
    // uma barra órfã no fim.
    $data = $this->builder->build($this->xml);

    expect($data->tribMun->municipioIncidencia)->toBe('Niterói / RJ');
});

it('falls back to the emit address for the prestador IBGE code', function () {
    // Mesma regra do resto do bloco: a NT manda ler de prest, e emit cobre a
    // omissão — a fixture não traz prest/end.
    $xml = str_replace(
        '<prest>
                    <CNPJ>11222333000181</CNPJ>',
        '<prest>
                    <CNPJ>11222333000181</CNPJ>
                    <end><endNac><cMun>3550308</cMun><CEP>01310100</CEP></endNac></end>',
        $this->xml,
    );
    $data = $this->builder->build($xml);

    expect($data->emitente->codigoIbge)->toBe('3550308');
});

it('carries the marca d\'água through, since the XML cannot tell', function () {
    // cStat só descreve como a nota foi gerada; cancelamento e substituição chegam
    // como evento separado. Por isso a marca vem de fora (NT 008, itens 2.5.1/2.5.2).
    $data = $this->builder->build($this->xml, MarcaDagua::Cancelada);

    expect($data->marcaDagua)->toBe(MarcaDagua::Cancelada);
});

it('leaves the marca d\'água null for a vigente NFS-e', function () {
    $data = $this->builder->build($this->xml);

    expect($data->marcaDagua)->toBeNull();
});

/**
 * NFS-e com todos os campos que a NT 008 manda unir em "Informações Complementares".
 *
 * A fixture base só traz `xInfComp`; os outros nove estão espalhados por grupos
 * opcionais do leiaute (substituição, obra, imóvel, evento, pedido), e é justamente
 * a união deles que o item 2.4.5 exige.
 */
function nfsenXmlComTodasAsInfoCompl(): string
{
    $xml = (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-autorizada.xml');

    $xml = str_replace(
        '<infoCompl>',
        '<obra><cObra>12345678901234567890</cObra></obra>'
        .'<atvEvento><idAtvEvt>EVT-2026-001</idAtvEvt></atvEvento>'
        .'<infoCompl>'
        .'<idDocTec>DRT-99887766</idDocTec>'
        .'<docRef>NF 4567</docRef>'
        .'<xPed>PC-00123</xPed>'
        .'<gItemPed><xItemPed>7</xItemPed><xItemPed>8</xItemPed></gItemPed>',
        $xml,
    );

    $xml = str_replace(
        '</infDPS>',
        '<subst><chSubstda>33033021211222333000181000000000001026010000010001</chSubstda>'
        .'<cMotivo>01</cMotivo></subst>'
        .'<IBSCBS><imovel><inscImobFisc>IM-000123</inscImobFisc></imovel></IBSCBS>'
        .'</infDPS>',
        $xml,
    );

    return str_replace('</infNFSe>', '<xOutInf>Observação da administração municipal</xOutInf></infNFSe>', $xml);
}

it('unites the ten complementary-information fields the notice lists', function () {
    $data = (new DanfseDataBuilder)->build(nfsenXmlComTodasAsInfoCompl());

    // Item 2.4.5: rótulos e ordem vêm da observação da linha "INFORMAÇÕES
    // COMPLEMENTARES"; as notas 7, 8 e 9 fixam os de substituição, obra/imóvel e evento.
    expect($data->informacoesComplementares)
        ->toContain('Inf. Cont.: Referente ao contrato')
        ->toContain('NFS-e Subst.: 33033021211222333000181000000000001026010000010001')
        ->toContain('Doc. Ref.: NF 4567')
        ->toContain('Cod. Obra: 12345678901234567890')
        ->toContain('Insc. Imob.: IM-000123')
        ->toContain('Cod. Evt.: EVT-2026-001')
        ->toContain('Doc. Tec.: DRT-99887766')
        ->toContain('Núm. Ped.: PC-00123')
        ->toContain('Item Ped.: 7, 8')
        ->toContain('Inf. A. T. Mun.: Observação da administração municipal');
});

it('orders and separates the complementary information as the notice prescribes', function () {
    $data = (new DanfseDataBuilder)->build(nfsenXmlComTodasAsInfoCompl());

    // "As informações devem ser separadas por pipes ( | )", na ordem da tabela.
    $rotulos = array_map(
        static fn (string $parte): string => trim(explode(':', $parte)[0]),
        explode(' | ', $data->informacoesComplementares),
    );

    expect($rotulos)->toBe([
        'Inf. Cont.', 'NFS-e Subst.', 'Doc. Ref.', 'Cod. Obra', 'Insc. Imob.',
        'Cod. Evt.', 'Doc. Tec.', 'Núm. Ped.', 'Item Ped.', 'Inf. A. T. Mun.',
    ]);
});

it('drops the label of a complementary field the NFS-e does not have', function () {
    // "Cod. Obra: -" numa nota que não é de obra gastaria a linha e sugeriria um dado
    // que não existe.
    $data = (new DanfseDataBuilder)->build($this->xml);

    expect($data->informacoesComplementares)->toStartWith('Inf. Cont.: ')
        ->not->toContain('Cod. Obra')
        ->not->toContain('Insc. Imob.')
        ->not->toContain('Item Ped.');
});

it('keeps the fixed totals line out of the complementary information', function () {
    // Nota 10: a linha é fixa e o corte do texto livre não pode prejudicá-la, então
    // ela não disputa espaço com os dez campos — vive em DanfseTotaisTributos.
    $data = (new DanfseDataBuilder)->build($this->xml);

    expect($data->informacoesComplementares)->not->toContain('Totais Aproximados');
    expect($data->totaisTributos->linhaNt008())
        ->toBe('Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: 4,50% ; Estaduais: 0,10% ; Municipais: 2,00%');
});

it('reads the monetary totals when the NFS-e reports values instead of percentages', function () {
    // Nota 10: o campo aceita "valores monetários OU percentuais". Só o percentual
    // era lido, e uma NFS-e que declarasse vTotTrib saía com três traços.
    $xml = str_replace(
        '<pTotTrib><pTotTribFed>4.50</pTotTribFed><pTotTribEst>0.10</pTotTribEst><pTotTribMun>2.00</pTotTribMun>',
        '<vTotTrib><vTotTribFed>67.50</vTotTribFed><vTotTribEst>1.50</vTotTribEst><vTotTribMun>30.00</vTotTribMun>',
        (string) preg_replace('/\s+(?=<)/', '', $this->xml),
    );
    $xml = str_replace('</pTotTrib>', '</vTotTrib>', $xml);

    $data = (new DanfseDataBuilder)->build($xml);

    expect($data->totaisTributos->federais)->toBe('R$ 67,50');
    expect($data->totaisTributos->estaduais)->toBe('R$ 1,50');
    expect($data->totaisTributos->municipais)->toBe('R$ 30,00');
});

it('skips an empty item among the order items', function () {
    // gItemPed repete até 99 vezes; um item vazio no meio viraria uma vírgula solta
    // entre os números, sugerindo um item que não existe.
    $xml = str_replace(
        '<gItemPed><xItemPed>7</xItemPed><xItemPed>8</xItemPed></gItemPed>',
        '<gItemPed><xItemPed>7</xItemPed><xItemPed></xItemPed><xItemPed>8</xItemPed></gItemPed>',
        nfsenXmlComTodasAsInfoCompl(),
    );

    $data = (new DanfseDataBuilder)->build($xml);

    expect($data->informacoesComplementares)->toContain('Item Ped.: 7, 8');
});

it('concatenates the issuing municipality with its state for the header', function () {
    // Item 2.4.5, linha MUNICÍPIO: xLocEmi + UF, "concatenar os dois campos".
    $data = (new DanfseDataBuilder)->build($this->xml);

    expect($data->municipioEmitente)->toBe('Niterói / RJ');
});

it('hides the issuing municipality for national tax code item 99', function () {
    // A própria linha manda não exibir: o item 99 cobre o que não é tributado pelo
    // município, e nomear um ali sugeriria uma competência que não existe.
    $xml = (string) preg_replace('|<cTribNac>[^<]*</cTribNac>|', '<cTribNac>990101</cTribNac>', $this->xml);

    $data = (new DanfseDataBuilder)->build($xml);

    // Vazio, não '-': '-' diria "sem dado", que é outra coisa.
    expect($data->municipioEmitente)->toBe('');
});

it('falls back to a dash when the NFS-e names no issuing municipality', function () {
    $xml = (string) preg_replace('|<xLocEmi>[^<]*</xLocEmi>|', '', $this->xml);
    $xml = (string) preg_replace('|<UF>[^<]*</UF>|', '', $xml);

    $data = (new DanfseDataBuilder)->build($xml);

    expect($data->municipioEmitente)->toBe('-');
});

// NT 008, item 2.4.5: os blocos de participante têm caminho alternativo em
// `end/endExt` — `xCidade` no lugar de `cMun` e `cEndPost` no lugar do CEP.
it('reads the address of a participant located abroad', function () {
    $xml = (string) preg_replace(
        '|<endNac>\s*<cMun>3550308</cMun>\s*<CEP>01310100</CEP>\s*</endNac>|',
        '<endExt><cPais>AR</cPais><cEndPost>C1425DKE</cEndPost><xCidade>Buenos Aires</xCidade><xEstProvReg>Buenos Aires</xEstProvReg></endExt>',
        $this->xml,
    );

    $data = $this->builder->build($xml);

    expect($data->tomador?->municipio)->toBe('Buenos Aires / Buenos Aires');
    expect($data->tomador?->cep)->toBe('C1425DKE');
    // Não existe código do IBGE no exterior, e o campo é único no DANFSe.
    expect($data->tomador?->codigoIbge)->toBe('-');
    expect($data->tomador?->codigoIbgeCep())->toBe('C1425DKE');
});

it('keeps the city alone when the foreign address names no province', function () {
    $xml = (string) preg_replace(
        '|<endNac>\s*<cMun>3550308</cMun>\s*<CEP>01310100</CEP>\s*</endNac>|',
        '<endExt><cPais>PT</cPais><cEndPost>1000-001</cEndPost><xCidade>Lisboa</xCidade></endExt>',
        $this->xml,
    );

    $data = $this->builder->build($xml);

    expect($data->tomador?->municipio)->toBe('Lisboa');
});

it('prefers the foreign address the DPS declared over the emitente registry', function () {
    // `emit/enderNac` é obrigatório em TCEmitente e traz endereço nacional mesmo
    // para prestador no exterior; a NT amarra o bloco a `infDPS/prest/`.
    $xml = str_replace(
        '<fone>2130001234</fone>',
        '<end><endExt><cPais>PT</cPais><cEndPost>1000-001</cEndPost><xCidade>Lisboa</xCidade><xEstProvReg>Lisboa</xEstProvReg></endExt>'
            .'<xLgr>Avenida da Liberdade</xLgr><nro>10</nro><xBairro>Santo António</xBairro></end><fone>2130001234</fone>',
        $this->xml,
    );

    $data = $this->builder->build($xml);

    expect($data->emitente->municipio)->toBe('Lisboa / Lisboa');
    expect($data->emitente->cep)->toBe('1000-001');
    expect($data->emitente->codigoIbge)->toBe('-');
});

// NT 008, item 2.4.5: nome e endereço saem em campos de 80 caracteres, com
// reticências acima de 77 — o leiaute admite bem mais que isso.
it('truncates a participant name longer than the notice allows', function () {
    $xml = str_replace(
        '<xNome>CLIENTE FICTICIO COMERCIO S.A.</xNome>',
        '<xNome>COMPANHIA BRASILEIRA DE DESENVOLVIMENTO DE SOFTWARE E SERVICOS DIGITAIS INTEGRADOS S.A.</xNome>',
        $this->xml,
    );

    $data = $this->builder->build($xml);

    expect($data->tomador?->nome)->toBe('COMPANHIA BRASILEIRA DE DESENVOLVIMENTO DE SOFTWARE E SERVICOS DIGITAIS...');
});

it('truncates a participant address longer than the notice allows', function () {
    $xml = str_replace(
        '<xLgr>Avenida Paulista</xLgr>',
        '<xLgr>Avenida Presidente Juscelino Kubitschek de Oliveira</xLgr>',
        $this->xml,
    );
    $xml = str_replace('<nro>1000</nro>', '<nro>5000</nro><xCpl>Bloco B Andar 12</xCpl>', $xml);
    $xml = str_replace('<xBairro>Bela Vista</xBairro>', '<xBairro>Vila Nova Conceicao</xBairro>', $xml);

    $data = $this->builder->build($xml);

    expect($data->tomador?->endereco)->toBe('Avenida Presidente Juscelino Kubitschek de Oliveira, 5000, Bloco B Andar 12,...');
});

// As descrições do leiaute passam dos 37 e 77 caracteres dos campos do item 2.4.5.
it('truncates the Simples Nacional and its apuration regime descriptions', function () {
    $xml = str_replace(
        '<opSimpNac>1</opSimpNac>',
        '<opSimpNac>2</opSimpNac><regApTribSN>3</regApTribSN>',
        $this->xml,
    );

    $data = $this->builder->build($xml);

    expect($data->emitente->simplesNacional)->toBe('Optante - Microempreendedor Individua...');
    expect($data->emitente->regimeSN)->toBe('Regime de apuração dos tributos federais e municipal por fora do SN conforme...');
});

it('truncates the municipal benefit description', function () {
    $xml = str_replace('<vLiq>', '<tpBM>4</tpBM><vLiq>', $this->xml);

    $data = $this->builder->build($xml);

    expect($data->tribMun->beneficioMunicipal)->toBe("Alíquota Diferenciada de 'aliqDifBM'...");
});

// Nota 12: "os campos sem informações no XML devem ser preenchidos com um traço (-)".
// O quadro saía em branco quando nenhum dos dez campos vinha preenchido.
it('marks the complementary information with a dash when the XML fills none of it', function () {
    $xml = preg_replace('|<infoCompl>.*?</infoCompl>|s', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->informacoesComplementares)->toBe('-');
});

// Máscara posicional "% / % / %" da tabela do item 2.4.5: descartar a posição vazia
// deslocaria as demais, e a redução da CBS seria lida como redução do IBS estadual.
it('keeps the empty slot of a positional field instead of shifting the others', function () {
    $xml = str_replace(
        ['<pRedAliqUF>10.00</pRedAliqUF>', '<pRedAliqMun>10.00</pRedAliqMun>', '<pRedAliqCBS>10.00</pRedAliqCBS>'],
        ['', '', '<pRedAliqCBS>1.00</pRedAliqCBS>'],
        $this->ibscbs,
    );
    $data = $this->builder->build($xml);

    expect($data->tribIbsCbs->reducaoAliquotas)->toBe('- / - / 1,00%');
});
