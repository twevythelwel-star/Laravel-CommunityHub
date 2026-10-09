"""Code-reduction analysis: measures before any target is set.

    python docs/architecture/reduction.py

Writes, next to this file:
  loc-by-area.csv           lines of code by folder
  oversized-files.csv       PHP/TS files over the size limits
  duplicate-blocks.csv      copy-pasted blocks (12+ identical normalised lines)
  forwarding-methods.csv    service/controller methods that only call another class
  unreferenced-classes.csv  classes nothing else names
  route-consolidation.csv   aliases, versioned copies and resource-controller candidates
  model-overlap.csv         models whose fields overlap heavily
  untested-writes.csv       routes that change data and no test touches
  reduction-candidates.csv  each removal candidate with its measured size
and prints a summary.
"""
import csv
import hashlib
import json
import os
import re
from collections import Counter, defaultdict

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
APP = os.path.join(ROOT, 'laravel')
OUT = os.path.dirname(os.path.abspath(__file__))


def read(path):
    try:
        with open(path, encoding='utf-8', errors='ignore') as f:
            return f.read()
    except OSError:
        return ''


def walk(rel, exts):
    base = os.path.join(APP, rel)
    for dirpath, dirs, names in os.walk(base):
        dirs[:] = [d for d in dirs if d not in ('node_modules', 'vendor', 'build', 'ui')] if rel.startswith('resources') else [d for d in dirs if d not in ('node_modules', 'vendor')]
        for n in names:
            if n.endswith(exts):
                yield os.path.join(dirpath, n)


def rel(p):
    return os.path.relpath(p, APP).replace('\\', '/')


def write_csv(name, rows):
    path = os.path.join(OUT, name)
    with open(path, 'w', newline='', encoding='utf-8') as f:
        if not rows:
            f.write('none\n')
            return
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader()
        w.writerows(rows)


php = list(walk('app', ('.php',))) + list(walk('routes', ('.php',))) + list(walk('database', ('.php',))) + list(walk('config', ('.php',)))
ts = [p for p in walk('resources/js', ('.ts', '.tsx'))]
ui_kit = [p for p in walk('resources/js/components/ui', ('.ts', '.tsx'))]
tests = list(walk('tests', ('.php',)))
src = {p: read(p) for p in php + ts + tests}
lines_of = {p: s.count('\n') + 1 for p, s in src.items()}

# ── 1. Lines of code by area ────────────────────────────────────────────────
area_rows = []
area = Counter()
for p in php + ts + tests:
    r = rel(p).split('/')
    key = '/'.join(r[:3]) if r[0] in ('app', 'resources') and len(r) > 3 else '/'.join(r[:2])
    area[key] += lines_of[p]
for k, v in area.most_common():
    area_rows.append({'Area': k, 'Lines': v})
write_csv('loc-by-area.csv', area_rows)

# ── 2. Oversized files ──────────────────────────────────────────────────────
over = []
for p in php + ts:
    n = lines_of[p]
    limit = 500 if p.endswith('.php') else 600
    if n > limit:
        over.append({'File': rel(p), 'Lines': n, 'Limit': limit})
over.sort(key=lambda r: -r['Lines'])
write_csv('oversized-files.csv', over)

# ── 3. Duplicate blocks ─────────────────────────────────────────────────────
WINDOW = 12
TRIVIAL = re.compile(r'^[\s{}\[\]();,<>/*]*$|^\s*(use |import |\*|//|return;|\}\);?$|<\/)')


def norm_lines(text):
    out = []
    for i, line in enumerate(text.split('\n'), 1):
        s = re.sub(r'\s+', ' ', line.strip())
        if not s or TRIVIAL.match(s):
            continue
        out.append((i, s))
    return out


seen = defaultdict(list)
for p in php + ts:
    if '/migrations/' in p.replace('\\', '/'):
        continue
    nl = norm_lines(src[p])
    for k in range(0, max(0, len(nl) - WINDOW + 1)):
        chunk = '\n'.join(s for _, s in nl[k:k + WINDOW])
        h = hashlib.md5(chunk.encode()).hexdigest()
        seen[h].append((p, nl[k][0]))

dup_lines_by_file = defaultdict(set)
pairs = Counter()
for h, locs in seen.items():
    files = {l[0] for l in locs}
    if len(locs) < 2:
        continue
    for p, line in locs:
        dup_lines_by_file[p].update(range(line, line + WINDOW))
    key = tuple(sorted(rel(f) for f in files))
    pairs[key] += 1

