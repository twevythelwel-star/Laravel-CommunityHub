"""Architecture baseline inventory for the Laravel app.

Re-run from the repository root after `php artisan route:list --json
--except-vendor > docs/architecture/routes.json` (run inside laravel/):

    python docs/architecture/inventory.py

Writes route-inventory.csv, service-inventory.csv, controller-inventory.csv
and model-inventory.csv next to this file, and prints a summary.

Everything here is derived from the code. Where a decision cannot be
derived (a known demo module, a known duplicate), it comes from OVERRIDES
below, each with its reason, and the row's Basis column says so.
"""
import csv
import json
import os
import re
import sys
from collections import Counter, defaultdict

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
APP = os.path.join(ROOT, 'laravel')
OUT = os.path.dirname(os.path.abspath(__file__))
ROUTES_JSON = sys.argv[1] if len(sys.argv) > 1 else os.path.join(OUT, 'routes.json')

ROLE_ABBR = 'SA=System Admin, A=Admin, S=Security, St=Staff, H=Homeowner, T=Temporary Homeowner'
GATE_ROLES = {
    'manageUsers': 'SA, A',
    'manageSecurity': 'SA, A, S',
    'scanPasses': 'SA, A, S',
    'manageBlocklist': 'SA, A, S',
    'accessNotificationInbox': 'H, T, S, A',
    'manageBoundary': 'SA',
    'manageLandmarks': 'SA, A',
    'viewBoundary': 'SA, A, S',
    'accessEstateInformation': 'SA, A, S, H, T',
    'accessCommunityLife': 'SA, A, H, T',
    'viewAppChangelog': 'SA',
    'operatePlatform': 'SA',
    'viewApiDocs': 'SA',
    'issueDocuments': 'SA, A',
    'accessBilling': 'SA, A, H, T',
    'manageBilling': 'SA, A',
    'reviewFeedback': 'SA, A',
    'broadcastNotices': 'SA, A',
    'manageFundraisers': 'SA, A',
    'registerVisitors': 'SA, A, H, T',
    'registerStaff': 'SA, A, H, T',
    'accessHomeownerFunctions': 'SA, A, H',
    'triggerEmergencyPanic': 'SA, A, H, T',
}

# Ordered: the first matching rule names the domain.
DOMAINS = [
    ('Safety', r'safety|silent-assistance|emergency|panic|credential-assistance|muster'),
    ('Security', r'gate-scanner|gate-sensor|access-governance|blocklist|block-list|access-log|occupancy|security|scan'),
    ('Visitors', r'visitor|guest|rsvp|invite|pre-regist'),
    ('Credentials', r'gate-pass|wallet(?!/topup)|credential|nfc|parking|pass'),
    ('People', r'household|renter|director|delegat|access-and-people|staff|residents|users'),
    ('Properties', r'propert|vehicle|anpr|map|boundary|landmark|geofence'),
    ('Billing', r'billing|payment|stripe|invoice|fundrais|donation|checkout|wallet/topup|subscription|coupon|terminal|deals|/p/'),
    ('Communications', r'notification|update|broadcast|chat|guideline|warning|feedback|message|twilio|calendar|community-?event'),
    ('Reporting', r'report|export|spreadsheet|pdf|analytics|overview|query-builder|telemetry'),
    ('Access', r'login|logout|auth|confirm-password|password|token|deactivation|profile|settings'),
]

DEMO_PATTERN = re.compile(
    r'simulat|benchmark|octane|observability|queues?/dispatch|features/(purge|simulate|activate|deactivate)'
    r'|api/v1/pdf|filament|\badmin$|\bportal$|query-builder|large.dataset|sandbox|playground|/demo|seed|ui-kit',
    re.I,
)

# Manual decisions, each with its reason. Matched against "METHOD uri".
OVERRIDES = [
    (r'wallet/simulate-nfc-tap', 'REBUILD', 'Simulated NFC tap; rebuild as an authenticated gate-device API'),
    (r'gate-sensor/(sequence|resolve|recent)', 'REBUILD', 'Sensor readings are simulated; rebuild as an authenticated gate-device API'),
    (r'vehicles/anpr[-/]lookup', 'KEEP', 'Used by the gate scanner and the Vehicles page; anpr-lookup/anpr/lookup are aliases (see Duplicate)'),
    (r'wallet/(apple|google|samsung)-pass', 'REBUILD', 'Wallet passes are not signed with real Apple/Google/Samsung credentials'),
    (r'api/tenant', 'REBUILD', 'Tenancy is half-wired (TenancyModuleTest fails); finish or remove'),
    (r'emergency/panic', 'MERGE', 'Overlaps Safety & Assistance (silent assistance); no screen calls it'),
    (r'operations/directory', 'MERGE', 'Livewire duplicate of /dashboard/directory'),
    (r'^(GET|HEAD)\S* (admin|portal|filament)$', 'REMOVE', 'Filament showcase hub with a role simulator; duplicates real pages'),
    (r'query-builder', 'REMOVE', 'Developer showcase, not an estate feature'),
    (r'api/v1/(octane|observability|queues|features)', 'REMOVE', 'Platform showcase endpoints; widen the attack surface'),
    (r'api/v1/pdf', 'MERGE', 'Generic PDF generator; keep only the invoice/receipt documents billing needs'),
    (r'credential-assistance', 'MERGE', 'Fourth credential screen; fold into one My Credential area'),
]

