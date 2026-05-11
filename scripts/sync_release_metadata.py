#!/usr/bin/env python3
"""Synchronize Joomla release metadata from a Git tag/version.

This script patches all version-dependent manifest, update-server, package,
plugin, and helper engine metadata used by GitHub Releases and Joomla installs.
The release workflow uses it both for the temporary packaging workspace and for
the default-branch metadata commit-back step so both paths share one source of
release truth.
"""

from __future__ import annotations

import argparse
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

REPOSITORY = "hazatmda/mod_splaskscore"
EXTENSION_ELEMENT = "mod_splaskscore"
MODULE_MANIFEST = Path("mod_splaskscore.xml")
UPDATE_MANIFEST = Path("updates.xml")
LEGACY_UPDATE_MANIFEST = Path("mod_splaskscore_update.xml")
PACKAGE_MANIFEST = Path("pkg_splaskscore.xml")
PLUGIN_MANIFEST = Path("plugins/task/splaskscoreanalytics/splaskscoreanalytics.xml")
HELPER_FILE = Path("helper.php")
VERSION_PATTERN = re.compile(r"\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?")
DESCRIPTION_VERSION_PATTERN = re.compile(r"(versi\s+)\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?", re.IGNORECASE)
BRACKET_VERSION_PATTERN = re.compile(r"(\[v)\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?(\])", re.IGNORECASE)


class MetadataSyncError(ValueError):
    """Raised when release metadata cannot be synchronized safely."""


def normalize_version(tag_or_version: str) -> str:
    """Return a SemVer-like release version, accepting an optional leading v."""
    value = tag_or_version.strip()
    if not value:
        raise MetadataSyncError("Release tag/version is empty")
    version = value[1:] if value.startswith("v") else value
    if not VERSION_PATTERN.fullmatch(version):
        raise MetadataSyncError(
            "Release version must look like SemVer, for example v1.2.3 or 1.2.3; "
            f"got {tag_or_version!r}"
        )
    return version


def expected_download_url(version: str) -> str:
    return (
        f"https://github.com/{REPOSITORY}/releases/download/"
        f"v{version}/{EXTENSION_ELEMENT}_v{version}.zip"
    )


def parse_xml(path: Path) -> ET.ElementTree:
    try:
        return ET.parse(path)
    except ET.ParseError as exc:
        raise MetadataSyncError(f"{path}: invalid XML: {exc}") from exc


def required_child(parent: ET.Element, path: str, source: Path) -> ET.Element:
    node = parent.find(path)
    if node is None:
        raise MetadataSyncError(f"{source}: missing <{path}>")
    return node


def set_required_text(parent: ET.Element, path: str, source: Path, value: str) -> tuple[str, str]:
    node = required_child(parent, path, source)
    old_value = (node.text or "").strip()
    if not old_value:
        raise MetadataSyncError(f"{source}: empty <{path}>")
    node.text = value
    return old_value, value


def sync_description_version(module_root: ET.Element, module_manifest: Path, version: str) -> tuple[str, str] | None:
    description = required_child(module_root, "description", module_manifest)
    old_value = (description.text or "").strip()
    if not old_value:
        raise MetadataSyncError(f"{module_manifest}: empty <description>")

    new_value, replacements = DESCRIPTION_VERSION_PATTERN.subn(rf"\g<1>{version}", old_value)
    if replacements == 0:
        return None

    description.text = new_value
    return old_value, new_value


def indent_xml(tree: ET.ElementTree) -> None:
    # ET.indent is available on the Python versions provided by GitHub-hosted
    # runners and keeps generated XML readable for diagnostics/artifacts.
    ET.indent(tree, space="    ")


def write_xml(tree: ET.ElementTree, path: Path) -> None:
    indent_xml(tree)
    tree.write(path, encoding="utf-8", xml_declaration=True, short_empty_elements=True)


def sync_manifest_version(manifest: Path, version: str, expected_type: str | None = None) -> list[str]:
    if not manifest.exists():
        return []

    tree = parse_xml(manifest)
    root = tree.getroot()
    if root.tag != "extension":
        raise MetadataSyncError(f"{manifest}: root element must be <extension>")
    if expected_type is not None and root.attrib.get("type") != expected_type:
        raise MetadataSyncError(f"{manifest}: extension type must be {expected_type!r}")

    old, new = set_required_text(root, "version", manifest, version)
    write_xml(tree, manifest)
    return [f"{manifest}: <version> {old} -> {new}"]


def sync_helper_engine_version(helper_file: Path, version: str) -> list[str]:
    if not helper_file.exists():
        return []

    text = helper_file.read_text()
    pattern = re.compile(r"(private\s+const\s+ENGINE_VERSION\s*=\s*['\"])" + VERSION_PATTERN.pattern + r"(['\"]\s*;)")
    match = pattern.search(text)
    if match is None:
        raise MetadataSyncError(f"{helper_file}: missing ENGINE_VERSION constant")

    old_version = match.group(0).split(match.group(1), 1)[1].rsplit(match.group(2), 1)[0]
    new_text = pattern.sub(rf"\g<1>{version}\g<2>", text, count=1)
    helper_file.write_text(new_text)
    return [f"{helper_file}: ENGINE_VERSION {old_version} -> {version}"]


