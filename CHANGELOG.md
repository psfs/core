# Changelog

All notable changes to this project are documented in this file.

## [2.2.1] - 2026-03-17

### Changed
- Removed failing GitHub Actions workflow `secret-scan` for current release cycle.
- Updated Composer `branch-alias` to keep development aligned with patch lane `2.2.x-dev`.
- Added API metadata analysis documentation:
  - `doc/API_ANNOTATIONS_ANALYSIS.md`
- Added formal versioning policy:
  - `doc/VERSIONING.md`

### Notes
- Legacy compatibility strategy remains unchanged.
- Release/tag policy is based on Git tags + Composer branch alias.
