<?php

use OwnerPro\Nfsen\Adapters\BaconQrCodeGenerator;
use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;
use OwnerPro\Nfsen\Adapters\DompdfHtmlToPdfConverter;

/**
 * As medidas e posições que a NT 008 fixa em centímetros.
 *
 * `DanfseSinglePageTest` garante que o documento cabe numa página; não diz onde as coisas
 * estão dentro dela. Os itens 2.4.2, 2.4.3 e a tabela 2.4.5 dão coordenadas absolutas, e
 * um CSS que as satisfaça hoje volta a divergir no primeiro ajuste de largura — foi assim
 * que o QR Code e o quadro do cabeçalho saíram do lugar.
 *
 * A leitura é feita no content stream do PDF, não numa rasterização: é PHP puro, sem
 * pdftoppm nem pdftotext, e as coordenadas são as que o Dompdf gravou, não as que um
 * detector de pixels inferiu.
 */
function nfsenConteudoPdf(string $xml): string
{
    $data = (new DanfseDataBuilder)->build($xml);
    $html = (new DanfseHtmlRenderer(new BaconQrCodeGenerator))->render($data);
    $pdf = (new DompdfHtmlToPdfConverter)->convert($html);

    preg_match_all('#stream\r?\n(.*?)endstream#s', $pdf, $streams);
    foreach ($streams[1] as $stream) {
        $inflado = @gzuncompress($stream);
        if (is_string($inflado) && str_contains($inflado, ' Td ')) {
            return $inflado;
        }
    }

    return '';
}

/** Converte a ordenada do PDF — origem no pé da página — em centímetros a partir do topo. */
function nfsenTopoEmCm(float $y): float
{
    return round((841.89 - $y) / 72 * 2.54, 2);
}

function nfsenEsquerdaEmCm(float $x): float
{
    return round($x / 72 * 2.54, 2);
}

/**
 * Casa uma coordenada com a da norma, tolerando um terço de milímetro.
 *
 * A NT dá as posições com duas casas em centímetros, mas o Dompdf reparte as larguras de
 * tabela em pontos e arredonda; exigir o centésimo exato faria o teste quebrar por 0,3pt
 * de diferença de repartição, que nenhum leitor do DANFSe percebe.
 */
function nfsenPertoDe(float $medido, float $exigido): void
{
    expect(abs($medido - $exigido))->toBeLessThanOrEqual(0.03,
        sprintf('Esperava %.2f cm, medi %.2f cm.', $exigido, $medido));
}

/** @return array{x: float, y: float, corpo: float} posição e corpo do texto, em pt */
function nfsenTexto(string $conteudo, string $trecho): array
{
    $achou = preg_match(
        '#BT ([\d.]+) ([\d.]+) Td /\w+ ([\d.]+) Tf\s*\[\('.preg_quote($trecho, '#').'#',
        $conteudo,
        $m,
    );

    expect($achou)->toBe(1, "Texto não encontrado no PDF: $trecho");

    return ['x' => (float) $m[1], 'y' => (float) $m[2], 'corpo' => (float) $m[3]];
}

/**
 * Ordenadas das divisórias de bloco — os segmentos horizontais de meio ponto que o
 * item 2.2.3 exige — da mais alta para a mais baixa, em pt.
 *
 * @return list<float>
 */
function nfsenDivisorias(string $conteudo): array
{
    preg_match_all('#([\d.]+) ([\d.]+) m\s+([\d.]+) ([\d.]+) l\s+S#', $conteudo, $m, PREG_SET_ORDER);

    $ys = [];
    foreach ($m as $traco) {
        if ($traco[2] === $traco[4] && (float) $traco[3] - (float) $traco[1] > 400) {
            $ys[] = (float) $traco[2];
        }
    }

    rsort($ys);

    return $ys;
}

