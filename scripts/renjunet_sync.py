#!/usr/bin/env python3
import ftplib
import hashlib
import io
import json
import os
import re
import secrets
import time
import urllib.request
import urllib.error
import xml.etree.ElementTree as ET
from collections import Counter
from pathlib import Path

BASE_URL = os.environ.get("RENJUNET_SITE_URL", "https://587.renju.org.tw").rstrip("/")
RIF = Path(os.environ.get("RENJUNET_RIF", "/tmp/renjunet.rif"))
SOURCE_URL = "https://www.renju.net/game/download/xml/"
TABLE_MAP = {
    "country": "RENJUNET_COUNTRY", "city": "RENJUNET_CITY", "month": "RENJUNET_MONTH",
    "rule": "RENJUNET_RULE", "opening": "RENJUNET_OPENING", "player": "RENJUNET_PLAYER",
    "tournament": "RENJUNET_TOURNAMENT", "game": "RENJUNET_GAME",
}
TABLES = list(TABLE_MAP.values())
STAGE = {name: name + "_SYNC" for name in TABLES}

DDL = {
"RENJUNET_COUNTRY": """CREATE TABLE IF NOT EXISTS `RENJUNET_COUNTRY` (`id` INT UNSIGNED NOT NULL,`name` VARCHAR(100) NOT NULL,`abbr` VARCHAR(10) NOT NULL DEFAULT '',`reversed` TINYINT(1) NOT NULL DEFAULT 0,PRIMARY KEY (`id`),KEY `idx_abbr` (`abbr`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci""",
"RENJUNET_CITY": """CREATE TABLE IF NOT EXISTS `RENJUNET_CITY` (`id` INT UNSIGNED NOT NULL,`country_id` INT UNSIGNED NOT NULL,`name` VARCHAR(150) NOT NULL,PRIMARY KEY (`id`),KEY `idx_country` (`country_id`),KEY `idx_name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci""",
"RENJUNET_MONTH": """CREATE TABLE IF NOT EXISTS `RENJUNET_MONTH` (`id` TINYINT UNSIGNED NOT NULL,`name` VARCHAR(20) NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci""",
"RENJUNET_RULE": """CREATE TABLE IF NOT EXISTS `RENJUNET_RULE` (`id` SMALLINT UNSIGNED NOT NULL,`name` VARCHAR(100) NOT NULL,`category` TINYINT UNSIGNED NULL,`info` TEXT NULL,PRIMARY KEY (`id`),KEY `idx_category` (`category`),KEY `idx_name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci""",
"RENJUNET_OPENING": """CREATE TABLE IF NOT EXISTS `RENJUNET_OPENING` (`id` SMALLINT UNSIGNED NOT NULL,`abbr` VARCHAR(20) NOT NULL DEFAULT '',`name` VARCHAR(100) NOT NULL,PRIMARY KEY (`id`),KEY `idx_abbr` (`abbr`),KEY `idx_name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci""",
"RENJUNET_PLAYER": """CREATE TABLE IF NOT EXISTS `RENJUNET_PLAYER` (`id` INT UNSIGNED NOT NULL,`disp_id` VARCHAR(20) NOT NULL,`name` VARCHAR(100) NOT NULL,`surname` VARCHAR(100) NOT NULL,`native_name` VARCHAR(150) NULL,`country_id` INT UNSIGNED NOT NULL,`city_id` INT UNSIGNED NOT NULL,`gender` TINYINT UNSIGNED NOT NULL DEFAULT 0,`birth` DATE NULL,PRIMARY KEY (`id`),KEY `idx_disp_id` (`disp_id`),KEY `idx_country` (`country_id`),KEY `idx_city` (`city_id`),KEY `idx_native_name` (`native_name`),KEY `idx_name` (`surname`,`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci""",
"RENJUNET_TOURNAMENT": """CREATE TABLE IF NOT EXISTS `RENJUNET_TOURNAMENT` (`id` INT UNSIGNED NOT NULL,`name` VARCHAR(255) NOT NULL,`country_id` INT UNSIGNED NOT NULL,`city_id` INT UNSIGNED NOT NULL,`year` SMALLINT UNSIGNED NULL,`month_id` TINYINT UNSIGNED NULL,`start_date` DATE NULL,`end_date` DATE NULL,`rule_id` SMALLINT UNSIGNED NULL,`rated` TINYINT(1) NOT NULL DEFAULT 0,`type` TINYINT UNSIGNED NULL,PRIMARY KEY (`id`),KEY `idx_dates` (`start_date`,`end_date`),KEY `idx_country` (`country_id`),KEY `idx_city` (`city_id`),KEY `idx_rule` (`rule_id`),KEY `idx_name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci""",
"RENJUNET_GAME": """CREATE TABLE IF NOT EXISTS `RENJUNET_GAME` (`id` INT UNSIGNED NOT NULL,`publisher_id` INT UNSIGNED NOT NULL,`tournament_id` INT UNSIGNED NOT NULL,`round` VARCHAR(50) NULL,`rule_id` SMALLINT UNSIGNED NOT NULL,`black_player_id` INT UNSIGNED NOT NULL,`white_player_id` INT UNSIGNED NOT NULL,`black_result` DECIMAL(3,1) NOT NULL,`black_time` DECIMAL(10,2) NULL,`white_time` DECIMAL(10,2) NULL,`opening_id` SMALLINT UNSIGNED NOT NULL,`alt` VARCHAR(100) NOT NULL DEFAULT '',`swap` VARCHAR(20) NOT NULL DEFAULT '',`moves` TEXT NOT NULL,`info` TEXT NULL,PRIMARY KEY (`id`),KEY `idx_tournament` (`tournament_id`),KEY `idx_black` (`black_player_id`),KEY `idx_white` (`white_player_id`),KEY `idx_players` (`black_player_id`,`white_player_id`),KEY `idx_publisher` (`publisher_id`),KEY `idx_rule` (`rule_id`),KEY `idx_opening` (`opening_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci""",
"RENJUNET_IMPORT": """CREATE TABLE IF NOT EXISTS `RENJUNET_IMPORT` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`source_file` VARCHAR(255) NOT NULL,`source_url` VARCHAR(500) NULL,`database_version` VARCHAR(20) NOT NULL,`database_date` DATE NOT NULL,`sha256` CHAR(64) NOT NULL,`imported_at` DATETIME NOT NULL,`country_count` INT UNSIGNED NOT NULL,`city_count` INT UNSIGNED NOT NULL,`month_count` INT UNSIGNED NOT NULL,`rule_count` INT UNSIGNED NOT NULL,`opening_count` INT UNSIGNED NOT NULL,`player_count` INT UNSIGNED NOT NULL,`tournament_count` INT UNSIGNED NOT NULL,`game_count` INT UNSIGNED NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `uq_sha256` (`sha256`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci""",
"PLAYER_RENJUNET": """CREATE TABLE IF NOT EXISTS `PLAYER_RENJUNET` (`player_id` INT NOT NULL,`renjunet_player_id` INT UNSIGNED NOT NULL,`matched_by` VARCHAR(50) NULL,`note` VARCHAR(255) NULL,PRIMARY KEY (`player_id`),UNIQUE KEY `uq_renjunet_player` (`renjunet_player_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci""",
}

