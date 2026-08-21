<?php
// 連接 TESTRANK 資料庫
require_once 'testranklogin.php';

// 定義允許的表名白名單
$allowed_tables = array('VC4', 'X33', 'X43', 'X44', '1M43');

if (isset($_POST['RANK']) && $_POST['RANK']) {
	// 驗證 RANK 表名
	$rank_val = $_POST['RANK'];
	$rank_table = in_array($rank_val, $allowed_tables) ? $rank_val : null;
	if (!$rank_table) {
		echo json_encode(array('error' => '無效的類型'));
		exit;
	}

	$time_val = isset($_POST['TIME']) ? $_POST['TIME'] : 0;
	$TIME = "";
	if ($time_val == 1){$TIME="WHERE DT>date_sub(CURDATE(),interval 1 day)";}
	if ($time_val == 2){$TIME="WHERE DT>date_sub(CURDATE(),interval 7 day)";}
	if ($time_val == 3){$TIME="WHERE DT>date_sub(CURDATE(),interval 1 month)";}
	if ($time_val == 4){$TIME="WHERE DT>date_sub(CURDATE(),interval 1 year)";}
	if ($time_val == 5){$TIME="";}
	
	$pageSize = 2000;
	$page = isset($_POST['PAGE']) ? max(1, intval($_POST['PAGE'])) : 1;
	$offset = ($page - 1) * $pageSize;
	
	// 查詢總筆數（使用已驗證的表名）
	$countSql = "SELECT COUNT(*) FROM `$rank_table` " . $TIME;
	$countStmt = $MYSQL->query($countSql);
	$total = $countStmt->fetchColumn();
	
	// 分頁查詢資料（使用已驗證的表名）
	$statement = $MYSQL->query("SELECT @rowno:=@rowno+1 `RANK`,T.* FROM (SELECT * FROM `$rank_table` $TIME ORDER BY S DESC ,Y DESC,YS DESC,B DESC,BS DESC,G DESC,GS DESC,AVG LIMIT $pageSize OFFSET $offset) `T`,(select @rowno:=$offset)`R`");
	$arr = array();
	$n = 0;
	foreach($statement as $row){
		$n = $n + 1;
		$arr[$n] = array($row['RANK'],$row['NAME'],$row['S'],$row['G'],$row['GS'],$row['B'],$row['BS'],$row['Y'],$row['YS'],$row['AT'],$row['AVG'],$row['LEVEL'],$row['DT']);
	}
	
	$result = array(
		'data' => $arr,
		'total' => intval($total),
		'page' => $page,
		'pageSize' => $pageSize
	);
	echo json_encode($result);

} else {
	// 驗證 TYPE 表名
	$type_val = isset($_POST['TYPE']) ? $_POST['TYPE'] : '';
	$type_table = in_array($type_val, $allowed_tables) ? $type_val : null;
	if (!$type_table) {
		echo json_encode(array('error' => '無效的類型'));
		exit;
	}

	$statement = $MYSQL->query("SELECT MAX(no)+1 FROM `$type_table`");
	$row_query = $statement->fetchAll();
	if (isset($row_query[0][0]) && $row_query[0][0]){
		$next_no = $row_query[0][0];
	}else{
		$next_no = 1;
	}

	// 使用 prepared statement 防止 SQL 注入
	$stmt = $MYSQL->prepare("INSERT INTO `$type_table` (no,DT,NAME,G,GS,B,BS,Y,YS,S,AT,AVG,LEVEL) VALUES (?,NOW(),?,?,?,?,?,?,?,?,?,?,?)");
	
	$p_name = isset($_POST['NAME']) ? $_POST['NAME'] : '';
	$p_g = isset($_POST['G']) ? intval($_POST['G']) : 0;
	$p_gs = isset($_POST['GS']) ? intval($_POST['GS']) : 0;
	$p_b = isset($_POST['B']) ? intval($_POST['B']) : 0;
	$p_bs = isset($_POST['BS']) ? intval($_POST['BS']) : 0;
	$p_y = isset($_POST['Y']) ? intval($_POST['Y']) : 0;
	$p_ys = isset($_POST['YS']) ? intval($_POST['YS']) : 0;
	$p_s = isset($_POST['S']) ? intval($_POST['S']) : 0;
	$p_at = isset($_POST['AT']) ? floatval($_POST['AT']) : 0;
	$p_avg = isset($_POST['AVG']) ? floatval($_POST['AVG']) : 0;
	$p_level = isset($_POST['LEVEL']) ? $_POST['LEVEL'] : '';

	$result_exec = $stmt->execute(array(
		$next_no,
		$p_name,
		$p_g,
		$p_gs,
		$p_b,
		$p_bs,
		$p_y,
		$p_ys,
		$p_s,
		$p_at,
		$p_avg,
		$p_level
	));

	if ($result_exec) {
		$statement = $MYSQL->query("SELECT T.* FROM (SELECT @rowno:=@rowno+1 `RANK`,no,(SELECT COUNT(no) FROM `$type_table`)`COUNT` FROM `$type_table`,(select @rowno:=0)`R` ORDER BY S DESC ,Y DESC,YS DESC,B DESC,BS DESC,G DESC,GS DESC,AVG)`T` WHERE no=$next_no");
		$rank_res = $statement->fetchAll();
		$arr_res = array($rank_res[0]['RANK'], $rank_res[0]['COUNT']);
		echo json_encode($arr_res); 
	} else {
	    echo json_encode(array('error' => '儲存失敗'));
	}
}
?>
