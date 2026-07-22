<?php

use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;
use OwnerPro\Nfsen\Danfse\Data\DanfseServico;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotais;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotaisTributos;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoFederal;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoIbsCbs;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Enums\MarcaDagua;
use OwnerPro\Nfsen\Enums\NfseAmbiente;

covers(DanfseHtmlRenderer::class);

// Helpers `fakeQrGen()`, `sampleParticipante()`, `sampleData()` vêm de tests/helpers/danfse.php (Task 16.5).

it('produces HTML containing chave de acesso', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData());

    expect($html)->toContain('33033021211222333000181000000000001026010000010000');
});

// Item 2.4.3 fixa um endereço só, sem exceção para o ambiente de teste.
it('embeds QR code pointing to the consulta URL the notice fixes', function (NfseAmbiente $ambiente): void {
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData($ambiente));

    expect($html)->toContain('FAKEQR_');
    $expectedPayload = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=33033021211222333000181000000000001026010000010000';
    expect($html)->toContain(base64_encode($expectedPayload));
})->with([NfseAmbiente::PRODUCAO, NfseAmbiente::HOMOLOGACAO]);

it('marks a homologacao NFS-e as legally void in the header', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $prod = $r->render(sampleData(NfseAmbiente::PRODUCAO));
    $homo = $r->render(sampleData(NfseAmbiente::HOMOLOGACAO));

    expect($prod)->not->toContain('SEM VALIDADE JURÍDICA');
    expect($homo)->toContain('SEM VALIDADE JURÍDICA');
});

it('leaves a homologacao NFS-e without a watermark of its own', function (): void {
    // A NT só prevê marca d'água nos itens 2.5.1 e 2.5.2. Havia uma de "HOMOLOGAÇÃO",
    // que numa nota cancelada em homologação se sobrepunha à exigida.
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData(NfseAmbiente::HOMOLOGACAO, marcaDagua: MarcaDagua::Cancelada));

    expect(substr_count($html, '<div class="watermark'))->toBe(1);
    expect($html)->toContain('<div class="watermark-nt">CANCELADA</div>')
        ->not->toContain('>HOMOLOGAÇÃO<');
});

it('shows "NÃO IDENTIFICADO" when there is no intermediario', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData(interm: null));

    expect($html)->toContain('NÃO IDENTIFICADO');
});

it('shows intermediario block when present', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $interm = sampleParticipante('INTERMEDIÁRIO LTDA');
    $html = $r->render(sampleData(interm: $interm));

    expect($html)->toContain('INTERMEDIÁRIO LTDA');
});

it('names the intermediário identification field the way the notice does', function (): void {
    // Item 2.4.5: o campo é "CNPJ / CPF / NIF" nos quatro blocos de participante. Só o
    // do intermediário omitia o NIF, embora o valor já resolva CNPJ, CPF, NIF e cNaoNIF.
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData(interm: sampleParticipante('INTERMEDIÁRIO LTDA')));

    expect(substr_count($html, 'CNPJ / CPF / NIF'))->toBe(3);
    expect($html)->not->toContain('>CNPJ / CPF<');
});

it('fills the header corner with the three fields of item 2.4.3', function (): void {
    // Item 2.4.3: no canto direito, o município do emitente, o ambiente gerador e o
    // tipo de ambiente. O quadro já foi ocupado pela identificação da prefeitura, que
    // não sai do XML — o item 2.1 veda, e a NT nunca a previu.
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData());

    expect($html)->toContain('Município: Niterói / RJ')
        ->toContain('Ambiente Gerador: Sistema Nacional da NFS-e')
        ->toContain('Tipo de Ambiente: Produção');
});

it('reports the environment type the NFS-e was generated in', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData(NfseAmbiente::HOMOLOGACAO));

    expect($html)->toContain('Tipo de Ambiente: Homologação');
});

