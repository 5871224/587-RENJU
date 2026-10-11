<?php
// 排名與比賽對局共用的績分顏色與儲存格。
function ratingColor($rating) {
    $value = max(1000.0, min(2600.0, (float)$rating));
    $stops = array(
      array(1000.0, array(21, 101, 192)),
      array(1400.0, array(46, 125, 50)),
      array(1800.0, array(201, 164, 0)),
      array(2200.0, array(245, 124, 0)),
      array(2600.0, array(211, 47, 47))
    );

    for ($i = 0; $i < count($stops) - 1; $i++) {
      $left = $stops[$i];
      $right = $stops[$i + 1];
      if ($value <= $right[0]) {
        $ratio = ($value - $left[0]) / ($right[0] - $left[0]);
        $rgb = array();
        for ($c = 0; $c < 3; $c++) {
          $rgb[$c] = (int)round($left[1][$c] + (($right[1][$c] - $left[1][$c]) * $ratio));
        }
        return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
      }
    }
    return '#D32F2F';
  }

function ratingCell($rating, bool $preserveDecimals = false): string {
  if ($preserveDecimals && ($rating === null || $rating === '')) {
    return '<TD></TD>';
  }
  $display = $preserveDecimals
    ? htmlspecialchars((string)$rating, ENT_QUOTES, 'UTF-8')
    : (string)(int)round((float)$rating);
  return "<TD class='rating-score' style='--rating-color:" . ratingColor($rating) . "'>" . $display . "</TD>";
}
