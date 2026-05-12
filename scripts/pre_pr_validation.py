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
PLUGIN_NAME = "plg_task_splaskscoreanalytics"
SYSTEM_PLUGIN_NAME = "plg_system_splaskscoreautomation"
PACKAGE_NAME = "pkg_splaskscore"
MODULE_MANIFEST = ROOT / "mod_splaskscore.xml"
UPDATE_MANIFEST = ROOT / "updates.xml"
PLUGIN_MANIFEST = ROOT / "plugins" / "task" / "splaskscoreanalytics" / "splaskscoreanalytics.xml"
SYSTEM_PLUGIN_MANIFEST = ROOT / "plugins" / "system" / "splaskscoreautomation" / "splaskscoreautomation.xml"
PACKAGE_MANIFEST = ROOT / "pkg_splaskscore.xml"
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


def build_package(version: str, plugin_zip: Path | None = None, system_plugin_zip: Path | None = None) -> Path:
    staging = BUILD_ROOT / EXTENSION_NAME
    zip_path = DIST_ROOT / f"{EXTENSION_NAME}_v{version}.zip"

    shutil.rmtree(BUILD_ROOT, ignore_errors=True)
    DIST_ROOT.mkdir(exist_ok=True)
    if zip_path.exists():
        zip_path.unlink()
    staging.mkdir(parents=True)

    for name in ["mod_splaskscore.php", "helper.php", "script.php", "mod_splaskscore.xml", "LICENSE", "LICENSE.txt"]:
        source = ROOT / name
        if source.exists():
            shutil.copy2(source, staging / name)

    package_dir = staging / "packages"
    if plugin_zip is not None:
        package_dir.mkdir(exist_ok=True)
        shutil.copy2(plugin_zip, package_dir / "plg_task_splaskscoreanalytics.zip")
    if system_plugin_zip is not None:
        package_dir.mkdir(exist_ok=True)
        shutil.copy2(system_plugin_zip, package_dir / "plg_system_splaskscoreautomation.zip")

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


def build_scheduler_plugin(version: str) -> Path:
    plugin_root = ROOT / "plugins" / "task" / "splaskscoreanalytics"
    DIST_ROOT.mkdir(exist_ok=True)
    zip_path = DIST_ROOT / f"{PLUGIN_NAME}_v{version}.zip"
    if zip_path.exists():
        zip_path.unlink()

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as archive:
        for path in sorted(plugin_root.rglob("*")):
            if path.is_file():
                archive.write(path, path.relative_to(plugin_root).as_posix())

    print(f"Built scheduler plugin installer simulation: {rel(zip_path)}")
    return zip_path


def build_system_plugin(version: str) -> Path:
    plugin_root = ROOT / "plugins" / "system" / "splaskscoreautomation"
    DIST_ROOT.mkdir(exist_ok=True)
    zip_path = DIST_ROOT / f"{SYSTEM_PLUGIN_NAME}_v{version}.zip"
    if zip_path.exists():
        zip_path.unlink()

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as archive:
        for path in sorted(plugin_root.rglob("*")):
            if path.is_file():
                archive.write(path, path.relative_to(plugin_root).as_posix())

    print(f"Built module-save automation plugin installer simulation: {rel(zip_path)}")
    return zip_path


def build_joomla_package(module_zip: Path, plugin_zip: Path, system_plugin_zip: Path, version: str) -> Path:
    package_zip = DIST_ROOT / f"{PACKAGE_NAME}_v{version}.zip"
    if package_zip.exists():
        package_zip.unlink()

    with zipfile.ZipFile(package_zip, "w", zipfile.ZIP_DEFLATED) as archive:
        archive.write(PACKAGE_MANIFEST, PACKAGE_MANIFEST.name)
        archive.write(module_zip, "packages/mod_splaskscore.zip")
        archive.write(plugin_zip, "packages/plg_task_splaskscoreanalytics.zip")
        archive.write(system_plugin_zip, "packages/plg_system_splaskscoreautomation.zip")

    print(f"Built Joomla package simulation: {rel(package_zip)}")
    return package_zip