it('omits the municipality line when the notice says not to show it', function (): void {
    // Item 2.4.5, linha MUNICÍPIO: "Não exibir, quando o item do cód. de tributação
    // nacional informado for 99". O builder devolve string vazia nesse caso.
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData(municipioEmitente: ''));

    expect($html)->not->toContain('Município:')
        // O resto do quadro continua: a supressão é da linha, não do bloco.
        ->toContain('Ambiente Gerador:');
});

it('always prints the official NFS-e logomark, which is not configurable', function (): void {
    // Item 2.4.3: a logomarca do canto esquerdo é da NFS-e, e a NT indica o arquivo
    // oficial. Não há quadro reservado à marca do emitente em lugar nenhum do DANFSe.
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData());

    $oficial = 'data:image/png;base64,'.base64_encode(
        (string) file_get_contents(__DIR__.'/../../../storage/danfse/logo-nfse.png')
    );

    expect($html)->toContain($oficial);
});

it('renders single dash for codigoTribMunicipal when codigo and desc are both empty', function (): void {
    // Sem cTribMun nem xTribMun, template não deve renderizar "- -" (traço duplo com espaço).
    $r = new DanfseHtmlRenderer(fakeQrGen());
    $base = sampleData();
    $data = new NfseData(
        chaveAcesso: $base->chaveAcesso, numeroNfse: $base->numeroNfse,
        competencia: $base->competencia, emissaoNfse: $base->emissaoNfse,
        numeroDps: $base->numeroDps, serieDps: $base->serieDps, emissaoDps: $base->emissaoDps,
        ambiente: $base->ambiente, situacao: $base->situacao, finalidade: $base->finalidade,
        emitidaPor: $base->emitidaPor, ambienteGerador: $base->ambienteGerador,
        municipioEmitente: $base->municipioEmitente,
        emitente: $base->emitente, tomador: $base->tomador,
        intermediario: $base->intermediario, destinatario: $base->destinatario, destinatarioEhTomador: $base->destinatarioEhTomador,
        servico: new DanfseServico(
            codigoTribNacional: '01.07.00', descTribNacional: 'Desenvolvimento',
            codigoTribMunicipal: '-', descTribMunicipal: '-',
            localPrestacao: 'São Paulo', paisPrestacao: '-', descricao: 'X', codigoNbs: '-',
        ),
        tribMun: $base->tribMun, tribFed: $base->tribFed, tribIbsCbs: $base->tribIbsCbs, totais: $base->totais,
        totaisTributos: $base->totaisTributos, informacoesComplementares: $base->informacoesComplementares,
    );

    $html = $r->render($data);

    expect($html)->not->toContain('- -');
});

it('prints the NBS code in the SERVIÇO PRESTADO block, where the notice puts it', function (): void {
    // Item 2.4.5: "CÓDIGO DA NBS" é campo do bloco de serviço, ao lado do código de
    // tributação. Ficava em "Informações Complementares", que a NT reserva à união
    // de outros dez campos.
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData(codigoNbs: '111032200'));

    $servico = substr($html, (int) strpos($html, 'SERVIÇO PRESTADO'), (int) strpos($html, 'TRIBUTAÇÃO MUNICIPAL') - (int) strpos($html, 'SERVIÇO PRESTADO'));

    expect($servico)->toContain('Código da NBS')->toContain('111032200');
});

it('prints a dash for the NBS code when the NFS-e has none', function (): void {
    // Nota 12: campo sem informação no XML sai com traço, não sumindo.
    $r = new DanfseHtmlRenderer(fakeQrGen());

    $html = $r->render(sampleData(codigoNbs: '-'));

    expect($html)->toContain('Código da NBS')->not->toContain('111032200');
});

