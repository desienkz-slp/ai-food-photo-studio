"""Restricted identity-only bridge: JSON RGB in, compositor RGB out."""
import base64
import json
import pathlib
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[3] / 'worker'))
from compositor import composite

request = json.load(sys.stdin)
width, height = request['width'], request['height']
original = base64.b64decode(request['rgb'], validate=True)
result = composite(width, height, original, original, bytes([255]) * (width * height))
if result.status != 'ok' or result.pixels != original:
    raise ValueError('identity compositor rejected input')
json.dump({'rgb': base64.b64encode(result.pixels).decode('ascii')}, sys.stdout)
