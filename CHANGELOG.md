# Changelog

All notable changes to this project will be documented in this file.

## [3.0.0] - Não lançado

### Fixed

- **`forStandalone()` nunca conseguiu enviar uma requisição sequer: o adapter HTTP dependia do facade `Http`, que exige um app Laravel bootado.** Toda operação standalone — emitir, cancelar, consultar, distribuir — morria antes de qualquer byte sair, com `RuntimeException("A facade root has not been set.")`; e por não ser falha de transporte, a exceção ainda escapava de `guardTransport()`, furando o contrato tipado de `CommunicationException`. A suíte nunca viu o defeito porque o Testbench boota um app e arma o facade em todo teste. `NfseHttpClient` passa a operar sobre uma `Illuminate\Http\Client\Factory`: injetável pelo construtor (o que também permite fake sem facade), própria quando não há app, e a **mesma instância do facade** sob Laravel — para que o `Http::fake()` dos consumidores continue interceptando, inclusive quando armado depois de o cliente ser construído.

  A mesma condição — framework instalado, app não bootado — derrubava também o despacho de eventos: `event()` e `report()` existem nesse cenário, mas ambos resolvem do container e lançam `BindingResolutionException`; o `report($e)` do resgate ficava desprotegido e a exceção subia pela operação inteira. Evento é best-effort por contrato do próprio trait: agora o resgate também é guardado, e a operação segue.

- **`DpsId::generate()` acomodava entrada de tamanho errado e devolvia um identificador bem formado e errado.** O município e a inscrição entram no ID com largura fixa, então um valor fora da medida não estourava o padrão `DPS[0-9]{42}` — era ajustado: `cLocEmi` de oito dígitos era cortado no sétimo e passava a nomear **outro** município; um CNPJ de cinco dígitos ganhava zeros à esquerda e virava **outra** inscrição, que existe. O método é `@api` e serve à reconciliação pós-timeout, onde isso custa caro: com o ID errado, `consultar()->dps($id)` responde `DPS_NOT_FOUND`, que o contrato de `IndeterminateResultException` manda ler como "é seguro reemitir com o mesmo nDPS" — um engano de digitação viraria emissão em dobro. Os três campos passam a ser conferidos na largura exata do schema (`TSCodMunIBGE` sete dígitos, `TSCNPJ` catorze, `TSCPF` onze, todos sem máscara). Dois testes que documentavam a truncagem como comportamento desejado foram substituídos.

- **Telefone de participante no exterior saía com máscara de DDD brasileiro.** `Formatter::phone()` mascara por contagem de dígitos, e um número estrangeiro de 10 ou 11 dígitos casa com a contagem: `12125551234` virava `(12) 12555-1234`, afirmando um DDD que não existe — e contradizendo o endereço que a mesma linha do bloco imprimia como estrangeiro. A tabela do item 2.4.5 não pede máscara alguma para "TELEFONE" (ao contrário do CEP, que ela exemplifica como `nn.nnn-nnn`), e `TSTelefone` separa os dois casos: "Preencher com o Código DDD + número do telefone. Nas operações com exterior é permitido informar o código do país + código da localidade + número do telefone". A máscara passa a valer só para quem está no país, pelo mesmo sinal que já decidia o município e o CEP, para os três campos do bloco não discordarem entre si. `Formatter::phone()` segue como está — quem a chama direto não muda de comportamento.

- **`distribuicao()` mandava `cnpjConsulta=` vazio para certificado sem CNPJ.** Com um e-CPF, `CertificateManager::extract()` devolve `cnpj => null` e o cliente guarda string vazia, que ia para a URL do mesmo jeito: `?cnpjConsulta=&lote=true`. O parâmetro não tem `required` no `GET /DFe/{NSU}` do swagger do ADN — é opcional —, então oferecer um valor malformado onde bastava calar só serve para o servidor recusar ou responder algo opaco. Sem CNPJ, o parâmetro sai da query e o ADN aplica o próprio default. Passá-lo explicitamente continua funcionando, com ou sem CNPJ no certificado.

- **Cinco campos de município e localidade iam ao papel sem o teto que a NT lhes dá.** Nome, endereço, descrições e rótulos de enum já eram cortados na medida do item 2.4.5; os campos alimentados por texto livre de localidade ficaram de fora, e o XSD permite a todos eles estourar a própria célula: "MUNICÍPIO / SIGLA UF" dos quatro participantes (37) recebe `xCidade` e `xEstProvReg`, 60 cada, chegando a 123 no ramo do exterior; "MUNICÍPIO" do cabeçalho (37) e "LOCAL DA PRESTAÇÃO" e "MUNICÍPIO DA INCIDÊNCIA" (42) recorrem a `xLocEmi`, `xLocPrestacao` e `xLocIncid`, todos `TSDesc150`; e a linha de incidência do IBS/CBS (56) recorre a `xLocalidadeIncid`, que é `TSDesc600`. Qualquer um deles empurrava o DANFSe contra o item 2.2, que exige uma única página.

  O corte usa a **capacidade cheia** da NT, e não a capacidade menos as reticências como em nome e endereço. A diferença tem razão: a tabela não pede reticências em campo de localidade nenhum, e o maior município do IBGE satura a régua — "Vila Bela da Santíssima Trindade / MT" tem exatamente 37 caracteres. Reservar as três posições truncaria um município legítimo em toda DANFSe dele, para proteger o leiaute de um caso que só o exterior produz.

- **A promessa de não derrubar o lote valia só até o primeiro campo fora do tipo declarado.** `DocumentoFiscal` afirma no próprio docblock que "um item que não pôde ser interpretado por completo **não** interrompe o lote" e que "o `nsu` é preservado em qualquer cenário" — mas só a falha de gzip era capturada, e todo o resto entrava cru em assinaturas `strict_types`. Um `NSU` em texto, uma `ChaveAcesso` que não fosse string, um item do lote que nem objeto fosse: qualquer um virava `TypeError`, que subia pelo `array_map` de `DistribuicaoResponse::fromApiResult()` e levava embora a página toda, com os NSU de que o chamador precisaria para refazer a busca.

  **Isto é tolerância deliberada, não correção de contrato:** `DistribuicaoNSU` no `ADN-Contribuinte-swagger.json` declara `NSU` como `integer/int64`, `ChaveAcesso` e `DataHoraGeracao` como `string`, os dois tipos como enums de string, e fecha o objeto com `additionalProperties: false` — um ADN em conformidade nunca manda outra coisa, e nenhuma destas guardas é alcançável contra ele. O que se corrigiu foi a promessa incondicional do docblock estar apostada na conformidade do servidor. Cada campo passa a ser lido com o tipo conferido, e o que não deu para ler é nomeado em `parseError`. Só o `nsu` é convertido, de texto decimal: é o campo que a classe promete preservar sempre, e dígitos não têm outra leitura. Nos demais, converter inventaria dado — os códigos de tipo são nomes, e a chave tem 50 dígitos que float algum guarda sem truncar. Item que não é objeto sai do lote: não traz nsu nem chave, e não há o que preservar dele.

  O risco fundamentado nesta mesma área — valor de enum desconhecido e campo ausente, que o swagger admite por não declarar `required` — já era coberto pelo `tryFrom` com `parseError`.

- **O identificador da DPS levava a inscrição do prestador mesmo quando não era ele quem emitia.** `TSEmitenteDPS` admite que o tomador (`tpEmit=2`) ou o intermediário (`tpEmit=3`) emitam a DPS, e `TSIdDPS` reúne município + inscrição federal + série + número. Série e número são do **emitente** — cada um controla a própria sequência —, então, com a inscrição do prestador ali, dois tomadores que emitissem para o mesmo prestador usando a própria série 1 nº 1 chegavam ao **mesmo `Id`**, e a chave deixava de ser única. Na importação de serviço era pior: o prestador estrangeiro só tem NIF, então a inscrição saía com 14 zeros e o `Id` ficava idêntico para todo tomador do município na mesma série e número — justamente a chave que `consultar()->dps($id)` usa para reconciliar depois de um timeout, e cujo `DPS_NOT_FOUND` autoriza reemitir. `DpsData::emitterIdentity()` passa a resolver o grupo que `tpEmit` designa. Como `toma` e `interm` são `minOccurs=0`, o XSD não consegue exigir o grupo do emitente: ausente, agora é `InvalidDpsArgument` em vez de identificador zerado.

- **A validação de identidade tinha um ponto cego que a desligava por inteiro.** As duas comparações exigiam os dois lados do **mesmo** campo (`certCnpj` com `prestCnpj`, `certCpf` com `prestCpf`), então um e-CNPJ contra prestador que só declarava CPF — ou o inverso — não caía em nenhuma delas, e a DPS era assinada e enviada sem checagem alguma, que é o oposto do que `validateIdentity: true` promete. Tipos cruzados passam a ser recusados, com a mesma indicação de `validateIdentity: false` para o caso do representante legal. Emitente sem inscrição federal (NIF/cNaoNIF) segue de fora: não há o que comparar, e reprovar ali negaria o único formato que lhe resta.

  A conferência também passa a ser contra o emitente, não contra o prestador. Com `tpEmit` 2 ou 3, cobrar de quem assina o CNPJ do prestador reprovava justamente a emissão legítima — e obrigava a desligar `validateIdentity` para emitir dentro da regra.

- **"TOTAL DEDUÇÕES/REDUÇÕES" saía sem o reembolso e o repasse.** A tabela do item 2.4.5 escreve o campo como `vDR | vCalcDR + vCalcReeRepRes`: a barra separa duas origens — o declarado na DPS e o apurado pelo fisco —, e a segunda é uma soma. O builder resolvia as três tags com `firstOf()`, que devolve a primeira não vazia, então `vCalcReeRepRes` (em `infNFSe/IBSCBS/valores`) nunca era somado a `vCalcDR` (em `infNFSe/valores`): uma NFS-e com 200,00 de dedução apurada e 50,00 de reembolso imprimia `R$ 200,00` no lugar de `R$ 250,00`. O campo irmão da mesma tela, "EXCLUSÕES E REDUÇÕES DA BASE DE CÁLCULO", já somava as cinco origens que a NT lhe dá — a distinção entre barra e sinal de mais é que tinha passado.

