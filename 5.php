<!DOCTYPE HTML>
<html>

<HEAD>
	<meta charset="UTF-8" />	
	<meta name="viewport" content="width=454, user-scalable=no">
	<link rel=stylesheet href="bb/bb.css?v=3">
	<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
	<script src="bb/bb55.js?v=3"></script>
	<?php

	$title = "《Renju Gomoku》";
	$image = "bb/renju.png";

	if (isset($_GET['move'])) {
		$MOVE = substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], "?") + 1);
		try {
			require_once 'bb/boradlogin.php';

			$statement = $MYSQL->prepare("SELECT TITLE,PNG FROM borad WHERE MOVE = :move");
			$statement->execute(array(':move' => $MOVE));
			$row = $statement->fetch(PDO::FETCH_ASSOC);
			if ($row) {
				if ($row['TITLE'] != "") {
					$title = $row['TITLE'];
				}
				if ($row['PNG'] != "") {
					$image = $row['PNG'];
				}
			}
		} catch (Exception $e) {
			error_log("5.php metadata lookup failed: " . $e->getMessage());
		}
	}

	$safeTitle = htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$safeImage = htmlspecialchars((string)$image, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	echo "<meta property='og:title' content='" . $safeTitle . "'/>\n";
	echo "<meta property='og:image' content='" . $safeImage . "'/>\n";

	?>

	<style>
		.readmediv table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 10px;
			border-radius: 8px;
			overflow: hidden;
			border: 1px solid #ddd;
		}

		.readmediv td {
			padding: 8px 10px;
			border-bottom: 1px solid #eee;
			vertical-align: middle;
		}

		.title-row {
			background-color: #99ccff !important;
			font-weight: bold;
		}

		.level-1 {
			background-color: #d0e8ff;
		}

		.level-2 {
			background-color: #e8f4ff;
		}

		.level-3 {
			background-color: #f5faff;
		}

		.readmediv tr:last-child td {
			border-bottom: none;
		}
	</style>
</HEAD>

