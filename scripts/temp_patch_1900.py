from pathlib import Path

p=Path('rank/rating-review.php')
s=p.read_text(encoding='utf-8')
s=s.replace('同一次重算結果，同時查看逐場差異、計算完整性與每位棋士最後差異；現階段全部唯讀，不會更新 RANK。','同一次重算結果，同時查看逐場差異、計算完整性與每位棋士最後差異；預覽本身唯讀，只有按「一鍵重建正式台灣排名」才會更新 RANK。')
p.write_text(s,encoding='utf-8')
