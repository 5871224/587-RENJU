#!/usr/bin/env python3
"""Safely maintain the primary-domain Apache routes from the 587 web deploy.

The FTPS target is the website's subdomain_587 folder. The primary domain
uses its parent public_html folder and is not otherwise stored in Git.
Preserve all existing parent .htaccess rules and back up before updating.
"""
from __future__ import annotations

from io import BytesIO
import ftplib
import posixpath

BEGIN = b"# BEGIN 587-RENJU CANONICAL ROUTES"
END = b"# END 587-RENJU CANONICAL ROUTES"

ROUTES = r"""# BEGIN 587-RENJU CANONICAL ROUTES
# Keep the main domain and its www host on one canonical HTTPS address.
RewriteEngine On
RewriteCond %{HTTP_HOST} ^renju\.org\.tw$ [NC]
RewriteRule ^ https://www.renju.org.tw%{REQUEST_URI} [R=301,L,NE]

RewriteCond %{HTTP_HOST} ^www\.renju\.org\.tw$ [NC]
RewriteCond %{HTTPS} off
RewriteRule ^ https://www.renju.org.tw%{REQUEST_URI} [R=301,L,NE]

# Internally serve the same website files already deployed to subdomain_587.
# Other domains sharing public_html are not rewritten.
RewriteCond %{HTTP_HOST} ^www\.renju\.org\.tw$ [NC]
RewriteCond %{REQUEST_URI} !^/subdomain_587(/|$) [NC]
RewriteRule ^(.*)$ subdomain_587/$1 [L]

# Protect the pre-change .htaccess backups from direct HTTP access.
<FilesMatch "^\.htaccess">
    Require all denied
</FilesMatch>
# END 587-RENJU CANONICAL ROUTES
""".encode("ascii")


def merge_routes(original: bytes) -> bytes:
    """Idempotently prepend managed routes while preserving all other rules."""
    count_start, count_end = original.count(BEGIN), original.count(END)
    if count_start != count_end or count_start > 1:
        raise ValueError("Ambiguous managed route markers in primary .htaccess")
    if count_start:
        start = original.index(BEGIN)
        end = original.index(END)
        if end < start:
            raise ValueError("Reversed managed route markers in primary .htaccess")
        preserved = original[:start].rstrip(b"\r\n") + b"\n" + original[end + len(END):].lstrip(b"\r\n")
    else:
        preserved = original
    return ROUTES + (b"\n" + preserved if preserved else b"")


def configure_primary_domain(ftp: ftplib.FTP_TLS, base_dir: str, commit_sha: str) -> None:
    """Back up and update public_html/.htaccess, or fail before deploying 587."""
    base = base_dir.rstrip("/") or "/"
    if posixpath.basename(base) != "subdomain_587":
        raise RuntimeError("Refusing to change primary domain: FTPS target is not subdomain_587")

    ftp.cwd(base)
    ftp.cwd("..")
    parent = ftp.pwd()
    if parent == base:
        raise RuntimeError("FTPS account cannot access parent public_html; no domain files changed")

    try:
        chunks: list[bytes] = []
        try:
            ftp.retrbinary("RETR .htaccess", chunks.append)
            original = b"".join(chunks)
        except ftplib.error_perm as exc:
            # Never mistake an unreadable .htaccess for a missing file.
            # The current server file must be read before it can be preserved.
            raise RuntimeError(
                "Cannot safely read public_html/.htaccess over FTPS; aborting without changing it"
            ) from exc

        updated = merge_routes(original)
        if updated == original:
            print("Primary-domain .htaccess already has the canonical rules")
            return

        if original:
            backup_name = ".htaccess.renju-backup-" + commit_sha[:12]
            ftp.storbinary("STOR " + backup_name, BytesIO(original))
            print("Backed up original primary-domain .htaccess as " + backup_name)

        ftp.storbinary("STOR .htaccess", BytesIO(updated))
        check: list[bytes] = []
        ftp.retrbinary("RETR .htaccess", check.append)
        if b"".join(check) != updated:
            # Restore immediately if verification detects a truncated upload.
            ftp.storbinary("STOR .htaccess", BytesIO(original))
            raise RuntimeError("Primary-domain .htaccess read-back mismatch; restored original")
        print("Primary-domain .htaccess read-back MATCH")
    finally:
        ftp.cwd(base)
