#!/usr/bin/env python3
"""Patch web_app/index.html with a server timezone loader (window.NovaTZ).
Run AFTER publish_web.sh / post_publish.py so it survives full web_app rebuilds.
Idempotent: skips if already patched.
"""
import pathlib

INJECT = '''
  <script>
    // تحميل المنطقة الزمنية من الخادم (يُحدَّد من إعدادات لوحة التحكم) وتوفيرها
    // للتطبيق كـ window.NovaTZ، وتعمل على أي استضافة دون حاجة لـ PHP في index.html.
    (function () {
      try {
        var apiBase = window.location.origin;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', apiBase + '/api/v1/health', true);
        xhr.timeout = 5000;
        xhr.onload = function () {
          try {
            var d = JSON.parse(xhr.responseText);
            var tz = (d && d.data && d.data.timezone) || null;
            if (tz) window.NovaTZ = tz;
          } catch (e) { /* ignore */ }
        };
        xhr.onerror = function () { /* ignore */ };
        xhr.send();
      } catch (e) { /* ignore */ }
    })();
  </script>'''

root = pathlib.Path(__file__).parent.parent / 'web_app'
idx = root / 'index.html'
html = idx.read_text(encoding='utf-8')

if 'window.NovaTZ' in html:
    print('already patched')
    raise SystemExit(0)

for marker in [
    '  <script id="nova-bootstrap" src="/web_app/flutter_bootstrap.js" async></script>',
    '  <script id="nova-bootstrap" src="flutter_bootstrap.js" async></script>',
    '<script id="nova-bootstrap" src="/web_app/flutter_bootstrap.js" async></script>',
    '<script id="nova-bootstrap" src="flutter_bootstrap.js" async></script>',
]:
    if marker in html:
        html = html.replace(marker, marker + INJECT, 1)
        break
else:
    print('ERROR: nova-bootstrap marker not found; manual inspection needed')
    raise SystemExit(1)

idx.write_text(html, encoding='utf-8')
print('patched NovaTZ loader')
