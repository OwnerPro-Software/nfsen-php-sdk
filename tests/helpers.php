<?php

use NFePHP\Common\Certificate;
use OwnerPro\Nfsen\Adapters\CertificateManager;
use OwnerPro\Nfsen\Adapters\NfseHttpClient;
use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Adapters\XmlSigner;
use OwnerPro\Nfsen\Dps\DTO\InfDPS\InfDPS;
use OwnerPro\Nfsen\Dps\DTO\Prest\Prest;
use OwnerPro\Nfsen\Dps\DTO\Serv\CServ;
use OwnerPro\Nfsen\Dps\DTO\Serv\Serv;
use OwnerPro\Nfsen\Dps\DTO\Shared\RegTrib;
use OwnerPro\Nfsen\Dps\DTO\Valores\Trib;
use OwnerPro\Nfsen\Dps\DTO\Valores\TribMun;
use OwnerPro\Nfsen\Dps\DTO\Valores\Valores;
use OwnerPro\Nfsen\Dps\DTO\Valores\VServPrest;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\CMotivoEmisTI;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\TpEmit;
use OwnerPro\Nfsen\Dps\Enums\Prest\OpSimpNac;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegEspTrib;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetISSQN;
use OwnerPro\Nfsen\Dps\Enums\Valores\TribISSQN;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Operations\NfseCanceller;
use OwnerPro\Nfsen\Operations\NfseConsulter;
use OwnerPro\Nfsen\Operations\NfseDistributor;
use OwnerPro\Nfsen\Operations\NfseEmitter;
use OwnerPro\Nfsen\Operations\NfseSubstitutor;
use OwnerPro\Nfsen\Pipeline\NfseRequestPipeline;
use OwnerPro\Nfsen\Pipeline\NfseResponsePipeline;
use OwnerPro\Nfsen\Support\GzipCompressor;
use OwnerPro\Nfsen\Support\XsdValidator;
use OwnerPro\Nfsen\Xml\Builders\CancellationBuilder;
use OwnerPro\Nfsen\Xml\DpsBuilder;

function makePfxContent(): string
{
    return file_get_contents(__DIR__.'/fixtures/certs/fake.pfx');
}

function makeIcpBrPfxContent(): string
{
    return file_get_contents(__DIR__.'/fixtures/certs/fake-icpbr.pfx');
}

function makeXsdValidator(): XsdValidator
{
    return new XsdValidator(__DIR__.'/../storage/schemes');
}

function makeTestCertificate(): Certificate
{
    return (new CertificateManager(makePfxContent(), 'secret'))->getCertificate();
}

function makeInfDps(
    ?NfseAmbiente $tpAmb = null,
    ?string $dhEmi = null,
    ?string $verAplic = null,
    ?string $serie = null,
    ?string $nDPS = null,
    ?string $dCompet = null,
    ?TpEmit $tpEmit = null,
    ?string $cLocEmi = null,
    ?CMotivoEmisTI $cMotivoEmisTI = null,
    ?string $chNFSeRej = null,
): InfDPS {
    return new InfDPS(
        tpAmb: $tpAmb ?? NfseAmbiente::HOMOLOGACAO,
        dhEmi: $dhEmi ?? '2026-02-27T10:00:00-03:00',
        verAplic: $verAplic ?? '1.0',
        serie: $serie ?? '1',
        nDPS: $nDPS ?? '1',
        dCompet: $dCompet ?? '2026-02-27',
        tpEmit: $tpEmit ?? TpEmit::Prestador,
        cLocEmi: $cLocEmi ?? '3501608',
        cMotivoEmisTI: $cMotivoEmisTI,
        chNFSeRej: $chNFSeRej,
    );
}

