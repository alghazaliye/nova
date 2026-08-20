#!/usr/bin/env python3
"""Post-publish fixes for web_app: dynamic base href, icons, manifest, favicon, .htaccess, gzip."""
import pathlib, gzip

root = pathlib.Path('web_app')

# 1) Dynamic base href (بعد أن يستبدل publish_web.sh base href بـ /web_app/)
idx = root / 'index.html'
html = idx.read_text(encoding='utf-8')
if 'id="dynamic-base"' not in html or 'var b = document.getElementById' not in html:
    script = '''  <base href="/" id="dynamic-base">
  <script>
    // Dynamic base href: works whether served from root or a sub-path (e.g. /web_app/).
    (function () {
      try {
        var p = window.location.pathname;
        var base = p.lastIndexOf('/') > 0 ? p.substring(0, p.lastIndexOf('/') + 1) : '/';
        var b = document.getElementById('dynamic-base');
        if (b && b.getAttribute('href') !== base) b.setAttribute('href', base);
      } catch (e) { /* ignore */ }
    })();
  </script>'''
    html = html.replace('  <base href="/web_app/">', script, 1)
    if 'id="dynamic-base"' not in html:
        # fallback: أي base href موجود
        import re
        html = re.sub(r'<base href="[^"]*"[^>]*>', script, html, count=1)
    idx.write_text(html, encoding='utf-8')
    print('patched dynamic base href')

# 2) COOP/COEP + CORS meta for skwasm multithreaded
if '<meta http-equiv="Cross-Origin-Opener-Policy"' not in html:
    meta = '''  <meta http-equiv="Cross-Origin-Opener-Policy" content="same-origin">
  <meta http-equiv="Cross-Origin-Embedder-Policy" content="credentialless">'''
    html = html.replace('</head>', meta + '\n</head>', 1)
    idx.write_text(html, encoding='utf-8')
    print('added COOP/COEP meta')

# 3) icons + manifest + favicon
icons = root / 'icons'
if not icons.exists():
    import subprocess, os
    icons.mkdir(parents=True, exist_ok=True)
    subprocess.run(['git', 'archive', '5aed39e', 'web_app/icons/'],
                   stdout=subprocess.DEVNULL) if False else None
    subprocess.run(f"git archive 5aed39e web_app/icons/ | tar xf - --transform='s|^web_app/||' -C {root}",
                   shell=True, cwd='/home/ubuntu/nova_new')
    print('extracted icons from 5aed39e')
import shutil
manifest = root / 'manifest.json'
src_manifest = pathlib.Path('/home/ubuntu/nova_new/nova_flutter/web/manifest.json')
if src_manifest.exists():
    shutil.copy(src_manifest, manifest)
    print('synced manifest.json (NOVA brand colors)')
if not manifest.exists():
    subprocess.run(f'git show 5aed39e:web_app/manifest.json > {manifest}',
                   shell=True, cwd='/home/ubuntu/nova_new')
    print('restored manifest.json')

favicon_ico = root / 'favicon.ico'
src_favicon = pathlib.Path('/home/ubuntu/nova_new/nova_flutter/web/favicon.ico')
if src_favicon.exists():
    shutil.copy(src_favicon, favicon_ico)
    print('synced favicon.ico')
fav = root / 'favicon.png'
if not fav.exists():
    try:
        from PIL import Image
        Image.open(icons / 'Icon-192.png').resize((32, 32)).save(fav)
        print('created favicon.png')
    except Exception as e:
        print('favicon failed:', e)

# 4) .htaccess for COOP/COEP (Render Apache) — لا نضعه في repo (يُحظر في router)
ht = root / '.htaccess'
ht.write_text('''<IfModule mod_headers.c>
  Header set Cross-Origin-Opener-Policy "same-origin"
  Header set Cross-Origin-Embedder-Policy "credentialless"
</IfModule>
''')
print('wrote .htaccess')

# 5) gzip
import sys
sys.path.insert(0, '/home/ubuntu/nova_new/scripts')
compress_root = None
# gzip inline
import gzip as _gz
for _f in root.rglob("*"):
    if _f.is_file() and _f.suffix.lower() in (".js",".wasm",".html",".json",".ttf",".otf",".css",".png",".jpg",".mjs") and not str(_f).endswith(".gz"):
        _g = pathlib.Path(str(_f) + ".gz")
        if not _g.exists() or _g.stat().st_mtime < _f.stat().st_mtime:
            _g.write_bytes(_gz.compress(_f.read_bytes(), compresslevel=6))
print("gzip done")