def qstr(value):
    if value is None: return "NULL"
    raw=str(value).encode("utf-8")
    if not raw: return "''"
    return "CONVERT(0x" + raw.hex() + " USING utf8mb4)"

def qint(value):
    if value is None or str(value).strip() == "": return "NULL"
    return str(int(value))

def qdec(value):
    if value is None or str(value).strip() == "": return "NULL"
    return str(value)

class Bridge:
    def __init__(self):
        self.server=os.environ.get("FTP_SERVER","").strip(); self.username=os.environ.get("FTP_USERNAME","").strip(); self.password=os.environ.get("FTP_PASSWORD",""); self.server_dir=os.environ.get("FTP_SERVER_DIR","").strip()
        if not self.server or not self.username or not self.password: raise RuntimeError("Missing FTPS settings")
        self.token=secrets.token_hex(32); self.filename="rn_sync_"+secrets.token_hex(10)+".php"; self.expires=int(time.time())+7200; self.ftp=None
    def __enter__(self):
        php=f'''<?php
declare(strict_types=1); header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
if (time() > {self.expires}) {{ http_response_code(410); echo json_encode(['error'=>'expired']); exit; }}
$provided=(string)($_SERVER['HTTP_X_RENJUNET_TOKEN'] ?? ''); if (!hash_equals('{self.token}', $provided)) {{ http_response_code(404); echo json_encode(['error'=>'not found']); exit; }}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {{ http_response_code(405); echo json_encode(['error'=>'method']); exit; }}
$config=require __DIR__ . '/config.local.php'; $body=json_decode((string)file_get_contents('php://input'), true); $op=is_array($body)?(string)($body['op']??''):'';
$blocked='/\\b(GRANT|REVOKE|CREATE\\s+USER|ALTER\\s+USER|DROP\\s+USER|SET\\s+PASSWORD|INTO\\s+OUTFILE|INTO\\s+DUMPFILE|LOAD\\s+DATA|INSTALL\\s+PLUGIN|UNINSTALL\\s+PLUGIN|SHUTDOWN)\\b/i';
try {{
$pdo=new PDO('mysql:host='.$config['host'].';dbname=renjuorg_587;charset=utf8mb4',$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if ($op==='query') {{ $sql=trim((string)($body['sql']??'')); if ($sql===''||!preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\\b/i',ltrim($sql))||preg_match($blocked,$sql)) throw new RuntimeException('read-only query required'); echo json_encode(['rows'=>$pdo->query($sql)->fetchAll()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }}
if ($op==='exec') {{ $sql=trim((string)($body['sql']??'')); if ($sql===''||preg_match($blocked,$sql)) throw new RuntimeException('blocked SQL'); echo json_encode(['affected'=>$pdo->exec($sql)]); exit; }}
if ($op==='batch') {{ $items=$body['sql']??null; if (!is_array($items)||!$items) throw new RuntimeException('batch required'); $pdo->beginTransaction(); try {{ foreach($items as $sql) {{ $sql=trim((string)$sql); if($sql===''||preg_match($blocked,$sql)) throw new RuntimeException('blocked SQL'); $pdo->exec($sql); }} $pdo->commit(); }} catch(Throwable $e) {{ if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }} echo json_encode(['ok'=>true]); exit; }}
throw new RuntimeException('invalid operation');
}} catch(Throwable $e) {{ http_response_code(500); echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }}
'''
        self.ftp=ftplib.FTP_TLS(); self.ftp.connect(self.server,21,timeout=60); self.ftp.login(self.username,self.password); self.ftp.prot_p(); self._cwd(self.server_dir); self.ftp.storbinary("STOR "+self.filename,io.BytesIO(php.encode("utf-8"))); self.url=BASE_URL+"/"+self.filename; return self
    def _cwd(self,path):
        path=path.replace("\\","/").strip()
        if not path: return
        if path.startswith("/"): self.ftp.cwd("/")
        for part in path.strip("/").split("/"):
            if part: self.ftp.cwd(part)
    def call(self,op,sql,attempts=6):
        payload=json.dumps({"op":op,"sql":sql},ensure_ascii=False).encode("utf-8"); last=None
        for attempt in range(1,attempts+1):
            try:
                req=urllib.request.Request(self.url,data=payload,method="POST",headers={"Content-Type":"application/json","X-Renjunet-Token":self.token,"User-Agent":"renjunet-sync/2.0"})
                with urllib.request.urlopen(req,timeout=300) as response: data=json.loads(response.read().decode("utf-8"))
                if "error" in data: raise RuntimeError(data["error"])
                return data
            except urllib.error.HTTPError as exc:
                body=exc.read().decode("utf-8",errors="replace").strip()
                try:
                    parsed=json.loads(body)
                    detail=str(parsed.get("error",body)) if isinstance(parsed,dict) else body
                except Exception:
                    detail=body
                last=RuntimeError(f"HTTP {exc.code}: {detail}")
                if attempt==attempts: raise last
                time.sleep(min(15,attempt*2))
            except Exception as exc:
                last=exc
                if attempt==attempts: raise
                time.sleep(min(15,attempt*2))
        raise last
    def query(self,sql): return self.call("query",sql).get("rows",[])
    def exec(self,sql): return self.call("exec",sql)
    def batch(self,statements): return self.call("batch",statements)
    def __exit__(self,exc_type,exc,tb):
        if self.ftp:
            try: self.ftp.delete(self.filename)
            except Exception: pass
            try: self.ftp.quit()
            except Exception: pass

