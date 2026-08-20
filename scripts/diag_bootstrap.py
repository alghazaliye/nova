t = open('/home/ubuntu/nova_new/web_app/flutter_bootstrap.js', encoding='utf-8', errors='ignore').read()

# Find the IIFE body (after the opening "(()=>{" )
body = t
# Look for the loader object and how builds are selected
# Search for 'builds' usage
for m in t.find('builds'):
    pass
idx = t.find('"builds"')
if idx < 0:
    idx = t.find('builds:')
print("builds usage at:", idx)
# print surrounding 2500 chars before that
if idx > 0:
    print("=== context before builds ===")
    print(t[max(0, idx-2500):idx+500])
