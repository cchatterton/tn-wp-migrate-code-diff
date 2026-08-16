# TN WP Migrate Code Diff

Author: Techn
Version: 0.8.8
Status: MVP

## Purpose

Compare code packages between two connected WP Migrate Pro sites and create a selective, code-only migration profile.

## Key Features

- Compares plugins, themes, and must-use plugins.
- Keeps the experimental Database / Images implementation installed but hidden behind a disabled feature flag while code deployment is validated.
- Reports same version, source newer, source older, absent on destination, and absent from source.
- Adds a live mode notice to WP Migrate after its direction and connection are configured.
- Shows only Compare Code in the WP Migrate interface while the separate Database/Images implementation is disabled.
- Inherits push/pull direction, connection details, and multisite conversion choices from WP Migrate's current on-screen state.
- Uses the active saved WP Migrate profile's exact code package selection as the initial comparison selection.
- Reports packages as Active, Inactive, or Not installed; detailed activation scope remains internal because remote multisite scope is not exact.
- Selects active source-only plugins/themes, active source upgrades, and inactive destination-only plugins by default, while leaving destination-only themes, must-use plugins, source downgrades, inactive source packages, and active destination-only plugins unselected.
- Provides per-section Select All, Deselect All, and Recommended controls.
- Defaults generated profile names to a chronological `Release-YYYYMMDD-HHMM` identifier.
- Creates a WP Migrate saved profile with database and media disabled.
- Separates Save release profile, Open profile, and Create release package into independent actions.
- Refreshes a comparison in place using the existing WP Migrate connection context.
- Creates a manual release ZIP from selected local source packages when the comparison direction is Push.
- Records selected destination-only packages as explicit removal operations, allowing a manual release to add, replace, and remove code packages.
- Provides its own destination-side **Settings > Upload Release** page, so the same plugin handles either migration direction.
- Provides **Settings > Release Notes**, backed by a custom network-aware history table, for manual release auditing.
- Records package creator/source metadata from the manifest when a release reaches Upload Release, along with deployment user/destination, rollback creation, timestamps, and package additions, version transitions, and removals.
- Provides separate Create Rollback and Install Release actions for a selected release ZIP.
- Shows an in-progress spinner and busy label while creating a release package, creating a rollback, or installing a release.
- Requests the WordPress admin memory allowance and an unlimited PHP execution window for long package operations where hosting policy permits.
- Creates a valid `<release-name>-rollback.zip` containing complete copies of replaced or removed destination packages and validated removal instructions for packages newly introduced by the release, without changing the site.
- Installs rollback ZIPs through the same Upload Release page without generating nested rollback packages.
- Never selects active destination-only packages for removal unless the operator chooses them explicitly.
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
- On a remote multisite, WP Migrate 2.7.8 exposes only a combined active flag, so the report deliberately uses the single label “Active” on both sides.
- Activation is informational. A generated code-only profile transfers selected files and does not change activation/database options.
- Create and retain the rollback ZIP before installing a normal release when rollback capability is required.
- Manual release packages are available only for Push comparisons because the package generator must read the source files locally.
- Forward and rollback removals are restricted to exact, validated plugin, theme, or must-use-plugin destinations; the active release installer cannot remove itself.
- A rollback created before installation contains the complete destination files for selected removals so those packages can be restored.
- Manual release checksums detect corruption or modification but do not establish publisher identity. Install only packages obtained from a trusted source.
- Release history stores WordPress user IDs/display names, site URLs, timestamps, and code package/version metadata; it does not store WP Migrate connection secrets.
- The WP Migrate secret key is stored only as part of the saved WP Migrate profile, matching WP Migrate's existing profile behaviour.

## Future Considerations

- Re-enable the separate Database / Images comparison after its migration-scope model is validated.
- Add optional package content fingerprints if version metadata proves insufficient.
- Add an optional remote endpoint for exact network-vs-subsite activation reporting.
- Add GitHub release updates after a public destination repository and release asset name are declared.