def inspect_package(zip_path: Path, version: str) -> None:
    module_root = read_xml(MODULE_MANIFEST)
    with zipfile.ZipFile(zip_path) as archive:
        names = set(archive.namelist())
        print("ZIP contents:")
        for name in sorted(names):
            print(f"- {name}")

        required_files = {"mod_splaskscore.php", "helper.php", "script.php", "mod_splaskscore.xml", "packages/plg_task_splaskscoreanalytics.zip", "packages/plg_system_splaskscoreautomation.zip"}
        missing_files = sorted(required_files - names)
        if missing_files:
            raise AssertionError(f"ZIP is missing required files: {', '.join(missing_files)}")

        required_prefixes = {"tmpl/", "media/"}
        missing_prefixes = sorted(prefix for prefix in required_prefixes if not any(name.startswith(prefix) for name in names))
        if missing_prefixes:
            raise AssertionError(f"ZIP is missing required file trees: {', '.join(missing_prefixes)}")

        packaged_manifest = archive.read("mod_splaskscore.xml").decode("utf-8")
        if f"<version>{version}</version>" not in packaged_manifest:
            raise AssertionError("Packaged mod_splaskscore.xml version does not match release version")

        if "<scriptfile>script.php</scriptfile>" not in packaged_manifest:
            raise AssertionError("Module manifest must register installer script for plugin install/upgrade flow")
        script_body = archive.read("script.php").decode("utf-8")
        for token in [
            "InstallerHelper::unpack",
            "Installer::getInstance()->install($installPath)",
            "InstallerHelper::cleanupInstall",
            "is_dir($packagesPath)",
            "is_dir($installPath)",
            "enablePlugin",
            "splaskscoreanalytics",
            "splaskscoreautomation",
        ]:
            if token not in script_body:
                raise AssertionError(f"Install/upgrade flow validation missing token: {token}")
        if "Installer::getInstance()->install($pluginZip)" in script_body:
            raise AssertionError("Bundled plugin ZIPs must be unpacked before Installer::install() to avoid Install path warnings")

        declares_sql = module_root.find("files/folder[.='sql']") is not None
        if declares_sql and not any(name.startswith("sql/") for name in names):
            raise AssertionError("Manifest declares sql folder, but sql files are missing from ZIP")

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


def validate_scheduler_plugin_packaging(plugin_zip: Path, version: str) -> None:
    plugin_root = read_xml(PLUGIN_MANIFEST)
    if plugin_root.attrib.get("type") != "plugin" or plugin_root.attrib.get("group") != "task":
        raise AssertionError("Scheduler plugin manifest must be a task plugin")
    if text_at(plugin_root, "version", PLUGIN_MANIFEST) != version:
        raise AssertionError("Scheduler plugin manifest version does not match release version")

    with zipfile.ZipFile(plugin_zip) as archive:
        names = set(archive.namelist())
        required = {"splaskscoreanalytics.php", "splaskscoreanalytics.xml"}
        missing = sorted(required - names)
        if missing:
            raise AssertionError(f"Scheduler plugin ZIP missing files: {', '.join(missing)}")
        plugin_code = archive.read("splaskscoreanalytics.php").decode("utf-8")
        for token in ["onTaskOptionsList", "onExecuteTask", "collectScheduledAnalytics", "6:00 AM"]:
            if token not in plugin_code and token not in archive.read("splaskscoreanalytics.xml").decode("utf-8"):
                raise AssertionError(f"Scheduler registration validation missing token: {token}")


