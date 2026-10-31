# Image worker: compositing core

Python 3.9+, standard library only; no dependencies or network. Run from this directory:

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -m unittest -v test_worker
```

`composite(width, height, original, background, mask)` accepts tightly packed row-major RGB `bytes` for both images, and one byte per pixel for the mask. Dimensions must match exactly. Width and height must be positive integers, each no larger than 8192, and the image must contain no more than 16,000,000 pixels. These are conservative input guardrails, not production capacity claims. Mask 255 copies the original pixel exactly; 0 copies the supplied background pixel. Any intermediate mask value is uncertain and returns `needs_review` with no output; malformed inputs also return `needs_review`. No interpolation, generation, image decoding, file I/O, or original overwrite is performed. This is an in-memory compositing primitive, not an end-to-end image worker or a quality guarantee; caller must verify that the mask correctly protects the product and handle persistence safely.
