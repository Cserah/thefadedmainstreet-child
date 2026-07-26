"""
Driver: turn a graded base image into a finished series thumbnail.

    python build_thumbnail.py <base.png> <WORD> <out.png>

The base is normalized to exactly 1280x720 (centre-cropped to 16:9 first, so
a model-returned frame that is slightly off-ratio is not squashed), then the
wordmark is applied by thumbnail_text.add_title.
"""

import os
import sys

from PIL import Image

from thumbnail_text import (add_title, save_with_preview,
                            BASELINE_UP, SHADOW, STROKE)

W, H = 1280, 720
MAX_BYTES = 2 * 1024 * 1024


def normalize(path, w=W, h=H):
    """Centre-crop to the target aspect, then resize. Never distorts."""
    img = Image.open(path).convert("RGB")
    sw, sh = img.size
    target = w / h
    if abs(sw / sh - target) > 1e-6:
        if sw / sh > target:            # too wide -> trim sides
            new_w = round(sh * target)
            off = (sw - new_w) // 2
            img = img.crop((off, 0, off + new_w, sh))
        else:                            # too tall -> trim top/bottom
            new_h = round(sw / target)
            off = (sh - new_h) // 2
            img = img.crop((0, off, sw, off + new_h))
    return img.resize((w, h), Image.LANCZOS), (sw, sh)


def main():
    import argparse
    p = argparse.ArgumentParser(description="Build a series thumbnail.")
    p.add_argument("base")
    p.add_argument("word")
    p.add_argument("out")
    # Per-image knobs. The scrim is the one that usually needs touching: a busy
    # or bright bottom (or painted lettering sitting where the word goes) needs
    # a stronger scrim than the 0.55 default to stay readable.
    p.add_argument("--scrim", type=float, help="scrim opacity at the bottom edge")
    p.add_argument("--scrim-band", type=float, help="scrim height as a fraction")
    p.add_argument("--width-frac", type=float, help="target wordmark width fraction")
    p.add_argument("--baseline", type=int, help="baseline px above bottom edge")
    p.add_argument("--size", help="output size, e.g. 2560x1440 (default 1280x720)")
    p.add_argument("--no-preview", action="store_true")
    a = p.parse_args()

    out_w, out_h = W, H
    if a.size:
        out_w, out_h = (int(v) for v in a.size.lower().split("x"))

    # The wordmark's width and cap height are fractions of the frame, so they
    # scale on their own. Baseline, shadow and stroke are absolute pixels and
    # would come out proportionally tiny at a larger size -- scale them here so
    # a 2560px render looks identical to the 1280px one, just with more pixels.
    k = out_w / W
    opts = {}
    if k != 1:
        opts["baseline_up"] = round(BASELINE_UP * k)
        opts["shadow"] = (round(SHADOW[0] * k), round(SHADOW[1] * k))
        opts["stroke"] = max(1, round(STROKE * k))
    if a.scrim is not None:
        opts["scrim_max"] = a.scrim
    if a.scrim_band is not None:
        opts["scrim_band"] = a.scrim_band
    if a.width_frac is not None:
        opts["width_frac"] = a.width_frac
    if a.baseline is not None:
        opts["baseline_up"] = a.baseline

    base, word, out = a.base, a.word, a.out
    img, src_size = normalize(base, out_w, out_h)
    up = out_w / src_size[0]
    print(f"base     : {os.path.basename(base)} {src_size[0]}x{src_size[1]} -> "
          f"{out_w}x{out_h}  ({'downscale' if up <= 1 else 'UPSCALE'} {up:.2f}x)")

    final, meta = add_title(img, word, report=True, **opts)
    prev = None if a.no_preview else os.path.splitext(out)[0] + "_preview.png"
    size = save_with_preview(final, out, prev)

    print(f"font     : {meta['font']} @ {meta['size']}px, tracking {meta['tracking']}px")
    print(f"wordmark : {meta['width_pct']}% width, {meta['height_pct']}% height "
          f"({meta['word_px'][0]}x{meta['word_px'][1]}px)")
    note = "" if size < MAX_BYTES else "  -- over YouTube's 2MB thumbnail limit"
    print(f"wrote    : {out} ({size/1024/1024:.2f} MB){note}")
    if prev:
        print(f"wrote    : {prev} (320x180)")


if __name__ == "__main__":
    main()
