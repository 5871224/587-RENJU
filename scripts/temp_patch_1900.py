from pathlib import Path

p = Path('rank/lib/rating.php')
s = p.read_text(encoding='utf-8')
old = """                } elseif ($renjuNetPlayerId === null || $renjuNetPlayerId <= 0) {
                    if ($tourId === 233) {
                        // RenjuNet 沒有安吉 C 組資料，未建立世界歷史分數的外國新人統一從 1900 起算。
                        $startRating = (float)RN_ELO_INITIAL_RATING;
                        $ratingSource = 'renjunet_initial';
                    } else {
                        $warnings[] = \"賽號 {$tourId} 外部棋士 {$playerId} 找不到 PLAYER_RENJUNET／RIF 對應，無法取得 RenjuNet Elo；暫用 GAME 保存分數\";
                        $startRating = ($savedStart !== null && $savedStart >= 1000) ? $savedStart : (float)RN_ELO_INITIAL_RATING;
                        $ratingSource = ($savedStart !== null && $savedStart >= 1000) ? 'game_saved_fallback' : 'renjunet_initial_fallback';
                    }
"""
new = """                } elseif ($renjuNetPlayerId === null || $renjuNetPlayerId <= 0) {
                    // 找不到 RenjuNet 身份／歷史 Elo 時，比照賽號 233 的特殊情況固定以 1900 起算；
                    // 不再回用 GAME.P1分/P2分，避免舊保存值被誤當成世界 Elo。
                    $warnings[] = \"賽號 {$tourId} 外部棋士 {$playerId} 找不到 PLAYER_RENJUNET／RIF 對應，固定以 \" . RN_ELO_INITIAL_RATING . \" 起算\";
                    $startRating = (float)RN_ELO_INITIAL_RATING;
                    $ratingSource = 'renjunet_initial_fallback';
"""
if old not in s:
    raise SystemExit('rating.php fallback block not found')
p.write_text(s.replace(old, new, 1), encoding='utf-8')

p = Path('rank/rating-review.php')
s = p.read_text(encoding='utf-8')
s = s.replace("'note'=>'找不到 RenjuNet 對應而退回 GAME／1900 的資料'", "'note'=>'找不到 RenjuNet 對應而固定使用 1900 的資料'")
s = s.replace('只有找不到正常 RenjuNet 來源、被迫退回舊 GAME 分數或 1900 的資料才列在這裡', '只有找不到正常 RenjuNet 對應、因此固定以 1900 起算的資料才列在這裡')
p.write_text(s, encoding='utf-8')