dup_rows = []
for files, windows in pairs.most_common(60):
    dup_rows.append({'Files sharing copied blocks': ' | '.join(files), 'Overlapping 12-line windows': windows})
write_csv('duplicate-blocks.csv', dup_rows)
duplicated_lines = sum(len(v) for v in dup_lines_by_file.values())

# ── 4. Forwarding methods ───────────────────────────────────────────────────
METHOD = re.compile(r'public function (\w+)\([^)]*\)[^{]*\{(.*?)\n    \}', re.S)
FORWARD = re.compile(r'^\s*(return\s+)?\$this->\w+->\w+\([^;]*\);\s*$', re.S)
fwd_rows = []
per_class = Counter()
methods_per_class = Counter()
for p in php:
    rp = rel(p)
    if not (rp.startswith('app/Services') or rp.startswith('app/Http/Controllers') or rp.startswith('app/Actions')):
        continue
    for name, body in METHOD.findall(src[p]):
        if name == '__construct':
            continue
        methods_per_class[rp] += 1
        stmts = [s for s in body.strip().split('\n') if s.strip() and not s.strip().startswith(('//', '*', '/*'))]
        if 1 <= len(stmts) <= 3 and FORWARD.match(' '.join(stmts)):
            per_class[rp] += 1
            fwd_rows.append({'File': rp, 'Method': name, 'Body': ' '.join(s.strip() for s in stmts)[:160]})
write_csv('forwarding-methods.csv', fwd_rows)
pure_forwarders = [c for c, n in per_class.items() if n == methods_per_class[c] and n > 0]

# ── 5. Unreferenced classes ─────────────────────────────────────────────────
everything = '\n'.join(src.values())
CLASSDECL = re.compile(r'^\s*(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+(\w+)', re.M)
unref = []
for p in php:
    rp = rel(p)
    if not rp.startswith('app/'):
        continue
    m = CLASSDECL.search(src[p])
    if not m:
        continue
    short = m.group(1)
    if rp.startswith(('app/Providers', 'app/Console/Commands', 'app/Http/Middleware', 'app/Policies', 'app/Listeners', 'app/Observers')):
        continue  # discovered by the framework, not named
    count = len(re.findall(r'\b' + re.escape(short) + r'\b', everything))
    own = len(re.findall(r'\b' + re.escape(short) + r'\b', src[p]))
    in_tests = len(re.findall(r'\b' + re.escape(short) + r'\b', '\n'.join(src[t] for t in tests)))
    outside = count - own
    if outside - in_tests <= 0:
        unref.append({'Class': rp, 'Lines': lines_of[p], 'Referenced only by tests': 'Yes' if in_tests else 'No'})
unref.sort(key=lambda r: -r['Lines'])
write_csv('unreferenced-classes.csv', unref)

# ── 6. Route consolidation ──────────────────────────────────────────────────
routes = json.load(open(os.path.join(OUT, 'routes.json'), encoding='utf-8-sig'))
by_action = defaultdict(list)
for r in routes:
    if r.get('action') and 'Closure' not in r['action']:
        by_action[r['action']].append(r)
cons = []
for action, rs in by_action.items():
    if len(rs) > 1:
        uris = sorted({r['uri'] for r in rs})
        kind = 'Versioned copy' if any(re.match(r'api/v\d', u) for u in uris) or any(u.startswith('api/') for u in uris) and len(uris) > 1 else 'Alias'
        cons.append({'Kind': kind, 'Action': action.replace('App\\Http\\Controllers\\', ''), 'Routes': ' | '.join(f"{r['method']} {r['uri']}" for r in rs), 'Removable routes': len(rs) - 1})
by_ctrl = defaultdict(set)
for r in routes:
    a = r.get('action') or ''
    if '@' in a:
        c, m = a.split('@')
        by_ctrl[c].add(m)
RESOURCE = {'index', 'store', 'update', 'destroy', 'show', 'create', 'edit'}
for c, ms in by_ctrl.items():
    hit = ms & RESOURCE
    if len(hit) >= 3:
        cons.append({'Kind': 'Resource candidate', 'Action': c.replace('App\\Http\\Controllers\\', ''), 'Routes': ', '.join(sorted(hit)), 'Removable routes': 0})
write_csv('route-consolidation.csv', cons)

# ── 7. Model overlap ────────────────────────────────────────────────────────
FILLABLE = re.compile(r'\$fillable\s*=\s*\[(.*?)\];', re.S)
fields = {}
for p in walk('app/Models', ('.php',)):
    m = FILLABLE.search(src.get(p, read(p)))
    if m:
        f = set(re.findall(r"'(\w+)'", m.group(1))) - {'user_id', 'status', 'notes', 'metadata', 'created_by', 'name', 'title', 'description', 'community_id', 'tenant_id'}
        if len(f) >= 4:
            fields[os.path.splitext(os.path.basename(p))[0]] = f