<body style='border: none; margin:auto; padding:0' class='page-with-fixed-buttons'>

	<div class="board587"></div>

	<br style="clear: both;" /><input class='readme' type='button' value='說明' check='0'>

	<div class='readmediv' style='font-size:1rem; display: none;'>
		<hr>
		五子棋打譜
		<table>
			<tbody>
				<tr class="title-row">
					<td colspan="2">使用說明</td>
				</tr>
				<tr class="level-1">
					<td><span style="background-color: #99ccff;">左鍵點棋盤</span></td>
					<td>次一手，編輯模式可落新子，若要PASS一手，可下在左下角</td>
				</tr>
				<tr class="level-1">
					<td><span style="background-color: #99ccff;">右鍵點棋盤</span></td>
					<td>前一手，<span style="background-color: #99ccff;">手機點棋盤座標文字</span>，也可回前一手</td>
				</tr>
				<tr class="level-1">
					<td colspan="2"><img src='bb/svg/edit.svg' width='20' style='vertical-align: middle;'>亮時，可修改盤面或文字，標題不隨盤面改變，解說會隨盤面變動</td>
				</tr>
				<tr class="title-row">
					<td colspan="2">按鈕說明</td>
				</tr>
				<tr class="level-1">
					<td><img src='bb/svg/double_arrow_left.svg' width='20' style='vertical-align: middle;'></td>
					<td>前一個分支，或起始點</td>
				</tr>
				<tr class="level-1">
					<td><img src='bb/svg/arrow_left.svg' width='20' style='vertical-align: middle;'></td>
					<td>前一手</td>
				</tr>
				<tr class="level-1">
					<td><img src='bb/svg/arrow_right.svg' width='20' style='vertical-align: middle;'></td>
					<td>次一手</td>
				</tr>
				<tr class="level-1">
					<td><img src='bb/svg/double_arrow_right.svg' width='20' style='vertical-align: middle;'></td>
					<td>次一個分支，或起始點</td>
				</tr>
				<tr class="level-1">
					<td><img src='bb/svg/share.svg' width='20' style='vertical-align: middle;'></td>
					<td>分享選項開關</td>
				</tr>
				<tr class="level-2">
					<td><img src='bb/svg/photo.svg' width='20' style='vertical-align: middle;'></td>
					<td>截圖，若不要標題或解說欄，可至<img src='bb/svg/settings.svg' width='20' style='vertical-align: middle;'>關閉</td>
				</tr>
				<tr class="level-2">
					<td><img src='bb/svg/link.svg' width='20' style='vertical-align: middle;'></td>
					<td>分享連結，建議落子數+標記數+文字數不要超過1000</td>
				</tr>
				<tr class="level-1">
					<td><img src='bb/svg/edit.svg' width='20' style='vertical-align: middle;'></td>
					<td>編輯模式開關</td>
				</tr>
				<tr class="level-2">
					<td><img src='bb/svg/font.svg' width='20' style='vertical-align: middle;'></td>
					<td>標記模式開關（<span style="background-color: #99ccff;">左鍵點棋盤</span>：標記。<span style="background-color: #99ccff;">右鍵點</span>：刪除）</td>
				</tr>
				<tr class="level-3">
					<td><img src='bb/svg/Aa.svg' width='20' style='vertical-align: middle;'><img src='bb/svg/star.svg' width='20' style='vertical-align: middle;'><img src='bb/svg/arrow.svg' width='20' style='vertical-align: middle;'></td>
					<td>切換標記欄預設文字，可手動輸入</td>
				</tr>
				<tr class="level-3">
					<td><img src='bb/svg/delete.svg' width='20' style='vertical-align: middle;'></td>
					<td>清空標記欄</td>
				</tr>
				<tr class="level-2">
					<td><img src='bb/svg/cancel.svg' width='20' style='vertical-align: middle;'></td>
					<td>刪除棋子及之後分支</td>
				</tr>
				<tr class="level-2">
					<td><img src='bb/svg/flag.svg' width='20' style='vertical-align: middle;'></td>
					<td>設定定位點</td>
				</tr>
				<tr class="level-2">
					<td><img src='bb/svg/flag_check.svg' width='20' style='vertical-align: middle;'></td>
					<td>跳到定位點</td>
				</tr>
				<tr class="level-2">
					<td><img src='bb/svg/number.svg' width='20' style='vertical-align: middle;'></td>
					<td>手順開關</td>
				</tr>
				<tr class="level-2">
					<td><img src='bb/svg/settings.svg' width='20' style='vertical-align: middle;'></td>
					<td>設定選項開關（分享連結也會是相同設定）</td>
				</tr>
				<tr class="level-3">
					<td><img src='bb/svg/dock_top.svg' width='20' style='vertical-align: middle;'></td>
					<td>標題欄開關</td>
				</tr>
				<tr class="level-3">
					<td><img src='bb/svg/dock_left.svg' width='20' style='vertical-align: middle;'></td>
					<td>解說欄開關</td>
				</tr>
				<tr class="level-3">
					<td><img src='bb/svg/flip.svg' width='20' style='vertical-align: middle;'></td>
					<td>鏡像盤面</td>
				</tr>
				<tr class="level-3">
					<td><img src='bb/svg/rotate_90.svg' width='20' style='vertical-align: middle;'></td>
					<td>旋轉90度盤面</td>
				</tr>
				<tr class="level-3">
					<td><img src='bb/svg/forbidden.svg' width='20' style='vertical-align: middle;'></td>
					<td>顯示禁手</td>
				</tr>
				<tr class="level-3">
					<td><img src='bb/svg/grid_3x3.svg' width='20' style='vertical-align: middle;'></td>
					<td>設定棋盤大小（２～１９）</td>
				</tr>
				<tr class="level-3">
					<td><img src='bb/svg/circle.svg' width='20' style='vertical-align: middle;'></td>
					<td>設定手順減少值，若減為０以下不顯示</td>
				</tr>
				<tr class="level-3">
					<td><img src='bb/svg/circle_n.svg' width='20' style='vertical-align: middle; -webkit-mask-image: linear-gradient(to left, black, transparent); mask-image: linear-gradient(to left, black, transparent);'></td>
					<td>設定隱藏前幾手手順</td>
				</tr>
			</tbody>
		</table>


		<script>
			$(".readme").click(function() {
				$(this).attr("check", Math.abs($(this).attr("check") - 1))
				$(".readmediv").toggle()
			});
		</script>

</body>

</html>
