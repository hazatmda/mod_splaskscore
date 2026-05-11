#!/usr/bin/env python3
"""Validate Joomla extension and update-server release metadata."""

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
DESCRIPTION_VERSION_PATTERN = re.compile(r"versi\s+(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)", re.IGNORECASE)


def text_at(parent: ET.Element, path: str, source: Path) -> str:
    node = parent.find(path)
    if node is None or node.text is None or not node.text.strip():
        raise ValueError(f"{source}: missing or empty <{path}>")
    return node.text.strip()


def parse_xml(path: Path) -> ET.Element:
    try:
        return ET.parse(path).getroot()
    except ET.ParseError as exc:
        raise ValueError(f"{path}: invalid XML: {exc}") from exc


def expected_download_url(version: str) -> str:
    return (
        f"https://github.com/{REPOSITORY}/releases/download/"
        f"v{version}/{EXTENSION_ELEMENT}_v{version}.zip"
    )


def validate_version(version: str) -> None:
    if not re.fullmatch(r"\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?", version):
        raise ValueError(f"Release version must not include the leading 'v' and must look like SemVer: {version}")


def validate(module_manifest: Path, update_manifest: Path, version: str | None) -> None:
    module_root = parse_xml(module_manifest)
    update_root = parse_xml(update_manifest)

    if module_root.tag != "extension":
        raise ValueError(f"{module_manifest}: root element must be <extension>")
    if module_root.attrib.get("type") != "module":
        raise ValueError(f"{module_manifest}: extension type must be 'module'")
    if module_root.attrib.get("client") != "administrator":
        raise ValueError(f"{module_manifest}: extension client must be 'administrator'")

    module_name = text_at(module_root, "name", module_manifest)
    module_version = text_at(module_root, "version", module_manifest)
    module_description = text_at(module_root, "description", module_manifest)
    if module_name != EXTENSION_ELEMENT:
        raise ValueError(f"{module_manifest}: <name> must be {EXTENSION_ELEMENT}")
    validate_version(module_version)
    description_versions = DESCRIPTION_VERSION_PATTERN.findall(module_description)
    for description_version in description_versions:
        if description_version != module_version:
            raise ValueError(
                f"{module_manifest}: description version {description_version} does not match manifest version {module_version}"
            )

    update_server = module_root.find("updateservers/server")
    if update_server is None:
        raise ValueError(f"{module_manifest}: missing <updateservers><server> declaration")
    if update_server.attrib.get("type") != "extension":
        raise ValueError(f"{module_manifest}: update server type must be 'extension'")
    if update_server.attrib.get("priority") != "1":
        raise ValueError(f"{module_manifest}: update server priority must be '1'")
    update_server_url = (update_server.text or "").strip()
    expected_update_server_url = f"https://raw.githubusercontent.com/{REPOSITORY}/main/{update_manifest.name}"
    if update_server_url != expected_update_server_url:
        raise ValueError(
            f"{module_manifest}: update server URL must be {expected_update_server_url}, got {update_server_url}"
        )

    if update_root.tag != "updates":
        raise ValueError(f"{update_manifest}: root element must be <updates>")
    updates = update_root.findall("update")
    if len(updates) != 1:
        raise ValueError(f"{update_manifest}: expected exactly one <update>, found {len(updates)}")
    update = updates[0]

    update_name = text_at(update, "name", update_manifest)
    update_element = text_at(update, "element", update_manifest)
    update_type = text_at(update, "type", update_manifest)
    update_version = text_at(update, "version", update_manifest)
    download_url = text_at(update, "downloads/downloadurl", update_manifest)
    target_platform = update.find("targetplatform")

    if update_name != EXTENSION_ELEMENT:
        raise ValueError(f"{update_manifest}: <name> must be {EXTENSION_ELEMENT}")
    if update_element != EXTENSION_ELEMENT:
        raise ValueError(f"{update_manifest}: <element> must be {EXTENSION_ELEMENT}")
    if update_type != "module":
        raise ValueError(f"{update_manifest}: <type> must be module")
    if target_platform is None:
        raise ValueError(f"{update_manifest}: missing <targetplatform>")
    if target_platform.attrib.get("name") != "joomla" or target_platform.attrib.get("version") != "5":
        raise ValueError(f"{update_manifest}: target platform must be Joomla 5")

    download = update.find("downloads/downloadurl")
    if download is None:
        raise ValueError(f"{update_manifest}: missing <downloads><downloadurl>")
    if download.attrib.get("type") != "full" or download.attrib.get("format") != "zip":
        raise ValueError(f"{update_manifest}: downloadurl must have type='full' and format='zip'")

    if update_version != module_version:
        raise ValueError(
            f"Version mismatch: {module_manifest} has {module_version}, {update_manifest} has {update_version}"
        )

    if version is not None:
        validate_version(version)
        if module_version != version:
            raise ValueError(f"Release tag version {version} does not match manifest version {module_version}")

    expected_url = expected_download_url(module_version)
    if download_url != expected_url:
        raise ValueError(f"Download URL must be {expected_url}, got {download_url}")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--version", help="Release version from the tag, without the leading 'v'.")
    parser.add_argument("--module-manifest", type=Path, default=MODULE_MANIFEST)
    parser.add_argument("--update-manifest", type=Path, default=UPDATE_MANIFEST)
    args = parser.parse_args()

    try:
        validate(args.module_manifest, args.update_manifest, args.version)
    except ValueError as exc:
        print(f"release metadata validation failed: {exc}", file=sys.stderr)
        return 1

    print("release metadata validation passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
