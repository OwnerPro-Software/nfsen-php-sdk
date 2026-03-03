# CLAUDE.md

## Quality Checks

Sempre rodar antes de considerar o trabalho concluído:

```bash
./vendor/bin/pest --coverage --min=100 # runs the complete suite test

# quality checks

./vendor/bin/pest --type-coverage --min=100 # runs type coverage tests
./vendor/bin/rector --dry-run # runs rector quality checks
./vendor/bin/phpstan analyse # run static analysis check
./vendor/bin/psalm --taint-analysis # security analysis
./vendor/bin/pint -p # runs the pint format rules

# if any quality checks changed any file, full suite need to be run again!
./vendor/bin/pest --coverage --min=100 # runs the complete suite test
# type-coverage
# rector
# phpstan
# psalm
# pint
```

O type coverage deve permanecer em 100%. Qualquer código novo ou alterado deve incluir type hints completos.

## Git

Nunca comitar o arquivo `composer.lock`.
