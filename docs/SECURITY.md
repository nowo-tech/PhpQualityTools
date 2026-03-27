# Security — PHP Quality Tools

## Scope

PHP Quality Tools is a **development-time** meta-package that ships PHP-CS-Fixer, PHPStan, Rector, and related **local** quality tooling. It does not expose HTTP endpoints. It runs as **CLI** in developer machines and CI.

## Attack surface

- **Executed binaries**: PHP scripts and vendor tools run with the same privileges as the user or CI job.
- **Configuration files** (`.php-cs-fixer.php`, `phpstan.neon`, `rector.php`, etc.): Should only be modified by trusted contributors; untrusted config could alter what code is transformed or analyzed.
- **CI integration**: Workflows that run fixers should use pinned versions and trusted checkouts.

## Threats and mitigations

| Threat | Mitigation |
|--------|------------|
| Supply-chain / tampered dependencies | Lock file; `composer audit`; verify checksums in CI. |
| Accidental commit of secrets in autofix | Review diffs; use secret scanning in CI. |
| Path traversal in custom tooling | Follow vendor defaults; do not execute untrusted PHP as part of rules without review. |

## Secrets and cryptography

- **No secrets** should be stored in this repository.
- Reporting contact for vulnerabilities: see **Reporting** below (aligned with `.github/SECURITY.md` policy).

## Logging

- Tools may print file paths. Do not enable debug modes that dump environment variables containing tokens in shared CI logs.

## Dependencies

- Run `composer audit` before releases.
- Document supported PHP versions and upgrade path in `docs/UPGRADING.md`.

## Reporting a vulnerability

**Please do not** report security vulnerabilities through public GitHub issues.

Send details privately to the maintainer (see `composer.json` and `.github/SECURITY.md`), including:

- Type of issue and affected files
- Steps to reproduce and impact
- Optional proof-of-concept (if safe to share privately)

We prefer **English** or **Spanish** for communications.

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | This document (and `.github/SECURITY.md` policy) remain aligned. |
| **`.gitignore` and `.env`** | No committed `.env` with secrets. |
| **No secrets in repo** | No API keys or tokens in tracked config. |
| **Recipe / installer** | N/A or documented; no embedded secrets. |
| **Input / output** | CLI and config parsing only; no remote code execution from untrusted input. |
| **Dependencies** | `composer audit` addressed. |
| **Logging** | CI logs without secrets. |
| **Cryptography** | N/A for core package. |
| **Permissions / exposure** | Tools run locally/CI only. |
| **Limits / DoS** | Large projects may need CI timeouts; document in CONTRIBUTING. |

Record confirmation in the release PR or changelog.
