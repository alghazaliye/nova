#!/usr/bin/env python3
"""Compress web assets with gzip for router.php serving."""
import gzip, os, sys

EXTS = ('.js', '.wasm', '.html', '.json', '.ttf', '.otf', '.css', '.png', '.jpg')
root = sys.argv[1] if len(sys.argv) > 1 else 'web_app'
count = 0
for dirpath, _, files in os.walk(root):
    for f in files:
        if f.endswith('.gz'):
            continue
        if not any(f.endswith(e) for e in EXTS):
            continue
        p = os.path.join(dirpath, f)
        gz = p + '.gz'
        if os.path.exists(gz) and os.path.getmtime(gz) >= os.path.getmtime(p):
            continue
        with open(p, 'rb') as src, gzip.open(gz, 'wb', compresslevel=6) as dst:
            dst.write(src.read())
        count += 1
print(f'compressed {count} files in {root}')