- **`consultar()->nfse()` relatava `sucesso: true` para 401, 404 e 500.** O adaptador HTTP encerrava com um resgate que devolvia qualquer corpo JSON não vazio, mesmo sem o envelope `erros`/`erro` da SEFIN, e o status se perdia ali: `SendsHttpRequests::get()` retorna `array`, sem ele. O único consumidor desse retorno, `NfseResponsePipeline::executeAndDecompress()`, só pergunta por `erros`/`erro` — então um gateway ou WAF que respondesse JSON próprio virava consulta bem-sucedida, com `chave` e `xml` nulos e o evento `NfseQueried` disparado. O caso mais provável era o 404 de nota inexistente. O resgate passa a valer só para o POST, que é onde ele serve: `NfseEmitter` reconhece a resposta sem `chaveAcesso` e a devolve como `SEM_CHAVE`. Na consulta, `HttpException` carrega status e corpo íntegro, que é o contrato que o README já descrevia. O teste que fixava o comportamento antigo usava corpo não-JSON e por isso nunca chegou a exercitar o resgate.

- **O NIF do prestador estrangeiro nunca chegava a ser impresso.** A tabela do item 2.4.5 amarra o campo "CNPJ / CPF / NIF" do prestador a `infDPS/prest/`, e só a ele; o builder consultava `infNFSe/emit` no mesmo passo, com o CNPJ do cadastro à frente do NIF declarado na DPS. Como `TCEmitente` abre com um `<xs:choice>` obrigatório de `CNPJ|CPF`, todo XML válido traz um dos dois — então o ramo do NIF era inalcançável, e todo prestador fora do país saía identificado como brasileiro. Os dois testes que cobriam o caso removiam o `emit/CNPJ` do XML para chegar ao NIF, o que o XSD proíbe: documentavam o defeito em vez de pegá-lo. `emit` continua como recurso, mas só quando `prest` não traz nenhuma das quatro identificações — situação que o schema já não admite.

- **Campo de máscara posicional perdia a posição vazia, e os valores restantes escorregavam para a casa da vizinha.** A tabela do item 2.4.5 descreve quatro campos por máscara de posições fixas — `CST / cClassTrib` (`nnn / nnnnnn`), o indicador de operação, `Red. Alíquota IBS / Red. Alíquota CBS` (`% / % / %`) e `Alíquota - IBS UF / IBS Mun` (`% / %`). Com só uma das reduções preenchida, o campo saía `1,00%`, que um leitor atribui à primeira posição da máscara — a redução do IBS estadual — quando o valor era da CBS. As posições ausentes passam a sair com o traço da nota 12, e o campo inteiro continua colapsando para um traço só quando nenhuma posição veio, para a NFS-e anterior à reforma não imprimir `- / - / -`. O município da incidência do ISSQN segue omitindo o país ausente: ali a concatenação é de nomes, não de máscara, e o país é a última posição.

- **O quadro de informações complementares saía em branco quando o XML não preenchia nenhum dos dez campos.** A nota 12 manda traço em campo sem informação, e a área ficava vazia. A linha fixa de totais aproximados da nota 10 continua à parte, em célula própria.

- **Situação e finalidade podiam estourar a coluna que a NT lhes dá.** As duas linhas do item 2.4.5 mandam cortar com reticências acima de 37 caracteres, em campos de 40 — a própria NT exemplifica a situação com "NFS-e de Decisão Judicial ou Administ...". Nenhuma descrição do leiaute chega lá hoje; o corte é guarda para a que chegar.

- **Município e UF vinham separados por hífen, como no portal, e não por barra, como na NT.** As linhas "MUNICÍPIO / SIGLA UF", "LOCAL DA PRESTAÇÃO" e "MUNICÍPIO DA INCIDÊNCIA DO ISSQN" do item 2.4.5 mandam "Concatenar o nome do município com a respectiva UF. Ex.: Município / UF" — `Niterói - RJ` passa a sair `Niterói / RJ`, em todos os blocos de participante, no cabeçalho e nos campos de incidência. O quadro do cabeçalho já usava a barra; os demais, não.

  O "(ext)" do exemplo da mesma tabela ("Ex.: nnnnnnn / nn.nnn-nnn ou nnnnnnn / nnnnnnnnnnn (ext)") fica decidido como **anotação da tabela, não literal a imprimir**: a linha declara 21 como tamanho do campo, e `nnnnnnn / nnnnnnnnnnn` já ocupa exatamente 21 — o sufixo levaria o campo a 27 e estouraria a largura que a própria linha fixa. Registrado em `ParticipanteBuilder::codigoPostal()`.

- **O CEP saía na máscara de uso corrente, não na da NT.** A linha "CÓDIGO IBGE / CEP" do item 2.4.5 exemplifica o campo como `nnnnnnn / nn.nnn-nnn`, com ponto após o segundo dígito — `01310100` saía `01310-100` em vez de `01.310-100`.

- **O código da NBS saía sem máscara.** A tabela do item 2.4.5 dá ao campo o formato `n.nnnn.nn.nn`, e `TSCodNBS` fixa exatamente nove dígitos (`[0-9]{9}`) — `115011000` era impresso cru, em vez de `1.1501.10.00`. CNPJ/CPF, telefone, CEP e código de tributação nacional já passavam pelo `Formatter`; a NBS ficou de fora por descuido, sem teste que cobrisse o formato.

- **O QR Code de uma NFS-e de homologação apontava para endereço fora da norma.** O item 2.4.3 fixa um endereço só — `https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=` —, e o renderizador o trocava pelo portal de homologação quando `tpAmb = 2`, para que a leitura do código encontrasse a nota. A troca não tem amparo na NT: o DANFSe de homologação é peça de teste, não circula, e o cabeçalho já o marca "NFS-e SEM VALIDADE JURÍDICA". Um QR que não resolve é o comportamento que a norma prescreve; um endereço próprio é divergência de conteúdo impresso.

- **Os blocos reduzidos à frase única saíam mais baixos que o mínimo das notas 2, 3 e 4.** Quando tomador, destinatário, intermediário ou a tributação municipal colapsam para a frase da NT, as notas do item 2.4.5 fixam altura mínima de 0,32 cm e largura mínima de 20,40 cm. A largura já vinha do bloco, que ocupa a linha inteira, mas a altura era a de uma linha de 7 pt — 0,29 cm, medidos no content stream. `Nt008GeometryTest` passa a medir a distância entre as divisórias que cercam o bloco colapsado.

- **O DANFSe descartava o endereço de participante no exterior.** A tabela do item 2.4.5 dá dois caminhos aos campos "MUNICÍPIO / SIGLA UF" e "CÓDIGO IBGE / CEP" de prestador, tomador, destinatário e intermediário: `end/endNac`, com o código do IBGE e o CEP, e `end/endExt`, com cidade, província e código postal do exterior. O builder lia só o primeiro, então um participante fora do país saía com traço nos dois campos, com o dado presente no XML — o SDK já **emitia** `endExt` (`ServicoBuilder`), mas não o lia de volta.

  O código postal do exterior sai sem máscara: `TSCodigoEndPostal` é alfanumérico e o formato de CEP brasileiro descartaria letras. No exterior não há código do IBGE, e o campo passa a ser montado por `DanfseParticipante::codigoIbgeCep()`, que imprime um lado só em vez de pôr um traço ao lado do código postal. Para o prestador, o `endExt` declarado na DPS vence o cadastro do fisco: `emit/enderNac` é obrigatório em `TCEmitente` e traria município e CEP brasileiros para quem a DPS declarou fora do país.

- **Cinco campos podiam estourar a largura que a NT lhes reserva.** A tabela do item 2.4.5 manda cortar com reticências acima de 77 caracteres o nome e o endereço dos participantes, e acima de 37 as descrições de opção pelo Simples Nacional e de benefício municipal — 77 para o regime de apuração pelo SN. O corte já existia para imunidade e suspensão do ISSQN, mas não para estes: `xNome` e o endereço concatenado admitem 255 caracteres no leiaute, e as descrições do próprio leiaute chegam a 59 (`OpSimpNac`), 136 (`RegApTribSN`) e 38 (`TipoBeneficioMunicipal`). Texto além da coluna quebra linha e pressiona a página única do item 2.2.

- **O DANFSe divergia do Anexo I em duas disposições e de três notas do item 2.4.5.** Auditoria completa contra a NT 008 v1.0, medindo o PDF gerado — coordenadas, corpos de fonte, cores e traços lidos do content stream — e, para o que depende do desenho, rasterizando as figuras da própria nota a 300 dpi. Ver `docs/auditoria/2026-07-21-conformidade-danfse-nt008.md`.

  Duas eram de **disposição**, que o item 2.2.4 torna obrigatória. Nos quatro blocos de participante, o e-mail subia para a linha do nome e o endereço dividia a sua com município e CEP; o Anexo I e a tabela 2.4.5 põem nome, município e CEP numa linha (`Sup 4,98`) e endereço e e-mail na seguinte (`Sup 5,62`). E dois campos ficavam uma coluna à esquerda do lugar, ambos por um `colspan="2"` que puxa o campo seguinte para o início do par: o "Regime de Apuração Tributária pelo SN", que o Anexo desenha em 5,41 embora a tabela diga 10,51 — conflito interno da NT, resolvido a favor do Anexo, como o bloco do ISSQN já fazia —, e o telefone do destinatário, que o Anexo e a tabela põem em 15,62 de comum acordo. O destinatário não tem inscrição municipal (`TCRTCInfoDest` não declara `IM`), e é a coluna dela que fica vazia.

  Três eram de **conteúdo**. O e-mail era o único campo dos blocos de participante a sair em branco quando o XML não o trazia, contra a nota 12 ("os campos sem informações no XML devem ser preenchidos com um traço"). A linha de PIS, COFINS e descrição das contribuições retidas — a marcada com `***` no Anexo — era impressa sempre, embora a nota 6 a limite a competências até o fim do ano-calendário de 2026; competência ilegível mantém a linha, porque `dCompet` é obrigatório no XSD e omitir tributo declarado por causa de um campo defeituoso perde mais do que uma linha a mais. E o campo de identificação do intermediário era rotulado `CNPJ / CPF`, sem o `NIF` que o leiaute nomeia e que o valor já resolvia.

  Os percentuais passam a usar a vírgula decimal que o resto do documento já usa nos valores monetários. As casas do XML são preservadas: `pAliq` traz duas, as alíquotas de IBS/CBS admitem mais, e reformatar com precisão fixa inventaria ou perderia dígito de um campo fiscal.

  Uma décima suspeita **não** se confirmou e está registrada como retratação na auditoria: a de que faltariam linhas divisórias internas nos blocos. O Anexo I, rasterizado, não tem grade interna alguma — apenas as linhas sólidas entre blocos, que é exatamente o que o template desenha. A afirmação vinha das figuras do item 2.4.5.1 lidas em miniatura, que trazem guias de célula próprias e servem para ilustrar o texto dos blocos colapsados das notas 2, 3 e 4, não o estilo do traçado.

