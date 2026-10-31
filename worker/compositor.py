from dataclasses import dataclass
from typing import Optional


# Conservative input guardrails; these are not production capacity claims.
MAX_DIMENSION = 8192
MAX_PIXELS = 16_000_000


@dataclass(frozen=True)
class Result:
    status: str
    pixels: Optional[bytes]
    reason: Optional[str] = None


def composite(width, height, original, background, mask):
    """Composite RGB bytes: mask 255 protects original, 0 selects background."""
    if (type(width) is not int or type(height) is not int or
            width <= 0 or height <= 0 or
            not all(isinstance(value, bytes) for value in (original, background, mask))):
        return Result('needs_review', None, 'invalid_input')
    count = width * height
    if (width > MAX_DIMENSION or height > MAX_DIMENSION or
            count > MAX_PIXELS):
        return Result('needs_review', None, 'invalid_dimensions')
    if len(original) != count * 3 or len(background) != count * 3 or len(mask) != count:
        return Result('needs_review', None, 'invalid_dimensions')
    if any(value not in (0, 255) for value in mask):
        return Result('needs_review', None, 'uncertain_mask')
    pixels = bytes(original[i] if mask[i // 3] == 255 else background[i]
                   for i in range(width * height * 3))
    return Result('ok', pixels)