beforeEach(function () {
    $this->conteudo = nfsenConteudoPdf((string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-autorizada.xml'));
});

it('places the QR code where the notice puts it', function () {
    // O QR entra como SVG: uma matriz de escala uniforme leva o desenho ao seu canto
    // inferior esquerdo, e a matriz de espelhamento seguinte carrega a altura do viewBox.
    $achou = preg_match(
        '#([\d.]+) 0\.000 0\.000 \1 ([\d.]+) ([\d.]+) cm.*?0\.000 0\.000 -1\.000 0\.000 ([\d.]+) cm#s',
        $this->conteudo,
        $m,
    );

    expect($achou)->toBe(1, 'Não achei a matriz de desenho do QR Code no PDF.');

    $escala = (float) $m[1];
    $lado = (float) $m[4] * $escala;

    // Item 2.4.3: X 17,48 cm, Y 1,67 cm, com no mínimo 1,52 x 1,52 cm.
    nfsenPertoDe(nfsenEsquerdaEmCm((float) $m[2]), 17.48);
    nfsenPertoDe(nfsenTopoEmCm((float) $m[3] + $lado), 1.67);
    expect(round($lado / 72 * 2.54, 2))->toBeGreaterThanOrEqual(1.52);
});

it('places the município/ambiente quadro where the notice puts it', function () {
    $texto = nfsenTexto($this->conteudo, 'Ambiente Gerador:');

    // Tabela 2.4.5: o quadro começa em 15,62 cm. O texto vem 2pt adiante, que é o
    // recuo lateral da célula.
    nfsenPertoDe(nfsenEsquerdaEmCm($texto['x'] - 2), 15.62);
});

// A tabela do item 2.4.5 e o Anexo I discordam sobre esta coluna: a tabela dá 10,51,
// o Anexo desenha o campo colado ao Simples Nacional. Manda o Anexo, pelo item 2.2.4.
it('seats the SN tax regime in the column the Anexo draws it in', function () {
    $texto = nfsenTexto($this->conteudo, 'Regime de Apura');

    nfsenPertoDe(nfsenEsquerdaEmCm($texto['x']), 5.41);
});

// O destinatário não tem inscrição municipal, e a coluna dela fica vazia: o telefone
// segue nos 15,62 em que a tabela do item 2.4.5 e o Anexo I concordam.
it('keeps the destinatário phone in the fourth column, as the other blocks do', function () {
    $conteudo = nfsenConteudoPdf((string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-ibscbs.xml'));

    preg_match_all('#BT ([\d.]+) [\d.]+ Td /\w+ [\d.]+ Tf\s*\[\(Telefone#', $conteudo, $telefones);

    expect($telefones[1])->toHaveCount(4);
    foreach ($telefones[1] as $x) {
        nfsenPertoDe(nfsenEsquerdaEmCm((float) $x - 2), 15.62);
    }
});

it('sets the identification labels in seven points, uppercase', function () {
    // Item 2.4.2: exceção dos campos do item 2.1.2 — os demais rótulos ficam em 6pt.
    expect(nfsenTexto($this->conteudo, 'CHAVE DE ACESSO DA NFS-E')['corpo'])->toBe(7.0);
    expect(nfsenTexto($this->conteudo, 'FINALIDADE')['corpo'])->toBe(7.0);
    expect(nfsenTexto($this->conteudo, 'CNPJ / CPF / NIF')['corpo'])->toBe(6.0);
});

it('sets the QR description in six points, over three lines', function () {
    // Item 2.4.3 dá o corpo e o número de linhas; passar de três invade o bloco do
    // prestador, que começa logo abaixo.
    expect(nfsenTexto($this->conteudo, 'A autenticidade desta NFS-e')['corpo'])->toBe(6.0);

    preg_match_all('#BT [\d.]+ ([\d.]+) Td /\w+ 6\.0 Tf\s*\[\((?:A autenticidade|leitura deste|acesso no)#', $this->conteudo, $linhas);

    expect(array_unique($linhas[1]))->toHaveCount(3);
});

// Notas 2, 3 e 4 do item 2.4.5: o bloco reduzido à frase única tem altura mínima de
// 0,32 cm e largura mínima de 20,40 cm.
it('gives a collapsed block the minimum height the notice sets', function () {
    $frase = nfsenTexto($this->conteudo, 'DESTINAT')['y'];

    $acima = null;
    $abaixo = null;
    foreach (nfsenDivisorias($this->conteudo) as $y) {
        if ($y > $frase) {
            $acima = $y;
        } elseif ($abaixo === null) {
            $abaixo = $y;
        }
    }

    expect($acima)->not->toBeNull()->and($abaixo)->not->toBeNull();
    expect(round(($acima - $abaixo) / 72 * 2.54, 3))->toBeGreaterThanOrEqual(0.32);
});
