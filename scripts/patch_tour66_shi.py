#!/usr/bin/env python3
import json
from renjunet_sync import Bridge

TOUR_ID = 66
SHI_PLAYER_ID = 363
SHI_RENJUNET_DISP_ID = '100132'


def main():
    with Bridge() as db:
        players = db.query(
            "SELECT `代號`,`姓名`,`RIF`,`國家`,`顯示` FROM `PLAYER` "
            "WHERE `姓名` IN ('李士文','師曉林') ORDER BY `代號`"
        )
        shi = [r for r in players if int(r['代號']) == SHI_PLAYER_ID and r['姓名'] == '師曉林']
        li = [r for r in players if r['姓名'] == '李士文']
        if len(shi) != 1:
            raise RuntimeError(f"PLAYER 363 師曉林驗證失敗: {shi}")
        if len(li) != 1:
            raise RuntimeError(f"李士文 PLAYER 資料不是唯一一筆: {li}")
        li_id = int(li[0]['代號'])
        if li_id == SHI_PLAYER_ID:
            raise RuntimeError('李士文與師曉林代號不應相同')

        rn_players = db.query(
            "SELECT `id`,`disp_id`,`surname`,`name`,`native_name` FROM `RENJUNET_PLAYER` "
            f"WHERE `disp_id`='{SHI_RENJUNET_DISP_ID}'"
        )
        if len(rn_players) != 1:
            raise RuntimeError(f"RenjuNet disp_id={SHI_RENJUNET_DISP_ID} 找不到唯一棋手: {rn_players}")
        shi_renjunet_id = int(rn_players[0]['id'])

        before = db.query(
            f"SELECT "
            f"SUM(CASE WHEN `P1`={li_id} THEN 1 ELSE 0 END) AS p1_li,"
            f"SUM(CASE WHEN `P2`={li_id} THEN 1 ELSE 0 END) AS p2_li,"
            f"SUM(CASE WHEN `P1`={SHI_PLAYER_ID} THEN 1 ELSE 0 END) AS p1_shi,"
            f"SUM(CASE WHEN `P2`={SHI_PLAYER_ID} THEN 1 ELSE 0 END) AS p2_shi "
            f"FROM `GAME` WHERE `比賽`={TOUR_ID}"
        )[0]

        rank_before = db.query(
            f"SELECT `代號`,`績分`,`勝`,`和`,`負` FROM `RANK` WHERE `比賽`={TOUR_ID} AND `代號` IN ({li_id},{SHI_PLAYER_ID}) ORDER BY `代號`"
        )
        if any(int(r['代號']) == SHI_PLAYER_ID for r in rank_before) and any(int(r['代號']) == li_id for r in rank_before):
            raise RuntimeError(f"賽號 {TOUR_ID} 的 RANK 同時已有李士文與師曉林，停止自動更新: {rank_before}")

        statements = [
            f"UPDATE `GAME` SET `P1`={SHI_PLAYER_ID} WHERE `比賽`={TOUR_ID} AND `P1`={li_id}",
            f"UPDATE `GAME` SET `P2`={SHI_PLAYER_ID} WHERE `比賽`={TOUR_ID} AND `P2`={li_id}",
            f"UPDATE `RANK` SET `代號`={SHI_PLAYER_ID} WHERE `比賽`={TOUR_ID} AND `代號`={li_id}",
            f"DELETE FROM `PLAYER_RENJUNET` WHERE `player_id`={SHI_PLAYER_ID} OR `renjunet_player_id`={shi_renjunet_id}",
            "INSERT INTO `PLAYER_RENJUNET` (`player_id`,`renjunet_player_id`,`matched_by`,`note`) "
            f"VALUES ({SHI_PLAYER_ID},{shi_renjunet_id},'manual','師曉林；RenjuNet disp_id {SHI_RENJUNET_DISP_ID}；賽號66資料修正')",
        ]
        db.batch(statements)

        after = db.query(
            f"SELECT "
            f"SUM(CASE WHEN `P1`={li_id} THEN 1 ELSE 0 END) AS p1_li,"
            f"SUM(CASE WHEN `P2`={li_id} THEN 1 ELSE 0 END) AS p2_li,"
            f"SUM(CASE WHEN `P1`={SHI_PLAYER_ID} THEN 1 ELSE 0 END) AS p1_shi,"
            f"SUM(CASE WHEN `P2`={SHI_PLAYER_ID} THEN 1 ELSE 0 END) AS p2_shi "
            f"FROM `GAME` WHERE `比賽`={TOUR_ID}"
        )[0]
        rank_after = db.query(
            f"SELECT `比賽`,`代號`,`績分`,`勝`,`和`,`負` FROM `RANK` "
            f"WHERE `比賽`={TOUR_ID} AND `代號` IN ({li_id},{SHI_PLAYER_ID}) ORDER BY `代號`"
        )
        mapping = db.query(
            f"SELECT `player_id`,`renjunet_player_id`,`matched_by`,`note` FROM `PLAYER_RENJUNET` "
            f"WHERE `player_id`={SHI_PLAYER_ID}"
        )

        if int(after.get('p1_li') or 0) != 0 or int(after.get('p2_li') or 0) != 0:
            raise RuntimeError(f"賽號 {TOUR_ID} 仍有李士文對局: {after}")
        if not mapping or int(mapping[0]['renjunet_player_id']) != shi_renjunet_id:
            raise RuntimeError(f"師曉林 PLAYER_RENJUNET mapping 未建立: {mapping}")

        print(json.dumps({
            'tour_id': TOUR_ID,
            'li_player_id': li_id,
            'shi_player_id': SHI_PLAYER_ID,
            'shi_renjunet_disp_id': SHI_RENJUNET_DISP_ID,
            'shi_renjunet_player_id': shi_renjunet_id,
            'renjunet_player': rn_players[0],
            'before': before,
            'after': after,
            'rank_before': rank_before,
            'rank_after': rank_after,
            'mapping': mapping,
        }, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
