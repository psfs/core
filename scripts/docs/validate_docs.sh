#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

DOC_FILES=(
  "README.md"
  "doc/OPERATIONS.md"
  "doc/PROPEL_WORKFLOW.md"
)

for f in "${DOC_FILES[@]}"; do
  if [[ ! -f "$f" ]]; then
    echo "[DOCS][ERROR] Missing required doc file: $f" >&2
    exit 1
  fi
done

python3 - <<'PY'
import os
import re
import sys
import subprocess
from pathlib import Path
from urllib.parse import urlparse

root = Path.cwd()
doc_files = [
    root / 'README.md',
    root / 'doc' / 'OPERATIONS.md',
    root / 'doc' / 'PROPEL_WORKFLOW.md',
]

link_pattern = re.compile(r'\[[^\]]+\]\(([^)]+)\)')
command_fence_pattern = re.compile(r'^```(?:bash|sh)\s*$')

local_errors = []
tag_errors = []
external_links = set()

for path in doc_files:
    text = path.read_text(encoding='utf-8')
    lines = text.splitlines()

    for i, line in enumerate(lines):
        if command_fence_pattern.match(line):
            prev = lines[i - 1].strip() if i > 0 else ''
            if prev not in ('<!-- validated -->', '<!-- example-only -->'):
                tag_errors.append(f'{path.relative_to(root)}:{i+1} command block missing tag')

    for m in link_pattern.finditer(text):
        target = m.group(1).strip()
        if not target or target.startswith('#'):
            continue
        if target.startswith('mailto:'):
            continue
        if target.startswith('http://') or target.startswith('https://'):
            external_links.add(target)
            continue

        rel = target.split('#', 1)[0].split('?', 1)[0]
        resolved = (path.parent / rel).resolve()
        if not resolved.exists():
            local_errors.append(f'{path.relative_to(root)} -> {target}')

if tag_errors:
    print('[DOCS][ERROR] Untagged command blocks detected:', file=sys.stderr)
    for err in tag_errors:
        print(f'  - {err}', file=sys.stderr)
    sys.exit(1)

if local_errors:
    print('[DOCS][ERROR] Broken local links detected:', file=sys.stderr)
    for err in local_errors:
        print(f'  - {err}', file=sys.stderr)
    sys.exit(1)

print('[DOCS] Local links and command tags: OK')

warns = []
for link in sorted(external_links):
    ok = False
    last = ''
    for _ in range(3):
        proc = subprocess.run(
            ['curl', '-L', '-s', '-o', '/dev/null', '-w', '%{http_code}', '--max-time', '10', link],
            capture_output=True,
            text=True,
            check=False,
        )
        code = proc.stdout.strip()
        last = code
        if code in {'200', '301', '302'}:
            ok = True
            break
    if not ok:
        warns.append((link, last))

if warns:
    print('[DOCS][WARN] External links not confirmed as 200/301/302 after retries:')
    for link, code in warns:
        print(f'  - {link} (last_http={code})')
else:
    print('[DOCS] External links: OK')
PY
