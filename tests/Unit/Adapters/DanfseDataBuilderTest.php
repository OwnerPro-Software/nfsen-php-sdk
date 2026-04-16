<?php

use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Exceptions\XmlParseException;

covers(DanfseDataBuilder::class);

beforeEach(function () {
    $this->builder = new DanfseDataBuilder;
    $this->xml = (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-autorizada.xml');
});

it('builds NfseData from authorized XML', function () {
    $data = $this->builder->build($this->xml);

    expect($data)->toBeInstanceOf(NfseData::class);
    expect($data->chaveAcesso)->toBe('3303302112233450000195000000000000100000000001');
    expect($data->numeroNfse)->toBe('10');
});

it('extracts emitente fields completely', function () {
    $data = $this->builder->build($this->xml);

    expect($data->emitente->nome)->toBe('EMPRESA EXEMPLO DESENVOLVIMENTO LTDA');
    expect($data->emitente->cnpjCpf)->toBe('11.222.333/0001-81');
    expect($data->emitente->telefone)->toBe('(21) 3000-1234');
    expect($data->emitente->email)->toBe('financeiro@example.org');
    expect($data->emitente->endereco)->toBe('Rua Visconde do Rio Branco, 100, Centro');
    expect($data->emitente->municipio)->toBe('Niterói - RJ');
    expect($data->emitente->cep)->toBe('24020-005');
    expect($data->emitente->simplesNacional)->toBe('Não Optante');
});

it('shows emitente city alone when UF is missing', function () {
    // UF ausente não deve descartar xLocEmi — mostrar cidade isolada é mais útil que '-'.
    $xml = preg_replace('|<UF>RJ</UF>|', '<UF></UF>', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->municipio)->toBe('Niterói');
});

it('shows dash for emitente municipio when both xLocEmi and UF are missing', function () {
    $xml = preg_replace('|<xLocEmi>[^<]+</xLocEmi>|', '<xLocEmi></xLocEmi>', $this->xml);
    $xml = preg_replace('|<UF>RJ</UF>|', '<UF></UF>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->municipio)->toBe('-');
});

it('shows dash for emitente municipio when xLocEmi is missing but UF is present', function () {
    // Sem xLocEmi não dá para compor "Cidade - UF"; devolver '-' em vez de " - RJ".
    $xml = preg_replace('|<xLocEmi>[^<]+</xLocEmi>|', '<xLocEmi></xLocEmi>', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->municipio)->toBe('-');
});

it('preserves emitente email case', function () {
    // Portal nacional preserva o case do XML. Lowercasing perdia info (ex.: WEB@JONATHANMARTINS.COM.BR).
    $xml = str_replace('<email>financeiro@example.org</email>', '<email>Financeiro@EXAMPLE.org</email>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->emitente->email)->toBe('Financeiro@EXAMPLE.org');
});

it('extracts tomador fields completely', function () {
    $data = $this->builder->build($this->xml);

    expect($data->tomador->nome)->toBe('CLIENTE FICTICIO COMERCIO S.A.');
    expect($data->tomador->cnpjCpf)->toBe('91.712.343/0001-34');
    expect($data->tomador->im)->toBe('654321');
    expect($data->tomador->telefone)->toBe('(11) 98765-4321');
    expect($data->tomador->email)->toBe('contato@clienteficticio.com.br');
    expect($data->tomador->endereco)->toBe('Avenida Paulista, 1000, Bela Vista');
    expect($data->tomador->municipio)->toBe('São Paulo - SP');
    expect($data->tomador->cep)->toBe('01310-100');
});

it('preserves tomador email case', function () {
    $xml = str_replace('<email>contato@clienteficticio.com.br</email>', '<email>CONTATO@clienteficticio.com.br</email>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->tomador->email)->toBe('CONTATO@clienteficticio.com.br');
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
    expect($data->intermediario?->cep)->toBe('07095-130');
});

it('preserves intermediario email case', function () {
    $xml = str_replace('<email>contato@intermediarioficticio.com.br</email>', '<email>Contato@INTERMEDIARIOFICTICIO.com.br</email>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->intermediario?->email)->toBe('Contato@INTERMEDIARIOFICTICIO.com.br');
});

it('returns dash for intermediario IM when empty', function () {
    $xml = str_replace('<IMPrestMun>123456</IMPrestMun>', '<IMPrestMun></IMPrestMun>', $this->xml);
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
    expect($data->servico->localPrestacao)->toBe('Niterói - RJ');
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

it('extracts codigoNbs when cNBS is present', function () {
    // Portal nacional prefixa "NBS: <cNBS>" nas informações complementares quando o código está presente.
    $xml = str_replace(
        '<xDescServ>Desenvolvimento de sistema',
        '<cNBS>111032200</cNBS><xDescServ>Desenvolvimento de sistema',
        $this->xml,
    );
    $data = $this->builder->build($xml);

    expect($data->servico->codigoNbs)->toBe('111032200');
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
    expect($data->tribMun->municipioIncidencia)->toBe('Niterói - RJ');
    expect($data->tribMun->valorServico)->toBe('R$ 1.500,00');
    expect($data->tribMun->bcIssqn)->toBe('R$ 1.350,00');
    expect($data->tribMun->aliquota)->toBe('2.00%');
    expect($data->tribMun->issqnApurado)->toBe('R$ 27,00');
});

it('returns dash for tribMun optional fields when absent', function () {
    $xml = preg_replace('|<pAliq>[^<]+</pAliq>|', '<pAliq></pAliq>', $this->xml);
    $xml = preg_replace('|<vBC>[^<]+</vBC>|', '<vBC></vBC>', (string) $xml);
    $xml = preg_replace('|<vISSQN>[^<]+</vISSQN>|', '<vISSQN></vISSQN>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->tribMun->aliquota)->toBe('-');
    expect($data->tribMun->bcIssqn)->toBe('-');
    expect($data->tribMun->issqnApurado)->toBe('-');
});

it('extracts tribFed fields', function () {
    $data = $this->builder->build($this->xml);

    expect($data->tribFed->irrf)->toBe('R$ 22,50');
    expect($data->tribFed->cp)->toBe('R$ 15,00');
    expect($data->tribFed->csll)->toBe('R$ 15,00');
    expect($data->tribFed->pis)->toBe('R$ 9,75');
    expect($data->tribFed->cofins)->toBe('R$ 45,00');
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
    expect($data->totais->issqnRetido)->toBe('R$ 27,00'); // tpRetISSQN=2 (retido)
    expect($data->totais->retencoesFederais)->toBe('R$ 52,50');
    expect($data->totais->pisCofins)->toBe('R$ 54,75');
    expect($data->totais->valorLiquido)->toBe('R$ 1.292,75');
});

it('returns dash for issqnRetido when tpRetISSQN is 1 (não retido)', function () {
    $xml = str_replace('<tpRetISSQN>2</tpRetISSQN>', '<tpRetISSQN>1</tpRetISSQN>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->totais->issqnRetido)->toBe('-');
});

it('returns dash for descontos when absent', function () {
    $xml = preg_replace('|<vDescCond>[^<]+</vDescCond>|', '<vDescCond></vDescCond>', $this->xml);
    $xml = preg_replace('|<vDescIncond>[^<]+</vDescIncond>|', '<vDescIncond></vDescIncond>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->totais->descontoCondicionado)->toBe('-');
    expect($data->totais->descontoIncondicionado)->toBe('-');
});

it('returns dash for retencoes sums when all empty', function () {
    $xml = preg_replace('|<vRetIRRF>[^<]+</vRetIRRF>|', '<vRetIRRF></vRetIRRF>', $this->xml);
    $xml = preg_replace('|<vRetCP>[^<]+</vRetCP>|', '<vRetCP></vRetCP>', (string) $xml);
    $xml = preg_replace('|<vRetCSLL>[^<]+</vRetCSLL>|', '<vRetCSLL></vRetCSLL>', (string) $xml);
    $xml = preg_replace('|<vPis>[^<]+</vPis>|', '<vPis></vPis>', (string) $xml);
    $xml = preg_replace('|<vCofins>[^<]+</vCofins>|', '<vCofins></vCofins>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->totais->retencoesFederais)->toBe('-');
    expect($data->totais->pisCofins)->toBe('-');
});

it('extracts totaisTributos percentages', function () {
    $data = $this->builder->build($this->xml);

    expect($data->totaisTributos->federais)->toBe('4.50%');
    expect($data->totaisTributos->estaduais)->toBe('0.10%');
    expect($data->totaisTributos->municipais)->toBe('2.00%');
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

it('returns empty tomador when toma block is absent', function () {
    $xml = preg_replace('|<toma>.*?</toma>|s', '', $this->xml);
    $data = $this->builder->build((string) $xml);

    expect($data->tomador->nome)->toBe('-');
    expect($data->tomador->cnpjCpf)->toBe('-');
    expect($data->tomador->municipio)->toBe('-');
});

it('handles emitente without CNPJ CPF or NIF', function () {
    $xml = preg_replace('|<CNPJ>[^<]+</CNPJ>|', '', $this->xml, 1);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->cnpjCpf)->toBe('-');
});

it('returns dash for emitente fields when text nodes are empty', function () {
    // Mantém estrutura de enderNac mas zera os valores dos text nodes do emitente
    $xml = str_replace('<xNome>EMPRESA EXEMPLO DESENVOLVIMENTO LTDA</xNome>', '<xNome></xNome>', $this->xml);
    $xml = str_replace('<xLgr>Rua Visconde do Rio Branco</xLgr>', '<xLgr></xLgr>', $xml);
    $xml = str_replace('<nro>100</nro>', '<nro></nro>', $xml);
    $xml = str_replace('<xBairro>Centro</xBairro>', '<xBairro></xBairro>', $xml);
    $xml = preg_replace('|<fone>2130001234</fone>|', '<fone></fone>', $xml);
    $xml = str_replace('<email>financeiro@example.org</email>', '<email></email>', (string) $xml);
    $xml = str_replace('<CEP>24020005</CEP>', '<CEP></CEP>', (string) $xml);
    $xml = preg_replace('|<xLocEmi>[^<]+</xLocEmi>|', '<xLocEmi></xLocEmi>', (string) $xml);
    $xml = str_replace('<UF>RJ</UF>', '<UF></UF>', (string) $xml);
    $data = $this->builder->build((string) $xml);

    expect($data->emitente->nome)->toBe('-');
    expect($data->emitente->telefone)->toBe('-');
    expect($data->emitente->email)->toBe('');
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
    expect($data->chaveAcesso)->toBe('XXX3303302112233450000195000000000000100000000001');
});

it('trims whitespace from address parts when joining', function () {
    // Insere whitespace nos três componentes de endereço (xLgr, nro, xBairro).
    $xml = str_replace('<xLgr>Rua Visconde do Rio Branco</xLgr>', '<xLgr>  Rua Visconde do Rio Branco  </xLgr>', $this->xml);
    $xml = str_replace('<nro>100</nro>', '<nro>  100  </nro>', $xml);
    $xml = str_replace('<xBairro>Centro</xBairro>', '<xBairro>  Centro  </xBairro>', $xml);
    $data = $this->builder->build($xml);

    expect($data->emitente->endereco)->toBe('Rua Visconde do Rio Branco, 100, Centro');
});

it('treats whitespace-only document as empty and falls through to next in firstNonEmpty', function () {
    // Substitui o CNPJ do emitente por whitespace (deve ser tratado como vazio após trim),
    // e adiciona um CPF que deve ser escolhido no lugar.
    $xml = str_replace('<CNPJ>11222333000181</CNPJ>', '<CNPJ>   </CNPJ><CPF>12345678901</CPF>', $this->xml);
    $data = $this->builder->build($xml);

    expect($data->emitente->cnpjCpf)->toBe('123.456.789-01');
});
