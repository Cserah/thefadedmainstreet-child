"""
Reusable thumbnail wordmark overlay for the Faded Main Street series.

    from thumbnail_text import add_title
    add_title("base.png", "FADED").save("thumbnail.png")

Draws a scrim over the bottom of the frame, then the word in heavy condensed
caps: white fill, a hard un-blurred black offset copy, and a black outline.

Sizing note -- the width and height targets cannot both be absolute across a
series, because a 5-letter word and a 10-letter word behave very differently
in a condensed face. The resolution order is:

  1. Size the font so the word's natural width hits WIDTH_FRAC.
  2. If that makes the caps taller than MAX_CAP_FRAC, clamp the size.
  3. Close the leftover width with uniform letter-spacing, but only up to
     MAX_TRACKING_RATIO x the mean letter width -- past roughly 1.0 the word
     stops reading as a word at sidebar size and becomes loose letters.

So long words land at ~85% naturally; short words land wide but still legible.
"""

import os
import tempfile
import urllib.request

from PIL import Image, ImageDraw, ImageFont

ANTON_URL = "https://github.com/google/fonts/raw/main/ofl/anton/Anton-Regular.ttf"

# Defaults -- every one is overridable per call.
# 0.78, not 0.85: YouTube's duration badge sits over the bottom-right corner
# and clipped the final glyph of an 85%-wide wordmark. At 78% the right edge
# lands near x=1139 on a 1280 frame, clear of the badge.
WIDTH_FRAC = 0.78          # target inked width as a fraction of frame width
MAX_CAP_FRAC = 0.25        # cap height ceiling as a fraction of frame height
MAX_TRACKING_RATIO = 0.9   # max letter-spacing as a multiple of mean advance
BASELINE_UP = 70           # baseline this many px above the bottom edge
SHADOW = (6, 6)            # hard offset copy, no blur
STROKE = 3                 # black outline around the glyphs
SCRIM_BAND = 0.40          # scrim covers this fraction of the frame, bottom-up
SCRIM_MAX = 0.55           # opacity at the very bottom edge


# --- font -------------------------------------------------------------------

def resolve_font(explicit=None):
    """Return (path, label). Prefers Anton, then Bebas Neue, then Impact."""
    if explicit:
        return explicit, os.path.basename(explicit)

    here = os.path.dirname(os.path.abspath(__file__))
    cache = os.path.join(tempfile.gettempdir(), "fms-fonts")
    win = os.path.join(os.environ.get("WINDIR", r"C:\Windows"), "Fonts")

    for path, label in [
        (os.path.join(here, "Anton-Regular.ttf"), "Anton"),
        (os.path.join(cache, "Anton-Regular.ttf"), "Anton (cached)"),
        (os.path.join(win, "BebasNeue-Regular.ttf"), "Bebas Neue"),
        (os.path.join(win, "Oswald-Bold.ttf"), "Oswald Bold"),
    ]:
        if os.path.exists(path):
            return path, label

    try:
        os.makedirs(cache, exist_ok=True)
        dest = os.path.join(cache, "Anton-Regular.ttf")
        urllib.request.urlretrieve(ANTON_URL, dest)
        ImageFont.truetype(dest, 40)
        return dest, "Anton (downloaded)"
    except Exception:
        pass

    impact = os.path.join(win, "impact.ttf")
    if os.path.exists(impact):
        return impact, "Impact (fallback)"
    raise RuntimeError("No suitable condensed font available.")


# --- metrics ----------------------------------------------------------------

def _positions(font, word, tracking):
    xs, pen = [], 0.0
    for ch in word:
        xs.append(pen)
        pen += font.getlength(ch) + tracking
    return xs