ov = []
names = sorted(fields)
for i, a in enumerate(names):
    for b in names[i + 1:]:
        inter = fields[a] & fields[b]
        union = fields[a] | fields[b]
        j = len(inter) / len(union)
        if j >= 0.3 and len(inter) >= 4:
            ov.append({'Model A': a, 'Model B': b, 'Shared fields': len(inter), 'Similarity': round(j, 2), 'Fields': ', '.join(sorted(inter))[:200]})
ov.sort(key=lambda r: -r['Similarity'])
write_csv('model-overlap.csv', ov)

# ── 8. Untested writes ──────────────────────────────────────────────────────
test_text = '\n'.join(src[t] for t in tests)
untested = []
for r in routes:
    if not re.search(r'POST|PUT|PATCH|DELETE', r['method']):
        continue
    uri = r['uri']
    name = r.get('name') or ''
    prefix = re.sub(r'\{[^}]+\}.*$', '', '/' + uri).rstrip('/')
    hit = (name and ("'" + name + "'") in test_text) or (prefix and prefix in test_text)
    if not hit:
        gates = [m.split(':', 1)[1] for m in (r.get('middleware') or []) if 'Authorize:' in m]
        untested.append({'Route': f"{r['method']} {uri}", 'Name': name, 'Gate': ', '.join(gates) or '(in controller or none)'})
write_csv('untested-writes.csv', untested)

# ── 9. Reduction candidates, measured ───────────────────────────────────────
CANDIDATES = [
    ('Demo: Octane', r'Octane'),
    ('Demo: Observability', r'Observability'),
    ('Demo: Queue dashboards/dispatch API', r'(Queue(Api|Monitoring)|HorizonQueueHub|Livewire/Queue)'),
    ('Demo: Feature flags API', r'(FeatureFlag|Livewire/Features)'),
    ('Demo: Generic PDF API', r'(PdfApi|Livewire/Pdf|Services/Pdf)'),
    ('Demo: Filament showcase hub', r'(Filament)'),
    ('Demo: Query builder playground', r'(QueryBuilder|query-builder)'),
    ('Demo: Performance/benchmark', r'(Performance|Benchmark)'),
    ('Payments: Modular stack (10 gateways)', r'Services/Payments/Modular'),
    ('Payments: Drivers stack', r'Services/Payments/Drivers'),
    ('Notifications: Universal engine', r'Services/Notifications/Universal'),
    ('Dead: AccessRelationshipEngine', r'AccessRelationshipEngine'),
]
cand_rows = []
all_files = php + ts + tests
for label, pat in CANDIDATES:
    rx = re.compile(pat)
    files = [p for p in all_files if rx.search(rel(p))]
    cand_rows.append({
        'Candidate': label,
        'Files': len(files),
        'App lines': sum(lines_of[p] for p in files if not rel(p).startswith('tests')),
        'Test lines': sum(lines_of[p] for p in files if rel(p).startswith('tests')),
        'Example files': ', '.join(rel(p) for p in files[:4]),
    })
write_csv('reduction-candidates.csv', cand_rows)

total_app = sum(lines_of[p] for p in php if rel(p).startswith('app'))
total_ts = sum(lines_of[p] for p in ts)
# The shadcn kit is vendored, so it is left out of every count above.
total_ui_kit = sum(read(p).count('\n') + 1 for p in ui_kit)
summary = {
    'lines': {'app_php': total_app, 'resources_js': total_ts, 'of_which_ui_kit': total_ui_kit, 'tests': sum(lines_of[p] for p in tests)},
    'oversized_files': len(over),
    'oversized_lines': sum(r['Lines'] for r in over),
    'duplicated_lines_estimate': duplicated_lines,
    'forwarding_methods': len(fwd_rows),
    'pure_forwarding_classes': pure_forwarders,
    'unreferenced_classes': len(unref),
    'unreferenced_lines': sum(r['Lines'] for r in unref),
    'route_aliases_and_copies_removable': sum(r['Removable routes'] for r in cons),
    'resource_candidates': sum(1 for r in cons if r['Kind'] == 'Resource candidate'),
    'model_overlap_pairs': len(ov),
    'untested_write_routes': len(untested),
    'candidates': {r['Candidate']: r['App lines'] + r['Test lines'] for r in cand_rows},
}
print(json.dumps(summary, indent=2))
