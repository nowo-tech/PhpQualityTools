# Feature Specification: Front-end and docs gates

**Feature Branch**: `main`  
**Created**: 2026-10-17  
**Status**: Released in 1.6.0  
**Input**: Harvest three reusable quality gates and one Makefile target from the Symfony Beacon app so every app of the "other" family adopts them instead of copying Beacon's scripts: `tests/Unit/Shared/NoInlineSvgTemplatesTest.php` (OTHER REQ-ICON-001), `scripts/check-asset-budgets.mjs` and the `php-worker-reset` Makefile target (OTHER REQ-ASSETS-APP-002, item 7), `scripts/check-doc-links.mjs` (OTHER REQ-DOCS-APP-001).

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#front-end-and-docs-gates-since-160), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-160)

---

## User Scenarios & Testing

### User Story 1 — One icon source (Priority: P1)

As a maintainer, `make qa` fails when someone pastes an inline `<svg>` into a Twig template or builds SVG markup in a Stimulus controller, so every icon goes through the icon component.

**Acceptance Scenarios**:

1. **Given** `templates/a.html.twig` with `<svg>` on line 2, **When** `check-no-inline-svg`, **Then** exit 1 and `error: inline <svg> in templates/a.html.twig:2 (…)` on stderr.
2. **Given** `assets/controllers/x_controller.ts` with `'<path d="…"/>'`, **Then** exit 1; the same string in `x_controller.test.ts`, `*.spec.ts`, `*.d.ts` or `node_modules/` is ignored.
3. **Given** `--allow=templates/bundles/NelmioApiDocBundle/SwaggerUi/index.html.twig` (a `<symbol>` sprite), **Then** that file passes; once the sprite is removed, the allow entry itself fails.

### User Story 2 — Front-end size budget (Priority: P1)

As a maintainer, the release gate fails when the CSS or JS a page loads on every request grows past its budget.

**Acceptance Scenarios**:

1. **Given** a Vite manifest and `budgets: { assets/app.ts: { css: 250kB } }`, **When** the entry plus its static imports weigh 248.5 kB of CSS, **Then** `OK   assets/app.ts css: 248.5 kB (gzip 36.3 kB) / budget 250.0 kB`, exit 0.
2. **Given** a budgeted entry missing from the manifest, **Then** `FAIL <entry>: not in the manifest …`, exit 1.
3. **Given** no build, **Then** exit 1 with "run the production build first"; **given** an invalid config, exit 2.

### User Story 3 — No broken documentation links (Priority: P1)

As a maintainer, a release cannot ship a manual chapter that shows a deleted screenshot or links a renamed file; optionally, renamed headings are caught too.

**Acceptance Scenarios**:

1. **Given** `![Shot](images/missing.webp)` in a tracked Markdown file, **Then** `docs/x.md:3 → images/missing.webp`, exit 1.
2. **Given** link syntax inside fenced or inline code, a URL, or a target above the repository root, **Then** it is skipped.
3. **Given** `--check-anchors` and `[x](USAGE.md#nope)` where `USAGE.md` has no heading slug, HTML anchor or `{#id}` attribute `nope`, **Then** `… (no such anchor)`, exit 1.

### User Story 4 — Fresh workers after a build (Priority: P2)

As a maintainer running FrankenPHP worker mode in Docker Compose, `make vite-build` / `make asset-budgets` restarts the PHP workers of the dev stack (and of the E2E stack when it is up) and waits until they are healthy.

---

## Requirements