- **A cobertura do bloco IBS/CBS rodava sobre XML que o schema oficial rejeitaria.** O grupo `IBSCBS`, o destinatário e o `indDest` eram exercitados por XML montado dentro dos testes, com o `IBSCBS` de `infNFSe` depois do `DPS` e sem o `finNFSe` obrigatório. O helper do pior caso da página única acumulava ainda um `xInfComp` de 2050 caracteres contra um máximo de 2000, valor terminado em espaço, os opcionais de `tribMun` depois de `tpRetISSQN` em vez de antes, e `nProcesso` e `nBM` fora dos seus padrões de dígitos. `DanfseFixtureSchemaTest` guardava as fixtures em disco, mas XML derivado em código escapava dessa guarda — o mesmo hábito de escrever a fixture para casar com o código, e não com o schema, que deixou passar os defeitos de 2.7.0 e 3.0.0. Não havia bug de produção: o builder é tolerante à ordem e aos campos ausentes. Passa a existir `tests/fixtures/danfse/nfse-ibscbs.xml`, com o grupo dos dois lados e um destinatário distinto do tomador, e o pior caso é derivado dela e submetido ao schema por teste próprio.

- **A DANFSe mutilava o NIF estrangeiro e ignorava o do prestador.** O campo do leiaute é `CNPJ / CPF / NIF`, mas o valor passava por `Formatter::cnpjCpf()`, que descarta todo não-dígito: `ES-B12345678` saía `12345678`, `PT501234567` saía `501234567`, `IE1234567AB` saía `1234567`. `TSNIF` é texto livre de até 40 caracteres — prefixo de país e letras fazem parte do identificador. Um documento fiscal saía com identificação estrangeira incompleta, sem erro nem aviso.

  Além disso, `NIF` e `cNaoNIF` do **prestador** nunca eram lidos. `TCEmitente` abre com `<xs:choice>CNPJ|CPF</xs:choice>` e não tem onde pôr um NIF; quem carrega prestador estrangeiro é `DPS/infDPS/prest` (`TCInfoPrestador`), e o builder lia dali apenas o `regTrib`. O SDK **emite** prestador com NIF desde sempre, mas o PDF que ele gera não conseguia mostrá-lo.

  A escolha do formato passa a vir da **procedência**, não da forma do texto: o XSD já declara o que cada nó carrega, então só `CNPJ`/`CPF` passam pelo formatter e o `NIF` sai como veio. `Formatter::cnpjCpf()` não foi alterado — voltou a receber apenas o que o nome promete. A decisão vive em `Danfse\Identificacao`, unidade própria e testada isoladamente.

  `cNaoNIF` também passa a ser lido, para prestador, tomador e intermediário: em vez de `-` sem explicação, a DANFSe imprime o motivo (`Dispensado do NIF`, `Não exigência do NIF`, `Não informado na nota de origem`), transcrito do `<xs:documentation>` de `TSCodNaoNIF`. `CNaoNIF` ganhou `label()`, com teste que extrai os rótulos do XSD em tempo de execução.

  Nota de nomenclatura: `DanfseParticipante::cnpjCpf` continua com esse nome por compatibilidade, embora possa conter NIF ou o motivo da ausência — é o campo `CNPJ / CPF / NIF` do leiaute.

- **`DanfseDataBuilder` lia `emit->NIF`, que não existe naquele nó.** `TCEmitente` abre com um `<xs:choice>` obrigatório de `CNPJ|CPF`, sem `NIF`. O prestador estrangeiro existe no schema — `TCInfoPrestador` aceita `CNPJ|CPF|NIF|cNaoNIF`, e este SDK emite os quatro —, mas quem o carrega é `DPS/infDPS/prest`, não `infNFSe/emit`. O acesso era o terceiro fallback de `firstNonEmpty()` e nunca produziu valor, então não houve mudança de comportamento; era contrato inexistente sugerido pelo código. (Encontrado pela mesma auditoria, que verifica os 118 acessos SimpleXML do builder contra o modelo de conteúdo do XSD.)

- **As fixtures de resposta carregavam XML que a API nunca emitiria, e agora são validadas contra o XSD.** `cancelar_sucesso.json` trazia `<NFSe/>` dentro de `eventoXmlGZipB64` — raiz de NFS-e num campo que carrega documento de evento —, `consultar_eventos.json` trazia `<Evento/>` quando `evento_v1.01.xsd` declara a raiz `<evento>` em minúsculo, e as duas fixtures de NFS-e traziam um elemento vazio sem `versao` nem `infNFSe`. Nenhuma delas validava. Não havia bug de produção — o XML é repassado opaco, sem inspeção da raiz —, mas nada no repositório verificava o caminho de consumo desses campos contra um documento real. As quatro passam a carregar documentos completos e XSD-válidos, e `tests/Unit/Fixtures/ResponseFixturesXsdTest.php` valida **toda** fixture, atual e futura, contra o schema da sua raiz. O teste também confere quantos campos validou, para um campo renomeado não o fazer passar por vacuidade.

- **Teste do `cNBS` montava XML fora da ordem do XSD.** `DanfseDataBuilderTest` inseria `<cNBS>` antes de `<xDescServ>`, mas `cServ` é uma sequência ordenada (`cTribNac, cTribMun, xDescServ, cNBS, cIntContrib`). O teste afirmava comportamento sobre um documento que a API nunca emite. Sem impacto em produção — SimpleXML é indiferente à ordem —, mas o mesmo hábito de escrever a fixture para casar com o código, e não com o schema, é o que deixou passar os defeitos de 2.7.0 e 3.0.0.

- **Americana/SP (IBGE `3501608`) declarava um endpoint completo onde o resolver espera uma URL base.** `sefin_production`/`sefin_staging` apontavam para `.../api/adn/dps/recepcao`, que é a recepção de DPS, não a raiz da API. Como consequência, **toda** operação do município precisava de um template `""` para o path nacional não ser concatenado ao endpoint de emissão — workaround introduzido em `74defaf` ("prevent malformed URLs from appending default paths to already-complete base URL"). O efeito colateral era a chave sumir da URL; desde a mudança de `resolveOperation()` nesta mesma versão, virava exceção, deixando **cinco das dez operações indisponíveis** para o município. A base passa a ser `.../api/adn` e só `emit_nfse` traz um path (`dps/recepcao`); as demais herdam `DEFAULT_OPERATIONS`. A URL de emissão é idêntica byte a byte à anterior nos dois ambientes — quem só emite não percebe diferença.

  Ressalva: que Americana sirva os caminhos nacionais sob `/api/adn` é inferência a partir do prefixo, não verificação — a prefeitura não publica contrato. Se algum caminho divergir, a operação passa a falhar com 404 em vez de silenciosamente atingir o recurso errado. (Encontrado pela mesma auditoria.)

- **BREAKING — `RegApTribSN::label()` descrevia o regime de apuração errado.** Os rótulos dos casos `2` e `3` diziam "pela NFS-e" onde `TSRegimeApuracaoSimpNac` (`storage/schemes/tiposSimples_v1.01.xsd`, idêntico na v1.00) diz "por fora do SN" — regimes de apuração diferentes. O texto sai impresso na DANFSe, no campo "Regime de Apuração do Simples Nacional": um prestador ME/EPP que ultrapassou sublimite e emite com `regApTribSN=2` recebia um documento fiscal afirmando que o ISSQN é apurado pela NFS-e, quando a declaração é de apuração fora do Simples Nacional. Nada acusava — o teste do enum repetia as strings do próprio enum, então passaria com qualquer rótulo.

  Os três rótulos passam a ser transcrição literal do `<xs:documentation>`, o que também troca "pelo Simples Nacional" por "pelo SN" no caso `1` (mesmo significado, wording do XSD). Quem exibe `label()` fora da DANFSe verá o texto mudar. O teste foi reescrito para extrair os rótulos do XSD em tempo de execução e comparar — agora falha se o código divergir da fonte de verdade. (Encontrado pela auditoria de conformidade código ↔ XSD/swagger; ver `docs/auditoria/2026-07-21-conformidade-xsd-swagger.md`.)

- **O binding do container passa a honrar `detect_not_delivered`.** `NfsenClient::for()` já lia a chave desde a 2.6.0, mas `NfsenServiceProvider::register()` montava o client sem repassá-la, caindo no default `false`. Um app com `NFSE_DETECT_NOT_DELIVERED=true` recebia `RequestNotDeliveredException` ao construir via `::for()` e `IndeterminateResultException` ao resolver `NfsenClient` pelo container — mesma configuração, contratos de exceção diferentes, sem aviso. Quem resolvia pelo container perdia o opt-in em silêncio, exatamente a fragilidade que a flag existe para evitar. Era a única chave de `config/nfsen.php` que o provider deixava de repassar. (Reportado pela auditoria do Pulsar sobre a v2.7.0.)