def _inked_bbox(font, word, tracking, stroke):
    """Bbox of the stroked word exactly as it will be rendered."""
    pad = 400
    probe = Image.new("L", (int(font.size * len(word) * 3) + pad * 2,
                            int(font.size * 3)), 0)
    d = ImageDraw.Draw(probe)
    for ch, x in zip(word, _positions(font, word, tracking)):
        d.text((pad + x, pad // 2), ch, font=font, fill=255,
               stroke_width=stroke, stroke_fill=255)
    return probe.getbbox(), pad


def _fit(font_path, word, frame_w, frame_h, width_frac, max_cap_frac,
         max_tracking_ratio, stroke):
    """Solve for (font, tracking). Both relations are linear, so no search."""
    target_w = width_frac * frame_w
    max_cap = max_cap_frac * frame_h

    ref = ImageFont.truetype(font_path, 100)
    d = ImageDraw.Draw(Image.new("L", (8, 8)))
    box = d.textbbox((0, 0), word, font=ref)
    cap_at_100 = box[3] - box[1]
    adv_at_100 = sum(ref.getlength(c) for c in word)

    size_for_width = target_w * 100.0 / adv_at_100
    size_for_cap = max_cap * 100.0 / cap_at_100
    size = max(8, int(round(min(size_for_width, size_for_cap))))
    font = ImageFont.truetype(font_path, size)

    tracking = 0.0
    if size_for_width > size_for_cap and len(word) > 1:
        # Height-clamped: recover width with letter-spacing, within the limit.
        b0, _ = _inked_bbox(font, word, 0, stroke)
        b1, _ = _inked_bbox(font, word, 50, stroke)
        w0, w1 = b0[2] - b0[0], b1[2] - b1[0]
        slope = (w1 - w0) / 50.0
        if slope > 0:
            tracking = max(0.0, (target_w - w0) / slope)
        mean_adv = sum(font.getlength(c) for c in word) / len(word)
        tracking = min(tracking, max_tracking_ratio * mean_adv)
    return font, tracking


# --- drawing ----------------------------------------------------------------

def _scrim(img, band_frac, max_opacity):
    w, h = img.size
    band = max(1, round(h * band_frac))
    top = h - band
    overlay = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(overlay)
    peak = round(255 * max_opacity)
    for i in range(band):
        a = round(peak * i / max(1, band - 1))
        d.line([(0, top + i), (w, top + i)], fill=(0, 0, 0, a))
    return Image.alpha_composite(img.convert("RGBA"), overlay)


def add_title(image, word, font_path=None, width_frac=WIDTH_FRAC,
              max_cap_frac=MAX_CAP_FRAC, max_tracking_ratio=MAX_TRACKING_RATIO,
              baseline_up=BASELINE_UP, shadow=SHADOW, stroke=STROKE,
              scrim_band=SCRIM_BAND, scrim_max=SCRIM_MAX, report=False):
    """Overlay `word` on `image`, returning a new RGB image.

    image  -- a PIL Image or a path.
    word   -- the title; upper-cased automatically.
    report -- if True, also return a dict of the resolved metrics.
    """
    img = Image.open(image) if isinstance(image, (str, os.PathLike)) else image
    img = img.convert("RGB")
    w, h = img.size
    word = word.upper()

    path, label = resolve_font(font_path)
    font, tracking = _fit(path, word, w, h, width_frac, max_cap_frac,
                          max_tracking_ratio, stroke)

    bbox, pad = _inked_bbox(font, word, tracking, stroke)
    word_w, word_h = bbox[2] - bbox[0], bbox[3] - bbox[1]

    # Centring is measured off the real inked bbox (which includes the stroke),
    # not the advance width, so side bearings cannot bias it. Verified on the
    # Mauer render: white fill spanned x 100-1179 in a 1280 frame, centre 639.5
    # vs 640 -- half a pixel, i.e. rounding. No offset is fed in from the crop
    # or the scrim; both are applied to the full frame and shift nothing.
    # Note the hard shadow deliberately sits outside this: it extends `shadow`
    # px right of the fill, so the glyphs are centred, not the glyphs+shadow.
    left = (w - word_w) / 2 - (bbox[0] - pad)
    baseline = h - baseline_up

    canvas = _scrim(img, scrim_band, scrim_max)
    layer = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(layer)
    xs = _positions(font, word, tracking)
    sx, sy = shadow

    for ch, x in zip(word, xs):        # hard offset copy, drawn underneath
        d.text((left + x + sx, baseline + sy), ch, font=font, anchor="ls",
               fill=(0, 0, 0, 255), stroke_width=stroke,
               stroke_fill=(0, 0, 0, 255))
    for ch, x in zip(word, xs):        # white fill with black outline
        d.text((left + x, baseline), ch, font=font, anchor="ls",
               fill=(255, 255, 255, 255), stroke_width=stroke,
               stroke_fill=(0, 0, 0, 255))

    out = Image.alpha_composite(canvas, layer).convert("RGB")
    if report:
        return out, {
            "font": label, "size": font.size, "tracking": round(tracking, 1),
            "width_pct": round(word_w / w * 100, 1),
            "height_pct": round(word_h / h * 100, 1),
            "word_px": (word_w, word_h),
        }
    return out


def save_with_preview(img, out_path, preview_path=None, preview=(320, 180)):
    """Save the thumbnail plus a downscaled preview for sidebar-size checks."""
    img.save(out_path, optimize=True)
    if preview_path:
        img.resize(preview, Image.LANCZOS).save(preview_path, optimize=True)
    return os.path.getsize(out_path)


if __name__ == "__main__":
    import sys
    if len(sys.argv) < 4:
        raise SystemExit("usage: thumbnail_text.py <base.png> <WORD> <out.png>")
    base, word, out = sys.argv[1], sys.argv[2], sys.argv[3]
    img, meta = add_title(base, word, report=True)
    prev = os.path.splitext(out)[0] + "_preview.png"
    size = save_with_preview(img, out, prev)
    print(f"font     : {meta['font']} @ {meta['size']}px, "
          f"tracking {meta['tracking']}px")
    print(f"wordmark : {meta['width_pct']}% width, {meta['height_pct']}% height")
    print(f"wrote    : {out} ({size/1024:.0f} KB) + {prev}")
