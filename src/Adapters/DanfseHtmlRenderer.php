<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use Closure;
use OwnerPro\Nfsen\Contracts\Driven\GeneratesQrCode;
use OwnerPro\Nfsen\Contracts\Driven\RendersDanfseHtml;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use Throwable;

final readonly class DanfseHtmlRenderer implements RendersDanfseHtml
{
    /** Endereço do QR Code fixado pelo item 2.4.3 da NT 008. */
    private const string CONSULTA_URL_PRODUCAO = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=';

    /**
     * Desvio deliberado do item 2.4.3, que fixa um endereço só.
     *
     * A chave de uma NFS-e de homologação não existe no portal de produção: o QR da
     * norma levaria o leitor a uma consulta sem resultado. Como o DANFSe de homologação
     * já se anuncia "SEM VALIDADE JURÍDICA" no cabeçalho, apontar para o ambiente que
     * de fato responde serve ao propósito do item — "consulta rápida via dispositivos
     * móveis" — sem risco de confundir o documento com um válido.
     */
    private const string CONSULTA_URL_HOMOLOGACAO = 'https://hom.nfse.fazenda.gov.br/ConsultaPublica/?tpc=1&chave=';

    private const string TEMPLATE_PATH = __DIR__.'/../../storage/danfse/template.php'; // @pest-mutate-ignore ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — constante de classe avaliada em tempo de compilação; mutações aparecem como UNCOVERED no PCOV.

    private const string CSS_PATH = __DIR__.'/../../storage/danfse/template.css'; // @pest-mutate-ignore ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — constante de classe avaliada em tempo de compilação.

    /**
     * Logomarca da NFS-e do canto esquerdo do cabeçalho, exigida pelo item 2.4.3 da NT
     * 008, que indica o arquivo oficial em gov.br. Vem embarcada no pacote e não é
     * configurável: substituí-la por marca do emitente imprimiria informação que não
     * consta do XML, o que o item 2.1 veda, e a NT não reserva quadro para isso.
     */
    private const string LOGO_PATH = __DIR__.'/../../storage/danfse/logo-nfse.png'; // @pest-mutate-ignore ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — constante de classe avaliada em tempo de compilação.

    private string $css;

    private string $logo;

    public function __construct(
        private GeneratesQrCode $qrGenerator,
    ) {
        $this->css = (string) file_get_contents(self::CSS_PATH); // @pest-mutate-ignore RemoveStringCast — file_get_contents retorna string do arquivo embarcado.
        $this->logo = 'data:image/png;base64,'.base64_encode((string) file_get_contents(self::LOGO_PATH)); // @pest-mutate-ignore RemoveStringCast,ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — data URI do arquivo embarcado.
    }

    public function render(NfseData $data): string
    {
        $consultaUrl = $data->ambiente->isHomologacao() ? self::CONSULTA_URL_HOMOLOGACAO : self::CONSULTA_URL_PRODUCAO;
        $qrCode = $this->qrGenerator->dataUri($consultaUrl.$data->chaveAcesso);
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $this->renderTemplate($data, $qrCode, $this->logo, $this->css, $h);
    }

    /**
     * As variáveis recebidas aqui (`$data`, `$qrCode`, `$logo`, `$css`, `$h`)
     * são consumidas pelo template incluído via `include` — o rector/phpstan não consegue
     * detectar esse uso, por isso a assinatura permanece "rica" mesmo aparentando dead code.
     *
     * @param  Closure(string):string  $h
     */
    private function renderTemplate(
        NfseData $data,
        string $qrCode,
        string $logo,
        string $css,
        Closure $h,
    ): string {
        ob_start(); // @pest-mutate-ignore RemoveFunctionCall — sem ob_start o include emite direto; mutação não é morta de forma determinística no harness do pest.
        try {
            include self::TEMPLATE_PATH;

            return ob_get_clean();
            // @codeCoverageIgnoreStart
        } catch (Throwable $throwable) {
            // Defensivo: se o template lançar, ob_get_clean() do try não roda — limpa o buffer órfão.
            // Template só lança em bug de deploy (arquivo corrompido); não exercitável em testes.
            ob_end_clean(); // @pest-mutate-ignore RemoveFunctionCall — cleanup do buffer órfão em caminho defensivo não exercitável pelos testes.
            throw $throwable;
        }

        // @codeCoverageIgnoreEnd
    }
}
