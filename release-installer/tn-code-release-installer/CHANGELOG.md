# Changelog

All notable changes to TN Code Release Installer are recorded here.

## 0.4.2 - 2026-08-15

- Accepts valid ZIP uploads when a host reports a non-standard ZIP MIME type, while retaining extension, archive, manifest, path, and checksum validation.

## 0.4.1 - 2026-08-15

- Synchronized the companion installer release with TN WP Migrate Code Diff 0.4.1.

## 0.4.0 - 2026-08-15

- Added the Upload Release admin page.
- Added validation for TN code release manifests, archive paths, package destinations, and SHA-256 file checksums.
- Added rollback-safe installation of included plugins, themes, and must-use plugins without changing activation state.
- Added native GitHub release update support.
