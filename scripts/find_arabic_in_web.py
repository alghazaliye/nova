#!/usr/bin/env python3
"""البحث عن النصوص العربية في كل ملفات web_app بحثًا عن مصدرها في runtime."""
import glob
import os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
WEB = os.path.join(ROOT, 'web_app')
WORDS = ['هاتف', 'تحقق', 'التالي', 'NOVA Messenger', 'سجّل', 'دخول', 'رمز التحقق']

for path in sorted(glob.glob(os.path.join(WEB, '**/*'), recursive=True)):
    if not os.path.isfile(path):
        continue
    rel = os.path.relpath(path, WEB)
    try:
        raw = open(path, 'rb').read()
    except Exception:
        continue
    txt = raw.decode('utf-8', errors='ignore')
    hits = [w for w in WORDS if w in txt]
    if hits:
        print(rel, '->', hits)
print('--- done ---')
