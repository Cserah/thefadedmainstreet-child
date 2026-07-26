"""
Build a 1280x720 YouTube thumbnail from the Coca-Cola ghost-sign photograph.

Pipeline: 16:9 crop -> color grade -> bottom scrim -> "FADED" wordmark.
Outputs thumbnail.png (1280x720) and thumbnail_preview.png (320x180).
"""

import os
import sys
import tempfile
import urllib.request

from PIL import Image, ImageDraw, ImageEnhance, ImageFont

# --- config -----------------------------------------------------------------

SOURCE = r"C:\Users\heron\OneDrive\Pictures\Screenshots\Screenshot 2026-07-25 141057.png"
OUT = "thumbnail.png"
OUT_PREVIEW = "thumbnail_preview.png"

W, H = 1280, 720
WORD = "FADED"

# Crop box measured against the 1834x884 source: centers the "Coca-Cola"
# script horizontally, seats it at ~34% height, and reserves the whitewashed
# wall base below the sign as the clean bottom third.
CROP = (483, 218, 483 + 1184, 218 + 666)

# Grade
GAMMA = 0.92          # <1 lifts midtones; the wall reads muddy otherwise
CONTRAST = 1.25       # +25%
SATURATION = 1.20     # +20%
WARM = (1.035, 1.000, 0.965)   # per-channel R,G,B -- subtle amber shift

# Scrim
SCRIM_BAND = 0.40     # bottom 40% of the frame
SCRIM_MAX = 0.55      # 55% black at the very bottom

# Wordmark
CAP_FRACTION = 0.236          # cap height as a fraction of frame height (~23.6%)

# "FADED" in Anton is only ~35% of frame width at the cap height above, so the
# width below is reached with uniform letter-spacing. The two can't both be
# free: hitting 85% needs gaps 1.8x the letter width, which at sidebar size
# reads as five separate letters rather than a word. 0.62 is the widest that
# still reads as "FADED" at a glance. Drop to 0.50 for the tightest, punchiest
# setting; raise to 0.85 for the literal spec.
WIDTH_FRACTION = 0.62         # inked width as a fraction of frame width
BASELINE_UP = 70              # baseline this many px up from the bottom edge
SHADOW_OFFSET = (6, 6)        # hard offset copy, no blur
STROKE = 4                    # black outline around the glyphs

ANTON_URL = "https://github.com/google/fonts/raw/main/ofl/anton/Anton-Regular.ttf"


# --- font -------------------------------------------------------------------

def resolve_font():
    """Return (path, label). Prefers Anton, then Oswald/Bebas, then Impact."""
    here = os.path.dirname(os.path.abspath(__file__))
    cache = os.path.join(tempfile.gettempdir(), "fms-fonts")

    for path, label in [
        (os.path.join(here, "Anton-Regular.ttf"), "Anton (local)"),
        (os.path.join(cache, "Anton-Regular.ttf"), "Anton (cached download)"),
    ]:
        if os.path.exists(path):
            return path, label

    win = os.path.join(os.environ.get("WINDIR", r"C:\Windows"), "Fonts")
    for name, label in [
        ("Oswald-Bold.ttf", "Oswald Bold"),
        ("BebasNeue-Regular.ttf", "Bebas Neue"),
    ]:
        cand = os.path.join(win, name)
        if os.path.exists(cand):
            return cand, label

    # Not installed anywhere -- fetch Anton from Google Fonts.
    try:
        os.makedirs(cache, exist_ok=True)
        dest = os.path.join(cache, "Anton-Regular.ttf")
        urllib.request.urlretrieve(ANTON_URL, dest)
        ImageFont.truetype(dest, 40)          # validate
        return dest, "Anton (downloaded from Google Fonts)"
    except Exception as exc:                   # offline / blocked
        print(f"  ! Anton download failed ({exc}); falling back to Impact")

    impact = os.path.join(win, "impact.ttf")
    if os.path.exists(impact):
        return impact, "Impact (Windows fallback)"

    raise SystemExit("No suitable font found.")


# --- text metrics -----------------------------------------------------------

def glyph_positions(font, tracking):
    """X offset for each glyph, with uniform extra letter-spacing."""
    xs, pen = [], 0.0
    for ch in WORD:
        xs.append(pen)
        pen += font.getlength(ch) + tracking
    return xs


def inked_bbox(font, tracking):
    """Bounding box of the stroked word as actually rendered."""
    probe = Image.new("L", (4000, 900), 0)
    d = ImageDraw.Draw(probe)
    for ch, x in zip(WORD, glyph_positions(font, tracking)):
        d.text((400 + x, 200), ch, font=font, fill=255,
               stroke_width=STROKE, stroke_fill=255)
    return probe.getbbox()


