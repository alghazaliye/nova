#!/usr/bin/env python3
"""Add message_edits and message_deletions tables to database/schema.sqlite.sql
(if not already present), matching the style used by other tables in the file."""

PATH = 'database/schema.sqlite.sql'
NEW_TABLES = '''
CREATE TABLE `message_edits` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `message_id` integer  NOT NULL
,  `conversation_id` integer  NOT NULL
,  `user_id` integer  NOT NULL
,  `old_body` text
,  `new_body` text
,  `edited_at` datetime NOT NULL DEFAULT current_timestamp
,  CONSTRAINT `fk_message_edits_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
);

CREATE TABLE `message_deletions` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `message_id` integer  NOT NULL
,  `conversation_id` integer  NOT NULL
,  `deleted_by` integer  NOT NULL
,  `original_body` text
,  `original_type` varchar(50) DEFAULT 'text'
,  `scope_type` varchar(20) DEFAULT 'me'
,  `deleted_at` datetime NOT NULL DEFAULT current_timestamp
,  CONSTRAINT `fk_message_deletions_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
);
'''

with open(PATH) as f:
    content = f.read()

if 'CREATE TABLE `message_edits`' in content:
    print('message_edits already in schema.sqlite.sql — nothing to do')
else:
    # Insert right after the message_reads table block
    marker = "REFERENCES `messages` (`id`) ON DELETE CASCADE\n);\nCREATE TABLE `message_reads`"
    # find end of message_reads table (easier: insert before message_reactions)
    anchor = "CREATE TABLE `message_reactions`"
    idx = content.index(anchor)
    new_content = content[:idx] + NEW_TABLES.lstrip('\n') + '\n' + content[idx:]
    with open(PATH, 'w') as f:
        f.write(new_content)
    print('Added message_edits & message_deletions to schema.sqlite.sql')

# Validation: ensure both present
with open(PATH) as f:
    c = f.read()
assert 'CREATE TABLE `message_edits`' in c and 'CREATE TABLE `message_deletions`' in c
print('validated: both tables present')
