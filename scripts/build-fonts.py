"""Trim Anek Bangla's variable weight axis to what the site uses (500–700).

The fontsource files cover 100–800 with 500 as the default, so dropping the
lighter half removes about a third of the bytes. The output is committed in
resources/fonts/, so this only needs re-running when the font package changes.

    pip install fonttools brotli
    python3 scripts/build-fonts.py
"""

from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "node_modules/@fontsource-variable/anek-bangla/files"
OUT = ROOT / "resources/fonts"
WEIGHTS = (500, 700)

OUT.mkdir(exist_ok=True)
for subset in ("bengali", "latin"):
    src = SRC / f"anek-bangla-{subset}-wght-normal.woff2"
    font = instancer.instantiateVariableFont(TTFont(src), {"wght": WEIGHTS})
    font.flavor = "woff2"
    dest = OUT / f"anek-bangla-{subset}-{WEIGHTS[0]}-{WEIGHTS[1]}.woff2"
    font.save(dest)
    print(f"{dest.relative_to(ROOT)}: {src.stat().st_size:,} -> {dest.stat().st_size:,} bytes")
