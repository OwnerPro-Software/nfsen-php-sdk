# CLAUDE.md

## Quality Checks

Sempre rodar antes de considerar o trabalho concluído:

```bash
./vendor/bin/pest # runs the complete suite test

# quality checks

./vendor/bin/pest --type-coverage --min=100 # runs type coverage tests
./vendor/bin/rector --dry-run # runs rector quality checks

# if any quality checks changed any file, full suite need to be run again!
./vendor/bin/pest # runs the complete suite test
```

O type coverage deve permanecer em 100%. Qualquer código novo ou alterado deve incluir type hints completos.

## Git

Nunca comitar o arquivo `composer.lock`.
