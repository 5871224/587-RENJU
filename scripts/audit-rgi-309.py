#!/usr/bin/env python3
"""Read-only comparison for ranked tournament 309; log no personal names."""
from collections import Counter, defaultdict
from renjunet_sync import Bridge

def numeric_key(v):
    digits="".join(c for c in str(v or "") if c.isdecimal())
    return str(int(digits)) if digits else ""

with Bridge() as db:
    tours=db.query("SELECT 賽號,棋譜 FROM TOURNAMENT WHERE 賽號=309")
    print("AUDIT_309_TOURS:",tours,flush=True)
    if not tours: raise RuntimeError("Ranked tournament 309 was not found")
    tid=numeric_key(tours[0]["棋譜"])
    if not tid: raise RuntimeError("Tournament is not linked to RenjuNet")
    locals=db.query("SELECT 輪次,P1,P2,勝負,備註,棋譜 FROM GAME WHERE 比賽=309")
    srcs=db.query("SELECT id,round,black_player_id,white_player_id,black_result FROM RENJUNET_GAME WHERE tournament_id="+tid)
    peeps=db.query("SELECT 代號,ID,RIF FROM PLAYER WHERE 代號 IN (SELECT P1 FROM GAME WHERE 比賽=309 UNION SELECT P2 FROM GAME WHERE 比賽=309)")
    rnp=db.query("SELECT id,disp_id FROM RENJUNET_PLAYER")
    valid={int(x["id"]) for x in rnp}
    disp=defaultdict(set)
    for p in rnp:
        key=numeric_key(p["disp_id"])
        if key: disp[key].add(int(p["id"]))
    mappings={}
    for p in peeps:
        pid=int(p["代號"]);direct=int(p["ID"] or 0)
        if direct in valid: mappings[pid]=direct
        else:
            targets=disp.get(numeric_key(p["RIF"]),set())
            if len(targets)==1: mappings[pid]=next(iter(targets))
    pairs=defaultdict(list)
    for g in srcs:
        key=tuple(sorted([int(g["black_player_id"]),int(g["white_player_id"])]))
        pairs[key].append(g)
    summary=Counter()
    examples=[]
    for g in locals:
        if g["棋譜"] or g["備註"] or int(g["P1"] or 0)<=0 or int(g["P2"] or 0)<=0: continue
        a=mappings.get(int(g["P1"]));b=mappings.get(int(g["P2"]))
        if not a or not b:
            summary["missing_identity"]+=1
            continue
        same_pair=pairs.get(tuple(sorted([a,b])),[])
        if not same_pair:
            summary["no_pair"]+=1
            continue
        summary["pair_found"]+=1
        score=float(g["勝負"])
        matches=[x for x in same_pair if abs((2*float(x["black_result"]) if a==int(x["black_player_id"]) else 2-2*float(x["black_result"]))-score)<.001]
        if matches: summary["score_matched"]+=1
        else: summary["score_mismatched"]+=1
        same_round=[x for x in same_pair if numeric_key(x["round"])==numeric_key(g["輪次"])]
        if same_round: summary["round_matched"]+=1
        else: summary["round_unmatched"]+=1
        if len(examples)<12 and (not matches or not same_round or a!=int(same_pair[0]["black_player_id"])):
            examples.append({"round":g["輪次"],"p1":a,"p2":b,"local_score":g["勝負"],"sources":[{"game_id":x["id"],"round":x["round"],"black":x["black_player_id"],"white":x["white_player_id"],"black_result":x["black_result"]} for x in same_pair[:2]]})
    print("AUDIT_309_COUNTS:",{"local":len(locals),"source":len(srcs),"resolved_players":len(mappings),"local_rounds":dict(Counter(str(x["輪次"]) for x in locals)),"renju_rounds":dict(Counter(str(x["round"]) for x in srcs))},flush=True)
    print("AUDIT_309_MATCHING:",dict(summary),flush=True)
    print("AUDIT_309_EXAMPLES:",examples,flush=True)