ROUTE_PARAM = re.compile(r'\{[^}]+\}')


def read(path):
    try:
        with open(path, encoding='utf-8', errors='ignore') as f:
            return f.read()
    except OSError:
        return ''


def files_under(rel, exts):
    base = os.path.join(APP, rel)
    for dirpath, _, names in os.walk(base):
        if any(x in dirpath for x in ('node_modules', 'vendor')):
            continue
        for n in names:
            if n.endswith(exts):
                yield os.path.join(dirpath, n)


# ── Callers: path literals and route('name') uses ───────────────────────────
LITERAL = re.compile(r"""['"`](/(?:dashboard|api|p|rsvp|guest|portal|admin|filament|operations|login|logout|webhooks|docs)[^'"`\s?#]*)""")
NAMED = re.compile(r"""route\(\s*['"]([\w.\-]+)['"]""")


def collect_callers(paths):
    literals, names = set(), set()
    for p in paths:
        src = read(p)
        for m in LITERAL.findall(src):
            literals.add(re.sub(r'\$\{[^}]*\}', '§', m).rstrip('/') or '/')
        names.update(NAMED.findall(src))
    return literals, names


ui_literals, ui_names = collect_callers(
    list(files_under('resources/js', ('.tsx', '.ts'))) + list(files_under('resources/views', ('.php',))) + list(files_under('app/Livewire', ('.php',)))
)
test_literals, test_names = collect_callers(list(files_under('tests', ('.php',))))
# Server-side callers: redirect and return URLs built with route(), such as a
# payment provider's success and cancel pages.
server_literals, server_names = collect_callers(
    [p for p in files_under('app', ('.php',)) if os.sep + 'Livewire' + os.sep not in p]
)


def uri_pattern(uri):
    escaped = re.escape('/' + uri.strip('/'))
    escaped = re.sub(r'\\\{[^}]+\\\}', '[^/]+', escaped)
    return re.compile('^' + escaped + '$')


def called(uri, name, literals, names):
    if name and name in names:
        return True
    pat = uri_pattern(uri)
    for lit in literals:
        probe = lit.replace('§', 'x')
        if pat.match(probe):
            return True
        # A template literal such as `/dashboard/visitors/${id}/extend`.
        if '§' in lit and re.fullmatch(re.escape(lit).replace('§', '[^/]+'), '/' + uri.strip('/').replace('{', '').replace('}', '')):
            return True
    return False


# ── Services injected into each controller ──────────────────────────────────
USE_LINE = re.compile(r'^use (App\\[\w\\]+);', re.M)


def controller_services(action):
    if '@' in action:
        cls, method = action.split('@', 1)
    else:
        cls, method = action, '__invoke'
    if not cls.startswith('App\\'):
        return '', method, ''
    path = os.path.join(APP, 'app', *cls[4:].split('\\')) + '.php'
    src = read(path)
    services = sorted({u.split('\\')[-1] for u in USE_LINE.findall(src) if '\\Services\\' in u or '\\Actions\\' in u})
    return os.path.relpath(path, APP).replace('\\', '/'), method, '; '.join(services)


def domain_of(text):
    for name, pat in DOMAINS:
        if re.search(pat, text, re.I):
            return name
    return 'Administration'


def permission_of(middleware):
    gates = [m.split(':', 1)[1] for m in middleware if m.startswith('Illuminate\\Auth\\Middleware\\Authorize:')]
    if gates:
        return ', '.join(gates), '; '.join(GATE_ROLES.get(g.split(',')[0], '?') for g in gates)
    if any('Authenticate' in m for m in middleware) or any('sanctum' in m.lower() for m in middleware):
        return 'signed-in (check in controller)', 'any signed-in'
    if any('ValidateSignature' in m for m in middleware):
        return 'signed URL', 'link holder'
    return 'public', 'anyone'


