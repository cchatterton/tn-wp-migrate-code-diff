# Changelog

All notable changes to TN WP Migrate Code Diff are recorded here.

## 0.4.1 - 2026-08-15

- Uses the exact code package selections from the active saved WP Migrate profile as the initial comparison selection.
- Keeps Recommended available as an explicit reset to the smart version and activation rules after the comparison opens.

## 0.4.0 - 2026-08-15

- Added a source-only “Create release package” action to the Code Comparison page.
- Added a versioned release manifest with package destinations and SHA-256 checksums.
- Added the companion TN Code Release Installer with an Upload Release admin page, safe archive validation, rollback during installation, and native GitHub updates.
- Preserves database, media, plugin activation, and theme activation settings during manual code deployment.

## 0.3.2 - 2026-08-15

- Leaves source-only packages unselected when they are inactive on the source.

## 0.3.1 - 2026-08-15

- Leaves different-version packages inactive on the source unselected in the initial and Recommended selections.

## 0.3.0 - 2026-08-15

- Added active Compare Code and Compare Database/Images choices with separate report pages.
- Added a Database / Images report comparing table presence, estimated rows, sizes, upload locations, and Media migration readiness.
- Preserves selected multisite scope and Push/Pull direction in both comparison modes.
- Renamed “New on source” to “Absent on Destination”.
- Simplified all active plugin and theme states to “Active” because remote multisite activation scope is not exact.
- Added a Recommended section toggle that restores the smart initial code selections.
- Made each code section collapsible and added live per-section and total release selection counts.

## 0.2.9 - 2026-08-15

- Leaves source package downgrades unselected by default while continuing to select upgrades and source-only packages.
- Added Select all and Deselect all controls to each code package section.
- Defaults profile names to a chronological `Release-YYYYMMDD-HHMM` identifier.
- Shows distinct Compare Code and unavailable Compare Database choices without mixing the two modes.
- Fixed generated profiles retaining stale direction and incomplete connection and Multisite Tools state.

## 0.2.8 - 2026-08-15

- Fixed Compare Now appearing before a required multisite subsite was selected.
- Detects WP Migrate's rendered multisite selects by structure as well as by ID.
- Re-checks comparison readiness when a multisite select changes.

## 0.2.7 - 2026-08-15

- Added WP Migrate 2.7.8 DOM fallbacks for detecting the active connection string and Push/Pull direction.
- Detects selected source and destination subsites when the lightweight Redux store omits Multisite Tools state.
- Re-renders the listener notice when connection and migration controls change.

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
