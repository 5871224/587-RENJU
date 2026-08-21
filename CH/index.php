<!DOCTYPE HTML>
<html>
<HEAD>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=450px, initial-scale=0.9">
	<link rel="stylesheet" href="puzzle.css">
	<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
</HEAD>
<body class="bg-dark">

<div class="container">
	<div class="section-title">Type</div>
	<div class="type-selector">
		<label class="type-option">
			<input id="VC4" type="radio" name="type" value="VC4" checked>
			<span class="type-card">衝四勝 (VC4)</span>
		</label>
		<label class="type-option">
			<input type="radio" name="type" value="X43">
			<span class="type-card">四三 (X43)</span>
		</label>
		<label class="type-option">
			<input type="radio" name="type" value="X33">
			<span class="type-card">雙三 (X33)</span>
		</label>
		<label class="type-option">
			<input type="radio" name="type" value="X44">
			<span class="type-card">雙四 (X44)</span>
		</label>
		<label class="type-option">
			<input type="radio" name="type" value="1M43">
			<span class="type-card">一手四三 (1M43)</span>
		</label>
	</div>

	<div id='level-container'>
		<div id='level-placeholder'></div>
	</div>
	
	<div class="controls" style="margin-top: 20px;">
		<input id="set" type="button" value="Set Game">
		<input id="rank-btn" type="button" value="Ranking" onClick="location.href='prank.php'">
	</div>

	<div class="section-title" style="margin-top: 40px;">Stats Overview</div>

	<?php
	require_once 'testlogin.php';
	$statement = $MYSQL->query("SELECT 'VC4' as 'db', level as 'level', count(level) as 'count' FROM VC4 GROUP BY level
								UNION ALL
								SELECT 'X33', 1, count(level) FROM X33
								UNION ALL
								SELECT 'X43', 1, count(level) FROM X43
								UNION ALL
								SELECT 'X44', 1, count(level) FROM X44
								UNION ALL
								SELECT '1M43', 1, count(level) FROM 1M43
								ORDER BY level");

	echo "<TABLE class='line'><colgroup><col width='100'><col width='150'><col width='100'></colgroup>";
	echo "<TR><TH>Type</TH><TH>Level</TH><TH>Total</TH></TR>";
	foreach ($statement as $row) {
		if ($db != $row['db']) {
			$T = $row['db'];
		} else {
			$T = "";
		}
		echo "<TR><TD>" . $T . "</TD><TD>" . $row['level'] . "</TD><TD>" . $row['count'] . "</TD></TR>";
		$db = $row['db'];
	}
	echo "</div>"; // End container
	$statement = $MYSQL->query("SELECT MIN(level) 'VC4MIN', MAX(level) 'VC4MAX' FROM VC4");
	foreach ($statement as $row) {
	}
	?>

	<script>
		var VC4MIN = <?php echo json_encode($row['VC4MIN']) ?>;
		var VC4MAX = <?php echo json_encode($row['VC4MAX']) ?>;
		var select = "";
		var levelMemoryKey = "CH_VC4_LEVEL";
		var typeMemoryKey = "CH_TYPE";

		function applySavedType() {
			var savedType = localStorage.getItem(typeMemoryKey);
			if (!savedType) return;
			var target = $("input[name=type][value='" + savedType + "']");
			if (target.length === 0) return;
			target.prop('checked', true);
		}

		function applySavedLevel() {
			var savedLevel = localStorage.getItem(levelMemoryKey);
			if (!savedLevel || !document.getElementById('select1')) return;
			var lv = parseInt(savedLevel, 10);
			if (isNaN(lv)) return;
			if (lv < VC4MIN || lv > VC4MAX) return;
			$('#select1').val(String(lv));
		}

		for (var i = VC4MIN * 1; i <= VC4MAX * 1; i++) {
			select = select + "<option value='" + i + "'>" + i + "</option>"
		}
		select = "<select id='select1'>" + select + "</select>"
		var levelHtml = "<div class='select'><div class='section-title'>Level</div>" + select + "</div>"
		$("#level-placeholder").replaceWith(levelHtml)

		applySavedType();
		applySavedLevel();

		if ($('input[name=type]:checked').val() != 'VC4') {
			$(".select").remove();
		}

		$(document).ready(function() {
			$('#set').click(function() {
				var type = $('input[name=type]:checked').val();
				localStorage.setItem(typeMemoryKey, type);

				var $form = $('<form method="POST" action="puzzle.php"></form>');
				$form.append($('<input type="hidden" name="type">').val(type));

				if (type == 'VC4') {
					var lv = $("#select1").val();
					localStorage.setItem(levelMemoryKey, lv);
					var S = Math.max(lv - 2, 3);
					var B = lv;
					$form.append($('<input type="hidden" name="S">').val(S));
					$form.append($('<input type="hidden" name="B">').val(B));
				}

				$('body').append($form);
				$form.submit();
			})

			$('input[name=type]').change(function() {
				localStorage.setItem(typeMemoryKey, $('input[name=type]:checked').val());

				if ($('input[name=type]:checked').val() == 'VC4') {
					$(".select").remove()
					$("#level-container").append(levelHtml)
					applySavedLevel();
				} else {
					$(".select").remove()
				}
			})

			$(document).on('change', '#select1', function() {
				localStorage.setItem(levelMemoryKey, $(this).val());
			})
		})
	</script>

</body>
</html>