# TN WP Migrate Code Diff

Author: Techn
Version: 0.2.9
Status: MVP

## Purpose

Compare code packages between two connected WP Migrate Pro sites and create a selective, code-only migration profile.

## Key Features

- Compares plugins, themes, and must-use plugins.
- Reports same version, different version, new on source, and absent from source.
- Adds a live mode notice to WP Migrate after its direction and connection are configured.
- Inherits push/pull direction, connection details, and multisite conversion choices from WP Migrate's current on-screen state.
- Reports local, selected-site, network-wide, aggregate remote-network, and must-use activation states where WP Migrate exposes them.
- Selects source-only packages and source upgrades by default, while leaving source downgrades unselected.
- Provides per-section Select all and Deselect all controls.
- Defaults generated profile names to a chronological `Release-YYYYMMDD-HHMM` identifier.
- Creates a WP Migrate saved profile with database and media disabled.
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
- Use “Check for updates” beneath the plugin on the WordPress Plugins screen to bypass stale caches and run WordPress's native update check.
- When a newer release exists, WordPress displays its standard update notice, “View details,” and “update now” action.

## Important Notes

- WP Migrate Pro must be installed and active locally and remotely. The two sites' ordinary themes and plugins are expected to differ; that is what this plugin compares.
- Both sites must run a mutually compatible WP Migrate Pro version, as required by WP Migrate's own signed connection handshake.
- WP Migrate's Themes & Plugins capability must return package inventory.
- This is a package/version comparison, not a file-content or line-by-line diff.
- On a remote multisite, WP Migrate 2.7.8 exposes only a combined active flag, so the UI says “active somewhere in network.” Exact selected-site and network-active states are available for the local multisite.
- Activation is informational. A generated code-only profile transfers selected files and does not change activation/database options.
- The WP Migrate secret key is stored only as part of the saved WP Migrate profile, matching WP Migrate's existing profile behaviour.

## Future Considerations

- Add optional package content fingerprints if version metadata proves insufficient.
- Add an optional companion endpoint on the remote site for exact network-vs-subsite activation reporting.
- Add GitHub release updates after a public destination repository and release asset name are declared.