def parse_metadata():
    if not RIF.is_file() or RIF.stat().st_size==0: raise RuntimeError("RenjuNet RIF is missing")
    sha=hashlib.sha256(RIF.read_bytes()).hexdigest(); root=next(ET.iterparse(RIF,events=("start",)))[1]; meta=dict(root.attrib); date=meta.get("date",""); version=meta.get("version","")
    if version!="1.0" or not re.fullmatch(r"\d{4}-\d{2}-\d{2}",date): raise RuntimeError("Unexpected RenjuNet metadata: "+repr(meta))
    return meta,sha

def ensure_schema(db):
    for sql in DDL.values(): db.exec(sql)

def table_count(db,table):
    rows=db.query(f"SELECT COUNT(*) AS n FROM `{table}`"); return int(rows[0]["n"]) if rows else 0

def import_to_staging(db,meta,sha):
    for table in reversed(TABLES): db.exec(f"DROP TABLE IF EXISTS `{STAGE[table]}`")
    for table in TABLES: db.exec(f"CREATE TABLE `{STAGE[table]}` LIKE `{table}`")
    specs={"country":(["id","name","abbr","reversed"],500),"city":(["id","country_id","name"],500),"month":(["id","name"],100),"rule":(["id","name","category","info"],100),"opening":(["id","abbr","name"],100),"player":(["id","disp_id","name","surname","native_name","country_id","city_id","gender","birth"],400),"tournament":(["id","name","country_id","city_id","year","month_id","start_date","end_date","rule_id","rated","type"],300),"game":(["id","publisher_id","tournament_id","round","rule_id","black_player_id","white_player_id","black_result","black_time","white_time","opening_id","alt","swap","moves","info"],220)}
    batches={tag:[] for tag in specs}; counts=Counter()
    def flush(tag):
        rows=batches[tag]
        if not rows: return
        cols,_=specs[tag]; table=STAGE[TABLE_MAP[tag]]; sql="INSERT INTO `"+table+"` ("+",".join("`"+c+"`" for c in cols)+") VALUES "+",".join("("+",".join(row)+")" for row in rows); db.exec(sql); batches[tag]=[]
        if tag=="game" and counts[tag]%5000<220: print("games imported:",counts[tag],flush=True)
    root_meta=None
    for event,el in ET.iterparse(RIF,events=("start","end")):
        if event=="start" and el.tag=="database" and root_meta is None:
            root_meta=dict(el.attrib)
            if root_meta.get("date")!=meta["date"] or root_meta.get("version")!=meta["version"]: raise RuntimeError("metadata changed while parsing")
            continue
        if event!="end": continue
        tag=el.tag; a=el.attrib; row=None
        if tag=="country": row=[qint(a["id"]),qstr(a["name"]),qstr(a.get("abbr","")),qint(a.get("reversed",0))]
        elif tag=="city": row=[qint(a["id"]),qint(a["country"]),qstr(a["name"])]
        elif tag=="month": row=[qint(a["id"]),qstr(a["name"])]
        elif tag=="rule": row=[qint(a["id"]),qstr(a["name"]),qint(a.get("category")),qstr(el.findtext("info"))]
        elif tag=="opening": row=[qint(a["id"]),qstr(a.get("abbr","")),qstr(a["name"])]
        elif tag=="player": row=[qint(a["id"]),qstr(a["disp_id"]),qstr(a.get("name","")),qstr(a.get("surname","")),qstr(a.get("native_name")),qint(a["country"]),qint(a["city"]),qint(a.get("gender",0)),qstr(a.get("birth"))]
        elif tag=="tournament": row=[qint(a["id"]),qstr(a["name"]),qint(a["country"]),qint(a["city"]),qint(a.get("year")),qint(a.get("month")),qstr(a.get("start")),qstr(a.get("end")),qint(a.get("rule")),qint(a.get("rated",0)),qint(a.get("type"))]
        elif tag=="game": row=[qint(a["id"]),qint(a["publisher"]),qint(a["tournament"]),qstr(a.get("round")),qint(a["rule"]),qint(a["black"]),qint(a["white"]),qdec(a["bresult"]),qdec(a.get("btime")),qdec(a.get("wtime")),qint(a["opening"]),qstr(a.get("alt","")),qstr(a.get("swap","")),qstr(el.findtext("move") or ""),qstr(el.findtext("info"))]
        if row is not None:
            counts[tag]+=1; batches[tag].append(row)
            if len(batches[tag])>=specs[tag][1]: flush(tag)
        if tag not in ("move","info"): el.clear()
    for tag in batches: flush(tag)
    if hashlib.sha256(RIF.read_bytes()).hexdigest()!=sha: raise RuntimeError("RIF changed while importing")
    for tag,table in TABLE_MAP.items():
        n=table_count(db,STAGE[table])
        if n!=counts[tag]: raise RuntimeError(f"{table} staging count {n} != parsed {counts[tag]}")
    checks={
      "city_country":"SELECT COUNT(*) AS n FROM `RENJUNET_CITY_SYNC` c LEFT JOIN `RENJUNET_COUNTRY_SYNC` p ON p.id=c.country_id WHERE p.id IS NULL",
      "player_country":"SELECT COUNT(*) AS n FROM `RENJUNET_PLAYER_SYNC` x LEFT JOIN `RENJUNET_COUNTRY_SYNC` p ON p.id=x.country_id WHERE p.id IS NULL",
      "player_city":"SELECT COUNT(*) AS n FROM `RENJUNET_PLAYER_SYNC` x LEFT JOIN `RENJUNET_CITY_SYNC` p ON p.id=x.city_id WHERE p.id IS NULL",
      "tour_country":"SELECT COUNT(*) AS n FROM `RENJUNET_TOURNAMENT_SYNC` x LEFT JOIN `RENJUNET_COUNTRY_SYNC` p ON p.id=x.country_id WHERE p.id IS NULL",
      "tour_city":"SELECT COUNT(*) AS n FROM `RENJUNET_TOURNAMENT_SYNC` x LEFT JOIN `RENJUNET_CITY_SYNC` p ON p.id=x.city_id WHERE p.id IS NULL",
      "tour_month":"SELECT COUNT(*) AS n FROM `RENJUNET_TOURNAMENT_SYNC` x LEFT JOIN `RENJUNET_MONTH_SYNC` p ON p.id=x.month_id WHERE x.month_id IS NOT NULL AND p.id IS NULL",
      "tour_rule":"SELECT COUNT(*) AS n FROM `RENJUNET_TOURNAMENT_SYNC` x LEFT JOIN `RENJUNET_RULE_SYNC` p ON p.id=x.rule_id WHERE x.rule_id IS NOT NULL AND p.id IS NULL",
      "game_publisher":"SELECT COUNT(*) AS n FROM `RENJUNET_GAME_SYNC` x LEFT JOIN `RENJUNET_PLAYER_SYNC` p ON p.id=x.publisher_id WHERE p.id IS NULL",
      "game_tournament":"SELECT COUNT(*) AS n FROM `RENJUNET_GAME_SYNC` x LEFT JOIN `RENJUNET_TOURNAMENT_SYNC` p ON p.id=x.tournament_id WHERE p.id IS NULL",
      "game_rule":"SELECT COUNT(*) AS n FROM `RENJUNET_GAME_SYNC` x LEFT JOIN `RENJUNET_RULE_SYNC` p ON p.id=x.rule_id WHERE p.id IS NULL",
      "game_black":"SELECT COUNT(*) AS n FROM `RENJUNET_GAME_SYNC` x LEFT JOIN `RENJUNET_PLAYER_SYNC` p ON p.id=x.black_player_id WHERE p.id IS NULL",
      "game_white":"SELECT COUNT(*) AS n FROM `RENJUNET_GAME_SYNC` x LEFT JOIN `RENJUNET_PLAYER_SYNC` p ON p.id=x.white_player_id WHERE p.id IS NULL",
      "game_opening":"SELECT COUNT(*) AS n FROM `RENJUNET_GAME_SYNC` x LEFT JOIN `RENJUNET_OPENING_SYNC` p ON p.id=x.opening_id WHERE p.id IS NULL",
      "mapping_preserved":"SELECT COUNT(*) AS n FROM `PLAYER_RENJUNET` m LEFT JOIN `RENJUNET_PLAYER_SYNC` p ON p.id=m.renjunet_player_id WHERE p.id IS NULL"}
    for name,query in checks.items():
        rows=db.query(query); n=int(rows[0]["n"]) if rows else -1
        if n!=0: raise RuntimeError(f"Referential check failed: {name}={n}")
    return counts

