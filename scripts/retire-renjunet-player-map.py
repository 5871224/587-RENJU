#!/usr/bin/env python3
"""One-time archival retirement of PLAYER_RENJUNET after checking live identities.

Permanently stops use of the original name while retaining the 3 historical
rows, including matched_by and note, in an archive for rollback.
"""
from collections import defaultdict
from renjunet_sync import Bridge

SOURCE = "PLAYER_RENJUNET"
ARCHIVE = "PLAYER_RENJUNET_ARCHIVE_20261011"


def normalized(value):
    digits = "".join(ch for ch in str(value or "") if ch.isdecimal())
    return str(int(digits)) if digits else ""


def main():
    with Bridge() as db:
        present = {next(iter(t.values())) for t in db.query("SHOW TABLES")}
        if SOURCE not in present:
            if ARCHIVE not in present:
                raise RuntimeError("Neither active nor archived RenjuNet mapping table was found")
            print("Retirement already complete; active mapping table absent.")
            return
        if ARCHIVE in present:
            raise RuntimeError("Both active and archive tables exist; refusing to overwrite archive")

        players = {int(row["代號"]): row for row in db.query("SELECT 代號,ID,RIF FROM PLAYER")}
        renju = db.query("SELECT id,disp_id FROM RENJUNET_PLAYER")
        renju_ids = {int(row["id"]) for row in renju}
        by_disp = defaultdict(set)
        for row in renju:
            key = normalized(row["disp_id"])
            if key:
                by_disp[key].add(int(row["id"]))
        mappings = db.query("SELECT player_id,renjunet_player_id FROM PLAYER_RENJUNET")
        if len(mappings) != 3:
            raise RuntimeError(f"Unexpected mapping row count: {len(mappings)}, expected 3; not archiving")
        for row in mappings:
            pid = int(row["player_id"])
            target = int(row["renjunet_player_id"])
            person = players.get(pid)
            if not person or target not in renju_ids:
                raise RuntimeError(f"Unmapped or invalid historical link for local player {pid}")
            direct = int(person["ID"] or 0)
            id_valid = direct in renju_ids
            rif_options = by_disp.get(normalized(person["RIF"]), set())
            if id_valid and direct != target:
                raise RuntimeError(f"Existing PLAYER.ID contradicts mapping at player {pid}")
            if len(rif_options) == 1 and target not in rif_options:
                raise RuntimeError(f"Existing PLAYER.RIF contradicts mapping at player {pid}")
            if not ((id_valid and direct == target) or (len(rif_options) == 1 and target in rif_options)):
                raise RuntimeError(f"PLAYER.ID/RIF cannot recreate mapping at player {pid}")

        print(f"Verified all {len(mappings)} historical mappings from PLAYER.ID/RIF.")
        db.exec("RENAME TABLE PLAYER_RENJUNET TO PLAYER_RENJUNET_ARCHIVE_20261011")
        rows = db.query("SELECT COUNT(*) AS n FROM PLAYER_RENJUNET_ARCHIVE_20261011")
        if int(rows[0]["n"]) != len(mappings):
            raise RuntimeError("Archive row-count verification failed")
        present_after = {next(iter(t.values())) for t in db.query("SHOW TABLES")}
        if SOURCE in present_after:
            raise RuntimeError("Retired source table still exists")
        print(f"Retired {SOURCE}; verified {ARCHIVE} contains {len(mappings)} preserved rows.")


if __name__ == "__main__":
    main()
