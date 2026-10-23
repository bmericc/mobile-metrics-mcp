"""'Metrics' yazısını Inter ExtraBold'dan path olarak çıkarır; KaleHost logosuyla aynı ölçüde SVG üretir.
# Kullanım: pip install fonttools; Inter değişken fontu (google/fonts ofl/inter) indir;
#   python tools/metrics_svg.py Inter[opsz,wght].ttf www/public/images

Logo: 760x260 birim, "KaleHost" büyük harf yüksekliği 95, taban çizgisi y=210.
Çıktı: yükseklik 260, taban y=210, "M" yüksekliği 95 — logoyla aynı yükseklikte gösterilince birebir hizalanır.
"""
import sys
from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.boundsPen import BoundsPen

font_path, out_dir = sys.argv[1], sys.argv[2]
font = instantiateVariableFont(TTFont(font_path), {"wght": 800, "opsz": 32})
gs = font.getGlyphSet()
cmap = font.getBestCmap()
hmtx = font["hmtx"]

cap = font["OS/2"].sCapHeight
scale = 95 / cap
baseline, height = 210, 260
tracking = -0.01 * font["head"].unitsPerEm  # hafif sıkı, logodaki gibi

pen = SVGPathPen(gs)
x = 0.0
glyphs = [cmap[ord(c)] for c in "Metrics"]
for i, g in enumerate(glyphs):
    # y yukarı -> SVG y aşağı; taban çizgisi y=210
    tp = TransformPen(pen, (scale, 0, 0, -scale, x * scale, baseline))
    gs[g].draw(tp)
    x += hmtx[g][0] + (tracking if i < len(glyphs) - 1 else 0)

# sol/sağ boşluğu kırp: mürekkep sınırları
bp = BoundsPen(gs)
xx = 0.0
for i, g in enumerate(glyphs):
    gs[g].draw(TransformPen(bp, (scale, 0, 0, scale, xx * scale, 0)))
    xx += hmtx[g][0] + (tracking if i < len(glyphs) - 1 else 0)
xmin, _, xmax, _ = bp.bounds
d = pen.getCommands()
width = round(xmax - xmin, 1)

for name, color in [("kalehost_metrics_yazi.svg", "#000000"), ("kalehost_metrics_yazi_beyaz.svg", "#ffffff")]:
    svg = (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{round(xmin,1)} 0 {width} {height}" '
           f'width="{width}" height="{height}">\n'
           f'  <!-- "Metrics", Inter ExtraBold (SIL OFL 1.1) path; KaleHost logosuyla ayni olcek: '
           f'yukseklik 260, taban y=210, buyuk harf 95 -->\n'
           f'  <path fill="{color}" d="{d}"/>\n</svg>\n')
    open(f"{out_dir}/{name}", "w").write(svg)
print("width", width, "xmin", round(xmin, 1))