function makePrestadorCnpj(
    ?string $CNPJ = null,
    ?string $xNome = null,
    ?RegTrib $regTrib = null,
): Prest {
    return new Prest(
        CNPJ: $CNPJ ?? '12345678000195',
        regTrib: $regTrib ?? new RegTrib(
            opSimpNac: OpSimpNac::NaoOptante,
            regEspTrib: RegEspTrib::Nenhum,
        ),
        xNome: $xNome ?? 'Empresa Teste',
    );
}

function makeServicoMinimo(?string $cLocPrestacao = null): Serv
{
    return new Serv(
        cServ: new CServ(
            cTribNac: '010101',
            xDescServ: 'Serviço',
            cNBS: '123456789',
        ),
        cLocPrestacao: $cLocPrestacao ?? '3501608',
    );
}

function makeChaveAcesso(): string
{
    return '12345678901234567890123456789012345678901234567890';
}

function makeValoresMinimo(?string $vServ = null): Valores
{
    return new Valores(
        vServPrest: new VServPrest(vServ: $vServ ?? '100.00'),
        trib: new Trib(
            tribMun: new TribMun(
                tribISSQN: TribISSQN::Tributavel,
                tpRetISSQN: TpRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
    );
}

function makeNfsenClient(
    ?GzipCompressor $gzipCompressor = null,
    ?string $pfxContent = null,
    string $prefeitura = '9999999',
): NfsenClient {
    $pfxContent ??= makePfxContent();
    $certManager = new CertificateManager($pfxContent, 'secret');
    $ambiente = NfseAmbiente::HOMOLOGACAO;
    $prefeituraResolver = new PrefeituraResolver(__DIR__.'/../storage/prefeituras.json');
    $xsdValidator = makeXsdValidator();
    $httpClient = new NfseHttpClient($certManager->getCertificate(), 30, 10, true);
    $signer = new XmlSigner($certManager->getCertificate(), 'sha1');

    $pipeline = new NfseRequestPipeline(
        ambiente: $ambiente,
        prefeituraResolver: $prefeituraResolver,
        gzipCompressor: $gzipCompressor ?? new GzipCompressor,
        signer: $signer,
        authorIdentity: $certManager,
        prefeitura: $prefeitura,
        httpClient: $httpClient,
        validateIdentity: false,
    );

    $queryExecutor = new NfseResponsePipeline($httpClient);
    $seFinUrl = $prefeituraResolver->resolveSeFinUrl($prefeitura, $ambiente);
    $adnUrl = $prefeituraResolver->resolveAdnUrl($prefeitura, $ambiente);

    $emitter = new NfseEmitter($pipeline, new DpsBuilder($xsdValidator));

    return new NfsenClient(
        emitter: $emitter,
        canceller: new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente),
        substitutor: new NfseSubstitutor($emitter),
        consulter: new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura),
        distributor: new NfseDistributor($httpClient, $prefeituraResolver, $prefeitura, $adnUrl, $certManager->extract()['cnpj'] ?? ''),
    );
}

require_once __DIR__.'/helpers/danfse.php';
require_once __DIR__.'/helpers/xsd.php';

/**
 * Caminhos absolutos declarados pelo XSD da NFS-e, expandindo o modelo de conteúdo.
 *
 * Travessia estrita: desce por sequence/choice/all/extension/group, mas nunca entra
 * no `complexType` de um elemento filho por atalho. Um `.//xs:element` aqui achataria
 * os complexTypes inline e aceitaria como válido um caminho que pula nível.
 *
 * @return array<string, string> caminho => nome do tipo XSD
 */