it('escapes HTML in data fields (XSS prevention)', function (): void {
    $malicious = sampleParticipante("<script>alert('xss')</script>");
    $data = new NfseData(
        chaveAcesso: 'X', numeroNfse: '1', competencia: '-', emissaoNfse: '-',
        numeroDps: '1', serieDps: '1', emissaoDps: '-',
        ambiente: NfseAmbiente::PRODUCAO,
        situacao: '-', finalidade: '-', emitidaPor: '-', ambienteGerador: '-', municipioEmitente: '-',
        emitente: $malicious, tomador: sampleParticipante(), intermediario: null, destinatario: null, destinatarioEhTomador: false,
        servico: new DanfseServico('-', '-', '-', '-', '-', '-', '-', '-'),
        tribMun: new DanfseTributacaoMunicipal('-', '-', '-', '-', '-', '-', '-', '-', '-', false, false, '-', '-', '-', '-', '-'),
        tribFed: new DanfseTributacaoFederal('-', '-', '-', '-', '-', '-'),
        tribIbsCbs: new DanfseTributacaoIbsCbs('-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-'),
        totais: new DanfseTotais('-', '-', '-', '-', '-', '-', '-', '-', '-'),
        totaisTributos: new DanfseTotaisTributos('-', '-', '-'),
        informacoesComplementares: '',
    );

    $r = new DanfseHtmlRenderer(fakeQrGen());
    $html = $r->render($data);

    expect($html)->not->toContain("<script>alert('xss')</script>");
    expect($html)->toContain('&lt;script&gt;');
    // Aspas simples também devem ser escapadas (garante ENT_QUOTES, não só ENT_COMPAT).
    expect($html)->toContain('&#039;xss&#039;');
});

it('states the destinatário is the tomador instead of calling it unidentified', function (): void {
    // NT 008, item 2.4.5: nota 3 para destinatário igual ao tomador, nota 2 para
    // destinatário sem dados. São frases diferentes porque dizem coisas diferentes.
    $base = sampleData();
    $data = new NfseData(
        chaveAcesso: $base->chaveAcesso, numeroNfse: $base->numeroNfse,
        competencia: $base->competencia, emissaoNfse: $base->emissaoNfse,
        numeroDps: $base->numeroDps, serieDps: $base->serieDps, emissaoDps: $base->emissaoDps,
        ambiente: $base->ambiente, situacao: $base->situacao, finalidade: $base->finalidade,
        emitidaPor: $base->emitidaPor, ambienteGerador: $base->ambienteGerador,
        municipioEmitente: $base->municipioEmitente,
        emitente: $base->emitente, tomador: $base->tomador, intermediario: null,
        destinatario: null, destinatarioEhTomador: true,
        servico: $base->servico, tribMun: $base->tribMun, tribFed: $base->tribFed, tribIbsCbs: $base->tribIbsCbs,
        totais: $base->totais, totaisTributos: $base->totaisTributos,
        informacoesComplementares: $base->informacoesComplementares,
    );

    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render($data);

    expect($html)->toContain('O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO');
    expect($html)->not->toContain('DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO');
});

it('collapses the ISSQN block when the operation falls outside the tax', function (): void {
    // NT 008, item 2.3.1: o bloco traz "apenas" a frase, e a altura devolvida vai para
    // os quadros elásticos.
    $base = sampleData();
    $tribMun = new DanfseTributacaoMunicipal(
        tributacaoIssqn: 'Não Incidência', municipioIncidencia: $base->tribMun->municipioIncidencia,
        regimeEspecial: $base->tribMun->regimeEspecial, tipoImunidade: $base->tribMun->tipoImunidade,
        suspensaoExigibilidade: $base->tribMun->suspensaoExigibilidade,
        numeroProcessoSuspensao: $base->tribMun->numeroProcessoSuspensao,
        beneficioMunicipal: $base->tribMun->beneficioMunicipal, calculoBM: $base->tribMun->calculoBM,
        totalDeducoesReducoes: $base->tribMun->totalDeducoesReducoes,
        exibeRegimeEImunidade: false, exibeBeneficioEDeducoes: false,
        bcIssqn: $base->tribMun->bcIssqn, aliquota: $base->tribMun->aliquota,
        retencaoIssqn: $base->tribMun->retencaoIssqn, issqnApurado: $base->tribMun->issqnApurado,
        sujeitaAoIssqn: false,
    );
    $data = new NfseData(
        chaveAcesso: $base->chaveAcesso, numeroNfse: $base->numeroNfse,
        competencia: $base->competencia, emissaoNfse: $base->emissaoNfse,
        numeroDps: $base->numeroDps, serieDps: $base->serieDps, emissaoDps: $base->emissaoDps,
        ambiente: $base->ambiente, situacao: $base->situacao, finalidade: $base->finalidade,
        emitidaPor: $base->emitidaPor, ambienteGerador: $base->ambienteGerador,
        municipioEmitente: $base->municipioEmitente,
        emitente: $base->emitente, tomador: $base->tomador, intermediario: null,
        destinatario: null, destinatarioEhTomador: false,
        servico: $base->servico, tribMun: $tribMun, tribFed: $base->tribFed,
        tribIbsCbs: $base->tribIbsCbs, totais: $base->totais, totaisTributos: $base->totaisTributos,
        informacoesComplementares: $base->informacoesComplementares,
    );

    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render($data);

    expect($html)->toContain('TRIBUTAÇÃO MUNICIPAL (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN');
    expect($html)->not->toContain('BC ISSQN');
    expect($html)->not->toContain('Alíquota Aplicada');
    // Os blocos vizinhos seguem inteiros: a supressão é deste, não da tributação toda.
    expect($html)->toContain('TRIBUTAÇÃO FEDERAL (EXCETO CBS)');
});

