# TN WP Migrate Code Diff

Author: Techn
Version: 0.4.0
Status: MVP

## Purpose

Compare code packages between two connected WP Migrate Pro sites and create a selective, code-only migration profile.

## Key Features

- Compares plugins, themes, and must-use plugins.
- Provides a separate Database / Images report for table presence, estimated row counts, table sizes, uploads locations, and Media migration readiness.
- Reports same version, different version, new on source, and absent from source.
- Adds a live mode notice to WP Migrate after its direction and connection are configured.
- Offers separate Compare Code and Compare Database/Images actions and report routes.
- Inherits push/pull direction, connection details, and multisite conversion choices from WP Migrate's current on-screen state.
- Reports packages as Active, Inactive, or Not installed; detailed activation scope remains internal because remote multisite scope is not exact.
- Selects active source-only packages and active source upgrades by default, while leaving source downgrades and all inactive source packages unselected.
- Provides per-section Select All, Deselect All, and Recommended controls.
- Defaults generated profile names to a chronological `Release-YYYYMMDD-HHMM` identifier.
- Creates a WP Migrate saved profile with database and media disabled.
- Creates a manual release ZIP from selected local source packages when the comparison direction is Push.
- Provides a companion TN Code Release Installer with a destination-side **Upload Release** page.
- Does not automatically delete destination-only packages.
- Delivers releases through the native WordPress Plugins update interface from public GitHub release assets.

## Folder Structure

- `tn-wp-migrate-code-diff.php`: plugin bootstrap and constants.
- `functions/`: admin, AJAX, comparison, profile, and connection logic.
- `templates/`: WordPress admin page markup.
- `scripts/`: vanilla JavaScript admin behaviour and API calls.
- `styles/`: scoped WordPress admin styles.

## Updates

- Repository: https://github.com/cchatterton/tn-wp-migrate-code-diff
- Release asset: `tn-wp-migrate-code-diff.zip`
- Companion installer asset: `tn-code-release-installer.zip`
- Use “Check for updates” beneath the plugin on the WordPress Plugins screen to bypass stale caches and run WordPress's native update check.
- When a newer release exists, WordPress displays its standard update notice, “View details,” and “update now” action.

## Important Notes

- WP Migrate Pro must be installed and active locally and remotely. The two sites' ordinary themes and plugins are expected to differ; that is what this plugin compares.
- Both sites must run a mutually compatible WP Migrate Pro version, as required by WP Migrate's own signed connection handshake.
- WP Migrate's Themes & Plugins capability must return package inventory.
- This is a package/version comparison, not a file-content or line-by-line diff.
- On a remote multisite, WP Migrate 2.7.8 exposes only a combined active flag, so the report deliberately uses the single label “Active” on both sides.
- Activation is informational. A generated code-only profile transfers selected files and does not change activation/database options.
- Manual release packages are available only for Push comparisons because the package generator must read the source files locally.
- Manual release checksums detect corruption or modification but do not establish publisher identity. Install only packages obtained from a trusted source.
- The WP Migrate secret key is stored only as part of the saved WP Migrate profile, matching WP Migrate's existing profile behaviour.

## Future Considerations

- Add optional package content fingerprints if version metadata proves insufficient.
- Add an optional companion endpoint on the remote site for exact network-vs-subsite activation reporting.
- Add GitHub release updates after a public destination repository and release asset name are declared.