def validate_system_plugin_packaging(plugin_zip: Path, version: str) -> None:
    plugin_root = read_xml(SYSTEM_PLUGIN_MANIFEST)
    if plugin_root.attrib.get("type") != "plugin" or plugin_root.attrib.get("group") != "system":
        raise AssertionError("Automation plugin manifest must be a system plugin")
    if text_at(plugin_root, "version", SYSTEM_PLUGIN_MANIFEST) != version:
        raise AssertionError("Automation plugin manifest version does not match release version")

    with zipfile.ZipFile(plugin_zip) as archive:
        names = set(archive.namelist())
        required = {"splaskscoreautomation.php", "splaskscoreautomation.xml"}
        missing = sorted(required - names)
        if missing:
            raise AssertionError(f"Automation plugin ZIP missing files: {', '.join(missing)}")
        plugin_code = archive.read("splaskscoreautomation.php").decode("utf-8")
        for token in ["onContentAfterSave", "synchronizeSchedulerForModule", "mod_splaskscore"]:
            if token not in plugin_code:
                raise AssertionError(f"Module-save scheduler synchronization missing token: {token}")


def validate_joomla_package(package_zip: Path, version: str) -> None:
    package_root = read_xml(PACKAGE_MANIFEST)
    if package_root.attrib.get("type") != "package":
        raise AssertionError("Package manifest must use type='package'")
    if text_at(package_root, "version", PACKAGE_MANIFEST) != version:
        raise AssertionError("Package manifest version does not match release version")

    with zipfile.ZipFile(package_zip) as archive:
        names = set(archive.namelist())
        required = {"pkg_splaskscore.xml", "packages/mod_splaskscore.zip", "packages/plg_task_splaskscoreanalytics.zip", "packages/plg_system_splaskscoreautomation.zip"}
        missing = sorted(required - names)
        if missing:
            raise AssertionError(f"Joomla package ZIP missing files: {', '.join(missing)}")


def validate_schema_and_workflows() -> None:
    install_sql = (ROOT / "sql" / "install.mysql.utf8.sql").read_text()
    helper = (ROOT / "helper.php").read_text()
    module_manifest = MODULE_MANIFEST.read_text()
    readme = (ROOT / "README.md").read_text()
    release_workflow = (ROOT / ".github" / "workflows" / "release.yml").read_text()
    script = (ROOT / "media" / "js" / "splaskscore.js").read_text()
    template = (ROOT / "tmpl" / "_score_card.php").read_text()

    schema_tokens = ["splaskscore_health", "source", "recorded_at", "engine_version", "signature", "triggered_by"]
    missing_schema = [token for token in schema_tokens if token not in install_sql]
    if missing_schema:
        raise AssertionError("DB migration/schema validation missing: " + ", ".join(missing_schema))

    helper_tokens = ["migrateHistoryTable", "isDuplicateHistoryRecord", "applyRetentionPolicy", "getAnalyticsHealth", "collectScheduledAnalytics", "refreshAnalyticsAjax", "synchronizeSchedulerForModule", "buildSchedulerRules"]
    missing_helper = [token for token in helper_tokens if token not in helper]
    if missing_helper:
        raise AssertionError("Install/upgrade/manual/scheduler helper validation missing: " + ", ".join(missing_helper))

    health_failure_tokens = [
        "strcmp($lastFailed, $effectiveSuccess) > 0",
        "must not be masked by an older successful collection",
        "'status' => $status",
    ]
    missing_health_tokens = [token for token in health_failure_tokens if token not in helper]
    if missing_health_tokens:
        raise AssertionError("Health failure precedence validation missing: " + ", ".join(missing_health_tokens))

    release_workflow_tokens = [
        "install -m 0644 script.php",
        "packages/plg_task_splaskscoreanalytics.zip",
        "packages/plg_system_splaskscoreautomation.zip",
        r"<scriptfile>script\.php</scriptfile>",
        r"Installer::getInstance\(\)->install",
        "--system-plugin-manifest",
    ]
    missing_release_workflow = [token for token in release_workflow_tokens if token not in release_workflow]
    if missing_release_workflow:
        raise AssertionError("Release workflow packaging validation missing: " + ", ".join(missing_release_workflow))

    scheduler_guidance_tokens = [
        "Automated analytics collection depends on Joomla Scheduled Tasks being active in the hosting environment",
        "analytics_scheduler_note",
        "analytics_multi_module_note",
        "Runtime collection still processes every published enabled module instance",
        "first/latest published instance",
    ]
    guidance_sources = readme + module_manifest + helper
    missing_guidance = [token for token in scheduler_guidance_tokens if token not in guidance_sources]
    if missing_guidance:
        raise AssertionError("Scheduler operational guidance validation missing: " + ", ".join(missing_guidance))

    validate_release_workflow_sequence(release_workflow)

    workflow_tokens = ["data-splask-refresh-trigger", "refreshAnalytics", "applyHealth", "splask-icon-button", "splask-history-header-actions"]
    combined = script + template
    missing_workflow = [token for token in workflow_tokens if token not in combined]
    if missing_workflow:
        raise AssertionError("Manual refresh UI validation missing: " + ", ".join(missing_workflow))

    visible_debug_tokens = ["Last Collection", "Status Analitik", "Last Failed", "data-splask-gap-warning"]
    visible_debug = [token for token in visible_debug_tokens if token in template]
    if visible_debug:
        raise AssertionError("Visible operational telemetry still present: " + ", ".join(visible_debug))