- **HTTP 204 deixa de ser tratado como resultado indeterminado.** `NfseHttpClient::getResponse()` classificava todo 2xx sem JSON legível como corpo ininterpretável — mas "204 No Content" define corpo vazio, então ali a ausência de JSON é a resposta correta. Na prática, um 204 lançava `IndeterminateResultException`, cujo contrato obriga o chamador a reconciliar antes de qualquer retry, por um simples "não há nada a retornar". Também deixava inalcançável o branch `EMPTY_RESPONSE` de `DistribuicaoResponse::fromHttpResponse()`, escrito justamente para esse caso e coberto apenas por um teste que montava `HttpResponse` à mão. `distribuicao()->documentos()` agora devolve `sucesso: false` com `EMPTY_RESPONSE`. Um 204 com corpo não-JSON contradiz o próprio status e segue indeterminado; num 200, corpo vazio continua indeterminado.

- **`cancelar()` falhava em host cujo fuso tem offset de minuto quebrado.** `dhEvento` usava `date('c')`, mas `TSDateTimeUTC` só aceita offset com minuto zero e na faixa `-11..+12`. Em `Asia/Kolkata` (+05:30), `Asia/Kathmandu` (+05:45), `Pacific/Chatham` (+12:45) ou sob `+13:00`, a validação XSD reprovava e **todo** cancelamento falhava naquele host. Passou a usar `gmdate('c')`, que é sempre válido e representa o mesmo instante. Os exemplos de `dhEmi` no README e em `examples/` tinham o mesmo defeito latente — `dhEmi` é `TSDateTimeUTC` também — e foram corrigidos para `gmdate()`.

- **`validateChaveAcesso()` aceitava chave com quebra de linha no fim.** Em PCRE, `$` casa também antes de um `\n` final, então `/^\d{50}$/` aprovava `"1…1\n"` apesar da mensagem prometer "exatamente 50 dígitos numéricos". A chave seguia interpolada na URL, produzindo requisição malformada em vez de `InvalidArgumentException`. Corrigido com o modificador `/D`.

- **`emitir()` descartava os metadados quando a resposta não trazia `chaveAcesso`.** Dos três branches de resposta, o de `SEM_CHAVE` era o único que jogava fora `idDps`, `tipoAmbiente`, `versaoAplicativo` e `dataHoraProcessamento`, todos presentes no corpo. Sem a chave, o `idDps` é justamente o único identificador que resta para reconciliar via `consultar()->dps()`. Agora são preservados, aceitando as duas grafias (`idDps` e `idDPS`), já que essa resposta não casa com nenhum dos dois envelopes documentados.

- **`HttpException::getResponseBody()` truncava o corpo em 500 bytes**, quebrando `NfseConsulter::parseHttpError()`, que faz `json_decode()` desse valor: um envelope de erro da SEFIN maior que o corte virava JSON inválido, e as mensagens estruturadas eram substituídas por um genérico `HTTP error: N` cuja `descricao` era um fragmento de JSON quebrado. O corpo agora é guardado inteiro — a mensagem da exceção nunca o incluiu, então não há impacto em log.

- **`DanfseDataBuilder` desreferenciava nós ausentes.** `build()` validava apenas `infNFSe` e `DPS/infDPS`; a partir daí, um XML truncado fazia cada nível seguinte virar `null`, emitindo `Warning: Attempt to read property … on null` e terminando em `TypeError`. `toPdf()` tem `catch (Throwable)` e absorvia isso, mas `toHtml()` não tem catch algum, então o `TypeError` vazava para o chamador. Os grupos que o XSD declara obrigatórios (`infDPS/prest`, `prest/regTrib`, `infDPS/serv`, `serv/cServ`, `infDPS/valores`, `valores/trib`, `trib/tribMun`, `trib/totTrib`, `infNFSe/emit`, `infNFSe/valores`) passam a ser verificados na entrada, lançando `XmlParseException` que nomeia o grupo faltante. Os opcionais (`toma`, `tribFed`) seguem tolerados.

- **BREAKING — template de operação sem placeholder deixa de descartar parâmetros em silêncio.** `PrefeituraResolver::resolveOperation()` fazia `str_replace('{chave}', …)` sobre um template que não continha placeholder algum: a substituição não fazia nada, o guard de placeholder residual passava (não sobrou nenhum) e `buildUrl()` devolvia a URL base pelada. O fallback `??` para os defaults nacionais não cobre isso, porque dispara em `null`, não em `''`.

  Na prática: Americana/SP (IBGE `3501608`) declarava `""` nas **seis** operações em `storage/prefeituras.json`. `consultar()->nfse($chave)` fazia GET na URL de recepção de DPS com a chave descartada, e `cancelar()` fazia **POST** de pedido de cancelamento nesse mesmo endpoint de recepção — ambos sem erro algum. Agora, uma operação que recebe parâmetros e cujo template não tem onde colocá-los lança `InvalidArgumentException` nomeando a operação, o município, o template e o arquivo a corrigir.

  `""` continua válido para operações sem parâmetro, em que a URL base do município já é o path completo — o caso legítimo que a estrutura de dados foi feita para expressar. O dado de Americana que motivava o exemplo acima foi corrigido nesta mesma versão (ver a entrada sobre a base `/api/adn`), então hoje nenhum município do repositório depende desse guard; ele permanece como rede para os que forem adicionados.

- **BREAKING — um documento ilegível deixa de derrubar o lote inteiro de distribuição.** `DocumentoFiscal::fromArray()` usava `TipoDocumentoFiscal::from()` sobre uma chave acessada sem checagem: um item sem `TipoDocumento` lançava `TypeError`, e um valor que esta versão do SDK não conhecesse lançava `ValueError` — que **não** é `NfseException` e escapava dos catches documentados. Um `ArquivoXml` corrompido lançava `NfseException`. Em qualquer um dos três, `distribuicao()->documentos()` perdia os outros 49 documentos do lote junto com o defeituoso. Nenhum campo de `DistribuicaoNSU` é obrigatório no swagger do ADN, e o governo pode passar a emitir tipos novos a qualquer momento.

  Agora o item entra no lote com os campos afetados em `null` e o motivo em `DocumentoFiscal::$parseError`. O `nsu` é preservado em todos os cenários, para que o chamador consiga refazer a busca daquele documento em específico. Alinha o comportamento ao de `DistribuicaoResponse::fromApiResult()`, que já degradava graciosamente diante de um `StatusProcessamento` desconhecido.

  **Migração.** `DocumentoFiscal::$tipoDocumento` passou de `TipoDocumentoFiscal` para `?TipoDocumentoFiscal`: quem faz `$doc->tipoDocumento->value` direto precisa checar `$doc->parseError === null` antes (ou usar `?->`). O construtor ganhou o sétimo parâmetro opcional `$parseError` no fim — chamadas posicionais e nomeadas existentes seguem válidas.

- **BREAKING — 5xx sem rejeição estruturada da SEFIN em operação que altera estado passa a lançar `IndeterminateResultException`** (antes: `HttpException`, ou nenhuma exceção). Afeta `emitir()`, `emitirDecisaoJudicial()`, `cancelar()` e `substituir()`. Duas rotas levavam ao mesmo risco: um 5xx com JSON não-envelope (`{"message": "Internal server error"}` de proxy) era devolvido como resultado normal e virava `sucesso: false` definitivo; um 5xx com corpo ilegível (página HTML de gateway) lançava `HttpException`. Nos dois casos o contrato do SDK classifica como resposta definitiva do servidor, e o README autoriza reenviar com o mesmo `nDPS` — mas um 5xx de proxy não prova que a SEFIN deixou de processar a emissão. Risco de nota duplicada.

  Um 5xx que **traz** `erros`/`erro` preenchido continua sendo rejeição definitiva: o envelope prova que a requisição chegou à SEFIN e foi processada. Consultas seguem lançando `HttpException` em 5xx — `GET` não altera estado, então não há o que reconciliar e o erro definitivo é a informação mais útil.

  **Migração.** Quem captura `HttpException` em torno de `emitir`/`cancelar`/`substituir` para tratar falha de servidor precisa passar a capturar também `IndeterminateResultException` — e, nela, reconciliar antes de qualquer reenvio, conforme a seção de reconciliação do README. `catch (CommunicationException)` cobre o caso novo sem distinguir subtipos.

- **`"erro": []` deixa de ser classificado como rejeição.** `ProcessingMessage::fromApiResult()` descartava a chave `erro` vazia desde a 2.3.1, mas os nove pontos que decidiam entre rejeição e processamento testavam `isset($result['erro'])` por conta própria. Um corpo `{"erro": [], "chaveAcesso": "35..."}` — forma que a API realmente produz — virava `sucesso: false` com `erros: []` (nenhuma mensagem), **descartando a `chaveAcesso` de uma nota autorizada** e disparando `NfseRejected('UNKNOWN', null)`. O caller perdia a chave e ficava sem base para reconciliar. Afetava `emitir()`, `substituir()`, `cancelar()`, `consultar()->nfse()`, `->dps()`, `->eventos()` e `->danfse()`.

- **Faltavam o sombreamento e a espessura de linha do item 2.2.3.** A NT manda fundo cinza claro de 5% de densidade no cabeçalho, nos títulos de cada bloco e nos campos "Emitente da NFS-e" e "Valor Líquido da NFS-e + IBS/CBS", "para contraste", com os demais em branco — o documento saía todo branco. E as linhas divisórias dos blocos, que a NT fixa em meio ponto, estavam em `1px` (0,75pt).

- **O conteúdo dos campos usava a fonte dos rótulos.** O item 2.4 pede Arial nos títulos e labels e Microsoft Sans Serif nos conteúdos; tudo saía em Arial. A segunda fonte é da Microsoft e não pode ser embarcada, então passa a ser declarada: o Dompdf a usa quando o consumidor a registra, e cai no Helvetica quando não. Fica como divergência conhecida, dependente do ambiente — o DejaVu Sans que acompanha o Dompdf seria o fallback óbvio, mas é largo o bastante para levar o pior caso da norma à segunda página, trocando esta divergência pela do item 2.2.

