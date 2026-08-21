#!/usr/bin/env python3
"""Read-only, byte-level comparison of the Git deployment set and an FTPS directory."""

from __future__ import annotations

import argparse
import ftplib
import hashlib
import io
import json
import os
import posixpath
import ssl
import subprocess
from pathlib import Path, PurePosixPath
from urllib.parse import urlsplit


TEXT_SUFFIXES = {
    ".css", ".csv", ".htm", ".html", ".ini", ".js", ".json", ".md",
    ".php", ".ps1", ".svg", ".txt", ".xml", ".yaml", ".yml",
}
LOCAL_EXCLUDES = {"README.md", "config.local.php.example"}
REMOTE_EXCLUDES = {".ftp-deploy-sync-state.json", "config.local.php"}


def is_deployment_file(path: str) -> bool:
    parts = PurePosixPath(path).parts
    return bool(parts) and not (
        any(part.startswith(".git") for part in parts)
        or parts[0] == "scripts"
        or path in LOCAL_EXCLUDES
    )


def normalize_newlines(data: bytes) -> bytes:
    return data.replace(b"\r\n", b"\n").replace(b"\r", b"\n")


def parse_server(value: str) -> tuple[str, int]:
    candidate = value.strip()
    if not candidate:
        raise ValueError("FTP_SERVER secret is empty")
    parsed = urlsplit(candidate if "://" in candidate else f"ftps://{candidate}")
    if parsed.scheme not in {"ftp", "ftps"} or not parsed.hostname:
        raise ValueError("FTP_SERVER must be a host name or an ftp/ftps URL")
    if parsed.path not in {"", "/"}:
        raise ValueError("Put the remote directory in FTP_SERVER_DIR, not FTP_SERVER")
    return parsed.hostname, parsed.port or 21


def git_files() -> dict[str, int]:
    raw = subprocess.check_output(["git", "ls-files", "-z"])
    paths = raw.decode("utf-8").rstrip("\0").split("\0") if raw else []
    return {
        path: Path(path).stat().st_size
        for path in paths
        if is_deployment_file(path)
    }


def scan_ftp(ftp: ftplib.FTP_TLS, root: str) -> dict[str, int]:
    # ponytail: this host is expected to support MLSD; add a LIST fallback only if it proves too old.
    files: dict[str, int] = {}
    pending = [root]
    while pending:
        directory = pending.pop()
        for name, facts in ftp.mlsd(directory, facts=["type", "size"]):
            if name in {".", ".."}:
                continue
            remote_path = posixpath.join(directory.rstrip("/"), name) or "/"
            relative = posixpath.relpath(remote_path, root)
            if relative.startswith("./"):
                relative = relative[2:]
            entry_type = facts.get("type", "")
            if entry_type == "dir":
                pending.append(remote_path)
            elif entry_type == "file" and relative not in REMOTE_EXCLUDES:
                files[relative] = int(facts.get("size", 0))
    return files


def download(ftp: ftplib.FTP_TLS, root: str, relative: str) -> bytes:
    buffer = io.BytesIO()
    remote_path = posixpath.join(root.rstrip("/"), relative)
    ftp.retrbinary(f"RETR {remote_path}", buffer.write, blocksize=256 * 1024)
    return buffer.getvalue()


def entry(path: str, local_size: int | None, remote_size: int | None) -> dict[str, object]:
    return {"path": path, "github_size": local_size, "ftp_size": remote_size}


def write_reports(report: dict[str, object]) -> None:
    Path("ftp-compare.json").write_text(
        json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    categories = ["same", "line_endings_only", "different", "github_only", "ftp_only", "errors"]
    labels = {
        "same": "完全相同",
        "line_endings_only": "只有換行格式不同",
        "different": "內容不同",
        "github_only": "只有 GitHub",
        "ftp_only": "只有 FTP",
        "errors": "比對錯誤",
    }
    lines = ["GitHub / FTP 唯讀比對報告", ""]
    for category in categories:
        items = report[category]
        lines.append(f"{labels[category]}: {len(items)}")
    for category in categories[1:]:
        items = report[category]
        if not items:
            continue
        lines.extend(["", f"[{labels[category]}]"])
        for item in items:
            if isinstance(item, dict):
                lines.append(
                    f"{item['path']} (GitHub={item.get('github_size')}, FTP={item.get('ftp_size')})"
                )
            else:
                lines.append(str(item))
    text = "\n".join(lines) + "\n"
    Path("ftp-compare.txt").write_text(text, encoding="utf-8")
    print("\n".join(lines[:8]))
    summary = os.environ.get("GITHUB_STEP_SUMMARY")
    if summary:
        with open(summary, "a", encoding="utf-8") as stream:
            stream.write("## GitHub / FTP 唯讀比對\n\n")
            for category in categories:
                stream.write(f"- {labels[category]}：{len(report[category])}\n")
            stream.write("\n完整清單請下載 `ftp-comparison` artifact。\n")


def compare() -> int:
    required = ["FTP_SERVER", "FTP_USERNAME", "FTP_PASSWORD", "FTP_SERVER_DIR"]
    missing = [name for name in required if not os.environ.get(name)]
    if missing:
        raise RuntimeError(f"Missing GitHub settings: {', '.join(missing)}")

    host, port = parse_server(os.environ["FTP_SERVER"])
    root = "/" + os.environ["FTP_SERVER_DIR"].strip("/")
    local = git_files()
    report: dict[str, list[object]] = {
        "same": [], "line_endings_only": [], "different": [],
        "github_only": [], "ftp_only": [], "errors": [],
    }

    with ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=120) as ftp:
        ftp.connect(host, port)
        ftp.login(os.environ["FTP_USERNAME"], os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        try:
            ftp.sendcmd("OPTS UTF8 ON")
        except ftplib.error_perm:
            pass

        remote = scan_ftp(ftp, root)
        local_paths, remote_paths = set(local), set(remote)
        report["github_only"] = [entry(path, local[path], None) for path in sorted(local_paths - remote_paths)]
        report["ftp_only"] = [entry(path, None, remote[path]) for path in sorted(remote_paths - local_paths)]

        for path in sorted(local_paths & remote_paths):
            item = entry(path, local[path], remote[path])
            is_text = PurePosixPath(path).suffix.lower() in TEXT_SUFFIXES
            if not is_text and local[path] != remote[path]:
                report["different"].append(item)
                continue
            try:
                local_data = Path(path).read_bytes()
                remote_data = download(ftp, root, path)
                if hashlib.sha256(local_data).digest() == hashlib.sha256(remote_data).digest():
                    report["same"].append(item)
                elif is_text and normalize_newlines(local_data) == normalize_newlines(remote_data):
                    report["line_endings_only"].append(item)
                else:
                    report["different"].append(item)
            except (OSError, ftplib.Error) as exc:
                report["errors"].append(f"{path}: {type(exc).__name__}: {exc}")

    write_reports(report)
    return 1 if report["errors"] else 0


def self_check() -> None:
    assert is_deployment_file("index.html")
    assert not is_deployment_file(".github/workflows/deploy.yml")
    assert not is_deployment_file("scripts/compare-ftp.py")
    assert normalize_newlines(b"a\r\nb\rc\n") == b"a\nb\nc\n"
    assert parse_server("example.com") == ("example.com", 21)
    print("Self-check passed.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--self-check", action="store_true")
    args = parser.parse_args()
    if args.self_check:
        self_check()
    else:
        raise SystemExit(compare())
