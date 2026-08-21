<!DOCTYPE HTML>
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=1000">
	<link rel=stylesheet href="puzzle.css">
	<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>

<script>
// 全域變數
let currentPage = 1;
let totalRecords = 0;
const pageSize = 2000;

/**
 * 根據資料陣列，產生表格 HTML
 */
function renderTable(data) {
    let DATA = '';
    
    if (data && Object.keys(data).length > 0) {
        for (let key in data) {
            let row = data[key];
            DATA += '<TR><TD class=P>' + row[0] + '</TD><TD>' + row[1] + '</TD><TD class=O>' + row[2] + '</TD><TD class=G>' + row[3] + '</TD><TD class=G><u>' + row[4] + '</u></TD><TD class=B>' + row[5] + '</TD><TD class=B><u>' + row[6] + '</u></TD><TD class=Y>' + row[7] + '</TD><TD class=Y><u>' + row[8] + '</u></TD><TD>' + (row[9] * 1).toFixed(3) + '</TD><TD><u>' + (row[10] * 1).toFixed(3) + '</u></TD><TD>' + row[11] + '</TD><TD>' + row[12] + '</TD></TR>';
        }
        DATA = "<TABLE class=rank><colgroup><col width='50'><col width='100'><col width='50'><col width='50'><col width='50'><col width='50'><col width='50'><col width='50'><col width='50'><col width='60'><col width='60'><col width='70'><col width='120'></colgroup><TR><TH>名次</TH><TH>暱稱</TH><TH>總分</TH><TH>準度</TH><TH>★</TH><TH>速度</TH><TH>★</TH><TH>深度</TH><TH>★</TH><TH>用時</TH><TH>平均</TH><TH>等級</TH><TH>紀錄日期</TH></TR>" + DATA + "</TABLE>";
    }
    
    $('#rank').html(DATA);
}

/**
 * 建立分頁選單
 */
function setupPagination() {
    const totalPages = Math.ceil(totalRecords / pageSize);
    let paginationHTML = '';

    if (totalPages > 1) {
        paginationHTML += "<div style='display: flex; align-items: center; gap: 10px;'>";
        paginationHTML += "<span class='section-title' style='font-size: 24px; margin-bottom: 0;'>Page</span> <select id='page_select'>";
        for (let i = 1; i <= totalPages; i++) {
            const start = (i - 1) * pageSize + 1;
            const end = Math.min(i * pageSize, totalRecords);
            const selected = (i === currentPage) ? 'selected' : '';
            paginationHTML += `<option value="${i}" ${selected}>第 ${i} 頁 (${start} - ${end})</option>`;
        }
        paginationHTML += "</select>";
        paginationHTML += `<span style='margin-left:10px; color:#888;'>共 ${totalRecords} 筆</span>`;
        paginationHTML += "</div>";
    } else if (totalRecords > 0) {
        paginationHTML = `<span style='color:#888;'>共 ${totalRecords} 筆</span>`;
    }

    $('#pagination_controls').html(paginationHTML);

    // 分頁選單 change 事件
    $('#page_select').change(function() {
        currentPage = parseInt($(this).val());
        loadPage(currentPage);
    });
}

/**
 * 從伺服器載入指定頁的資料
 */
function loadPage(page) {
    $('#rank').html('<div style="text-align: center; padding: 50px; opacity: 0.6;">資料讀取中...</div>');

    $.ajax({
        url: "puzzlerank.php",
        type: "POST",
        data: {
            RANK: $('#type').val(),
            TIME: $('#time').val(),
            PAGE: page
        },
        dataType: "json",
        success: function(response) {
            totalRecords = response.total || 0;
            currentPage = response.page || 1;
            
            setupPagination();
            renderTable(response.data || {});
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log('error', textStatus, errorThrown);
            $('#rank').html('讀取資料時發生錯誤，請檢查主控台(Console)以獲取更多資訊。');
        }
    });
}

// 當文件載入完成後執行
$(document).ready(function() {
    // 立即讀取第一頁
    loadPage(1);

    // 當 #type 或 #time 改變時，重置到第一頁
    $('#type, #time').change(function() {
        currentPage = 1;
        loadPage(1);
    });
});
</script>
</head>
<body class="bg-dark">

<div class="container" style="max-width: 1000px;">
	<div class="controls" style="margin-bottom: 25px; flex-wrap: wrap;">
		<div style="display: flex; align-items: center; gap: 10px;">
			<span class="section-title" style="margin-bottom: 0;">Type</span>
			<select id='type'>
				<option value='VC4'>VC4</option>
				<option value='X33'>X33</option>
				<option value='X43'>X43</option>
				<option value='X44'>X44</option>
				<option value='1M43'>1M43</option>
			</select>
		</div>

		<div style="display: flex; align-items: center; gap: 10px;">
			<span class="section-title" style="margin-bottom: 0;">Range</span>
			<select id='time'>
				<option value='1'>近一天</option>
				<option value='2'>近一週</option>
				<option value='3' selected>近一月</option>
				<option value='4'>近一年</option>
				<option value='5'>全部</option>
			</select>
		</div>

		<div style="flex-grow: 1;"></div>
		
		<input id="menu-btn" type="button" value="Menu" onClick="location.href='index.php'">
	</div>
	<div id="pagination_controls" style="margin-bottom: 15px;"></div>
	
	<div id="rank">
		<div style="text-align: center; padding: 50px; opacity: 0.6;">
			Searching for the best players...
		</div>
	</div>
</div> <!-- End container -->
</body>
</html>