- **O DANFSe não imprimia a marca d'água de NFS-e cancelada ou substituída.** Os itens 2.5.1 e 2.5.2 da NT 008 exigem marca diagonal — "CANCELADA" ou "SUBSTITUÍDA" — no documento auxiliar da nota que saiu de vigência, em Arial de no mínimo 50 pontos e cinza K35. Nada a imprimia. A informação não está no XML: `infNFSe/cStat` só descreve como a nota foi gerada, e cancelamento e substituição chegam depois, como evento separado. Por isso passa a ser escolha de quem renderiza, via `Enums\MarcaDagua` no segundo argumento de `toPdf()`/`toHtml()`.

- **"Informações Complementares" trazia só o `xInfComp`.** O item 2.4.5 define o campo como a união de dez campos espalhados pelo leiaute, cada um com rótulo próprio, na ordem da tabela e separados por ` | `. Substituição (`chSubstda`), documento de referência, obra (`cObra`), inscrição imobiliária, evento (`idAtvEvt`), documento técnico, número e item do pedido e informações da administração municipal (`xOutInf`) nunca chegavam ao papel — dados que o contribuinte declarou e o documento fiscal omitia.

- **Os totais aproximados de tributos ocupavam um bloco que a NT não prevê.** A nota 10 do item 2.4.5 os coloca dentro de "Informações Complementares", numa linha fixa e obrigatória; o template lhes dava quadro próprio, contrariando o item 2.2.4 ("a disposição de campos obrigatoriamente obedecer ao disposto no respectivo anexo"). Os valores também passam a ser lidos de `vTotTrib` quando a NFS-e reporta montantes em vez de percentuais — a mesma nota admite os dois, e só o ramo percentual era tratado.

- **O bloco "VALOR TOTAL DA NFS-e" tinha cinco células numa tabela de quatro colunas.** A quinta transbordava para fora da moldura e arrastava o rodapé do documento junto. O bloco passa a ter o desenho de duas linhas do Anexo I. Na mesma correção, "Total das Retenções (ISSQN / Federais)" volta a ser o campo único que o item 2.1.11 define (`vTotalRet`, que o fisco já soma; recalculado apenas quando ausente, que é `minOccurs=0`) — estava dividido em dois. Saíram do bloco "ISSQN Retido" e "PIS/COFINS - Débito Apuração Própria", que a NT não põe ali: o primeiro está no bloco de tributação municipal e o segundo no federal.

- **O canto direito do cabeçalho não trazia nenhum dos três campos do item 2.4.3.** Município do emitente (`xLocEmi` + UF, 8 pontos), ambiente gerador e tipo de ambiente (6 pontos). O ambiente gerador aparecia no centro, onde não é o lugar dele, e o tipo de ambiente não era impresso. A linha do município é suprimida quando o item do código de tributação nacional é 99, como a própria tabela do item 2.4.5 determina.

- **Uma NFS-e cancelada em homologação saía com duas marcas d'água sobrepostas.** A de "HOMOLOGAÇÃO" não vinha da NT — que sinaliza esse ambiente pela expressão "NFS-e SEM VALIDADE JURÍDICA" no cabeçalho (item 2.4.3) — e ainda esbarrava no item 2.1, que proíbe imprimir o que não consta do arquivo da NFS-e. Removida.

- **O quadro de texto livre cortava no meio de uma linha e a linha de totais era desenhada por cima.** O teto de 44pt não era múltiplo da entrelinha, e a linha fixa de totais aproximados, sendo irmã do texto na mesma célula, era sobreposta pelo que transbordava — exatamente o que a nota 10 quer evitar ao mandar que o corte seja "sem prejuízo" dela. Os dois quadros de texto livre passam a crescer sem teto, como o item 2.3.1 prevê, e a linha de totais ganhou célula própria.

- **`Formatter::limit()` podia devolver só o rótulo no lugar do campo inteiro.** O recuo até o último espaço, que existe para não partir palavra ao meio, não tinha piso: num texto quase sem espaços — uma chave de acesso, um código longo, um rótulo seguido de dado contínuo — o único espaço podia estar no começo, e um campo de 1500 caracteres saía como `Inf. Cont.:...`, 14 caracteres. O recuo agora só vale se preservar 90% do limite.

- **As margens do formulário estavam acima do máximo da NT.** O item 2.2.2 fixa entre 0,15 cm e 0,20 cm em cada lateral, inclusive superior e inferior; estavam em 0,247 cm. Corrigidas para 0,176 cm — e eram justamente esses pontos que faltavam para o pior caso da norma caber na página única do item 2.2.

- **O código da NBS era impresso dentro de "Informações Complementares".** O item 2.4.5 o declara campo do bloco "Serviço Prestado", ao lado do código de tributação. Movido, e na mesma correção o local da prestação passa a concatenar município, UF e país no campo único que a NT prescreve.

### Added

- `IndeterminateResultException::fromServerError()` — 5xx sem rejeição estruturada da SEFIN. Sem `phase`: nenhuma fase de transporte falhou, a resposta chegou inteira; o que falta é evidência sobre o processamento.
- `ProcessingMessage::hasApiError()` — critério único de "a resposta traz erro da SEFIN". Classificação e extração de mensagens agora derivam da mesma regra interna, o que impede a divergência acima de voltar. Também resolve o caso `{"erros": [], "erro": {...}}`, em que o plural vazio escondia o singular preenchido.

- `Enums\MarcaDagua` — marca d'água dos itens 2.5.1 e 2.5.2 (`Cancelada`, `Substituida`), com `texto()` devolvendo o que vai impresso. Aceita como segundo argumento de `RendersDanfse::toPdf()`/`toHtml()` e de `BuildsDanfseData::build()`; omitir imprime o DANFSe sem marca, como nota vigente.
- `NfseData::$municipioEmitente` — campo "MUNICÍPIO" do quadro de identificação (`xLocEmi` + UF). String vazia quando a NT manda não exibir (item 99 do código de tributação nacional), distinta do `-` que marca campo sem dado.
- `NfseData::$marcaDagua` — a marca escolhida para aquele documento, ou `null`.
- `DanfseTotaisTributos::linhaNt008()` — a linha fixa da nota 10, com o texto e a pontuação transcritos da nota.
- `DanfseTributacaoFederal::$exibePisCofins` — nota 6 do item 2.4.5: a linha de PIS/COFINS só é impressa para competência até o fim do ano-calendário de 2026. Default `true`, para quem monta o DTO à mão.
- `DanfseTributacaoMunicipal::$sujeitaAoIssqn` — `false` reduz o bloco à frase do item 2.3.1, "TRIBUTAÇÃO MUNICIPAL (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN". Só `tribISSQN = 4` (Não Incidência) chega assim: imunidade e exportação de serviço também não recolhem o imposto, mas a NT reserva campo no bloco para cada uma — "Tipo de Imunidade do ISSQN" para a primeira e o país de `cPaisResult` dentro de "Município / UF / País da Incidência" para a segunda —, e colapsá-las apagaria o dado que as distingue. Default `true`.
- `Formatter::percent()` — percentual com vírgula decimal, preservando as casas que o XML trouxe.

### Changed

- **BREAKING — `NfseData::$tomador` passou de `DanfseParticipante` para `?DanfseParticipante`.** A nota 2 do item 2.4.5 manda reduzir o bloco a uma única frase quando a NFS-e não traz tomador — "TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e" —, e o DANFSe desenhava o bloco inteiro com oito traços, o que afirma que os campos existem e vieram vazios. `intermediario` e `destinatario` já eram anuláveis e já colapsavam assim; o tomador passa a seguir o mesmo padrão, em vez de ganhar um sinalizador à parte para o mesmo problema.

  **Migração.** Quem lê `$data->tomador->nome` sem checar nulo quebra numa NFS-e sem `infDPS/toma` — use `?->` ou teste `=== null` antes. O bloco colapsado devolve 1,42 cm de altura aos quadros "Descrição do Serviço" e "Informações Complementares", que é o destino que o item 2.3.1 dá ao espaço liberado.

- **BREAKING — `Enums\TipoEvento`: os 18 casos foram renomeados, sem exceção.** Os nomes anteriores foram atribuídos por posição sobre a lista numérica do swagger, sem conferir a documentação de cada elemento `eNNNNNN` em `storage/schemes/tiposEventos_v1.01.xsd`, e ficaram deslocados em relação ao evento real. Como o valor inteiro de cada caso nunca mudou, o defeito era silencioso: `consultar()->eventos()` montava a URL com um código válido, porém de outro evento, e devolvia o documento errado sem erro. Os códigos permanecem idênticos — apenas os nomes mudam.

  | Antes | Código | Agora |
  |---|---|---|
  | `CancelamentoPorIniciativaPrestador` | 101101 | `Cancelamento` |
  | `CancelamentoPorIniciativaFisco` | 101103 | `SolicitacaoCancelamentoAnaliseFiscal` |
  | `CancelamentoPorDecisaoJudicial` | 105102 | `CancelamentoPorSubstituicao` |
  | `CancelamentoPorDecisaoAdministrativa` | 105104 | `CancelamentoDeferidoAnaliseFiscal` |
  | `CancelamentoPorOficio` | 105105 | `CancelamentoIndeferidoAnaliseFiscal` |
  | `AnaliseParaCancelamento` | 202201 | `ConfirmacaoPrestador` |
  | `AnaliseParaCancelamentoDecisaoJudicial` | 202205 | `RejeicaoPrestador` |
  | `SolicitacaoCancelamento` | 203202 | `ConfirmacaoTomador` |
  | `SolicitacaoCancelamentoDecisaoJudicial` | 203206 | `RejeicaoTomador` |
  | `RejeicaoCancelamento` | 204203 | `ConfirmacaoIntermediario` |
  | `RejeicaoCancelamentoDecisaoJudicial` | 204207 | `RejeicaoIntermediario` |
  | `ConclusaoCancelamento` | 205204 | `ConfirmacaoTacita` |
  | `ConclusaoCancelamentoDecisaoJudicial` | 205208 | `AnulacaoRejeicao` |
  | `SubstituicaoPorIniciativaPrestador` | 305101 | `CancelamentoPorOficio` |
  | `SubstituicaoPorIniciativaFisco` | 305102 | `BloqueioPorOficio` |
  | `SubstituicaoPorOficio` | 305103 | `DesbloqueioPorOficio` |
  | `BloqueioNfse` | 467201 | `InclusaoNfseDan` |
  | `TravamentoNfse` | 907201 | `TributosNfseRecolhidos` |

  **Migração.** Não há camada de compatibilidade: `CancelamentoPorOficio` existe nos dois esquemas apontando para códigos diferentes (105105 antes, 305101 agora), então um alias depreciado mudaria o significado desse nome em silêncio — exatamente a falha que a correção elimina. Migre por **código**, não por nome: localize o valor inteiro que seu código usava hoje na coluna do meio e adote o nome da coluna da direita. Quem passava `int` direto (`eventos($chave, 105102)`) não é afetado.

  `Cancelamento` (101101) segue como default de `consultar()->eventos()` — só o nome mudou.