# ── Routes ─────────────────────────────────────────────────────────────────
with open(ROUTES_JSON, encoding='utf-8-sig') as f:
    routes = json.load(f)

action_counts = Counter(r['action'] for r in routes if r.get('action') and 'Closure' not in r['action'])

rows = []
for r in routes:
    method = r['method'].replace('|HEAD', '')
    uri = r['uri']
    name = r.get('name') or ''
    action = r.get('action') or 'Closure'
    middleware = r.get('middleware') or []
    key = f'{method} {uri}'
    text = f'{uri} {name} {action}'

    controller, cmethod, services = controller_services(action)
    permission, roles = permission_of(middleware)
    is_api = uri.startswith('api/') or 'webhook' in uri
    used_ui = called(uri, name, ui_literals, ui_names)
    used_tests = called(uri, name, test_literals, test_names)
    used_server = called(uri, name, server_literals, server_names)
    used_ui = used_ui or used_server
    demo = bool(DEMO_PATTERN.search(text))
    duplicate = action_counts.get(action, 0) > 1

    classification, basis = None, None
    for pat, cls, why in OVERRIDES:
        if re.search(pat, key, re.I):
            classification, basis = cls, 'manual: ' + why
            break
    if classification is None:
        if demo:
            classification, basis = 'REMOVE', 'heuristic: demo/showcase route'
        elif duplicate:
            classification, basis = 'MERGE', f'heuristic: {action} is bound to {action_counts[action]} routes'
        elif not used_ui and not used_tests and not is_api and method != 'GET':
            classification, basis = 'REMOVE', 'heuristic: no caller in the UI or tests (verify before removing)'
        elif not used_ui and not is_api and method == 'GET' and not used_tests:
            classification, basis = 'REMOVE', 'heuristic: page/endpoint no screen or test reaches (verify)'
        else:
            classification, basis = 'KEEP', 'heuristic: reachable and not flagged'

    status = 'Demo' if demo else ('External API' if is_api else 'Production')
    used = 'UI' if used_ui else ('External' if is_api else ('Tests only' if used_tests else 'No caller found'))

    rows.append({
        'Route': key,
        'Name': name,
        'Purpose (domain)': domain_of(text),
        'Role': roles,
        'Permission': permission,
        'Controller': controller or action,
        'Method': cmethod,
        'Services/Actions': services,
        'Status': status,
        'Used?': used,
        'Duplicate?': 'Yes' if duplicate else '',
        'Demo?': 'Yes' if demo else '',
        'Production?': 'No' if demo else 'Yes',
        'Classification': classification,
        'Basis': basis,
    })

rows.sort(key=lambda x: (x['Purpose (domain)'], x['Route']))
with open(os.path.join(OUT, 'route-inventory.csv'), 'w', newline='', encoding='utf-8') as f:
    w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
    w.writeheader()
    w.writerows(rows)

# ── Services ────────────────────────────────────────────────────────────────
all_php = list(files_under('app', ('.php',))) + list(files_under('routes', ('.php',))) + list(files_under('database', ('.php',)))
corpus = {p: read(p) for p in all_php}
test_corpus = '\n'.join(read(p) for p in files_under('tests', ('.php',)))


def refs(short, own_path):
    pat = re.compile(r'\b' + re.escape(short) + r'\b')
    return sum(1 for p, src in corpus.items() if p != own_path and pat.search(src))


svc_rows = []
for path in sorted(files_under('app/Services', ('.php',))):
    src = read(path)
    short = os.path.splitext(os.path.basename(path))[0]
    loc = src.count('\n') + 1
    public = len(re.findall(r'\n\s+public function (?!__construct)', src))
    n_refs = refs(short, path)
    n_tests = len(re.findall(r'\b' + re.escape(short) + r'\b', test_corpus))
    rel = os.path.relpath(path, APP).replace('\\', '/')

    # What the class really is. app/Services holds contracts, data objects and
    # drivers as well as services; only the last kind is a "service".
    if re.search(r'^\s*interface\s+\w+', src, re.M) or '/Contracts/' in rel:
        kind = 'Contract'
    elif re.search(r'^\s*enum\s+\w+', src, re.M):
        kind = 'Enum'
    elif '/DTOs/' in rel or re.search(r'(Request|Result|Payload|Message|Instruction|NotSent|Number)$', short) and public <= 2:
        kind = 'Data object'
    elif re.search(r'/(Drivers|Channels|Providers)/', rel):
        kind = 'Driver/Channel'
    elif re.search(r'^\s*(final\s+)?class\s+\w+\s+extends\s+\w*(Exception|Error)\b', src, re.M):
        kind = 'Exception'
    else:
        kind = 'Service'

    flags = []
    if n_refs == 0:
        flags.append('Unused in app')
    if kind == 'Service' and public <= 2 and loc < 80:
        flags.append('Thin')
    if loc > 600:
        flags.append('Large')
    if 'Unused in app' in flags:
        rec = 'REMOVE' if n_tests == 0 else 'REMOVE (tests only)'
    elif 'Large' in flags:
        rec = 'SPLIT'
    elif 'Thin' in flags:
        rec = 'MERGE into domain service'
    elif kind in ('Data object', 'Contract', 'Enum', 'Exception'):
        rec = 'MOVE out of Services'
    else:
        rec = 'KEEP'
    svc_rows.append({
        'Service': short,
        'Kind': kind,
        'Path': rel,
        'Domain': domain_of(rel + ' ' + short.replace('Service', '')),
        'LOC': loc,
        'Public methods': public,
        'App references': n_refs,
        'Test references': n_tests,
        'Flags': ', '.join(flags),
        'Recommendation': rec,
    })