def fit_font(font_path):
    """Solve for the size that hits the cap height, then the tracking that
    hits the target width. Both are linear, so two probes each is exact."""
    target_cap = CAP_FRACTION * H
    target_w = WIDTH_FRACTION * W

    probe = ImageFont.truetype(font_path, 100)
    d = ImageDraw.Draw(Image.new("L", (10, 10)))
    cap_at_100 = d.textbbox((0, 0), WORD, font=probe)[3] - \
        d.textbbox((0, 0), WORD, font=probe)[1]
    size = max(8, round(target_cap * 100 / cap_at_100))
    font = ImageFont.truetype(font_path, size)

    b0 = inked_bbox(font, 0)
    b1 = inked_bbox(font, 50)
    w0, w1 = b0[2] - b0[0], b1[2] - b1[0]
    slope = (w1 - w0) / 50.0
    tracking = (target_w - w0) / slope if slope else 0.0
    return font, size, max(0.0, tracking)


def draw_word(layer, font, tracking, left, baseline):
    d = ImageDraw.Draw(layer)
    sx, sy = SHADOW_OFFSET
    xs = glyph_positions(font, tracking)

    # Hard offset copy first, so the real glyphs sit on top of it.
    for ch, x in zip(WORD, xs):
        d.text((left + x + sx, baseline + sy), ch, font=font, anchor="ls",
               fill=(0, 0, 0, 255), stroke_width=STROKE,
               stroke_fill=(0, 0, 0, 255))
    for ch, x in zip(WORD, xs):
        d.text((left + x, baseline), ch, font=font, anchor="ls",
               fill=(255, 255, 255, 255), stroke_width=STROKE,
               stroke_fill=(0, 0, 0, 255))


# --- grade ------------------------------------------------------------------

def grade(img):
    lut = [round(255 * (i / 255) ** GAMMA) for i in range(256)]
    img = img.point(lut * 3)
    img = ImageEnhance.Contrast(img).enhance(CONTRAST)
    img = ImageEnhance.Color(img).enhance(SATURATION)
    warm = []
    for mult in WARM:
        warm += [min(255, round(i * mult)) for i in range(256)]
    return img.point(warm)


def scrim(img):
    band = round(H * SCRIM_BAND)
    top = H - band
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(overlay)
    peak = round(255 * SCRIM_MAX)
    for i in range(band):
        a = round(peak * i / (band - 1))
        d.line([(0, top + i), (W, top + i)], fill=(0, 0, 0, a))
    return Image.alpha_composite(img.convert("RGBA"), overlay)


# --- main -------------------------------------------------------------------

def main():
    src_path = sys.argv[1] if len(sys.argv) > 1 else SOURCE
    src = Image.open(src_path).convert("RGB")
    print(f"source      : {os.path.basename(src_path)}  {src.size[0]}x{src.size[1]}")

    img = src.crop(CROP).resize((W, H), Image.LANCZOS)
    print(f"crop        : {CROP[2]-CROP[0]}x{CROP[3]-CROP[1]} at ({CROP[0]}, {CROP[1]}) -> {W}x{H}")

    img = grade(img)
    img = scrim(img)

    font_path, font_label = resolve_font()
    font, size, tracking = fit_font(font_path)

    bbox = inked_bbox(font, tracking)
    word_w, word_h = bbox[2] - bbox[0], bbox[3] - bbox[1]

    # inked_bbox drew from pen x=400; shift so the inked word centers on the
    # frame. Vertical placement comes from the "ls" (baseline) anchor below.
    left = (W - word_w) / 2 - (bbox[0] - 400)
    baseline = H - BASELINE_UP

    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw_word(layer, font, tracking, left, baseline)
    img = Image.alpha_composite(img, layer).convert("RGB")

    img.save(OUT, optimize=True)
    if os.path.getsize(OUT) > 2 * 1024 * 1024:
        img.save(OUT, optimize=True, compress_level=9)

    img.resize((320, 180), Image.LANCZOS).save(OUT_PREVIEW, optimize=True)

    kb = os.path.getsize(OUT) / 1024
    print(f"font        : {font_label}  @ {size}px, tracking {tracking:.1f}px")
    print(f"wordmark    : {word_w}x{word_h}px "
          f"({word_w/W*100:.1f}% width, {word_h/H*100:.1f}% height), "
          f"baseline {BASELINE_UP}px up")
    print(f"wrote       : {OUT}  {img.size[0]}x{img.size[1]}  {kb:.0f} KB")
    print(f"wrote       : {OUT_PREVIEW}  320x180")


if __name__ == "__main__":
    main()
