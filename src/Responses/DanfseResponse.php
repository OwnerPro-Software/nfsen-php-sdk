<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

/**
 * @api
 */
final readonly class DanfseResponse
{
    /**
     * Código do aviso de que a API remota de DANFSe foi sobrestada.
     *
     * A Nota Técnica nº 008, de 05/05/2026 (`storage/danfse/nt-008-se-cgnfse-danfse-20260505.pdf`,
     * seção 1) suspendeu `https://adn.nfse.gov.br/danfse` em 01/07/2026 e transferiu a
     * geração do documento para o emissor. Toda falha desta operação passa a carregar
     * este aviso porque, depois daquela data, a causa mais provável é essa — e não um
     * problema de rede ou de chave.
     */
    public const string API_SOBRESTADA = 'DANFSE_API_SOBRESTADA';

    /** @param list<ProcessingMessage> $erros */
    public function __construct(
        public bool $sucesso,
        public ?string $pdf = null,
        public array $erros = [],
    ) {}
}