function nfsenXsdPaths(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $schemes = __DIR__.'/../storage/schemes';
    $xs = 'http://www.w3.org/2001/XMLSchema';

    $tipos = [];
    $grupos = [];
    $globais = [];

    foreach (['NFSe_v1.01.xsd', 'tiposComplexos_v1.01.xsd', 'DPS_v1.01.xsd'] as $arquivo) {
        $doc = new DOMDocument;
        $doc->load($schemes.'/'.$arquivo);

        foreach ($doc->documentElement?->childNodes ?? [] as $filho) {
            if (! $filho instanceof DOMElement || $filho->namespaceURI !== $xs) {
                continue;
            }
            $nome = $filho->getAttribute('name');
            if ($nome === '') {
                continue;
            }
            match ($filho->localName) {
                'complexType' => $tipos[$nome] ??= $filho,
                'group' => $grupos[$nome] ??= $filho,
                'element' => $globais[$nome] ??= $filho,
                default => null,
            };
        }
    }

    $caminhos = [];
    $semPrefixo = fn (string $qname): string => (string) preg_replace('/^.*:/', '', $qname);

    /** @var Closure(?DOMElement, string, list<string>): void $percorreTipo */
    $percorreTipo = function (?DOMElement $tipo, string $prefixo, array $pilha) use (
        &$percorreTipo, &$caminhos, $tipos, $grupos, $xs, $semPrefixo
    ): void {
        if (! $tipo instanceof DOMElement) {
            return;
        }
        $nome = $tipo->getAttribute('name');
        if ($nome !== '') {
            if (in_array($nome, $pilha, true)) {
                return;
            }
            $pilha[] = $nome;
        }

        /** @var Closure(DOMElement): void $modelo */
        $modelo = function (DOMElement $no) use (
            &$modelo, &$percorreTipo, &$caminhos, $prefixo, $pilha, $tipos, $grupos, $xs, $semPrefixo
        ): void {
            foreach ($no->childNodes as $filho) {
                if (! $filho instanceof DOMElement || $filho->namespaceURI !== $xs) {
                    continue;
                }
                if (in_array($filho->localName, ['sequence', 'choice', 'all', 'complexContent', 'simpleContent', 'extension', 'restriction'], true)) {
                    $modelo($filho);

                    continue;
                }
                if ($filho->localName === 'group') {
                    $ref = $semPrefixo($filho->getAttribute('ref'));
                    if ($ref !== '' && isset($grupos[$ref])) {
                        $modelo($grupos[$ref]);
                    }

                    continue;
                }
                if ($filho->localName !== 'element') {
                    continue;
                }
                $nomeEl = $filho->getAttribute('name');
                if ($nomeEl === '') {
                    continue;
                }
                $caminho = $prefixo.'/'.$nomeEl;
                $tipoRef = $filho->getAttribute('type');
                $caminhos[$caminho] = $tipoRef !== '' ? $semPrefixo($tipoRef) : '(inline)';

                if ($tipoRef !== '' && isset($tipos[$semPrefixo($tipoRef)])) {
                    $percorreTipo($tipos[$semPrefixo($tipoRef)], $caminho, $pilha);

                    continue;
                }
                foreach ($filho->childNodes as $interno) {
                    if ($interno instanceof DOMElement && $interno->namespaceURI === $xs && $interno->localName === 'complexType') {
                        $percorreTipo($interno, $caminho, $pilha);
                    }
                }
            }
        };

        $modelo($tipo);
    };

    $raiz = $globais['NFSe'] ?? null;
    $tipoRaiz = $semPrefixo($raiz instanceof DOMElement ? $raiz->getAttribute('type') : '');
    $percorreTipo($tipos[$tipoRaiz] ?? null, 'NFSe', []);

    return $cache = $caminhos;
}

/**
 * Arquivos que leem o XML da NFS-e, e o nó que cada variável deles guarda.
 *
 * Escrito à mão a partir das atribuições de cada classe — e é por isso que uma
 * variável fora deste mapa faz o teste falhar em vez de ser ignorada. Ignorar em
 * silêncio é como a verificação vai ficando cega conforme o código cresce.
 *
 * A lista de arquivos importa tanto quanto os aliases: quando a montagem dos
 * participantes saiu do `DanfseDataBuilder` para o `ParticipanteBuilder`, varrer
 * só o primeiro deixaria metade dos caminhos sem verificação — e verde.
 *
 * Um alias pode apontar para mais de um nó quando o mesmo código percorre nós de
 * tipo idêntico (tomador e intermediário são ambos TCInfoPessoa).
 *
 * @return list<array{arquivo: string, globais: array<string, string|list<string>>, porMetodo: array<string, array<string, string|list<string>>>}>
 */
