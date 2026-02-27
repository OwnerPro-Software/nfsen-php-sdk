# CLAUDE.md

## Quality Checks

Sempre rodar antes de considerar o trabalho concluído:

```bash
./vendor/bin/pest # runs the complete suite test
./vendor/bin/pest --type-coverage --min=100 # runs type coverage tests
```

O type coverage deve permanecer em 100%. Qualquer código novo ou alterado deve incluir type hints completos.

## Git

Nunca comitar o arquivo `composer.lock`.
