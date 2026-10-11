#!/usr/bin/env python3
"""One-time read-only structural inspection for safe GAME score rebuild."""
from renjunet_sync import Bridge
with Bridge() as db:
    for name in ("GAME", "RANK"):
        columns=db.query("SHOW COLUMNS FROM `"+name+"`")
        interesting=[{"Field":str(c.get("Field")), "Type":str(c.get("Type")), "Key":str(c.get("Key")), "Null":str(c.get("Null"))} for c in columns if name=="RANK" or c.get("Field") in ("比賽","輪次","P1","P2","P1分","P2分","備註","棋譜")]
        print("RATING_STRUCT_"+name+"_COLS:",interesting,flush=True)
        info=db.query("SHOW TABLE STATUS LIKE '"+name+"'")
        print("RATING_STRUCT_"+name+"_STATUS:",[{"Engine":s.get("Engine"),"Rows":s.get("Rows")} for s in info],flush=True)
    for n in ("GAME","RANK"):
        t=db.query("SHOW TRIGGERS LIKE '"+n+"'")
        print("RATING_STRUCT_"+n+"_TRIGGERS:",len(t),flush=True)
    rows=db.query("SELECT COUNT(*) AS n, SUM(P1分 IS NULL OR P1分=0 OR P2分 IS NULL OR P2分=0) AS missing FROM GAME WHERE 比賽=309")
    print("RATING_STRUCT_309:",rows,flush=True)
