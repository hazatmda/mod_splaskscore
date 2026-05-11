#!/usr/bin/env python3
"""Run mandatory pre-PR Joomla package simulation and UI sanity checks."""

from __future__ import annotations

import argparse
import os
import re
import shutil
import subprocess
import sys
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
EXTENSION_NAME = "mod_splaskscore"
MODULE_MANIFEST = ROOT / "mod_splaskscore.xml"
UPDATE_MANIFEST = ROOT / "updates.xml"
BUILD_ROOT = ROOT / "build" / "pre-pr"
DIST_ROOT = ROOT / "dist"
EXCLUDED_DIRS = {".git", ".github", "build", "dist", "node_modules", "vendor"}


def rel(path: Path) -> str:
    return str(path.relative_to(ROOT))


def run(command: list[str]) -> None:
    print("$ " + " ".join(command))
    subprocess.run(command, cwd=ROOT, check=True)


def read_xml(path: Path) -> ET.Element:
    return ET.parse(path).getroot()


def text_at(parent: ET.Element, path: str, source: Path) -> str:
    node = parent.find(path)
    if node is None or node.text is None or not node.text.strip():
        raise AssertionError(f"{rel(source)} is missing <{path}>")
    return node.text.strip()


def release_version() -> str:
    return text_at(read_xml(MODULE_MANIFEST), "version", MODULE_MANIFEST)


def build_package(version: str) -> Path:
    staging = BUILD_ROOT / EXTENSION_NAME
    zip_path = DIST_ROOT / f"{EXTENSION_NAME}_v{version}.zip"

    shutil.rmtree(BUILD_ROOT, ignore_errors=True)
    DIST_ROOT.mkdir(exist_ok=True)
    if zip_path.exists():
        zip_path.unlink()
    staging.mkdir(parents=True)

    for name in ["mod_splaskscore.php", "helper.php", "mod_splaskscore.xml", "LICENSE", "LICENSE.txt"]:
        source = ROOT / name
        if source.exists():
            shutil.copy2(source, staging / name)

    for name in ["tmpl", "media", "language", "sql"]:
        source = ROOT / name
        if source.is_dir():
            shutil.copytree(source, staging / name)

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as archive:
        for directory, dirnames, filenames in os.walk(staging):
            dirnames.sort()
            filenames.sort()
            current = Path(directory)
            if current != staging:
                archive.write(current, current.relative_to(staging).as_posix() + "/")
            for filename in filenames:
                path = current / filename
                archive.write(path, path.relative_to(staging).as_posix())

    print(f"Built Joomla installer simulation: {rel(zip_path)}")
    return zip_path


def inspect_package(zip_path: Path, version: str) -> None:
    module_root = read_xml(MODULE_MANIFEST)
    with zipfile.ZipFile(zip_path) as archive:
        names = set(archive.namelist())
        print("ZIP contents:")
        for name in sorted(names):
            print(f"- {name}")

        required = {"mod_splaskscore.php", "helper.php", "mod_splaskscore.xml", "tmpl/", "media/"}
        missing = sorted(required - names)
        if missing:
            raise AssertionError(f"ZIP is missing required entries: {', '.join(missing)}")

        packaged_manifest = archive.read("mod_splaskscore.xml").decode("utf-8")
        if f"<version>{version}</version>" not in packaged_manifest:
            raise AssertionError("Packaged mod_splaskscore.xml version does not match release version")

        declares_sql = module_root.find("files/folder[.='sql']") is not None
        if declares_sql and "sql/" not in names:
            raise AssertionError("Manifest declares sql folder, but sql/ is missing from ZIP")

        install_sql = text_at(module_root, "install/sql/file", MODULE_MANIFEST)
        uninstall_sql = text_at(module_root, "uninstall/sql/file", MODULE_MANIFEST)
        for sql_path in [install_sql, uninstall_sql]:
            if sql_path not in names:
                raise AssertionError(f"SQL file declared by manifest is missing from ZIP: {sql_path}")
            if not archive.read(sql_path).strip():
                raise AssertionError(f"SQL file in ZIP is empty: {sql_path}")

        forbidden = [name for name in names if name.startswith((".git/", ".github/", "scripts/", "build/", "dist/"))]
        if forbidden:
            raise AssertionError(f"ZIP includes non-installable development paths: {', '.join(sorted(forbidden))}")


