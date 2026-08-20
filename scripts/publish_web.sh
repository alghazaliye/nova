#!/bin/bash
# نشر build/web إلى web_app مع إصلاح URIs الخاصة بـ offline (Drift wasm) وضغط gzip
set -e
cd /home/ubuntu/nova_new

# نسخ skwasm.wasm (قد لا يخرجه flutter build)
for src in /home/ubuntu/flutter/bin/cache/flutter_web_sdk/canvaskit/skwasm.wasm /home/ubuntu/flutter/bin/cache/flutter_web_sdk/canvaskit/skwasm.js; do
  [ -f "$src" ] && cp "$src" nova_flutter/build/web/canvaskit/ 2>/dev/null && echo "copied skwasm $(basename $src)"
done
# نسخ wasm/wasm worker إلى build قبل النشر (تُنسخ مرة واحدة بعد كل flutter build)
D=$(ls -d /home/ubuntu/.pub-cache/hosted/pub.dev/drift-2.* | head -1)
cp "$D/drift_worker.js" nova_flutter/build/web/ 2>/dev/null || true
cp "$D/extension/devtools/build/sqlite3.wasm" nova_flutter/build/web/ 2>/dev/null || true

rm -rf web_app
cp -r nova_flutter/build/web web_app
sed -i 's|<base href="/">|<base href="/web_app/">|' web_app/index.html
python3 - <<'EOF'
import pathlib
p = pathlib.Path('web_app')
for f in list(p.rglob('*.js')):
    t = f.read_text(encoding='utf-8', errors='ignore')
    orig = t
    # الصيغة المصدرية (قبل الترجمة)
    t = t.replace("sqlite3Uri: Uri.parse('sqlite3.wasm')", "sqlite3Uri: Uri.parse('/web_app/sqlite3.wasm')")
    t = t.replace("driftWorkerUri: Uri.parse('drift_worker.js')", "driftWorkerUri: Uri.parse('/web_app/drift_worker.js')")
    # الصيغة المترجمة (minified dart2js): A.cM("sqlite3.wasm") — A.cM = const literal المصغّر
    t = t.replace('A.cM("sqlite3.wasm")', 'A.cM("/web_app/sqlite3.wasm")')
    t = t.replace('A.cM("drift_worker.js")', 'A.cM("/web_app/drift_worker.js")')
    # تغطية أي minifier symbol آخر
    t = t.replace('("sqlite3.wasm")', '("/web_app/sqlite3.wasm")')
    t = t.replace('("drift_worker.js")', '("/web_app/drift_worker.js")')
    if t != orig:
        f.write_text(t, encoding='utf-8')
        print("patched", f.name)
EOF
python3 scripts/gzip_web.py web_app

# === عناصر ثابتة إضافية (لا يخرجه flutter build) ===
cp -r web_app/canvaskit/skwasm.wasm web_app/skwasm.wasm 2>/dev/null || true
if [ ! -d web_app/icons ]; then
  mkdir -p web_app/icons
  git archive 5aed39e web_app/icons/ --format=tar 2>/dev/null | tar xf - --transform='s|^web_app/||' -C web_app/ || true
fi
[ -f web_app/icons/Icon-192.png ] && python3 -c "
from PIL import Image
Image.open('web_app/icons/Icon-192.png').resize((32,32)).save('web_app/favicon.png')" || true
if [ ! -f web_app/manifest.json ]; then
  git show 5aed39e:web_app/manifest.json > web_app/manifest.json 2>/dev/null || true
fi
echo "DONE: web_app published"
