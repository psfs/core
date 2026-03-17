# PSFS Versioning Policy

## Current baseline

- Latest tagged release: `2.2.0` (next patch prepared: `2.2.1`)
- Composer package version: `2.2.0`
- Branch alias: `dev-master -> 2.2.x-dev`

## Versioning model

PSFS follows Semantic Versioning:

- `MAJOR.MINOR.PATCH`
- `MAJOR`: breaking changes in public contracts
- `MINOR`: backward-compatible features/improvements
- `PATCH`: backward-compatible fixes/refactors

Examples:

- `2.2.1` patch release
- `2.3.0` minor release
- `3.0.0` breaking major release

## Rules for bumping

1. Patch (`2.2.x`)
- Bug fixes
- Internal refactors without public contract break
- Security fixes compatible with current APIs

2. Minor (`2.x.0`)
- New features with backward compatibility
- New optional metadata/attributes support

3. Major (`x.0.0`)
- Route/signature/contract changes marked as public
- Removal of legacy fallback behavior

## Release workflow (recommended)

All commands should run in Docker:

```bash
docker exec core-php-1 php vendor/bin/phpunit
```

### Patch release example (`2.2.1`)

1. Update `composer.json`:
- `"version": "2.2.1"`
- `"extra.branch-alias.dev-master": "2.2.x-dev"` (keep on patch lane)

2. Validate:

```bash
docker exec core-php-1 php vendor/bin/phpunit
```

3. Commit and tag:

```bash
git add composer.json
git commit -m "chore(release): 2.2.1"
git tag 2.2.1
git push origin master --tags
```

### Minor release example (`2.3.0`)

1. Update `composer.json`:
- `"version": "2.3.0"`
- `"extra.branch-alias.dev-master": "2.3.x-dev"`

2. Validate and tag as above.

## Notes

- Keep version and tag synchronized.
- Avoid removing legacy compatibility in patch/minor unless explicitly approved.
- Public contract changes require major bump and migration notes.
