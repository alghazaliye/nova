#!/usr/bin/env python3
"""Add the missing call_signals table to database/schema.sqlite.sql.

CallController uses call_signals for WebRTC signaling, but the table was never
added to the SQLite schema, causing HTTP 500 on /calls/{id}/signal and
/calls/{id}/signals.
"""
PATH = 'database/schema.sqlite.sql'

BLOCK = '''
CREATE TABLE `call_signals` (
  `id` integer  NOT NULL  PRIMARY KEY AUTOINCREMENT
,  `call_id` integer  NOT NULL
,  `sender_id` integer  NOT NULL
,  `signal_type` text  NOT NULL
,  `payload` text  NOT NULL
,  `created_at` DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
,  CONSTRAINT `fk_call_signals_call` FOREIGN KEY (`call_id`) REFERENCES `calls` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_call_signals_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
'''

INDEXES = '''
CREATE INDEX "idx_call_signals_idx_call_id" ON "call_signals" (`call_id`);
CREATE INDEX "idx_call_signals_idx_created_at" ON "call_signals" (`created_at`);
'''

with open(PATH) as f:
    content = f.read()

if 'CREATE TABLE `call_signals`' in content or 'CREATE TABLE call_signals' in content:
    print('call_signals already present — nothing to do')
    raise SystemExit(0)

# Insert right after the last index line for calls (idx_calls_idx_calls_created_at)
anchor = 'CREATE INDEX "idx_calls_idx_calls_created_at" ON "calls" (`created_at`);\n'
pos = content.find(anchor)
if pos == -1:
    print('ERROR: anchor line not found')
    raise SystemExit(1)
insert_at = pos + len(anchor)
new_content = content[:insert_at] + BLOCK + INDEXES + '\n' + content[insert_at:]

with open(PATH, 'w') as f:
    f.write(new_content)
print('call_signals table + indexes added to schema.sqlite.sql')
