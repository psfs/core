# Assets Contracts (Twig + Pipeline + Atomic I/O)

## Scope
- `src/base/extension/AssetsNode.php`
- `src/base/extension/AssetsParser.php`
- `src/base/extension/TemplateFunctions.php`
- `src/base/extension/traits/CssTrait.php`
- `src/base/extension/traits/JsTrait.php`
- `src/base/types/helpers/FileHelper.php` (atomic helpers used by assets flow)

## Runtime Contracts
1. `AssetsNode::compile()`
- Always emits parser pipeline in order: `new AssetsParser` -> `setHash` -> `init` -> `addFile*` -> `compile` -> `printHtml`.
- Must preserve input script/style sequence.
- Hash identity must be unique per assets block (`template path + type + line`) to avoid bundle collisions inside the same template.

2. `AssetsParser::addFile()`
- Adds file only when extension matches parser type (`js` or `css`).
- Ignores invalid/missing paths silently.

3. `AssetsParser::setHash()`
- Output hash is versioned with `cache.var` suffix when available.

4. `AssetsParser::compile()`
- JS: delegates to `JsTrait::compileJs`.
- CSS: delegates to `CssTrait::compileCss`.
- Must deduplicate source files before compile.

5. `AssetsParser::printHtml()`
- `debug=true` and compiled list not empty -> emits one tag per source file.
- Otherwise emits one combined tag (`/js/{hash}.js` or `/css/{hash}.css`).

6. `AssetsParser::extractCssLineResource()`
- Copies `url(...)` static resources to document root preserving relative path below `public`.
- Missing origin must not crash request flow; it is treated as non-fatal.

7. `TemplateFunctions::resource()`
- In non-debug mode, maintains copy cache list (`{cache.var}.file.cache`) and supports forced copy.
- Resource copy must stay deterministic and side-effect-safe under concurrent access.

8. `TemplateFunctions::asset()/processAsset()`
- Returns URL/path when source exists and is resolvable by domain mapping.
- Returns empty string when not resolvable.

## Atomic I/O Contracts (Assets Safety)
1. `FileHelper::writeFileAtomic(path, data)`
- Writes via temp file + atomic rename.
- Returns `false` on failure, never throws by default.

2. `FileHelper::copyFileAtomic(source, target)`
- Copies to temp target and atomically renames.
- Returns `false` when source is missing or rename fails.
- Preserves source file mode when possible and keeps target readable by web server processes.

3. `FileHelper::deleteFile(path)`
- Idempotent delete contract: returns `true` when file is already absent.

4. `FileHelper::withExclusiveLock(lockPath, callback)`
- Executes callback under `LOCK_EX` and returns callback result.
- Returns `null` if lock cannot be acquired/opened.

## Compatibility Rules
- No public API signature changes in Twig helpers or parser methods.
- Existing bundle naming and routing behavior must remain compatible.
- Debug and production rendering contracts are preserved.
- Legacy fallback behavior remains active.

## Test Evidence
- `tests/base/extension/TemplateFunctionsTest.php`
- `tests/base/extension/AssetsParserTest.php`
- `tests/base/extension/AssetsNodeTest.php`
- `tests/base/type/helper/FileHelperTest.php`
- `tests/base/TemplateTest.php`
- `tests/base/type/helper/GeneratorHelperTest.php`
