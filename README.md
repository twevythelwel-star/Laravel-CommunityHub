# Community Hub

A gated-community management platform: resident gate passes with signed QR
tokens, visitor pre-registration, a security access log, a geofenced
community map, billing, fundraising, notices and safety alerts.

**The application lives in [`laravel/`](laravel/)** (Laravel 12, Inertia and
React). Setup, architecture, security notes and deployment are in
[`laravel/README.md`](laravel/README.md).

## Repository layout

| Path | What it is |
|---|---|
| `laravel/` | The application |
| `.github/workflows/ci.yml` | Tests, type check, build, style and dependency audits on every push and pull request |
| `.github/agents/`, `.agents/`, `AGENTS.md` | AI agent personas and rules for this workspace |
| `docs/blueprint.md` | The original product blueprint |

## The original Next.js app

This project started as a Next.js 15 / Firebase Studio app with no backend.
It was converted to Laravel, and the Next.js source was then removed from
the working tree. It is preserved in git history: check out `f3c48c6`, the
last commit before the conversion, to run it or compare screens.

```bash
git checkout f3c48c6
```
