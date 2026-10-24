#!/usr/bin/env python3
"""Verify the live rule page really serves the stylesheet from this commit.

This runs after FTPS upload. A merged PR is not proof of a live deployment.
Only stdlib and the curl already used by this repository's CI are required.
"""
from __future__ import annotations
import os
import re
import subprocess
from pathlib import Path
from urllib.parse import quote


BASE = "https://587.renju.org.tw"
COMMIT = os.environ.get("CURRENT_SHA", "local-check")


def fetch(relative: str) -> str:
    separator = "&" if "?" in relative else "?"
    url = BASE + relative + separator + "deploy_check=" + quote(COMMIT, safe="")
    response = subprocess.run(
        [
            "curl", "-k", "-f", "-sS", "-L",
            "--retry", "3", "--retry-delay", "3", "--max-time", "35",
            "-H", "Cache-Control: no-cache", url,
        ],
        capture_output=True, text=True, timeout=160, check=False,
    )
    if response.returncode != 0:
        raise RuntimeError(
            f"Unable to read live {relative}: curl exit {response.returncode}; "
            + response.stderr.strip()[:300]
        )
    return response.stdout


source = Path("rule.htm").read_text(encoding="utf-8")
match = re.search(r'href="rule-tree-visual\.css\?v=([\w-]+)"', source)
if not match:
    raise RuntimeError("Source rule.htm does not reference the shared visual stylesheet.")
version = match.group(1)

actual_page = fetch("/rule.htm")
if f'rule-tree-visual.css?v={version}' not in actual_page:
    raise RuntimeError(
        f"Live rule.htm is out of date: expected rule-tree-visual.css?v={version}. "
        "Check GitHub Actions deployment and FTP_SERVER_DIR; HTML may be stale in CDN."
    )
print(f"Live rule.htm references correct shared visual stylesheet version: {version}")

actual_css = fetch(f"/rule-tree-visual.css?v={version}")
css_compact = re.sub(r"/\*[\s\S]*?\*/", "", actual_css)
if not re.search(
    r"\.rule-tree-layout\s+\.rule-tree-card-button:is\(\s*:hover\s*,\s*:focus-visible\s*\)",
    css_compact,
):
    raise RuntimeError(
        "Live shared CSS is stale or missing public rule-button hover selector. "
        "Check FTPS target directory, CDN caching, and the generated CSS URL."
    )
if not re.search(r"background\s*:\s*#edf6fc", css_compact, re.I):
    raise RuntimeError("Live shared CSS lacks the requested light-blue hover background.")
if not re.search(r"\.rule-tree-grid\s+\.rule-tree-node-toggle:is\(", css_compact):
    raise RuntimeError("Live shared CSS lacks the synchronized admin rule hover selector.")
print("Live stylesheet confirmed: light-blue hover and shared front/admin rules are present.")
