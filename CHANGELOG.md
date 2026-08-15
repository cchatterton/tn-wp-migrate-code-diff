# Changelog

All notable changes to TN WP Migrate Code Diff are recorded here.

## 0.8.6 - 2026-08-15

- Separates release-package preparation from the browser download.
- Returns structured package-creation errors through WordPress AJAX.
- Downloads a successfully prepared ZIP through a short-lived, authenticated URL instead of handling ZIP bytes in JavaScript.

## 0.8.5 - 2026-08-15

- Loads the WordPress file API before creating release or rollback temporary ZIP files.
- Fixes the fatal `wp_tempnam()` error on `admin-post.php` package requests.

## 0.8.4 - 2026-08-15

- Removes the global release-history schema check from `admin_init`.
- Initializes release-history storage only on activation, Upload Release processing, or when Release Notes is opened.
- Keeps comparison and Create release package requests completely independent of release-history storage.

## 0.8.3 - 2026-08-15

- Simplifies the plugin-row actions to WP Migrate and Upload Release alongside WordPress native actions.
- Keeps Release Notes available from Settings without duplicating it in the plugin row.

## 0.8.2 - 2026-08-15

- Writes release history only on the receiving site through Upload Release.
- Keeps source creator/site and version metadata in the ZIP manifest without writing a source-side history record during package creation.
- Removes the release-history database operation from Create release package.

## 0.8.1 - 2026-08-15

- Limits default destination-only inactive removal selections to plugins.
- Keeps destination-only themes and must-use plugins available for manual selection but unselected by default.

## 0.8.0 - 2026-08-15

- Adds a network-aware custom release history table created on activation or upgrade.
- Adds Settings → Release Notes with package creator/source, deployment user/destination, rollback status, event timestamps, and added/updated/removed package details.
- Embeds the source package creator, destination site, and before/after versions in new release manifests without storing connection secrets.
- Records package creation, rollback creation, and successful installation events.
- Removes internal plugin paths from the visible comparison table while retaining them for package operations.

## 0.7.0 - 2026-08-15

- Enables destination-only packages as selectable removal operations in manual releases.
- Selects inactive destination-only packages by default while leaving active destination-only packages unselected.
- Adds validated forward removal instructions to the release manifest.
- Includes complete destination files for selected removals in the rollback package so rollback restores them.
- Keeps all removal destinations constrained to safe WordPress code paths and prevents self-removal.

## 0.6.3 - 2026-08-15

- Adds in-progress spinners and busy labels to Create release package, Create Rollback, and Install Release.
- Uses managed downloads so release and rollback buttons recover correctly after completion or failure.
- Raises the WordPress admin memory limit and removes the PHP execution deadline for package operations where the host permits it.

## 0.6.2 - 2026-08-15

- Splits Different version into amber Source is Newer and red Source is Older statuses.
- Keeps active source upgrades recommended and source downgrades unselected.

## 0.6.1 - 2026-08-15

- Splits the manual upload workflow into independent Create Rollback and Install Release actions.
- Repairs incomplete Media and Search & Replace state in previously generated release profiles so WP Migrate can render reopened profiles correctly.
- Leaves must-use plugins unselected in the initial and Recommended comparison selections while preserving explicit saved-profile selections.
- Removes explanatory narrative from the code comparison summary and release controls.

## 0.6.0 - 2026-08-15

- Generates and downloads a reusable `<release-name>-rollback.zip` before installing a normal manual release.
- Includes complete existing files for packages being replaced, without the source-release development-file exclusions.
- Records packages introduced by the incoming release as validated removal operations in the rollback manifest.
- Restores replaced packages and removes newly introduced packages when the rollback ZIP is uploaded.
- Prevents rollback releases from generating nested rollback packages.
- Stops installation before changing files if the required rollback package cannot be created and validated.
- Refreshes an open comparison from its current comparison token instead of relying on the shorter-lived entry-page token.

## 0.5.2 - 2026-08-15

- Completely hides Database/Images comparison from the WP Migrate interface.
- Retains its scripts, report, routes, and backend implementation behind the `TWMCD_DATABASE_COMPARISON_ENABLED` feature flag for future reactivation.

## 0.5.1 - 2026-08-15

- Split Save release profile, Open profile, and Create release package into three independent comparison-page actions.
- Added an in-place Refresh comparison action.
- Preserves the comparison context after saving or refreshing so the other release actions remain available.
- Repairs the complete WP Migrate Multisite Tools state on legacy generated `Release-YYYYMMDD-HHMM` profiles.
- Temporarily disabled Database/Images comparison in both the UI and AJAX handlers.

## 0.5.0 - 2026-08-15

- Integrated Upload Release, archive validation, checksum verification, installation, and rollback into the main plugin.
- Removed the separate TN Code Release Installer plugin and release asset.
- Allows the same single plugin installation to create or receive manual code releases in either site role.
- Excludes this plugin from its own code comparison and manual release payload.

## 0.4.2 - 2026-08-15

- Synchronized the comparison plugin release with the companion installer ZIP upload compatibility fix.

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