function nfsenXmlReaderFiles(): array
{
    $n = 'NFSe/infNFSe';
    $d = $n.'/DPS/infDPS';

    return [
        [
            'arquivo' => __DIR__.'/../src/Adapters/DanfseDataBuilder.php',
            'globais' => [
                'root' => 'NFSe', 'children' => 'NFSe', 'inf' => $n,
                'emit' => $n.'/emit', 'valNfse' => $n.'/valores',
                'infDps' => $d, 'IBSCBS' => $d.'/IBSCBS', 'prest' => $d.'/prest', 'regTrib' => $d.'/prest/regTrib',
                'serv' => $d.'/serv', 'cServ' => $d.'/serv/cServ', 'locPrest' => $d.'/serv/locPrest',
                'toma' => $d.'/toma', 'valores' => $d.'/valores',
                'desc' => $d.'/valores/vDescCondIncond', 'trib' => $d.'/valores/trib',
                'tribMun' => $d.'/valores/trib/tribMun', 'exigSusp' => $d.'/valores/trib/tribMun/exigSusp', 'tribFed' => $d.'/valores/trib/tribFed',
                'pc' => $d.'/valores/trib/tribFed/piscofins', 'totTrib' => $d.'/valores/trib/totTrib',
                'p' => $d.'/valores/trib/totTrib/pTotTrib',
                'v' => $d.'/valores/trib/totTrib/vTotTrib',
                'infoCompl' => $d.'/serv/infoCompl',
                // Bloco IBS/CBS: valores apurados em infNFSe/IBSCBS, declarações em infDPS/IBSCBS.
                'ibsNfse' => $n.'/IBSCBS', 'valIbs' => $n.'/IBSCBS/valores',
                'totCibs' => $n.'/IBSCBS/totCIBS', 'gIbsCbs' => $d.'/IBSCBS/valores/trib/gIBSCBS',
            ],
            'porMetodo' => [],
        ],
        [
            'arquivo' => __DIR__.'/../src/Danfse/ParticipanteBuilder.php',
            'globais' => [
                'inf' => $n, 'emit' => $n.'/emit', 'enderEmit' => $n.'/emit/enderNac',
                'prest' => $d.'/prest', 'regTrib' => $d.'/prest/regTrib', 'endPrest' => $d.'/prest/end',
                'toma' => $d.'/toma', 'interm' => $d.'/interm', 'dest' => $d.'/IBSCBS/dest',
                // Tomador e intermediário são o mesmo complexType (TCInfoPessoa) e
                // compartilham um método. O alias tem de render OS DOIS nós: mapeá-lo
                // só para `toma` faria o intermediário sumir do inventário de campos
                // cobertos — verde, e cego, que é o defeito que estes testes caçam.
                // `deInfoPessoa()` lê IM, que TCRTCInfoDest não declara — por isso o
                // destinatário só entra no método comum, abaixo.
                'pessoa' => [$d.'/toma', $d.'/interm'],
            ],
            'porMetodo' => [
                'participanteDe' => [
                    'pessoa' => [$d.'/toma', $d.'/interm', $d.'/IBSCBS/dest'],
                    'end' => [$d.'/toma/end', $d.'/interm/end', $d.'/IBSCBS/dest/end'],
                    'endNac' => [$d.'/toma/end/endNac', $d.'/interm/end/endNac', $d.'/IBSCBS/dest/end/endNac'],
                    'endExt' => [$d.'/toma/end/endExt', $d.'/interm/end/endExt', $d.'/IBSCBS/dest/end/endExt'],
                ],
                // O método comum de município serve os três participantes de
                // `participanteDe()` e também o prestador, que chega por
                // `municipioDoPrestador()`.
                'codigoIbgeDe' => [
                    'endNac' => [$d.'/toma/end/endNac', $d.'/interm/end/endNac', $d.'/IBSCBS/dest/end/endNac', $d.'/prest/end/endNac'],
                    'endExt' => [$d.'/toma/end/endExt', $d.'/interm/end/endExt', $d.'/IBSCBS/dest/end/endExt', $d.'/prest/end/endExt'],
                ],
                'codigoPostal' => [
                    'endNac' => [$d.'/toma/end/endNac', $d.'/interm/end/endNac', $d.'/IBSCBS/dest/end/endNac', $d.'/prest/end/endNac'],
                    'endExt' => [$d.'/toma/end/endExt', $d.'/interm/end/endExt', $d.'/IBSCBS/dest/end/endExt', $d.'/prest/end/endExt'],
                ],
                'municipioDaPessoa' => [
                    'endNac' => [$d.'/toma/end/endNac', $d.'/interm/end/endNac', $d.'/IBSCBS/dest/end/endNac', $d.'/prest/end/endNac'],
                    'endExt' => [$d.'/toma/end/endExt', $d.'/interm/end/endExt', $d.'/IBSCBS/dest/end/endExt', $d.'/prest/end/endExt'],
                ],
            ],
        ],
    ];
}

