#!/usr/bin/env python3
"""Read-only audit: PLAYER.ID/RIF versus PLAYER_RENJUNET.

Uses the short-lived authenticated bridge from the RenjuNet sync;
never writes to database. Logs only counts and internal numeric IDs.
"""
import collections
import json
from renjunet_sync import Bridge


def normalize(value):
    raw = str(value or "").strip()
    digits = "".join(ch for ch in raw if ch.isdecimal())
    return str(int(digits)) if digits else ""


def numeric_id(value):
    raw = str(value or "").strip()
    return int(raw) if raw.isdecimal() and int(raw) > 0 else 0


def audit(db):
    local = db.query("SELECT 代號, ID, RIF FROM PLAYER ORDER BY 代號")
    renju = db.query("SELECT id, disp_id FROM RENJUNET_PLAYER")
    mappings = db.query("SELECT player_id, renjunet_player_id FROM PLAYER_RENJUNET")
    by_id = {int(p["id"]) for p in renju}
    by_disp = collections.defaultdict(set)
    for p in renju:
        key = normalize(p["disp_id"])
        if key:
            by_disp[key].add(int(p["id"]))
    mapped = {int(p["player_id"]): int(p["renjunet_player_id"]) for p in mappings}
    counts = collections.Counter()
    problem = collections.defaultdict(list)
    seen_id = collections.defaultdict(list)
    assigned = collections.defaultdict(list)
    for p in local:
        lid = int(p["代號"])
        rawid = str(p["ID"] or "").strip()
        rawrif = str(p["RIF"] or "").strip()
        rid = numeric_id(rawid)
        rifids = by_disp.get(normalize(rawrif), set()) if rawrif else set()
        saved = mapped.get(lid, 0)
        if rawid:
            counts["id_filled"] += 1
            if not rid:
                problem["id_non_numeric"].append(lid)
            elif rid not in by_id:
                problem["id_unknown_in_renjunet"].append(lid)
            else:
                counts["id_valid"] += 1
                seen_id[rid].append(lid)
        if rawrif:
            counts["rif_filled"] += 1
            if len(rifids) == 1:
                counts["rif_unique"] += 1
            elif len(rifids) > 1:
                problem["rif_ambiguous"].append(lid)
            else:
                problem["rif_not_found"].append(lid)
        if rid in by_id and len(rifids) == 1 and rid not in rifids:
            problem["id_rif_conflict"].append(lid)
        if saved:
            counts["mapped_rows"] += 1
            if saved not in by_id:
                problem["map_unknown_in_renjunet"].append(lid)
            if rid and rid in by_id and rid != saved:
                problem["map_id_conflict"].append(lid)
            if len(rifids) == 1 and saved not in rifids:
                problem["map_rif_conflict"].append(lid)
            if rid == saved:
                counts["mapped_confirmed_by_id"] += 1
            elif saved in rifids and len(rifids) == 1:
                counts["mapped_confirmed_by_rif"] += 1
            elif rid not in by_id and not rifids:
                problem["map_only"].append(lid)
        if rid in by_id:
            target = rid
            counts["resolve_by_id"] += 1
        elif len(rifids) == 1:
            target = next(iter(rifids))
            counts["resolve_by_rif"] += 1
        else:
            target = 0
            counts["unresolved"] += 1
        if target:
            assigned[target].append(lid)
        if saved and target and saved != target:
            problem["effective_mapping_changes"].append(lid)
        if saved and not target:
            problem["mapping_would_be_lost"].append(lid)
    for rid, pids in assigned.items():
        if len(pids) > 1:
            problem["duplicate_resolved_renjunet_id"].extend(pids)
    for rid, pids in seen_id.items():
        if len(pids) > 1:
            problem["duplicate_player_id"].extend(pids)
    counts["player_rows"] = len(local)
    counts["renjunet_player_rows"] = len(renju)
    counts["orphaned_mapping_rows"] = len(set(mapped) - {int(p["代號"]) for p in local})
    risk = [
        "map_id_conflict", "map_rif_conflict", "id_rif_conflict",
        "effective_mapping_changes", "mapping_would_be_lost", "duplicate_resolved_renjunet_id",
        "id_unknown_in_renjunet", "id_non_numeric", "map_unknown_in_renjunet",
    ]
    report = {
        "counts": dict(sorted(counts.items())),
        "problems": {
            key: {"count": len(values), "sample_local_ids": values[:18]}
            for key, values in sorted(problem.items()) if values
        },
        "safe_to_remove_mapping_table_without_changes": not any(problem.get(k) for k in risk),
    }
    print("RENJUNET_PLAYER_IDENTITY_AUDIT=" + json.dumps(report, ensure_ascii=False, sort_keys=True), flush=True)
    return report["safe_to_remove_mapping_table_without_changes"]


if __name__ == "__main__":
    with Bridge() as database:
        audit(database)