def publish(db,meta,sha,counts):
    delete_order=["RENJUNET_GAME","RENJUNET_TOURNAMENT","RENJUNET_PLAYER","RENJUNET_CITY","RENJUNET_COUNTRY","RENJUNET_MONTH","RENJUNET_RULE","RENJUNET_OPENING"]
    insert_order=["RENJUNET_COUNTRY","RENJUNET_CITY","RENJUNET_MONTH","RENJUNET_RULE","RENJUNET_OPENING","RENJUNET_PLAYER","RENJUNET_TOURNAMENT","RENJUNET_GAME"]
    statements=["SET FOREIGN_KEY_CHECKS=0"]+[f"DELETE FROM `{t}`" for t in delete_order]+[f"INSERT INTO `{t}` SELECT * FROM `{STAGE[t]}`" for t in insert_order]+["SET FOREIGN_KEY_CHECKS=1"]
    db.batch(statements)
    source_file="renjunet_v10_"+meta["date"].replace("-","")+".rif"
    db.exec("INSERT INTO `RENJUNET_IMPORT` (`source_file`,`source_url`,`database_version`,`database_date`,`sha256`,`imported_at`,`country_count`,`city_count`,`month_count`,`rule_count`,`opening_count`,`player_count`,`tournament_count`,`game_count`) VALUES ("+qstr(source_file)+","+qstr(SOURCE_URL)+","+qstr(meta["version"])+","+qstr(meta["date"])+","+qstr(sha)+",NOW(),"+",".join(str(counts[k]) for k in ("country","city","month","rule","opening","player","tournament","game"))+")")
    for t in reversed(TABLES): db.exec(f"DROP TABLE IF EXISTS `{STAGE[t]}`")

