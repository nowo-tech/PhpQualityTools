# Release checklist

Use this checklist when cutting a new version.

## Before releasing

- [ ] All tests pass: `make test`
- [ ] PHPStan passes: `make phpstan`
- [ ] Code style: `make cs-check` (and `make cs-fix` if needed)
- [ ] Update [docs/CHANGELOG.md](CHANGELOG.md): move items from `[Unreleased]` to a new `[X.Y.Z] - YYYY-MM-DD` section
- [ ] Update [docs/UPGRADING.md](UPGRADING.md): add an "Upgrading to X.Y.Z" section if needed
- [ ] Update [README.md](../README.md) compatibility table if applicable

## Releasing (e.g. 1.0.11)

```bash
# 1. Commit release prep (changelog, upgrading, readme)
git add docs/CHANGELOG.md docs/UPGRADING.md README.md
git add -u   # optional: include all modified files (PHPStan fixes, etc.)
git commit -m "chore(release): prepare 1.0.11"

# 2. Create annotated tag (see BRANCHING.md)
git tag -a v1.0.11 -m "Release v1.0.11 - Composer script config paths, docs, 100% PHP coverage, PHP 8.1+, Symfony 6.0-7.4, Laravel 9-11"

# 3. Push branch and tag
git push origin main
git push origin v1.0.11
```

## After pushing

- [ ] Create a GitHub Release from tag `v1.0.11`
- [ ] Copy the relevant CHANGELOG section into the release notes
- [ ] Publish the release

## Tag message convention

Include compatibility in the tag message, for example:

- `Release v1.0.11 - Composer script config paths, docs, 100% PHP coverage, PHP 8.1+, Symfony 6.0-7.4, Laravel 9-11`

See [BRANCHING.md](BRANCHING.md) for branch and tag workflow.