/**
 * Caminhos do XML que o SDK efetivamente navega para montar o DANFSe.
 *
 * @return array{caminhos: list<string>, problemas: list<string>, acessos: int}
 */
function nfsenDanfseBuilderPaths(): array
{
    $xsd = nfsenXsdPaths();
    $caminhos = [];
    $problemas = [];
    $acessos = 0;

    foreach (nfsenXmlReaderFiles() as $config) {
        $rotulo = basename($config['arquivo']);
        $fonte = (string) file_get_contents($config['arquivo']);

        foreach (preg_split('/(?=\n    (?:private|public) function )/', $fonte) ?: [] as $bloco) {
            preg_match('/function (\w+)/', $bloco, $m);
            $metodo = $m[1] ?? '(topo)';

            preg_match_all('/\$(\w+)((?:\s*\??->\s*\w+)+)\s*(\()?/', $bloco, $ocorrencias, PREG_SET_ORDER);

            foreach ($ocorrencias as $o) {
                $var = $o[1];
                if ($var === 'this') {
                    continue; // colaborador, não nó XML
                }

                $cadeia = array_values(array_filter(
                    array_map('trim', preg_split('/\??->/', trim($o[2])) ?: []),
                    fn (string $s): bool => $s !== '',
                ));

                if (($o[3] ?? '') === '(') {
                    array_pop($cadeia); // último segmento é chamada de método
                }
                if ($cadeia === []) {
                    continue;
                }

                $acessos++;
                $base = $config['porMetodo'][$metodo][$var] ?? $config['globais'][$var] ?? null;

                if ($base === null) {
                    $problemas[] = sprintf(
                        '%s %s(): variável $%s não está no mapa de aliases — declare o nó que ela guarda.',
                        $rotulo,
                        $metodo,
                        $var,
                    );

                    continue;
                }

                foreach ((array) $base as $raiz) {
                    $atual = $raiz;
                    foreach ($cadeia as $segmento) {
                        $proximo = $atual.'/'.$segmento;
                        if (! array_key_exists($proximo, $xsd)) {
                            $problemas[] = sprintf(
                                '%s %s(): $%s->%s resolve para "%s", que não existe no XSD — SimpleXML devolveria null e o campo sairia "-" no PDF.',
                                $rotulo,
                                $metodo,
                                $var,
                                implode('->', $cadeia),
                                $proximo,
                            );

                            continue 3;
                        }
                        $atual = $proximo;
                    }
                    $caminhos[$atual] = true;
                }
            }
        }
    }

    return ['caminhos' => array_keys($caminhos), 'problemas' => $problemas, 'acessos' => $acessos];
}
