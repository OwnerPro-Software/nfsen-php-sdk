# Contribuindo

Obrigado por considerar contribuir com o **NFSe Nacional**!

## Como contribuir

1. Faça um fork do repositório
2. Crie uma branch para sua feature ou correção (`git checkout -b minha-feature`)
3. Faça suas alterações
4. Certifique-se de que todos os checks passam (veja abaixo)
5. Faça commit das suas alterações
6. Envie um Pull Request

## Quality Checks

Antes de enviar seu PR, execute todos os checks de qualidade:

```bash
# Testes com cobertura de 100%
./vendor/bin/pest --coverage --min=100

# Cobertura de tipos (100%)
./vendor/bin/pest --type-coverage --min=100

# Análise estática (nível 10)
./vendor/bin/phpstan analyse

# Análise de segurança
./vendor/bin/psalm --taint-analysis

# Verificação de código
./vendor/bin/rector --dry-run

# Formatação (Laravel preset)
./vendor/bin/pint
```

Ou execute todos de uma vez:

```bash
composer quality
```

## Regras

- **Cobertura de testes:** 100% obrigatório. Todo código novo deve ter testes.
- **Cobertura de tipos:** 100% obrigatório. Todo código novo deve ter type hints completos.
- **Formatação:** Seguimos o preset Laravel via Pint. Execute `./vendor/bin/pint` antes de commitar.
- **Análise estática:** PHPStan nível 10 e Psalm nível 1. Sem erros permitidos.

## Princípios de Teste

- Teste a API pública, nunca detalhes de implementação
- Um conceito por teste
- Padrão Arrange-Act-Assert (AAA)
- Testes devem ser rápidos, independentes e repetíveis
- Não teste o framework — teste seu código

## Pull Requests

- Descreva claramente o que foi alterado e por quê
- Referencie issues relacionadas quando aplicável
- Mantenha PRs focados — uma feature ou correção por PR