- `TipoEvento` passou a compartilhar o vocabulário de `TipoEventoDistribuicao`: os mesmos eventos, vistos pelos canais de consulta e de distribuição, agora têm o mesmo nome nos dois enums.

- **BREAKING — `detect_not_delivered` deixou de existir; a detecção é sempre feita.** Somem a chave de `config/nfsen.php`, a variável `NFSE_DETECT_NOT_DELIVERED` e o parâmetro `$detectNotDelivered` de `NfsenClient::forStandalone()` e de `NfseHttpClient`. `TransportFailureClassifier::classify()` perdeu o segundo argumento.

  A flag existia para não alterar catches escritos antes da 2.6.0, mas o que ela desligava é a classificação **correta**: com ela em `false`, uma falha de DNS, TCP ou TLS — em que nenhum byte chegou à SEFIN — era relatada como `IndeterminateResultException`, cujo contrato obriga o chamador a reconciliar antes de reenviar. Trabalho e latência a mais para um caso em que o reenvio direto é comprovadamente seguro.

  A classificação segue exigindo evidência inequívoca (errno do cURL 6, 7, 35, 58, 60); qualquer ambiguidade, incluindo todo timeout, permanece indeterminada.

  **Migração.** Remova a chave e a variável. Quem já capturava `CommunicationException` não precisa de nada. Quem só capturava `IndeterminateResultException` passa a ver `RequestNotDeliveredException` escapar em falhas pré-envio — capture-a, ou capture a base `CommunicationException`.

- **BREAKING — a flag do auto-render virou `auto_danfse`, de primeiro nível.** O bloco `danfse` de `config/nfsen.php` some: no lugar de `danfse.enabled`, com `NFSE_DANFSE_AUTO`, fica `auto_danfse`, com `NFSE_AUTO_DANFSE`. O default segue `false`, e a chave ausente — caso de todo config publicado antes desta versão — também.

  `NfsenClient::isDanfseEnabled()` foi removido junto — era um método público cujo corpo virou um cast, e cada caminho o faz por conta própria agora.

  A checagem estrita `=== true` deu lugar a esse cast, o que corta dos dois lados. Ganha-se `1` e `'1'` sendo tratados como ligado, em vez de descartados em silêncio — o caso de quem alimenta a config de banco ou de painel. Perde-se a proteção contra o inverso: vindas de fonte que não tipa bool, as strings `'false'`, `'off'` e `'no'` são **verdadeiras** em PHP e ligam o auto-render. Quem não usa `env()` deve converter antes de escrever na config.

  Vale lembrar o custo de ligar: cerca de 300 ms e 15 KB por nota, em `emitir()`, `emitirDecisaoJudicial()`, `substituir()` e `consultar()->nfse()`. `$client->danfse()->toPdf($xml)` está sempre disponível, com a flag ou sem ela.

  **Migração.** Troque `NFSE_DANFSE_AUTO` por `NFSE_AUTO_DANFSE` e, se publicou o config, o bloco `danfse` pela chave `auto_danfse`.

- **BREAKING — o DANFSe deixou de ser customizável.** Saem `Danfse\DanfseConfig`, `Danfse\MunicipalityBranding`, `Danfse\LogoLoader`, o trait `Danfse\Concerns\ValidatesArrayShape`, o argumento de `NfsenClient::danfse()` e as chaves `logo_path`, `logo_data_uri` e `municipality` da configuração, com as variáveis `NFSE_DANFSE_LOGO_PATH`, `NFSE_DANFSE_LOGO_DATA_URI` e `NFSE_DANFSE_MUN_*`. O bloco `danfse` de `config/nfsen.php` fica só com `enabled`, e o parâmetro `$danfse` de `for()`/`forStandalone()` passa de `array|false|null` para `bool` — ele só liga e desliga o auto-render.

  A NT 008 descreve o documento campo a campo e não deixa nada à escolha do emissor. O item 2.2.4 obriga a seguir o Anexo I; o item 2.1 proíbe imprimir o que não consta do arquivo da NFS-e; os itens 2.3.1 a 2.3.3 listam as únicas alterações permitidas, todas supressões de blocos vazios. Havia dois pontos de entrada para conteúdo arbitrário, ambos no cabeçalho:

  **Identificação da prefeitura** (`MunicipalityBranding`) substituía o quadro que o item 2.4.3 reserva ao município do emitente, ao ambiente gerador e ao tipo de ambiente — os três campos deixavam de ser impressos. A NT não esqueceu o caso: dados adicionais do município têm lugar reservado dentro de "Informações Complementares" (`xOutInf`), que passou a ser impresso nesta mesma versão.

  **Logomarca** (`logo_path`/`logo_data_uri`) trocava por qualquer imagem o quadro que o item 2.4.3 dá à logomarca **da NFS-e**, indicando inclusive o arquivo oficial em gov.br; `logo_path: false` a suprimia por completo. Ela agora vem sempre de `storage/danfse/logo-nfse.png`, embarcado no pacote. A NT não reserva quadro para marca do emitente em lugar nenhum do documento.

  Os dois eram herança do port de `andrevabo/danfse-nacional`, anterior à NT 008 — de quando cada município tinha layout próprio e o DANFSe vinha da API do ADN, sobrestada em 01/07/2026.

  **Migração.** Remova as chaves e as variáveis de ambiente; `danfse(['logo_path' => …])` vira `danfse()`. `for(danfse: [...])` vira `for(danfse: true)`; `for(danfse: false)` e `forStandalone(danfse: false)` seguem funcionando com o mesmo significado.

- **BREAKING — `DanfseTotais`: `$issqnRetido`, `$retencoesFederais` e `$pisCofins` deram lugar a `$totalRetencoes`.** O item 2.1.11 define um campo só para o bloco de totais, e os outros dois valores já têm lugar próprio: o ISSQN retido no bloco de tributação municipal (`tribMun->retencaoIssqn` e `->issqnApurado`) e o PIS/COFINS de apuração própria no federal (`tribFed->pis` e `->cofins`). Nenhuma informação se perdeu do documento.

- **BREAKING — `NfseData` ganhou `$municipioEmitente` entre `$ambienteGerador` e `$emitente`.** Quem constrói o DTO por argumentos posicionais precisa ajustar; por argumentos nomeados, basta acrescentar o campo.

- **`informacoesComplementares` volta ao limite da NT.** Era cortado em 1000 caracteres, escolha do SDK para forçar a página única do item 2.2; passa a usar os 1997 da própria norma, com reticências acima disso, num campo de 2000. Cortar metade do campo era decidir por conta própria o que o contribuinte declarou. A página única passou a ser sustentada pelo layout — margens do item 2.2.2 e entrelinha —, verificada a cada execução por teste que renderiza o PDF e conta as páginas no pior caso da norma.

### Notas

- Os caminhos de Americana/SP (`3501608`) para consulta e cancelamento **não são confirmados pela prefeitura**, que não publica contrato. Após a separação de base e path descrita acima, essas operações passam a herdar os caminhos nacionais sob `/api/adn` — inferência a partir do prefixo, não verificação. Caminho divergente resulta em 404, não em requisição silenciosamente dirigida ao recurso errado. Só a emissão é comprovadamente correta, e a URL dela não mudou.
- 467201 e 907201 não constam em nenhum XSD — existem apenas no swagger da SEFIN Nacional. Seus nomes derivam da correspondência posicional com as duas últimas entradas de `TipoEventoDistribuicao`, cujas 16 primeiras conferem com o XSD elemento a elemento. Os 16 códigos documentados no XSD são verificados por teste.

## [2.7.0] - 2026-07-21

Fecha o ciclo da reconciliação: o cancelamento indeterminado passa a ter os mesmos três desfechos ancorados em evidência que a emissão já tinha desde a 2.5.0 — registrou, comprovadamente não registrou, inconclusivo.

### Added

- `EventsResponse::EVENT_NOT_FOUND` — código de erro dedicado retornado em `erros[0]->codigo` quando `consultar()->eventos()` recebe HTTP 404 da SEFIN (evento comprovadamente inexistente, distinto de erro transitório). Erros originais da SEFIN, se presentes no corpo do 404, são preservados a partir de `erros[1]` — mesma convenção de `NfseResponse::DPS_NOT_FOUND`. Na reconciliação de cancelamento, `EVENT_NOT_FOUND` é a prova de que o cancelamento não registrou e o reenvio é seguro; qualquer outro `sucesso: false` permanece inconclusivo.
- `IndeterminateResultException::fromMissingResponseField()` — 2xx com JSON válido porém sem o campo obrigatório da operação.
- `ExecutesNfseRequests::executeRaw()` ganhou o parâmetro opcional `$requiredField`: um 2xx cujo corpo não traga o campo como string não-vazia lança `IndeterminateResultException` de dentro do pipeline, disparando `NfseFailed` (nunca `NfseQueried`). Porta interna — ver nota de `@internal` abaixo.

