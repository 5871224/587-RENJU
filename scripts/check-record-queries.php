<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/riftw/record-queries.php';

function assertRecordTest(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

final class RecordMockCursor implements IteratorAggregate
{
    private array $values;
    private int $index = 0;
    public function __construct(array $values) { $this->values = array_values($values); }
    public function fetch(int $mode = PDO::FETCH_ASSOC) {
        return $this->values[$this->index++] ?? false;
    }
    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array { return $this->values; }
    public function closeCursor(): void {}
    public function getIterator(): Traversable { return new ArrayIterator($this->values); }
}
final class RecordMockDB
{
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function query(string $sql): RecordMockCursor { return new RecordMockCursor($this->rows); }
}
final class RecordMockLimits
{
    private array $limits;
    public function __construct(array $limits) { $this->limits = $limits; }
    public function execute(array $params): void {}
    public function fetchColumn() { return array_shift($this->limits); }
    public function closeCursor(): void {}
}
final class RecordMockRates
{
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function prepare(string $sql): RecordMockLimits { return new RecordMockLimits([4, 1]); }
    public function query(string $sql): RecordMockCursor { return new RecordMockCursor($this->rows); }
}

// 新版與舊版的「較早 DISTINCT 姓名未滿五人」條件對照。
// 同日期並列、同一人重複取得段位、不同棋手共享姓名均須維持舊條件。
$denFixture = [
    ['代號'=>1,'姓名'=>'甲','段位'=>'初段','日期'=>'2001-01-01','生日'=>'1990-01-01','天數'=>4018,'年紀'=>'11.0000','性別'=>1,'段序'=>1],
    ['代號'=>2,'姓名'=>'乙','段位'=>'初段','日期'=>'2002-01-01','生日'=>'1990-01-01','天數'=>4383,'年紀'=>'12.0000','性別'=>1,'段序'=>1],
    ['代號'=>3,'姓名'=>'丙','段位'=>'初段','日期'=>'2003-01-01','生日'=>'1990-01-01','天數'=>4748,'年紀'=>'13.0000','性別'=>2,'段序'=>1],
    ['代號'=>4,'姓名'=>'丁','段位'=>'初段','日期'=>'2004-01-01','生日'=>'1990-01-01','天數'=>5113,'年紀'=>'14.0000','性別'=>1,'段序'=>1],
    ['代號'=>5,'姓名'=>'戊','段位'=>'初段','日期'=>'2005-01-01','生日'=>'1990-01-01','天數'=>5479,'年紀'=>'15.0000','性別'=>1,'段序'=>1],
    ['代號'=>6,'姓名'=>'己','段位'=>'初段','日期'=>'2005-01-01','生日'=>'1990-01-01','天數'=>5479,'年紀'=>'15.0000','性別'=>1,'段序'=>1],
    ['代號'=>7,'姓名'=>'庚','段位'=>'初段','日期'=>'2006-01-01','生日'=>'1990-01-01','天數'=>5844,'年紀'=>'16.0000','性別'=>1,'段序'=>1],
    ['代號'=>8,'姓名'=>'甲','段位'=>'初段','日期'=>'2007-01-01','生日'=>'1990-01-01','天數'=>6209,'年紀'=>'17.0000','性別'=>1,'段序'=>1],
];
foreach (['early'=>'日期', 'young'=>'天數'] as $mode=>$key) {
    $expected = [];
    foreach ($denFixture as $row) {
        $earlier = [];
        foreach ($denFixture as $other) {
            if ($other['段位'] === $row['段位'] && $other[$key] < $row[$key]) {
                $earlier[$other['姓名']] = true;
            }
        }
        if (count($earlier) < 5) $expected[] = $row['姓名'] . $row['日期'];
    }
    $actual = array_map(static fn($r) => $r['姓名'] . $r['日期'], recordDenFirstFive($denFixture, $mode));
    sort($expected);
    sort($actual);
    assertRecordTest($expected === $actual, "段位 {$mode} 舊版 DISTINCT/日期並列條件不一致");
}

// 四種連續對局只須查詢同一份雙方視角的排序序列。
$streakData = [];
for ($i=1; $i<=66; ++$i) {
    $value = $i<=9 ? 2 : ($i<=16 ? 1 : 0);
    $streakData[] = [
        '代號'=>1, '姓名'=>'甲', '比賽'=>$i, '輪次'=>1, '對手'=>2,
        '勝負'=>$value, '方位'=>1, '開始'=>'2020-01-01', '結束'=>'2020-12-31'
    ];
}
$streaks = recordConsecutiveBoards(new RecordMockDB($streakData));
assertRecordTest(count($streaks['win'])===1 && (int)$streaks['win'][0]['連勝']===9, '連勝分組錯誤');
assertRecordTest(count($streaks['unbeaten'])===1 && (int)$streaks['unbeaten'][0]['連勝']===16, '不敗分組錯誤');
assertRecordTest(count($streaks['draw'])===1 && (int)$streaks['draw'][0]['連勝']===7, '連和分組錯誤');
assertRecordTest(count($streaks['nondraw'])===1 && (int)$streaks['nondraw'][0]['連勝']===50, '不和分組錯誤');

// 原有模板固定讀取「勝率」，應映射為每張榜單實際的百分比。
$rates = recordRateBoards(new RecordMockRates([
    ['代號'=>1,'姓名'=>'甲','勝'=>16,'和'=>2,'負'=>2,'對局數'=>20,
     '得分率'=>'85.00','勝率'=>'80.00','和棋率'=>'10.00','年平均'=>'4.0'],
    ['代號'=>2,'姓名'=>'乙','勝'=>10,'和'=>6,'負'=>4,'對局數'=>20,
     '得分率'=>'65.00','勝率'=>'50.00','和棋率'=>'30.00','年平均'=>'4.0'],
]), '2026-10-11', '2021-10-11');
assertRecordTest((float)$rates['score'][0]['勝率']===85.0, '得分率未對應舊模板');
assertRecordTest((float)$rates['wins'][0]['勝率']===80.0, '勝率未對應舊模板');
assertRecordTest((float)$rates['draws'][0]['勝率']===30.0, '和棋率未對應舊模板');

$demographics = recordDemographicBoards(new RecordMockDB([
    ['性別'=>1,'生日'=>'1990-02-01','月日'=>201],
    ['性別'=>1,'生日'=>'1990-03-01','月日'=>301],
    ['性別'=>2,'生日'=>'1990-04-01','月日'=>401],
    ['性別'=>null,'生日'=>null,'月日'=>null],
]));
assertRecordTest(count($demographics['zodiac'])===3, '星座人數統計錯誤');
assertRecordTest(array_sum(array_column($demographics['zodiac'],'人數'))===3, '生日母體錯誤');

echo "Record refactor regression fixtures passed (promotion ties, streaks, rates, demographics).\n";
