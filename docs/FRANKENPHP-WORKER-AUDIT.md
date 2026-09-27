# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/php-quality-tools` (`composer-plugin`) |
| Audited revision | `v1.0.17` / `9911447` |
| Audit date | 2026-09-23 |
| Method | Manual review of every file under `src/` (`Plugin.php`, custom Rector rules and set, custom PHP-CS-Fixer fixers and set) |
| **Verdict** | — **Not applicable** — Composer plugin that installs Rector / PHP-CS-Fixer / Twig-CS-Fixer config files; the custom rules only run inside the Rector and PHP-CS-Fixer CLI processes. No Symfony bundle, no container services, nothing runs inside the HTTP worker |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Why it does not run in the worker

- `composer.json` declares `"type": "composer-plugin"` with `extra.class = NowoTech\PhpQualityTools\Plugin` and only requires `php` and `composer-plugin-api`. There is no `Bundle` class, DI extension, service config, route, listener or Twig extension.
- `src/Plugin.php` is instantiated by Composer. It reacts to `post-install-cmd` / `post-update-cmd` (`src/Plugin.php:131-165`): copies config files, suggests missing dev dependencies (optionally running `composer require --dev` through `exec()`, `:361-369`) and adds Composer scripts. `uninstall()` only prints a message (`:121-124`).
- `src/Rector/Rules/*` extend `Rector\Rector\AbstractRector` and `src/PhpCsFixer/Rules/*` implement PHP-CS-Fixer fixers. They are wired through `CustomRulesSet::getRules()` / `CustomFixersSet::getFixers()` from `.rector.php` / `.php-cs-fixer.php`, i.e. only inside those CLI tools. Their `rector/rector` and `friendsofphp/php-cs-fixer` dependencies are dev-only.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ N/A | No container services. `Plugin` keeps `$composer` / `$io` set in `activate()` (`src/Plugin.php:99-103`) inside the Composer process |
| Static properties / `static` locals | ✅ | None. `CustomRulesSet` and `CustomFixersSet` only have pure static methods; rules/fixers keep no mutable properties |
| `ResetInterface` / `kernel.reset` coverage | ✅ N/A | Nothing to reset |
| Request / user / locale captured in services | ✅ N/A | No HTTP code |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used |
| Doctrine / EntityManager | ✅ N/A | No persistence |
| Output, headers, `exit`, shutdown functions | ✅ N/A | Output through Composer's `IOInterface`; `CustomRulesSet::reportMissingDependencies()` writes to `STDERR` on CLI or `trigger_error()` otherwise (`src/Rector/Set/CustomRulesSet.php:99-125`) |
| Resources (files, sockets, cURL) held open | ✅ N/A | One-shot file copies during Composer runs |
| Memory growth across requests | ✅ N/A | Short-lived Composer / Rector / PHP-CS-Fixer processes |
| Blocking I/O and timeouts | ✅ N/A | `exec('composer require …')` only during an interactive Composer run |
| Third-party static state | ✅ N/A | Rector / PHP-CS-Fixer internals, CLI only |
| PHPStan FrankenPHP rulesets | ✅ | `extension.neon`, `ruleset-classic.neon` and `ruleset-worker.neon` included in `phpstan.neon.dist` |

Worker demo: none (no `demo/` directory; expected for a Composer plugin).

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| — (no Symfony services) | — | — | N/A | N/A |
| `NowoTech\PhpQualityTools\Plugin` (Composer plugin, not a Symfony service) | Composer process only | `$composer`, `$io` set in `activate()` | N/A | N/A |
| 5 Rector rules + `CustomRulesSet`, 3 PHP-CS-Fixer fixers + `CustomFixersSet` | CLI tools only | none | N/A | N/A |

## Findings

No worker-mode findings: the package never executes inside the HTTP worker.

### W-01 — Plugin may spawn `composer require` from a Composer hook (Info)

- **Where:** `src/Plugin.php:330-369` (`installDependencies()` → `exec()`), reached only when Composer runs interactively and the user confirms.
- **Worker impact:** none; it runs in the developer's Composer process, never in a web request. Mentioned only because it is the one place that starts a child process.
- **Recommendation:** in Docker images for FrankenPHP, run `composer install --no-dev --no-interaction` so the plugin (a dev dependency) is not installed and never prompts.

## Usage recommendations in worker mode

- Install it as a development dependency; it has no effect on FrankenPHP workers.
- Do not reference the Rector rules, fixers or `Plugin` from application code; they depend on dev-only packages.
- Generated `.rector.php` / `.php-cs-fixer.php` files belong to the project root, not to `public/`.

## Re-audit triggers

Re-run this audit if the package gains a Symfony bundle class, a DI extension, runtime (non-dev) dependencies, or PHP classes meant to be called during HTTP requests.