### Changed

- **`consultar()->eventos()` com HTTP 404** retorna `sucesso: false` com `EVENT_NOT_FOUND` em `erros[0]` — antes o 404 sem corpo estruturado lançava `HttpException`, e com corpo estruturado virava um `sucesso: false` genérico, indistinguível de erro transitório.
- **`ExecutesNfseRequests` marcado `@internal`** e **`ExecutesNfseRequests::execute()` removido** — `consultar()->eventos()` passou a usar `executeRaw()` (precisa do status para distinguir o 404), único consumidor restante do método. A interface é porta de wiring, construída apenas pelo `NfsenClient`: só afeta quem a implementa por conta própria; nenhum uso público muda.
- **HTTP 404 sem corpo de erro não dispara mais `NfseQueried`** em `consultar()->dps()` e `consultar()->eventos()` — o recurso não existe, então não é consulta bem-sucedida (nem rejeição da SEFIN, que continua disparando `NfseRejected` quando o 404 traz `erros`/`erro`). `NfseRequested` continua sendo disparado. Listeners que contavam consultas bem-sucedidas deixam de contar 404s.
- **`consultar()->eventos()` com 2xx sem `eventoXmlGZipB64`** (ex.: corpo `{}`) agora lança `IndeterminateResultException` — antes retornava `sucesso: true` com `xml: null`. 404 é o sinal canônico de ausência; 200 sem o campo obrigatório é anomalia ininterpretável, e a régua da 2.5.0/2.6.0 é "classificação exige certeza, viés para indeterminado". Quem tratava `xml: null` como "sem evento" deve migrar para o branch `EVENT_NOT_FOUND`. Nesse caminho os listeners observam `NfseRequested` → `NfseFailed`, como já acontece com 2xx de corpo ilegível.

## [2.6.0] - 2026-07-20

Separa "não entregue" de "indeterminado": falhas de DNS, conexão TCP e handshake TLS acontecem antes de qualquer byte HTTP ser enviado — a requisição comprovadamente não chegou à SEFIN, e o retry direto é seguro sem reconciliação. Opt-in para não quebrar catches da 2.5.0.

### Added