it('collapses the tomador block to the notice wording when there is no tomador', function (): void {
    // NT 008, item 2.4.5, nota 2: o bloco traz "apenas" a frase — não os campos vazios.
    $base = sampleData();
    $data = new NfseData(
        chaveAcesso: $base->chaveAcesso, numeroNfse: $base->numeroNfse,
        competencia: $base->competencia, emissaoNfse: $base->emissaoNfse,
        numeroDps: $base->numeroDps, serieDps: $base->serieDps, emissaoDps: $base->emissaoDps,
        ambiente: $base->ambiente, situacao: $base->situacao, finalidade: $base->finalidade,
        emitidaPor: $base->emitidaPor, ambienteGerador: $base->ambienteGerador,
        municipioEmitente: $base->municipioEmitente,
        emitente: $base->emitente, tomador: null, intermediario: null,
        destinatario: null, destinatarioEhTomador: false,
        servico: $base->servico, tribMun: $base->tribMun, tribFed: $base->tribFed,
        tribIbsCbs: $base->tribIbsCbs, totais: $base->totais, totaisTributos: $base->totaisTributos,
        informacoesComplementares: $base->informacoesComplementares,
    );

    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render($data);

    expect($html)->toContain('TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e');
    expect($html)->not->toContain('TOMADOR / ADQUIRENTE');
    // O bloco do prestador, que usa os mesmos rótulos, continua inteiro.
    expect($html)->toContain('PRESTADOR / FORNECEDOR');
});

it('omits the PIS/COFINS row once the competência passes 2026', function (): void {
    // NT 008, item 2.4.5, nota 6: a linha marcada com *** no Anexo I só é impressa
    // para competência até o final do ano-calendário de 2026.
    $base = sampleData();
    $tribFed = new DanfseTributacaoFederal(
        irrf: $base->tribFed->irrf, cp: $base->tribFed->cp, csll: $base->tribFed->csll,
        pis: $base->tribFed->pis, cofins: $base->tribFed->cofins,
        descricaoContribuicoesRetidas: $base->tribFed->descricaoContribuicoesRetidas,
        exibePisCofins: false,
    );
    $data = new NfseData(
        chaveAcesso: $base->chaveAcesso, numeroNfse: $base->numeroNfse,
        competencia: $base->competencia, emissaoNfse: $base->emissaoNfse,
        numeroDps: $base->numeroDps, serieDps: $base->serieDps, emissaoDps: $base->emissaoDps,
        ambiente: $base->ambiente, situacao: $base->situacao, finalidade: $base->finalidade,
        emitidaPor: $base->emitidaPor, ambienteGerador: $base->ambienteGerador,
        municipioEmitente: $base->municipioEmitente,
        emitente: $base->emitente, tomador: $base->tomador, intermediario: null,
        destinatario: null, destinatarioEhTomador: false,
        servico: $base->servico, tribMun: $base->tribMun, tribFed: $tribFed, tribIbsCbs: $base->tribIbsCbs,
        totais: $base->totais, totaisTributos: $base->totaisTributos,
        informacoesComplementares: $base->informacoesComplementares,
    );

    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render($data);

    expect($html)->not->toContain('PIS - Débito Apuração Própria');
    expect($html)->not->toContain('COFINS - Débito Apuração Própria');
    expect($html)->not->toContain('Descrição Contrib. Sociais - Retidas');
    // O resto do bloco fica: a nota 6 suprime a linha, não a tributação federal.
    expect($html)->toContain('IRRF');
    expect($html)->toContain('Contribuição Previdenciária - Retida');
});