def validate_live(db,counts=None):
    result={}
    for tag,table in TABLE_MAP.items():
        result[tag]=table_count(db,table)
        if counts is not None and result[tag]!=counts[tag]: raise RuntimeError(f"Published count mismatch: {table}")
    mapping=table_count(db,"PLAYER_RENJUNET"); missing=db.query("SELECT COUNT(*) AS n FROM `PLAYER_RENJUNET` m LEFT JOIN `RENJUNET_PLAYER` p ON p.id=m.renjunet_player_id WHERE p.id IS NULL")
    if missing and int(missing[0]["n"])!=0: raise RuntimeError("PLAYER_RENJUNET contains missing RenjuNet player ids")
    return result,mapping

def write_summary(lines):
    path=os.environ.get("GITHUB_STEP_SUMMARY")
    if path:
        with open(path,"a",encoding="utf-8") as fh: fh.write("\n".join(lines)+"\n")

def main():
    meta,sha=parse_metadata(); print("RenjuNet database date:",meta["date"]); print("SHA256:",sha)
    with Bridge() as db:
        ensure_schema(db); latest=db.query("SELECT `database_date`,`sha256`,`imported_at` FROM `RENJUNET_IMPORT` ORDER BY `imported_at` DESC,`id` DESC LIMIT 1")
        if latest:
            old_date=str(latest[0]["database_date"]); old_sha=str(latest[0]["sha256"])
            if meta["date"]<old_date: raise RuntimeError(f"Downloaded RenjuNet data {meta['date']} is older than installed {old_date}")
            if sha==old_sha:
                counts,mapping=validate_live(db); print("Already current; no database changes needed."); write_summary(["## RenjuNet sync","- Status: already current",f"- Database date: {meta['date']}",f"- SHA256: `{sha}`",f"- Games: {counts['game']}",f"- Players: {counts['player']}",f"- PLAYER_RENJUNET mappings preserved: {mapping}"]); return 0
        counts=import_to_staging(db,meta,sha); publish(db,meta,sha,counts); live,mapping=validate_live(db,counts); print("RenjuNet sync completed successfully."); write_summary(["## RenjuNet sync","- Status: updated successfully",f"- Database date: {meta['date']}",f"- SHA256: `{sha}`",f"- Games: {live['game']}",f"- Players: {live['player']}",f"- PLAYER_RENJUNET mappings preserved: {mapping}"])
    return 0

if __name__=="__main__": raise SystemExit(main())
