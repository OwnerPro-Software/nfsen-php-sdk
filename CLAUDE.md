# CLAUDE.md

## Quality Checks

Sempre rodar antes de considerar o trabalho concluído:

```bash
./vendor/bin/pest --type-coverage --min=100
```

O type coverage deve permanecer em 100%. Qualquer código novo ou alterado deve incluir type hints completos.
