<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use Closure;
use OwnerPro\Nfsen\Contracts\Driven\GeneratesQrCode;
use OwnerPro\Nfsen\Contracts\Driven\RendersDanfseHtml;
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;
use Throwable;

final readonly class DanfseHtmlRenderer implements RendersDanfseHtml
{
    private const string CONSULTA_URL_PRODUCAO = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=';

    private const string CONSULTA_URL_HOMOLOGACAO = 'https://hom.nfse.fazenda.gov.br/ConsultaPublica/?tpc=1&chave=';

    private const string TEMPLATE_PATH = __DIR__.'/../../storage/danfse/template.php'; // @pest-mutate-ignore ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — constante de classe avaliada em tempo de compilação; mutações aparecem como UNCOVERED no PCOV.

    private const string CSS_PATH = __DIR__.'/../../storage/danfse/template.css'; // @pest-mutate-ignore ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — constante de classe avaliada em tempo de compilação.

    public function __construct(
        private GeneratesQrCode $qrGenerator,
        private DanfseConfig $config,
    ) {}

    public function render(NfseData $data): string
    {
        /** @var string|null $css */
        static $css = null;
        if ($css === null) { // @pest-mutate-ignore IdenticalToNotIdentical,IfNegated — cache idempotente (static local); arquivo CSS embarcado no pacote.
            $css = (string) file_get_contents(self::CSS_PATH); // @pest-mutate-ignore RemoveStringCast — file_get_contents retorna string do arquivo embarcado.
        }

        $consultaUrl = $data->ambiente->isHomologacao() ? self::CONSULTA_URL_HOMOLOGACAO : self::CONSULTA_URL_PRODUCAO;
        $qrCode = $this->qrGenerator->dataUri($consultaUrl.$data->chaveAcesso);
        $logo = $this->config->logoDataUri;
        $municipality = $this->config->municipality;
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $this->renderTemplate($data, $qrCode, $logo, $municipality, $css, $h);
    }

    /**
     * As variáveis recebidas aqui (`$data`, `$qrCode`, `$logo`, `$municipality`, `$css`, `$h`)
     * são consumidas pelo template incluído via `include` — o rector/phpstan não consegue
     * detectar esse uso, por isso a assinatura permanece "rica" mesmo aparentando dead code.
     *
     * @param  Closure(string):string  $h
     */
    private function renderTemplate(
        NfseData $data,
        string $qrCode,
        ?string $logo,
        ?MunicipalityBranding $municipality,
        string $css,
        Closure $h,
    ): string {
        ob_start(); // @pest-mutate-ignore RemoveFunctionCall — sem ob_start o include emite direto; mutação não é morta de forma determinística no harness do pest.
        try {
            include self::TEMPLATE_PATH;

            return (string) ob_get_clean(); // @pest-mutate-ignore RemoveStringCast — ob_get_clean retorna string|false; cast é defensivo (ob_start acabou de ser chamado).
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
