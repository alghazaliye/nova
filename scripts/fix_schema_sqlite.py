#!/usr/bin/env python3
"""Final fix for database/schema.sqlite.sql:
1. Remove the duplicated message_edits/message_deletions blocks I added earlier.
2. Fix the pre-existing message_deletions line (VARCHAR(20) -> VARCHAR(50)).
3. Add message_edits table exactly once, near the fixed message_deletions.
"""

PATH = 'database/schema.sqlite.sql'
with open(PATH) as f:
    lines = f.readlines()

# ---- Step 1: remove duplicated blocks (lines 222-244 region) ----
out = []
skip = False
i = 0
removed = 0
while i < len(lines):
    line = lines[i]
    if line.strip() == 'CREATE TABLE `message_edits` (':
        # skip until closing ');'
        while i < len(lines) and lines[i].strip() != ');':
            i += 1
        i += 1  # skip ');'
        # skip blank lines following
        while i < len(lines) and lines[i].strip() == '':
            i += 1
        removed += 1
        continue
    if line.strip() == 'CREATE TABLE `message_deletions` (':
        while i < len(lines) and lines[i].strip() != ');':
            i += 1
        i += 1
        while i < len(lines) and lines[i].strip() == '':
            i += 1
        removed += 1
        continue
    out.append(line)
    i += 1
print(f'removed {removed} duplicated block(s)')

content = ''.join(out)

# ---- Step 2 & 3: fix pre-existing deletions line, add edits after it ----
old_del = "CREATE TABLE message_deletions (id INTEGER PRIMARY KEY AUTOINCREMENT, message_id INTEGER NOT NULL, conversation_id INTEGER NOT NULL, deleted_by INTEGER NOT NULL, original_body TEXT, original_type VARCHAR(20), scope_type VARCHAR(20) NOT NULL DEFAULT 'for_me', deleted_at DATETIME NOT NULL DEFAULT (datetime('now','localtime')));"
new_del = ("CREATE TABLE message_deletions (id INTEGER PRIMARY KEY AUTOINCREMENT, message_id INTEGER NOT NULL, "
           "conversation_id INTEGER NOT NULL, deleted_by INTEGER NOT NULL, original_body TEXT, "
           "original_type VARCHAR(50), scope_type VARCHAR(20) NOT NULL DEFAULT 'for_me', "
           "deleted_at DATETIME NOT NULL DEFAULT (datetime('now','localtime')));")

assert old_del in content, 'pre-existing deletions line not found'
content = content.replace(old_del, new_del)

edits_line = ("CREATE TABLE message_edits (id INTEGER PRIMARY KEY AUTOINCREMENT, message_id INTEGER NOT NULL, "
              "conversation_id INTEGER NOT NULL, user_id INTEGER NOT NULL, old_body TEXT, new_body TEXT, "
              "edited_at DATETIME NOT NULL DEFAULT (datetime('now','localtime')));")

# Insert edits right before deletions
content = content.replace(new_del, edits_line + '\n' + new_del, 1)

with open(PATH, 'w') as f:
    f.write(content)

# ---- Validate with real sqlite3 ----
import subprocess, tempfile, os
tmp = tempfile.mktemp(suffix='.db')
r = subprocess.run(['sqlite3', tmp], input=content, capture_output=True, text=True)
if r.returncode != 0:
    print('SQL ERROR:', r.stderr)
    raise SystemExit(1)
r2 = subprocess.run(['sqlite3', tmp,
    "SELECT name FROM sqlite_master WHERE name IN ('message_edits','message_deletions') ORDER BY name"],
    capture_output=True, text=True)
print('tables:', r2.stdout.strip())
os.unlink(tmp)
print('DONE: schema.sqlite.sql fixed and validated')
