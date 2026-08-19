#!/bin/bash
# نشر build/web إلى web_app مع إصلاح URIs الخاصة بـ offline (Drift wasm) وضغط gzip
set -e
cd /home/ubuntu/nova_new

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
echo "DONE: web_app published"