- **FR-SVG-001**: `bin/check-no-inline-svg` (`Gate\Svg\NoInlineSvgCommand`). Templates (`--templates`, default `templates`; `--template-ext`, default `.twig`) fail on `/<svg[\s>\/]/i`; scripts (`--scripts`, default `assets`; `--script-ext`, default `.ts`) fail on `/<(svg|path|circle|rect|polyline|polygon)[\s>\/]/i`. Skips `node_modules/`, `*.test.*`, `*.spec.*`, `*.d.ts`. Reports `file:line` of the first match per file. `--no-scripts`; `--root`.
- **FR-SVG-002**: `--allow=<path>` (root-relative, repeatable) exempts a file; an allowed file that is not scanned or no longer matches is a violation (no stale allowlist).
- **FR-SVG-003**: Exit 0 (`OK: no inline SVG (N template(s), M script(s), K allowed).`), 1 violations (stderr), 2 usage (unknown / empty option, empty suffix list, explicit directory or root missing). Missing default directories are skipped.
- **FR-AB-001**: `bin/check-asset-budgets` (`Gate\Assets\AssetBudgetCommand`). Budgets from `config/quality/asset-budgets.{yaml,yml,json,php}` or `--config` (`AssetBudgetConfig`: keys `manifest`, `build_dir`, `budgets`; unknown keys rejected) and repeatable `--budget=<entry>:<kind>=<size>` (overrides the config). Kinds `js`, `css`, `js_gzip`, `css_gzip`; sizes integer bytes or number + `B` / `k` / `kB` / `KiB` / `M` / `MB` / `MiB`.
- **FR-AB-002**: `ViteManifest`: entry file + statically imported chunks (`imports`, recursively, each once) for JS (`.js` / `.mjs`), their `css` lists and CSS entry files for CSS; `dynamicImports` ignored; undefined imports and malformed chunks rejected. Manifest default `public/build/.vite/manifest.json`, then `public/build/manifest.json`; build dir default the manifest directory without `.vite/`.
- **FR-AB-003**: Output `OK  ` / `FAIL <entry> <kind>: <raw> (gzip <gz>) / budget <b>` (kB = 1000 bytes, one decimal), summary line. Exit 0 within budget, 1 over budget / entry not in manifest / manifest or built file missing, 2 usage / config / manifest format / gzip budget without ext-zlib.
- **FR-DL-001**: `bin/check-doc-links` (`Gate\DocLinks\DocLinksCommand`). Files: `git ls-files -z --cached --others --exclude-standard -- '*.md'` through `Process\CommandRunnerInterface`; outside a work tree or with `--no-git`, a walk skipping `.git`, `vendor`, `node_modules`, `var`. `--exclude` (path, directory prefix or `fnmatch` glob).
- **FR-DL-002**: Link syntax `[text](target)` / `![alt](target)` with optional `<…>` and `"title"`, one level of nested brackets, every `[` position (badge inside link yields both). Skipped: scheme URLs, `//`, fenced code (``` / ~~~), inline code, targets above the root. `/x` is root-relative; `?query` and `#fragment` ignored for existence; percent-decoding.
- **FR-DL-003**: `--check-anchors`: in-page `#x` and `file.md#x` must match `MarkdownAnchors` of the target: GitHub slugs of ATX and setext headings outside fences (lower case, `[^\p{L}\p{M}\p{N}\p{Pc}\- ]` removed, spaces → `-`, github-slugger de-duplication), HTML `id` / `name` attributes, `{#id}` heading attributes; case-insensitive.
- **FR-DL-004**: Exit 0 (`OK: doc links (N Markdown files, no broken relative link or image).`, `…link, image or anchor).` with anchors), 1 (`Broken relative links / images[ / anchors] (N):` + `file:line → target[ (no such anchor)]` on stderr), 2 usage.
- **FR-MK-001**: `resources/make/php-worker-reset.mk`: target `php-worker-reset`; variables `PHP_WORKER_RESET_COMPOSE` (`docker compose`), `PHP_WORKER_RESET_EXTRA_COMPOSE` (empty), `PHP_WORKER_RESET_SERVICE` (`php`), `PHP_WORKER_RESET_CACHE_CLEAR` (`bin/console cache:clear --no-warmup`), `PHP_WORKER_RESET_TIMEOUT` (`90`). Cache clear (failure ignored) + restart for the main stack and for the extra stack only when its service answers `exec … true`; wait for `healthy` (no healthcheck = healthy) per container, exit 1 on timeout.

### Compatibility

- **FR-COMPAT-001**: Composer plugin and existing gates unchanged; no new `require` (`symfony/yaml` stays a suggestion); MINOR release. `src/Gate/` stays excluded from the Igor audit (CLI-only code).

## Success Criteria

- **SC-001**: 100 % PHP line coverage (`make test-coverage`).
- **SC-002**: `make release-check` passes, including `make docs-links` on this repository.
- **SC-003**: Integration tests run the three real binaries and the Makefile snippet (fake `docker` / compose wrappers on `PATH`).
- **SC-004**: Run read-only against Symfony Beacon: same results as its scripts (481 Markdown files OK, 245 templates / 45 scripts OK with the Swagger UI sprite allowed, the five entries within budget).

## Out of scope

- Adopting the gates in Beacon (follow-up in the app); the three broken `#anchors` `--check-anchors` reports in Beacon's docs.
- Reference-style links (`[x]: path`) and HTML `<img src>` / `<a href>` targets.