def validate_release_metadata(version: str, release_tag: str | None) -> None:
    run([sys.executable, "scripts/validate_release_metadata.py", "--version", version])
    if release_tag is not None and release_tag != f"v{version}":
        raise AssertionError(f"Release tag/version alignment failed: {release_tag} != v{version}")


def php_files() -> list[Path]:
    return sorted(
        path for path in ROOT.rglob("*.php")
        if not any(part in EXCLUDED_DIRS for part in path.relative_to(ROOT).parts)
    )


def validate_php() -> None:
    if shutil.which("php") is None:
        raise AssertionError("PHP CLI is required for lint validation")
    for path in php_files():
        run(["php", "-l", rel(path)])


def strip_css_comments(css: str) -> str:
    if css.count("/*") != css.count("*/"):
        raise AssertionError("CSS has an unterminated block comment")
    return re.sub(r"/\*.*?\*/", "", css, flags=re.S)


def validate_css() -> None:
    css_files = sorted((ROOT / "media" / "css").glob("*.css"))
    if not css_files:
        raise AssertionError("No CSS files found under media/css")

    for path in css_files:
        css = strip_css_comments(path.read_text())
        if "<<<<<<<" in css or ">>>>>>>" in css:
            raise AssertionError(f"Conflict marker found in {rel(path)}")
        if css.count("{") != css.count("}"):
            raise AssertionError(f"Unbalanced CSS braces in {rel(path)}")

    combined = "\n".join(path.read_text() for path in css_files)
    required_groups = [
        [".splask-widget[data-splask-appearance=\"dark\"]", ".splask-widget[data-splask-appearance='dark']"],
        [".splask-history-modal"],
        [".splask-history-summary"],
        [".splask-history-table"],
        ["--splask-panel-bg"],
    ]
    for group in required_groups:
        if not any(token in combined for token in group):
            raise AssertionError(f"CSS sanity check missing selector/token: {' or '.join(group)}")


def validate_js() -> None:
    js_files = sorted((ROOT / "media" / "js").glob("*.js"))
    if not js_files:
        raise AssertionError("No JS files found under media/js")
    if shutil.which("node") is None:
        raise AssertionError("Node.js is required for JS syntax validation")
    for path in js_files:
        run(["node", "--check", rel(path)])


def validate_dashboard_consistency() -> None:
    helper = (ROOT / "helper.php").read_text()
    script = (ROOT / "media" / "js" / "splaskscore.js").read_text()
    styles = (ROOT / "media" / "css" / "splaskscore.css").read_text()

    checks = {
        "central PHP score formatter": "formatScorePercent" in helper,
        "dashboard JS score formatter": "function formatScore" in script and "formatScore(score)" in script,
        "Malay weekday/month PHP formatting": "MALAY_WEEKDAYS" in helper and "MALAY_MONTHS" in helper,
        "Malay weekday/month JS formatting": "const MALAY_WEEKDAYS" in script and "const MALAY_MONTHS" in script,
        "analytics modal rendering": "renderHistoryModal" in helper and "data-splask-history-chart" in helper,
        "history AJAX rendering": "loadHistory" in script and "bindHistoryModal" in script,
        "dark appearance behavior": "resolveAppearance" in script and ("data-splask-appearance=\"dark\"" in styles or "data-splask-appearance='dark'" in styles),
        "light appearance baseline": "data-splask-appearance-mode" in script and "--splask-panel-bg" in styles,
    }

    failures = [name for name, passed in checks.items() if not passed]
    if failures:
        raise AssertionError("Dashboard consistency checks failed: " + ", ".join(failures))

    if re.search(r"number_format\([^\n]+score[^\n]+,\s*0\)", helper, re.IGNORECASE):
        raise AssertionError("Precision consistency failed: score displays must not round to whole percentages")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--release-tag", help="Expected release tag, for example v1.2.3. Defaults to v<manifest version>.")
    args = parser.parse_args()

    try:
        version = release_version()
        release_tag = args.release_tag or f"v{version}"
        print(f"Pre-PR validation for {EXTENSION_NAME} {release_tag}")
        validate_release_metadata(version, release_tag)
        zip_path = build_package(version)
        inspect_package(zip_path, version)
        validate_php()
        validate_css()
        validate_js()
        validate_dashboard_consistency()
    except (AssertionError, subprocess.CalledProcessError, ET.ParseError, zipfile.BadZipFile) as exc:
        print(f"pre-PR validation failed: {exc}", file=sys.stderr)
        return 1

    print("pre-PR validation passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