with open(os.path.join(OUT, 'service-inventory.csv'), 'w', newline='', encoding='utf-8') as f:
    w = csv.DictWriter(f, fieldnames=list(svc_rows[0].keys()))
    w.writeheader()
    w.writerows(svc_rows)

# ── Controllers ─────────────────────────────────────────────────────────────
QUERY = re.compile(r'::(where|query|find|findOrFail|create|firstOrCreate|updateOrCreate|with|whereIn|count|all)\(|DB::|->where\(|->update\(|->delete\(')
ctl_rows = []
for path in sorted(files_under('app/Http/Controllers', ('.php',))):
    src = read(path)
    loc = src.count('\n') + 1
    methods = len(re.findall(r'\n\s+public function (?!__construct)', src))
    queries = len(QUERY.findall(src))
    inline_validation = src.count('->validate([')
    form_requests = len(re.findall(r'\(\s*\w+Request \$', src))
    flags = []
    if loc > 400:
        flags.append('Fat (>400 LOC)')
    if queries > 25:
        flags.append(f'Query-heavy ({queries})')
    if inline_validation:
        flags.append(f'Inline validation x{inline_validation}')
    ctl_rows.append({
        'Controller': os.path.relpath(path, os.path.join(APP, 'app/Http/Controllers')).replace('\\', '/'),
        'LOC': loc,
        'Public methods': methods,
        'LOC per method': round(loc / methods) if methods else loc,
        'Query calls': queries,
        'Inline validate()': inline_validation,
        'FormRequests used': form_requests,
        'Flags': ', '.join(flags),
        'Recommendation': 'THIN OUT' if ('Fat (>400 LOC)' in flags or queries > 25) else 'KEEP',
    })

with open(os.path.join(OUT, 'controller-inventory.csv'), 'w', newline='', encoding='utf-8') as f:
    w = csv.DictWriter(f, fieldnames=list(ctl_rows[0].keys()))
    w.writeheader()
    w.writerows(ctl_rows)

# ── Models ──────────────────────────────────────────────────────────────────
mdl_rows = []
for path in sorted(files_under('app/Models', ('.php',))):
    short = os.path.splitext(os.path.basename(path))[0]
    n_refs = refs(short, path)
    mdl_rows.append({
        'Model': short,
        'Domain': domain_of(short),
        'App references': n_refs,
        'Recommendation': 'REVIEW (unused)' if n_refs == 0 else 'KEEP',
    })

with open(os.path.join(OUT, 'model-inventory.csv'), 'w', newline='', encoding='utf-8') as f:
    w = csv.DictWriter(f, fieldnames=list(mdl_rows[0].keys()))
    w.writeheader()
    w.writerows(mdl_rows)

# ── Summary ─────────────────────────────────────────────────────────────────
summary = {
    'routes': len(rows),
    'route_classification': Counter(r['Classification'] for r in rows),
    'route_domains': Counter(r['Purpose (domain)'] for r in rows),
    'route_status': Counter(r['Status'] for r in rows),
    'route_used': Counter(r['Used?'] for r in rows),
    'services': len(svc_rows),
    'service_recommendation': Counter(s['Recommendation'] for s in svc_rows),
    'service_kinds': Counter(s['Kind'] for s in svc_rows),
    'service_domains': Counter(s['Domain'] for s in svc_rows),
    'controllers': len(ctl_rows),
    'controller_recommendation': Counter(c['Recommendation'] for c in ctl_rows),
    'inline_validate_total': sum(c['Inline validate()'] for c in ctl_rows),
    'models': len(mdl_rows),
    'models_unused': sum(1 for m in mdl_rows if m['App references'] == 0),
}
print(json.dumps(summary, indent=2, default=dict))