def synchronize_release_metadata(release_tag: str | None) -> None:
    if release_tag is None:
        return
    run([sys.executable, "scripts/sync_release_metadata.py", "--tag", release_tag])


def validate_release_workflow_sequence(release_workflow: str) -> None:
    ordered_tokens = [
        "Resolve release version",
        "Synchronize release metadata from tag",
        "Validate Joomla update metadata",
        "Build clean Joomla installation ZIP",
        "Validate Joomla package contents",
        "Upload workflow artifact",
        "Attach ZIP to GitHub Release",
    ]
    positions = []
    for token in ordered_tokens:
        position = release_workflow.find(token)
        if position == -1:
            raise AssertionError(f"Release workflow sequencing validation missing step: {token}")
        positions.append(position)

    if positions != sorted(positions):
        raise AssertionError(
            "Release workflow steps must resolve version, synchronize metadata, validate metadata, "
            "build and validate the package, then publish ZIP assets"
        )

    upload_tokens = [
        "path: ${{ env.zip_path }}",
        "if-no-files-found: error",
        "files: ${{ env.zip_path }}",
        "fail_on_unmatched_files: true",
    ]
    missing_upload_tokens = [token for token in upload_tokens if token not in release_workflow]
    if missing_upload_tokens:
        raise AssertionError("Release ZIP upload validation missing: " + ", ".join(missing_upload_tokens))


def validate_release_metadata(version: str, release_tag: str | None) -> None:
    if release_tag is not None and release_tag != f"v{version}":
        raise AssertionError(f"Release tag/version alignment failed: {release_tag} != v{version}")
    run([sys.executable, "scripts/validate_release_metadata.py", "--version", version])


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
        release_tag = args.release_tag
        synchronize_release_metadata(release_tag)
        version = release_version()
        release_tag = release_tag or f"v{version}"
        print(f"Pre-PR validation for {EXTENSION_NAME} {release_tag}")
        validate_release_metadata(version, release_tag)
        plugin_zip = build_scheduler_plugin(version)
        system_plugin_zip = build_system_plugin(version)
        zip_path = build_package(version, plugin_zip, system_plugin_zip)
        package_zip = build_joomla_package(zip_path, plugin_zip, system_plugin_zip, version)
        inspect_package(zip_path, version)
        validate_scheduler_plugin_packaging(plugin_zip, version)
        validate_system_plugin_packaging(system_plugin_zip, version)
        validate_joomla_package(package_zip, version)
        validate_schema_and_workflows()
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
