# Changelog

All notable changes to TN WP Migrate Code Diff are recorded here.

## 0.2.6 - 2026-08-15

- Added spacing below the Code Diff notice on the WP Migrate screen.

## 0.2.5 - 2026-08-15

- Fixed the listener notice removing itself when WP Migrate's active screen did not expose the expected URL hash route.
- Uses the WP Migrate-only PHP mount and live migration intent instead of browser hash routing.
- Positions the visible notice immediately after WP Migrate's own update notice instead of above the application header.

## 0.2.4 - 2026-08-15

- Fixed WP Migrate's loading screen hanging when Code Diff was active.
- Moved the listener notice outside WP Migrate's React-owned root using the `wpmdb_notices` hook.
- Removed the document-wide mutation observer that could repeatedly conflict with React rendering.
- Added bounded startup polling while retaining live Redux subscriptions for connection and multisite changes.

## 0.2.3 - 2026-08-15

- Added the standards-compliant public GitHub release configuration.
- Added native WordPress update discovery and installation from the exact release ZIP asset.
- Added Plugins-screen “GitHub” and nonce-protected “Check for updates” links.
- Added the native “View details” modal using GitHub release notes.
- Added short-lived release caching, separate failure diagnostics, forced-check handling, and post-upgrade cache clearing.

## 0.2.2 - 2026-08-15

- Added an always-visible listener status on WP Migrate's Migrate route for direct integration debugging.
- Added explicit waiting states for WP Migrate state, connection, and required subsite selection.
- Loads the small scoped integration listener across WordPress admin screens, then activates only when WP Migrate's DOM is present.

## 0.2.1 - 2026-08-15

- Fixed notice detection on WP Migrate 2.7.8 by accepting its populated remote-site and parsed connection state as connected evidence.
- Added screen-hook detection as a fallback for loading integration assets.
- Resubscribes if WP Migrate replaces its Redux store while loading Pro add-ons.

## 0.2.0 - 2026-08-15

- Added a live comparison notice to WP Migrate's Migrate screen.
- Removed the duplicate direction and connection form and the visible plugin menu entry.
- Added automatic comparison from a short-lived snapshot of WP Migrate's active connection.
- Preserved multisite-to-single-site, single-site-to-multisite, network, and selected-subsite profile settings.
- Added selected-site, network-active, aggregate remote-network, inactive, and must-use activation reporting.
- Kept activation differences informational because a code-only deployment does not migrate activation options.

## 0.1.0 - 2026-08-15

- Added signed WP Migrate connection reuse for code inventory comparison.
- Added plugin, theme, and must-use plugin version and presence reporting.
- Added code-only WP Migrate profile generation with database and media disabled.
