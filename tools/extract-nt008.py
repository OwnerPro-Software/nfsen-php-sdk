#!/usr/bin/env python3
"""Extrai a tabela 2.4.5 da Nota Técnica nº 008 para uma fixture JSON.

A seção 2.4.5 de `storage/danfse/nt-008-se-cgnfse-danfse-20260505.pdf` é a única
fonte que diz de qual TAG do XML sai cada campo impresso do DANFSe. A suíte PHP
não pode ler o PDF (exigiria Python no CI), então o dado entra no repositório
como fixture derivada — e `Nt008FieldCoverageTest` confere cada caminho dela
contra o XSD a cada execução, para que a fixture não possa divergir da fonte em
silêncio.

Uso:

    python3 -m venv .venv-nt008
    .venv-nt008/bin/pip install pdfplumber
    .venv-nt008/bin/python tools/extract-nt008.py

Escreve `tests/fixtures/nt008/campos-2.4.5.json`.

Sobre o método: as fronteiras de linha e coluna vêm das réguas vetoriais
desenhadas no PDF, via pdfplumber. Duas tentativas anteriores parseavam texto
posicionado e *inferiam* as fronteiras — precisavam de um viés calibrado à mão e
erravam sempre que a célula era alinhada ao topo e a medida centralizada na
linha. Fronteira inferida é palpite; régua é dado.
"""
from __future__ import annotations

import json
import pathlib
import re
import sys

try:
    import pdfplumber
except ImportError:  # pragma: no cover
    sys.exit("pdfplumber ausente. Veja as instruções de uso no topo deste arquivo.")

RAIZ = pathlib.Path(__file__).resolve().parent.parent
PDF = RAIZ / "storage/danfse/nt-008-se-cgnfse-danfse-20260505.pdf"
SAIDA = RAIZ / "tests/fixtures/nt008/campos-2.4.5.json"

# Páginas da seção 2.4.5 (0-based). A tabela começa na 16 e termina na 22.
PAGINAS = range(15, 22)

# Fronteiras das colunas, em pontos de PDF, lidas das réguas verticais do documento.
COLUNAS = ((200, "nome"), (264, "caminho"), (300, "tag"), (321, "alt"),
           (349, "larg"), (378, "esq"), (406, "sup"), (519, "obs"))


def coluna(x0: float) -> str:
    for limite, nome in COLUNAS:
        if x0 < limite:
            return nome
    return "tam"


def normaliza(celula: str) -> str:
    """Normaliza uma célula de caminho ou de tag.

    A ordem importa. Os separadores "ou" (alternativa) e "+" (concatenação) viram
    marcas explícitas ANTES de a quebra de linha ser removida — se a remoção viesse
    primeiro, "endNac/ ou\nNFSe/..." colaria em "ou NFSe" e o caminho alternativo
    ficaria irreconhecível. Já a quebra dentro de um token ("NFSe/infNFSe/emi" +
    "t/enderNac/") tem de sumir sem deixar espaço.
    """
    t = re.sub(r"\s*\bou\b\s*", " | ", celula)
    t = t.replace("\n", "")
    t = re.sub(r"\s*\+\s*", " + ", t)

    return re.sub(r"\s+", " ", t).strip()


def extrair() -> list[dict[str, str]]:
    registros: list[dict[str, str]] = []
    bloco: str | None = None

    with pdfplumber.open(PDF) as pdf:
        for numero in PAGINAS:
            for tabela in pdf.pages[numero].find_tables():
                # Texto vem de extract() (já limpo); a coluna lógica vem do bbox
                # correspondente em rows[].cells, alinhado por índice. Recortar a
                # página de novo com crop() traz fragmentos da coluna vizinha.
                for linha, textos in zip(tabela.rows, tabela.extract()):
                    celulas: dict[str, str] = {}
                    for bbox, texto in zip(linha.cells, textos):
                        if not bbox or not texto:
                            continue
                        c = coluna(bbox[0])
                        # O "\n" do PDF é quebra de linha dentro da célula e some sem
                        # deixar espaço — um caminho quebra no meio do token
                        # ("NFSe/infNFSe/emi" + "t/enderNac/"). Espaços de verdade ficam:
                        # são eles que separam alternativas ("cMun ou xCidade").
                        celulas[c] = (celulas.get(c, "") + "\n" + texto).strip()

                    nome = re.sub(r"\s+", " ", celulas.get("nome", "")).strip()
                    caminho = normaliza(celulas.get("caminho", ""))

                    # Linha de grade sem nome é continuação do caminho da linha
                    # anterior (".../DPS" + "/infDPS/"), não linha a descartar.
                    # Um caminho completo termina em "/"; só o que quebrou no meio de
                    # um token não termina ("NFSe/infNFSe/DPS" + "/infDPS/"). Anexar a
                    # um caminho já completo o corromperia com a cauda de outra linha.
                    if not nome:
                        if caminho and registros and not registros[-1]["caminho"].endswith("/"):
                            registros[-1]["caminho"] += caminho
                        continue

                    if nome in ("NOME", "BLOCO", "CAMPO"):
                        continue

                    tag = normaliza(celulas.get("tag", ""))
                    if not caminho and not tag:
                        bloco = nome
                        continue

                    registros.append({
                        "bloco": bloco or "",
                        "campo": nome,
                        "caminho": caminho,
                        "tag": tag,
                        "obs": re.sub(r"\s+", " ", celulas.get("obs", "")).strip(),
                        "tamanho": celulas.get("tam", "").strip(),
                    })

    return registros


def main() -> None:
    registros = extrair()
    SAIDA.parent.mkdir(parents=True, exist_ok=True)
    SAIDA.write_text(json.dumps(registros, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    blocos: dict[str, int] = {}
    for r in registros:
        blocos[r["bloco"]] = blocos.get(r["bloco"], 0) + 1

    print(f"{len(registros)} campos em {len(blocos)} blocos -> {SAIDA.relative_to(RAIZ)}")
    for b, n in blocos.items():
        print(f"  {b[:46]:46s} {n:3d}")


if __name__ == "__main__":
    main()