it('omits the suppressible ISSQN rows the NFS-e has no data for', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    expect($html)->not->toContain('Tipo de Imunidade do ISSQN');
    expect($html)->not->toContain('Benefício Municipal');
    // O resto do bloco continua: a supressão é por linha, não pelo bloco.
    expect($html)->toContain('BC ISSQN');
});

it('prints no marca d\'água for a vigente NFS-e', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    expect($html)->not->toContain('<div class="watermark-nt">');
});

it('prints the "CANCELADA" marca d\'água required by item 2.5.1', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen()))
        ->render(sampleData(marcaDagua: MarcaDagua::Cancelada));

    expect($html)->toContain('<div class="watermark-nt">CANCELADA</div>');
});

it('prints the "SUBSTITUÍDA" marca d\'água required by item 2.5.2', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen()))
        ->render(sampleData(marcaDagua: MarcaDagua::Substituida));

    expect($html)->toContain('<div class="watermark-nt">SUBSTITUÍDA</div>');
});

it('styles the marca d\'água with the measurements of items 2.5.1 and 2.5.2', function (): void {
    // Diagonal, formato normal, mínimo 50 pontos, Arial, cinza K35 (= #a6a6a6).
    $html = (new DanfseHtmlRenderer(fakeQrGen()))
        ->render(sampleData(marcaDagua: MarcaDagua::Cancelada));

    $css = substr($html, (int) strpos($html, '.watermark-nt {'));

    expect($css)->toContain('rotate(-45deg)')
        ->toContain('font-size: 50pt')
        ->toContain('font-weight: normal')
        ->toContain('color: #a6a6a6')
        ->toContain('font-family: Arial');
});

it('gives the approximate taxes no block of their own', function (): void {
    // A NT 008 não prevê esse bloco: a nota 10 do item 2.4.5 põe os totais dentro de
    // "Informações Complementares", e o item 2.2.4 manda obedecer ao Anexo I.
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    expect($html)->not->toContain('TOTAIS APROXIMADOS DOS TRIBUTOS');
});

it('prints the fixed totals line inside the complementary information block', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    $bloco = substr($html, (int) strpos($html, 'INFORMAÇÕES COMPLEMENTARES'));

    expect($bloco)->toContain('Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: 4,50% ; Estaduais: 0,10% ; Municipais: 2,00%');
});

it('gives the fixed totals line a table row of its own', function (): void {
    // Nota 10: o corte do texto livre é "sem prejuízo à linha de Totais Aproximados
    // dos Tributos, que é fixa". Como irmã do texto na mesma célula, ela era
    // sobreposta pelo que transbordava — só o layout de tabela garante a separação.
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    $bloco = substr($html, (int) strpos($html, 'INFORMAÇÕES COMPLEMENTARES'));
    $linhaDoTexto = (int) strpos($bloco, 'texto-livre');
    $linhaDosTotais = (int) strpos($bloco, 'Totais Aproximados');

    expect(substr($bloco, $linhaDoTexto, $linhaDosTotais - $linhaDoTexto))->toContain('<tr>');
});

it('lets the free-text boxes grow instead of clipping them', function (): void {
    // O teto de 44pt cortava no meio de uma linha, e a linha de totais era desenhada
    // por cima do pedaço cortado. O item 2.3.1 trata estes dois quadros como
    // elásticos; quem garante a página única é o limite de caracteres da NT.
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    expect($html)->not->toContain('overflow: hidden');
});

