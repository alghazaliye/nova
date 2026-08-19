#!/bin/bash
# إرشاد Flutter لاستخدام canvaskit.js المحلية بدل gstatic CDN (قد يكون محجوبًا)
cd /home/ubuntu/nova_new
if [ -f web_app/flutter_bootstrap.js ]; then
  python3 - <<'EOF'
import pathlib
p = pathlib.Path('web_app/flutter_bootstrap.js')
t = p.read_text(encoding='utf-8')
orig = t
# في bootstrap الجديد يمكن تمرير canvasKitBaseUrl عبر config، لكن الأسلم patch مباشر:
# نمط: e.engineRevision&&!e.useLocalCanvasKit?W("https://www.gstatic.com/flutter-canvaskit",e.engineRevision)
t = t.replace('https://www.gstatic.com/flutter-canvaskit', 'https://www.gstatic.com/flutter-canvaskit', 0)
# استبدال URL بـ CDN محلي عبر دالة n.canvasKitBaseUrl إن وُجدت؛ fallback: rewrite عبر config
# الطريقة الموثوقة: إقحام config.canvasKitBaseUrl في استدعاء loader.load
target = 'https://www.gstatic.com/flutter-canvaskit'
local_url = '"canvasKitBaseUrl":"/web_app/canvaskit/"'
if target in t:
    # إضافة canvasKitBaseUrl إلى config في flutter_bootstrap قبل load
    t = t.replace('"mainJsPath":"main.dart.js"', '"mainJsPath":"main.dart.js","canvasKitBaseUrl":"/web_app/canvaskit/"')
if t != orig:
    p.write_text(t, encoding='utf-8')
    print("canvaskit url patched")
EOF
  python3 scripts/gzip_web.py web_app
fi
echo "DONE canvaskit fix"