def sync_legacy_update_manifest(legacy_manifest: Path, version: str) -> list[str]:
    if not legacy_manifest.exists():
        return []

    legacy_tree = parse_xml(legacy_manifest)
    legacy_root = legacy_tree.getroot()
    if legacy_root.tag != "updates":
        raise MetadataSyncError(f"{legacy_manifest}: root element must be <updates>")

    updates = legacy_root.findall("update")
    if len(updates) != 1:
        raise MetadataSyncError(f"{legacy_manifest}: expected exactly one <update>, found {len(updates)}")
    update = updates[0]

    update_element = (required_child(update, "element", legacy_manifest).text or "").strip()
    if update_element != EXTENSION_ELEMENT:
        raise MetadataSyncError(f"{legacy_manifest}: <element> must be {EXTENSION_ELEMENT}")

    changes: list[str] = []
    old, new = set_required_text(update, "version", legacy_manifest, version)
    changes.append(f"{legacy_manifest}: <version> {old} -> {new}")

    description = required_child(update, "description", legacy_manifest)
    old_description = (description.text or "").strip()
    if not old_description:
        raise MetadataSyncError(f"{legacy_manifest}: empty <description>")
    new_description, replacements = BRACKET_VERSION_PATTERN.subn(rf"\g<1>{version}\g<2>", old_description)
    if replacements:
        description.text = new_description
        changes.append(f"{legacy_manifest}: <description> {old_description} -> {new_description}")

    download = required_child(update, "downloads/downloadurl", legacy_manifest)
    old_url = (download.text or "").strip()
    if not old_url:
        raise MetadataSyncError(f"{legacy_manifest}: empty <downloads><downloadurl>")
    new_url = expected_download_url(version)
    download.text = new_url
    changes.append(f"{legacy_manifest}: <downloadurl> {old_url} -> {new_url}")

    write_xml(legacy_tree, legacy_manifest)
    return changes


def sync_metadata(
    module_manifest: Path,
    update_manifest: Path,
    legacy_update_manifest: Path,
    package_manifest: Path,
    plugin_manifest: Path,
    helper_file: Path,
    version: str,
) -> list[str]:
    module_tree = parse_xml(module_manifest)
    update_tree = parse_xml(update_manifest)
    module_root = module_tree.getroot()
    update_root = update_tree.getroot()

    if module_root.tag != "extension":
        raise MetadataSyncError(f"{module_manifest}: root element must be <extension>")
    if update_root.tag != "updates":
        raise MetadataSyncError(f"{update_manifest}: root element must be <updates>")

    updates = update_root.findall("update")
    if len(updates) != 1:
        raise MetadataSyncError(f"{update_manifest}: expected exactly one <update>, found {len(updates)}")
    update = updates[0]

    module_name = (required_child(module_root, "name", module_manifest).text or "").strip()
    update_element = (required_child(update, "element", update_manifest).text or "").strip()
    if module_name != EXTENSION_ELEMENT:
        raise MetadataSyncError(f"{module_manifest}: <name> must be {EXTENSION_ELEMENT}")
    if update_element != EXTENSION_ELEMENT:
        raise MetadataSyncError(f"{update_manifest}: <element> must be {EXTENSION_ELEMENT}")

    changes: list[str] = []
    old, new = set_required_text(module_root, "version", module_manifest, version)
    changes.append(f"{module_manifest}: <version> {old} -> {new}")

    description_change = sync_description_version(module_root, module_manifest, version)
    if description_change is not None:
        old, new = description_change
        changes.append(f"{module_manifest}: <description> {old} -> {new}")

    old, new = set_required_text(update, "version", update_manifest, version)
    changes.append(f"{update_manifest}: <version> {old} -> {new}")

    download = required_child(update, "downloads/downloadurl", update_manifest)
    old_url = (download.text or "").strip()
    if not old_url:
        raise MetadataSyncError(f"{update_manifest}: empty <downloads><downloadurl>")
    new_url = expected_download_url(version)
    download.text = new_url
    changes.append(f"{update_manifest}: <downloadurl> {old_url} -> {new_url}")

    write_xml(module_tree, module_manifest)
    write_xml(update_tree, update_manifest)
    changes.extend(sync_legacy_update_manifest(legacy_update_manifest, version))
    changes.extend(sync_manifest_version(package_manifest, version, expected_type="package"))
    changes.extend(sync_manifest_version(plugin_manifest, version, expected_type="plugin"))
    changes.extend(sync_helper_engine_version(helper_file, version))
    return changes


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--tag", help="Release tag, for example v1.2.3. May include the leading v.")
    parser.add_argument("--version", help="Release version without the leading v. Used if --tag is not provided.")
    parser.add_argument("--module-manifest", type=Path, default=MODULE_MANIFEST)
    parser.add_argument("--update-manifest", type=Path, default=UPDATE_MANIFEST)
    parser.add_argument("--legacy-update-manifest", type=Path, default=LEGACY_UPDATE_MANIFEST)
    parser.add_argument("--package-manifest", type=Path, default=PACKAGE_MANIFEST)
    parser.add_argument("--plugin-manifest", type=Path, default=PLUGIN_MANIFEST)
    parser.add_argument("--helper-file", type=Path, default=HELPER_FILE)
    args = parser.parse_args()

    source = args.tag or args.version
    if source is None:
        print("release metadata synchronization failed: provide --tag or --version", file=sys.stderr)
        return 2

    try:
        version = normalize_version(source)
        changes = sync_metadata(
            args.module_manifest,
            args.update_manifest,
            args.legacy_update_manifest,
            args.package_manifest,
            args.plugin_manifest,
            args.helper_file,
            version,
        )
    except MetadataSyncError as exc:
        print(f"release metadata synchronization failed: {exc}", file=sys.stderr)
        return 1

    print(f"release metadata synchronized for version {version}")
    for change in changes:
        print(f"- {change}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