- `Exceptions\CommunicationException` — base abstrata das falhas de comunicação. `IndeterminateResultException` agora a estende (antes estendia `NfseException` diretamente; `instanceof NfseException` continua valendo). `catch (CommunicationException)` cobre os dois subtipos e equivale a tratar tudo como indeterminado — sempre seguro.
- `Exceptions\RequestNotDeliveredException` — a requisição comprovadamente nunca foi entregue (`phase`: `dns`|`connect`|`tls`); a operação não foi processada e o reenvio direto é seguro. Lançada **apenas** com `detectNotDelivered: true`.
- Flag `detectNotDelivered` (default `false`): parâmetro em `NfsenClient::forStandalone()`, chave `detect_not_delivered` / env `NFSE_DETECT_NOT_DELIVERED` no config Laravel. Com `false`, o comportamento é idêntico ao da 2.5.0 (toda falha de comunicação lança `IndeterminateResultException`).
- `Support\TransportFailureClassifier` — a decisão entregue/não-entregue usa apenas o errno do cURL extraído do handler context do Guzzle (nunca texto de mensagem, que varia entre versões do libcurl). Regra: certeza obrigatória, viés para indeterminado — `RequestNotDeliveredException` só com errno 6/7/35/58/60; errno ausente, ambíguo ou desconhecido classifica como indeterminado (mantendo a `phase` informacional do sniffing legado como diagnóstico). cURL 28 (timeout) é **sempre** indeterminado: em conexão keep-alive reutilizada o cURL zera os timers de connect (curl issue #2703), então timeout de connect não é provável.
- `IndeterminateResultException::fromTransportFailureWithPhase()` — variante com fase explícita derivada do errno.

### Notas

- Com `detectNotDelivered: true`, um timeout de connect (cURL 28) reporta `phase: 'read'` (errno não prova a fase de timeouts); com a flag desativada, mantém a fase legada `'connect'` sniffada da mensagem. `phase` é diagnóstico — não a use para decidir retry.
- A classificação vale para todas as operações HTTP do SDK (emissão, cancelamento, substituição, consultas, distribuição).

## [2.5.0] - 2026-07-20

Suporte a reconciliação de resultado indeterminado: permite descobrir o estado real de uma DPS após falha de comunicação antes de qualquer retry, eliminando o risco de dupla emissão.

### Added

- `Support\DpsId::generate()` — builder público do identificador de 45 posições da DPS (`TSIdDPS`), fonte única da regra fiscal de formação do ID. `Xml\DpsBuilder` passou a delegar para ele. Valida o retorno contra o padrão `DPS[0-9]{42}` e lança `InvalidDpsArgument` em entrada inválida — inclusive quando CNPJ e CPF são ambos `null` (inscrição zerada silenciosa seria um ID fiscalmente inválido; o caso legítimo de prestador estrangeiro com NIF/cNaoNIF requer `allowEmptyInscricao: true`).
- `Exceptions\IndeterminateResultException` — lançada quando o SDK não consegue obter uma resposta completa e legível em qualquer caminho HTTP (`post`, `get`, `getBytes`, `getResponse`, `head`). Cobre três situações: falha antes de qualquer resposta (timeout, DNS, conexão recusada, TLS), falha no meio da transferência (conexão resetada, corpo truncado — cURL 18/56/92) e resposta 2xx com corpo ilegível (JSON inválido ou vazio). Propriedade `phase` (`connect`|`dns`|`read`|`tls`|`transfer`|`body`|`null`) indica a fase da falha quando detectável. Contrato: capturá-la significa que a SEFIN pode ou não ter processado a requisição — **nunca faça retry cego**; reconcilie via `DpsId::generate()` + `consultar()->dps($id)` antes; qualquer outra exceção ou resposta é definitiva.
- `NfseResponse::DPS_NOT_FOUND` — código de erro dedicado retornado em `erros[0]->codigo` quando `consultar()->dps($id)` recebe HTTP 404 da SEFIN (DPS comprovadamente inexistente, distinto de erro transitório). Erros originais da SEFIN, se presentes, são preservados a partir de `erros[1]`.
- `ExecutesNfseRequests::executeRaw()` — retorna a resposta HTTP crua (status + JSON + corpo) para consultas que precisam distinguir status; lança `HttpException` para status inesperado (≠ 200/201/404) sem corpo de erro estruturado.
- Dependência explícita de `guzzlehttp/guzzle` (já era transitiva via `illuminate/http`) — o SDK agora captura `TransferException` do Guzzle diretamente para cobrir versões do Laravel que não a envelopam.

### Changed

- **`consultar()->verificarDps()`** retorna `false` **apenas em HTTP 404**. Qualquer outro status ≠ 200 (401, 403, 429, redirect…) agora lança `HttpException` — antes retornava `false`, o que podia ser lido como "DPS não existe" e induzir dupla emissão. O throw acontece dentro do pipeline de eventos, disparando `NfseFailed` (paridade com 5xx). Falha de transporte lança `IndeterminateResultException`.
- **Falhas de conexão** que antes vazavam como `Illuminate\Http\Client\ConnectionException` crua (ou `RequestException`/`TransferException`, conforme a versão do Laravel) agora chegam ao integrador como `IndeterminateResultException` (exceção original em `getPrevious()`). Quem capturava `ConnectionException` diretamente deve migrar o catch.
- **Respostas 2xx com corpo ilegível** (JSON inválido, vazio ou escalar) em `post`/`get`/`getResponse` agora lançam `IndeterminateResultException` — antes viravam array vazio e podiam propagar como falso sucesso (ex.: `consultar()->dps()` reconciliando contra um 200 de load balancer com página HTML retornava `sucesso: true` com `chave: null`). Em `distribuicao()`, o caso 200 com corpo vazio que antes retornava `EMPTY_RESPONSE` agora também lança (corpo `{}` válido continua retornando `EMPTY_RESPONSE`).
- **Respostas de erro sem corpo estruturado** em `post`/`get` agora lançam `HttpException` para qualquer status não-2xx (antes apenas 5xx; um redirect ou 4xx com corpo vazio retornava array vazio silencioso). Redirects continuam não sendo seguidos.
- `consultar()->dps()` com HTTP 5xx/redirect sem corpo de erro estruturado agora lança `HttpException` de forma consistente (antes um redirect com corpo vazio era interpretado como sucesso).

## [2.4.0] - 2026-05-11

### Added

- Campos `mensagemErro` e `correcao` no evento `NfseRejected` para facilitar debug e logs operacionais sem precisar inspecionar o payload da resposta. `mensagemErro` é preenchido com `ProcessingMessage::descricao` (fallback `mensagem`); `correcao` espelha `ProcessingMessage::complemento`. Ambos são `?string` com default `null` — retrocompatível com listeners que só leem `operacao`/`codigoErro`.

## [2.3.1] - 2026-04-16

### Added

- Campo `codigoNbs` em `DanfseServico` — código NBS (Nomenclatura Brasileira de Serviços) extraído de `cServ/cNBS`.
- Renderização de **NBS:** (label em negrito) no bloco INFORMAÇÕES COMPLEMENTARES do DANFSE quando `cNBS` presente na NFS-e.
- Resolução de município via tabela IBGE para Local da Prestação (`cLocPrestacao`) e Município de Incidência (`cLocIncid`), produzindo "Cidade - UF" em paridade com o portal nacional.

### Fixed

- `DanfseDataBuilder`: crash ao processar XMLs sem blocos opcionais (`tribFed`, `piscofins`, `pTotTrib`, `end` do tomador/intermediário). Método `str()` agora aceita `?SimpleXMLElement`; acessos a filhos opcionais usam `?->`.
- `DanfseDataBuilder`: Código de Tributação Municipal exibia "- -" quando `cTribMun` e `xTribMun` ausentes. Agora exibe "-".
- `DanfseDataBuilder`: `descTribNacional`/`descTribMunicipal` retornavam string vazia (em vez de "-") quando `xTribNac`/`xTribMun` ausentes, causando concatenação espúria no template.
- `DanfseDataBuilder`: email de emitente/tomador/intermediário era forçado a minúsculas via `strtolower()`. Portal nacional preserva o case do XML; SDK agora também preserva.
- `Formatter::limit()`: truncava no meio de palavra (ex.: "programas de co..."). Agora retrocede ao último espaço antes do limite (ex.: "programas de...").

### Changed

- DANFSE CSS compactado para maior paridade visual com o portal nacional: fontes reduzidas (body 7pt→6.5pt, labels 7pt→6.5pt, values 8pt→7pt), padding reduzido, QR Code 70px→60px. Adicionado `@page { size: A4 }`, `max-height` e `overflow: hidden` para garantir renderização em página única.

## [2.3.0] - 2026-04-15

### Added

- `NfsenClient::for()` e `NfsenClient::forStandalone()` ganham parâmetro `array|false|null $danfse`
  que ativa auto-geração de DANFSE PDF em `emitir()`, `emitirDecisaoJudicial()`, `substituir()`
  e `consultar()->nfse()`. Sentinel `false` força desligar quando config global está ativa.
- Campos `pdf: ?string` e `pdfErrors: list<ProcessingMessage>` em `NfseResponse`.
- `DanfseConfig::fromArray()` e `MunicipalityBranding::fromArray()` com validação schema-like
  (whitelist de chaves + tipos + regras de negócio; `InvalidArgumentException` no boot).
- Bloco `danfse` em `config/nfsen.php` com `enabled` gate e envs `NFSE_DANFSE_*`.
- `NfsenClient::danfse()` — gera DANFSE (PDF e HTML) a partir do XML da NFS-e autorizada. Aceita `DanfseConfig|array|null`.
- Customização via `DanfseConfig` (logo de empresa) e `MunicipalityBranding` (identificação do município emissor).
- Métodos `label()` e `labelOf(?string)` nos enums `OpSimpNac`, `RegApTribSN`, `RegEspTrib`, `TpRetISSQN`, `TribISSQN` e `NfseAmbiente`.
- Exceção `XmlParseException`.

### Changed

- `DanfseDataBuilder`: fallback de `tpAmb` inválido (fora de `{1,2}`) mudou de `PRODUCAO` para `HOMOLOGACAO`. Fail-safe visual — XML suspeito renderiza com watermark "SEM VALIDADE JURÍDICA". Não afeta NFS-e autorizadas reais; SEFIN sempre emite `tpAmb` válido.
- `config/nfsen.php`: envs vazias (`NFSE_DANFSE_LOGO_PATH=`, `NFSE_DANFSE_LOGO_DATA_URI=`, `NFSE_DANFSE_MUN_LOGO_PATH=`, `NFSE_DANFSE_MUN_LOGO_DATA_URI=`) agora viram `null` em vez de string vazia — evita `InvalidArgumentException` no boot ao tentar carregar arquivo com path `''`.

## [2.2.1] - 2026-04-08

### Fixed
- `ProcessingMessage::fromArray()` agora converte valores não-string (arrays/objetos retornados pela API) para JSON em vez de falhar com type error. Corrige crash quando campo `Mensagem` vem como objeto Enum do Swagger do ADN.

## [2.2.0] - 2026-04-08

### Added
- `HttpResponse` DTO com `statusCode`, `json` e `body` para respostas HTTP completas.
- Interface `SendsRawHttpRequests` com método `getResponse()` para acesso a respostas HTTP sem perda de informação.
- `DistribuicaoResponse::fromHttpResponse()` — novo factory method que preserva HTTP status code e body raw em cenários de erro.
- `NfseHttpClient` agora implementa `SendsRawHttpRequests` além de `SendsHttpRequests`.

### Changed
- `NfseDistributor` usa `SendsRawHttpRequests::getResponse()` em vez de `SendsHttpRequests::get()`, preservando HTTP status code e body raw em todas as respostas de erro.

### Fixed
- Respostas HTTP 4xx com body vazio (ex: 429 rate limiting), redirects (3xx) e respostas 2xx com corpo vazio agora são diagnosticáveis — o `DistribuicaoResponse` inclui o status code no `codigo` do erro e o body raw no `complemento`.
- `ProcessingMessage::fromArray()` agora converte valores não-string (arrays/objetos retornados pela API) para JSON em vez de falhar com type error. Corrige crash quando campo `Mensagem` vem como objeto Enum do Swagger do ADN.

## [2.1.1] - 2026-04-08

### Fixed
- `DistribuicaoResponse::fromApiResult()` agora inclui o JSON completo da API no campo `complemento` e as chaves presentes no `descricao` quando `StatusProcessamento` é ausente ou inválido, facilitando o diagnóstico de respostas inesperadas.

## [2.1.0] - 2026-04-08

### Added
- Distribuição de documentos fiscais via ADN Contribuinte (`$client->distribuicao()`)
  - `documentos(int $nsu)` — consulta em lote por NSU
  - `documento(int $nsu)` — consulta unitária por NSU
  - `eventos(string $chave)` — consulta todos os eventos de uma NFS-e
- Novos DTOs: `DistribuicaoResponse`, `DocumentoFiscal`
- Novos enums: `StatusDistribuicao`, `TipoDocumentoFiscal`, `TipoEventoDistribuicao`
- Campo `parametros` adicionado ao `ProcessingMessage`

## [2.0.0] - 2026-04-03

### Added
- Suporte a Laravel 13 (`illuminate/http`, `illuminate/support`, `illuminate/contracts` `^13.0`)
- Suporte a `orchestra/testbench` `^11.0` (testbench v11 = Laravel 13)
- Typed constants (`const array`, `const string`) via PHP 8.3
- Atributo `#[Override]` em métodos sobrescritos
- CI com matrix PHP 8.3/8.4 × Laravel 11/12/13

### Changed
- Requisito mínimo de PHP alterado de **8.2** para **8.3**
- Pest 3 → Pest 4, pest-plugin-laravel 3 → 4.1, pest-plugin-type-coverage 3 → 4

### Breaking Changes
- **PHP 8.2 não é mais suportado** — requisito mínimo agora é PHP 8.3

## [1.0.1] - 2026-03-26

### Security
- Validação HTTPS obrigatória nas URLs de prefeitura em ambiente de produção
- Cross-check de identidade: CNPJ do certificado digital é verificado contra o CNPJ do prestador na DPS antes do envio
- Remoção de exposição de chave privada em mensagens de erro do `CertificateManager`

## [1.0.0] - 2026-03-24

Primeira versão estável sob o namespace `OwnerPro\Nfsen` e pacote `ownerpro/nfsen-php-sdk`.

Reescrita completa do projeto original, com arquitetura hexagonal, 100% de cobertura
de testes, tipos e mutações, e suporte a PHP 8.2+ com Laravel 11/12.

### Added
- `NfsenClient::for()` — instância configurada por tenant via container Laravel
- `NfsenClient::forStandalone()` — instância standalone sem dependência do container
- Emissão de NFSe (`emitir`) e emissão por decisão judicial (`emitirDecisaoJudicial`)
- Cancelamento de NFSe (`cancelar`)
- Substituição de NFSe (`substituir`) — emissão da substituta + cancelamento da original em uma única requisição
- Consultas fluentes: `consultar()->nfse/dps/danfse/eventos($chave)`
- Verificação de DPS: `consultar()->verificarDps($idDps)`
- DTOs tipados para toda a estrutura DPS conforme XSD v1.01
- Responses tipados: `NfseResponse`, `DanfseResponse`, `EventsResponse`, `ProcessingMessage`
- Eventos Laravel: `NfseEmitted`, `NfseCancelled`, `NfseSubstituted`, `NfseQueried`, `NfseRequested`, `NfseRejected`, `NfseFailed`
- Assinatura digital XML com certificado A1 (PFX/P12)
- Validação XSD dos documentos antes do envio
- mTLS via `tmpfile()` — sem escrita nomeada em disco, sem CNPJ no path
- SSL habilitado corretamente (`verify: true`)
- Facade `Nfsen` para uso simplificado com Laravel
- Override de ambiente em runtime via `NfsenClient::for(..., ambiente: NfseAmbiente::PRODUCAO)`
- Identificação de prefeituras exclusivamente por código IBGE (7 dígitos)
- Suporte a PHP 8.2, 8.3 e 8.4
- Suporte a Laravel 11 e 12

### Arquitetura
- Arquitetura hexagonal com ports & adapters
- Contratos (interfaces) separados em Driving (entrada) e Driven (infraestrutura)
- Pipeline de requisição/resposta com concerns reutilizáveis
- DTOs imutáveis com validação exclusiva de campos mutuamente exclusivos
- Enums tipados seguindo nomenclatura do XSD oficial
- Testes de arquitetura (arch tests) garantindo fronteiras hexagonais
- 100% de cobertura de testes, tipos e mutações (1129 mutações)
- CI com matrix PHP 8.2/8.3/8.4 × Laravel 11/12
- Quality gates: PHPStan, Psalm (taint analysis), Rector, Pint

### Breaking Changes (em relação ao projeto original)
- Namespace: `Hadder\NfseNacional` → `OwnerPro\Nfsen`
- Pacote: `ownerpro/nfsen-php-sdk` (antes não publicado no Packagist)
- Requisito mínimo de PHP alterado de 8.1 para **8.2**
- API pública completamente nova: `NfsenClient::for($pfx, $senha, $ibge)->emitir($dpsData)`
- Identificação de prefeituras exclusivamente por código IBGE; suporte a nome legado removido
- Removido `Helpers.php` com `now()` global
- Removidas dependências `symfony/var-dumper` e `tecnickcom/tcpdf`

### Créditos
Este pacote teve como base o trabalho do projeto original
[nfse-nacional](https://github.com/Rainzart/nfse-nacional) de **Fernando Friedrich**,
construído sobre o [NFePHP](https://github.com/nfephp-org) de **Roberto L. Machado**.
Agradecimento a todos os contribuidores do projeto original.
