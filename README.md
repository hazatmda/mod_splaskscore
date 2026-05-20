# SPLaSK Score (`mod_splaskscore`)

Administrator Joomla module for displaying SPLaSK assessment scores, operational review dates, analytics history, and dashboard monitoring for SPLaSK operations.

Latest stable release: **v1.6.25**

## Main Features

- Displays SPLaSK assessment score from the official SPLaSK API.
- Displays `Tarikh Semakan` with full operational timestamp.
- Displays `Semakan Seterusnya` as date-only operational cadence based on `Tarikh Semakan + 1 hari`.
- Provides a Joomla administrator dashboard card with score, grade, trend, review metadata, and analytics access.
- Provides analytics modal with KPI summary, trend chart, history table, pagination, and notes.
- Supports Catatan editing with ACL-aware permission behavior.
- Supports ACL-aware CSV export for analytics records.
- Exports all analytics records, not only the current page.
- CSV export includes UTF-8 BOM for Excel compatibility.
- CSV export escapes commas, quotes, multiline content, and protects against spreadsheet formula injection.
- Supports Joomla Scheduled Tasks for automated analytics collection.
- Supports Joomla Update Server metadata through GitHub releases.
- Includes About tab metadata for product, owner, repository, compatibility, and current version.

## Installation

1. Download `mod_splaskscore_v1.6.25.zip` from [Releases](https://github.com/hazatmda/mod_splaskscore/releases).
2. In Joomla administrator, go to **System > Install > Extensions**.
3. Upload and install the ZIP package.
4. Open the administrator module configuration.
5. Enter the SPLaSK API token.
6. Configure analytics automation if scheduled collection is required.

## Automatic Updates

This module supports Joomla Update Server metadata.

The update metadata files are:

- `updates.xml`
- `mod_splaskscore_update.xml`

Current release metadata points to:

- Tag: `v1.6.25`
- Module package: `mod_splaskscore_v1.6.25.zip`
- Package installer: `pkg_splaskscore_v1.6.25.zip`

## Analytics Automation and Joomla Scheduled Tasks

The module configuration is the main control surface for analytics automation.

After installation or module save, SPLaSK Score can synchronize the related Joomla Scheduled Task configuration for analytics collection.

Operational note:

> Automatic analytics collection depends on the Joomla Scheduled Tasks runner/cron being active in the hosting environment. If the scheduler runner is not active, the task can exist and be enabled but will not execute until Joomla Scheduled Tasks are processed.

## Multi-Module Behavior

SPLaSK Score uses a shared Joomla Scheduled Task routine:

```text
splaskscore.analytics.collect
```

When the task runs, it processes published administrator module instances that:

- have a configured token
- enable automatic analytics collection
- are eligible for collection based on module parameters

For environments with multiple module instances, use one primary module instance as the operational source for automation timing/frequency settings.

## CSV Export

The analytics modal includes an ACL-aware `Export CSV` action.

Export behavior:

- exports all analytics records
- not limited to the current page
- uses CSV format only
- includes UTF-8 BOM for Excel compatibility
- escapes commas, quotes, and multiline values
- protects against formula-leading values beginning with `=`, `+`, `-`, or `@`

Exported columns:

- Tarikh
- Masa
- Skor
- Gred
- Status
- Catatan

Access behavior:

- users who can edit Catatan can export CSV
- users without Catatan edit permission cannot use the export action

## ACL Behavior

Current ACL behavior:

- Catatan editing uses the existing edit permission logic.
- CSV export temporarily reuses the same permission rule as Catatan editing.

Future component architecture may introduce a dedicated export permission, but current behavior intentionally keeps ACL simple and consistent.

## Release and Validation Workflow

Before opening a PR or publishing a release, run the pre-release validation workflow:

```bash
python3 scripts/pre_pr_validation.py --release-tag v1.6.25
```

The validation workflow checks:

- module ZIP generation
- package ZIP generation
- generated ZIP contents
- manifest metadata synchronization
- update XML metadata synchronization
- release URL alignment
- package filename alignment
- `fields/` packaging integrity
- SQL install/uninstall file integrity
- PHP syntax validation
- JavaScript syntax validation
- CSS/dashboard sanity checks
- install/upgrade package integrity

Release metadata must remain synchronized across:

- `helper.php` engine version
- `mod_splaskscore.xml`
- `pkg_splaskscore.xml`
- `updates.xml`
- `mod_splaskscore_update.xml`
- plugin manifests
- About tab displayed version
- release tag
- package filenames
- download URLs

## Current Version

- Version: **1.6.25**
- Release tag: **v1.6.25**
- Module package: **mod_splaskscore_v1.6.25.zip**
- Package installer: **pkg_splaskscore_v1.6.25.zip**
- Joomla compatibility: **Joomla 5.x**
- PHP compatibility: **PHP 8.1+**

## Recent Changelog

### v1.6.25 — About Tab Rendering Stabilization Hotfix

- Replaced fragile custom Joomla FormField About implementation with stable Joomla `note` field rendering.
- Fixed About tab metadata card rendering regression.
- Restored full About metadata card display.
- Preserved centralized ENGINE_VERSION usage for dynamic version rendering.
- Synchronized release metadata to `1.6.25`.
- Validated package/build workflow and ZIP integrity.

### v1.6.24 — Analytics Control Alignment and About Field Hotfix

- Fixed Export CSV button alignment in analytics modal.
- Ensured Export CSV and `Baris Setiap Halaman` controls share the same row.
- Improved responsive behavior for 1366x768 and 1920x1080.
- Corrected AboutMetadata field type resolution attempt.
- Synchronized release metadata to `1.6.24`.

### v1.6.23 — Release Metadata Synchronization

- Synchronized all release metadata after post-release UI fixes.
- Updated helper version, manifests, update XML, package names, plugin manifests, and release URLs.
- Confirmed generated ZIP artifacts include required files.

### v1.6.22 — Analytics CSV Export

- Added ACL-aware Export CSV button to analytics modal.
- Export all analytics records in UTF-8 CSV format.
- Added proper CSV escaping and formula injection protection.
- Added dynamic About tab version handling groundwork.
- Added `fields/` packaging support for release artifacts.

### v1.6.5 — Semakan Seterusnya Date-Only Display Refinement

- Refined `Semakan Seterusnya` display to show date-only cadence.
- Preserved full timestamp for `Tarikh Semakan` and analytics audit records.
- Synchronized release metadata to `v1.6.5`.

### v1.6.0 — Unified Telemetry Dashboard

- Introduced unified analytics modal with KPI rail and chart composition.
- Improved enterprise dashboard visual structure and analytics presentation.

### v1.5.7 — Operational Analytics Foundation

- Stabilized analytics chart readability.
- Preserved daily operational history and frontend pagination foundation.

## Project Information

- Owner: **Muhammad Azizan Hazim**
- Organization: **Unit Infrastruktur dan Keselamatan Digital, Bahagian Digital dan Teknologi Maklumat (BDTM)**
- Repository: <https://github.com/hazatmda/mod_splaskscore>
- Issue tracker: <https://github.com/hazatmda/mod_splaskscore/issues>

## License

This project is licensed under the [GNU General Public License v3.0](LICENSE.txt).

You may use, modify, and redistribute this code provided that:

- original copyright notices are preserved
- the GPL license is included
- redistributed modified versions remain available under the same license

The license is intended to preserve software freedom for the open-source community.
