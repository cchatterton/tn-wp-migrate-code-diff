# TN Code Release Installer

Author: Techn  
Version: 0.4.2

## Purpose

Installs manual code release packages created by TN WP Migrate Code Diff.

## Usage

1. Install and activate this plugin on the destination site.
2. Open **Settings > Upload Release**. On Multisite, open **Network Admin > Settings > Upload Release**.
3. Select the release ZIP created from the source site's Code Comparison page.
4. Choose **Upload and install release**.

The installer validates the package structure and SHA-256 file checksums before changing code. It replaces only packages listed in the manifest and preserves database, media, and activation settings.

## Updates

- Repository: https://github.com/cchatterton/tn-wp-migrate-code-diff
- Release asset: `tn-code-release-installer.zip`
- Use “Check for updates” beneath the plugin on the WordPress Plugins screen to run a native update check.

## Requirements and limitations

- PHP's Zip extension is required to inspect releases.
- WordPress must have direct write access to the relevant content directories.
- Release checksums detect corruption or modification but do not establish publisher identity; only install releases obtained from a trusted source.
- The plugin does not activate or deactivate plugins or themes.