it('lays the VALOR TOTAL block out in four columns, as the annex draws it', function (): void {
    // Havia uma linha de cinco células numa tabela de quatro: a última transbordava
    // para fora da moldura e arrastava o rodapé do documento junto.
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    // Do <table> que abre o bloco até o </table> que o fecha: cortar pelo título
    // deixaria a primeira célula de fora, porque o título mora dentro dela.
    $titulo = (int) strpos($html, 'VALOR TOTAL DA NFS-e');
    $inicio = (int) strrpos(substr($html, 0, $titulo), '<table');
    $bloco = substr($html, $inicio, (int) strpos($html, '</table>', $inicio) - $inicio);

    $linhas = array_filter(
        explode('<tr>', $bloco),
        static fn (string $linha): bool => str_contains($linha, '<td'),
    );

    expect($linhas)->toHaveCount(2);

    foreach ($linhas as $linha) {
        // Sem colspan no bloco: quatro células por linha, uma por coluna do anexo.
        expect(substr_count($linha, '<td'))->toBe(4);
        expect($linha)->not->toContain('colspan');
    }
});

it('prints the seven fields of item 2.1.11 in the VALOR TOTAL block', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    expect($html)->toContain('Valor da Operação / Serviço')
        ->toContain('Desconto Incondicionado')
        ->toContain('Desconto Condicionado')
        ->toContain('Total das Retenções (ISSQN / Federais)')
        ->toContain('Valor Líquido da NFS-e')
        ->toContain('Total do IBS/CBS')
        ->toContain('Valor Líquido da NFS-e + IBS/CBS')
        // O ISSQN retido tem lugar no bloco municipal; o PIS/COFINS próprio, no federal.
        ->not->toContain('PIS/COFINS - Débito Apur. Própria')
        ->not->toContain('Total das Retenções Federais');
});

it('shades exactly what item 2.2.3 names, and nothing else', function (): void {
    // "O cabeçalho, os títulos de cada bloco de campos e os campos 'Emitente da NFS-e'
    // e 'Valor Líquido da NFS-e + IBS/CBS' devem ter sombreamento (fundo) na cor cinza
    // claro (5% de densidade) […] mantendo-se o fundo branco para os demais campos."
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    // 5% de preto = #f2f2f2, e é o único fundo do documento.
    expect($html)->toContain('background-color: #f2f2f2;')
        ->and(preg_match_all('/background-color:/', $html))->toBe(1);

    $sombreados = substr_count($html, 'class="sombreado"');
    expect($sombreados)->toBe(2);

    // Do <body> em diante: o CSS inlinado cita os dois campos no comentário que explica
    // a regra, e uma busca no documento inteiro casaria com ele.
    $corpo = substr($html, (int) strpos($html, '<body>'));

    foreach (['Emitente da NFS-e', 'Valor Líquido da NFS-e \+ IBS/CBS'] as $campo) {
        expect($corpo)->toMatch('#<td class="sombreado">\s*<span class="label">'.$campo.'</span>#');
    }
});

it('draws block dividers at half a point and the page border at one', function (): void {
    // Item 2.2.3: "as linhas divisórias dos blocos […] deverão ter 0,5 (meio) ponto de
    // espessura. Além disso, página deverá ter borda de 1 (um) ponto".
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    expect($html)->toContain('border-bottom: 0.5pt solid #000;')
        ->toContain('border: 1pt #000 solid;')
        ->not->toContain('border-bottom: 1px');
});

it('asks for the two fonts of item 2.4, one per role', function (): void {
    // "Arial para os títulos/labels e Microsoft Sans Serif para os conteúdos."
    $html = (new DanfseHtmlRenderer(fakeQrGen()))->render(sampleData());

    $css = substr($html, (int) strpos($html, '<style>'), (int) strpos($html, '</style>'));

    expect($css)->toMatch('/body \{[^}]*font-family: Arial/s')
        ->toMatch('/\.value \{[^}]*font-family: \'Microsoft Sans Serif\'/s');
});
