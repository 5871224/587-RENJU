#!/usr/bin/env python3
"""Guard against navigation regressions after moving to www.renju.org.tw."""
from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SUFFIXES = {".php", ".htm", ".html"}
LEGACY_MENU = (b"https://587.renju.org.tw/menu.html", b"http://587.renju.org.tw/menu.html")
LEGACY_JQUERY = b"https://587.renju.org.tw/js/jquery-3.7.1.min.js"
failures: list[str] = []

for path in ROOT.rglob("*"):
    if not path.is_file() or path.suffix.lower() not in SUFFIXES:
        continue
    if any(folder in {".git", ".github", "scripts"} for folder in path.relative_to(ROOT).parts):
        continue
    data = path.read_bytes()
    rel = path.relative_to(ROOT).as_posix()
    if any(old in data for old in LEGACY_MENU):
        failures.append(f"{rel}: absolute 587 URL for shared navigation")
    if b"menu.html" in data and LEGACY_JQUERY in data:
        failures.append(f"{rel}: navigation page loads jQuery through the redirecting 587 host")

fragment = (ROOT / "menu.html").read_text(encoding="utf-8")
if 'id="menu"' not in fragment or 'class="site-nav-brand"' not in fragment:
    failures.append("menu.html: shared navigation markup is missing")
if "587.renju.org.tw/" in fragment:
    failures.append("menu.html: shared navigation still links to the redirecting 587 hostname")

for path in ("index.html", "rule.htm", "riftw/record.php", "riftw/world-record.php", "teach/teach006.htm"):
    if b"menu.html" not in (ROOT / path).read_bytes():
        failures.append(f"{path}: shared menu is not loaded")

if failures:
    raise SystemExit("Navigation regression check failed:\n" + "\n".join(failures))

print("Navigation regression check passed: all managed pages use same-origin menu requests.")
