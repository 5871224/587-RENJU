<?php
declare(strict_types=1);
require_once __DIR__ . '/login.php';

function wrH($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function wrDate($value): string {
    if (!is_string($value)) return '';
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
    return $day && $day->format('Y-m-d') === trim($value) ? $day->format('Y-m-d') : '';
}
function wrInt($value, int $default = 0): int {
    return is_scalar($value) && ctype_digit((string)$value) ? (int)$value : $default;
}
function wrLink(array $filters, int $page): string {
    $filters['p'] = $page;
    return 'world-record.php?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
}
function wrLabel(array $player): string {
    return trim((string)$player['surname'] . ' ' . (string)$player['name']);
}

/**
 * RenjuNet uses English country/region names and frequently three-letter abbreviations.
 * Localize labels at render time; original IDs and source data remain unchanged.
 */
function wrCountryName($english, $abbr = ''): string {
    static $names = [
        'Afghanistan' => '阿富汗',
        'Åland Islands' => '奧蘭群島',
        'Albania' => '阿爾巴尼亞',
        'Algeria' => '阿爾及利亞',
        'American Samoa' => '美屬薩摩亞',
        'Andorra' => '安道爾',
        'Angola' => '安哥拉',
        'Anguilla' => '安奎拉',
        'Antarctica' => '南極洲',
        'Antigua & Barbuda' => '安地卡及巴布達',
        'Argentina' => '阿根廷',
        'Armenia' => '亞美尼亞',
        'Aruba' => '荷屬阿魯巴',
        'Ascension Island' => '阿森松島',
        'Australia' => '澳洲',
        'Austria' => '奧地利',
        'Azerbaijan' => '亞塞拜然',
        'Bahamas' => '巴哈馬',
        'Bahrain' => '巴林',
        'Bangladesh' => '孟加拉',
        'Barbados' => '巴貝多',
        'Belarus' => '白俄羅斯',
        'Belarusian Renju Association' => '白俄羅斯連珠協會',
        'Belgium' => '比利時',
        'Belize' => '貝里斯',
        'Benin' => '貝南',
        'Bermuda' => '百慕達',
        'Bhutan' => '不丹',
        'Bolivia' => '玻利維亞',
        'Bosnia & Herzegovina' => '波士尼亞與赫塞哥維納',
        'Botswana' => '波札那',
        'Bouvet Island' => '布威島',
        'Brazil' => '巴西',
        'British Indian Ocean Territory' => '英屬印度洋領地',
        'British Virgin Islands' => '英屬維京群島',
        'Brunei' => '汶萊',
        'Brunei Darussalam' => '汶萊',
        'Bulgaria' => '保加利亞',
        'Burkina Faso' => '布吉納法索',
        'Burundi' => '蒲隆地',
        'Cambodia' => '柬埔寨',
        'Cameroon' => '喀麥隆',
        'Canada' => '加拿大',
        'Canary Islands' => '加那利群島',
        'Cape Verde' => '維德角',
        'Caribbean Netherlands' => '荷蘭加勒比區',
        'Cayman Islands' => '開曼群島',
        'Central African Republic' => '中非共和國',
        'Ceuta & Melilla' => '休達與梅利利亞',
        'Chad' => '查德',
        'Chile' => '智利',
        'China' => '中國',
        'China, People\'s Republic of' => '中國',
        'Chinese Hong Kong' => '香港',
        'Chinese Taipei' => '台灣',
        'Christmas Island' => '聖誕島',
        'Clipperton Island' => '克里派頓島',
        'Cocos (Keeling) Islands' => '科克斯（基靈）群島',
        'Colombia' => '哥倫比亞',
        'Comoros' => '葛摩',
        'Congo - Brazzaville' => '剛果（布拉薩）',
        'Congo - Kinshasa' => '剛果（金夏沙）',
        'Cook Islands' => '庫克群島',
        'Costa Rica' => '哥斯大黎加',
        'Cote d\'Ivoire' => '象牙海岸',
        'Côte d’Ivoire' => '象牙海岸',
        'Croatia' => '克羅埃西亞',
        'Cuba' => '古巴',
        'Curaçao' => '庫拉索',
        'Cyprus' => '賽普勒斯',
        'Czech Republic' => '捷克',
        'Czechia' => '捷克',
        'Democratic People\'s Republic of Korea' => '北韓',
        'Denmark' => '丹麥',
        'Diego Garcia' => '迪亞哥加西亞島',
        'Djibouti' => '吉布地',
        'Dominica' => '多米尼克',
        'Dominican Republic' => '多明尼加共和國',
        'Ecuador' => '厄瓜多',
        'Egypt' => '埃及',
        'El Salvador' => '薩爾瓦多',
        'England' => '英格蘭',
        'Equatorial Guinea' => '赤道幾內亞',
        'Eritrea' => '厄利垂亞',
        'Estonia' => '愛沙尼亞',
        'Eswatini' => '史瓦帝尼',
        'Ethiopia' => '衣索比亞',
        'European Union' => '歐盟',
        'Eurozone' => '歐元區',
        'Falkland Islands (Islas Malvinas)' => '福克蘭群島（馬爾維納斯群島）',
        'Faroe Islands' => '法羅群島',
        'Fiji' => '斐濟',
        'Finland' => '芬蘭',
        'France' => '法國',
        'French Guiana' => '法屬圭亞那',
        'French Polynesia' => '法屬玻里尼西亞',
        'French Southern Territories' => '法屬南部屬地',
        'FYR Macedonia' => '北馬其頓',
        'Gabon' => '加彭',
        'Gambia' => '甘比亞',
        'Georgia' => '喬治亞',
        'Germany' => '德國',
        'Ghana' => '迦納',
        'Gibraltar' => '直布羅陀',
        'Great Britain' => '英國',
        'Greece' => '希臘',
        'Greenland' => '格陵蘭',
        'Grenada' => '格瑞那達',
        'Guadeloupe' => '瓜地洛普',
        'Guam' => '關島',
        'Guatemala' => '瓜地馬拉',
        'Guernsey' => '根息',
        'Guinea' => '幾內亞',
        'Guinea-Bissau' => '幾內亞比索',
        'Guyana' => '蓋亞那',
        'Haiti' => '海地',
        'Heard & McDonald Islands' => '赫德島及麥唐納群島',
        'Honduras' => '宏都拉斯',
        'Hong Kong' => '香港',
        'Hong Kong China' => '香港',
        'Hong Kong SAR' => '香港',
        'Hong Kong SAR China' => '香港',
        'Hong Kong, China' => '香港',
        'Hongkong' => '香港',
        'Hungary' => '匈牙利',
        'Iceland' => '冰島',
        'India' => '印度',
        'Indonesia' => '印尼',
        'Iran' => '伊朗',
        'Iraq' => '伊拉克',
        'Ireland' => '愛爾蘭',
        'Isle of Man' => '曼島',
        'Israel' => '以色列',
        'Italy' => '義大利',
        'Ivory Coast' => '象牙海岸',
        'Jamaica' => '牙買加',
        'Japan' => '日本',
        'Jersey' => '澤西島',
        'Jordan' => '約旦',
        'Kazakhstan' => '哈薩克',
        'Kenya' => '肯亞',
        'Kiribati' => '吉里巴斯',
        'Korea' => '韓國',
        'Korea, Democratic People\'s Republic of' => '北韓',
        'Korea, Republic of' => '南韓',
        'Kosovo' => '科索沃',
        'Kuwait' => '科威特',
        'Kyrgyzstan' => '吉爾吉斯',
        'Lao People\'s Democratic Republic' => '寮國',
        'Laos' => '寮國',
        'Latvia' => '拉脫維亞',
        'Lebanon' => '黎巴嫩',
        'Lesotho' => '賴索托',
        'Liberia' => '賴比瑞亞',
        'Libya' => '利比亞',
        'Liechtenstein' => '列支敦斯登',
        'Lithuania' => '立陶宛',
        'Luxembourg' => '盧森堡',
        'Macao' => '澳門',
        'Macao SAR China' => '澳門',
        'Macao, China' => '澳門',
        'Macau' => '澳門',
        'Macau, China' => '澳門',
        'Macedonia' => '北馬其頓',
        'Madagascar' => '馬達加斯加',
        'Malawi' => '馬拉威',
        'Malaysia' => '馬來西亞',
        'Maldives' => '馬爾地夫',
        'Mali' => '馬利',
        'Malta' => '馬爾他',
        'Marshall Islands' => '馬紹爾群島',
        'Martinique' => '馬丁尼克',
        'Mauritania' => '茅利塔尼亞',
        'Mauritius' => '模里西斯',
        'Mayotte' => '馬約特島',
        'Mexico' => '墨西哥',
        'Micronesia' => '密克羅尼西亞',
        'Moldova' => '摩爾多瓦',
        'Moldova, Republic of' => '摩爾多瓦',
        'Monaco' => '摩納哥',
        'Mongolia' => '蒙古',
        'Montenegro' => '蒙特內哥羅',
        'Montserrat' => '蒙哲臘',
        'Morocco' => '摩洛哥',
        'Mozambique' => '莫三比克',
        'Myanmar (Burma)' => '緬甸',
        'Namibia' => '納米比亞',
        'Nauru' => '諾魯',
        'Nepal' => '尼泊爾',
        'Netherlands' => '荷蘭',
        'New Caledonia' => '新喀里多尼亞',
        'New Zealand' => '紐西蘭',
        'Nicaragua' => '尼加拉瓜',
        'Niger' => '尼日',
        'Nigeria' => '奈及利亞',
        'Niue' => '紐埃島',
        'Norfolk Island' => '諾福克島',
        'North Korea' => '北韓',
        'North Macedonia' => '北馬其頓',
        'Northern Ireland' => '北愛爾蘭',
        'Northern Mariana Islands' => '北馬利安納群島',
        'Norway' => '挪威',
        'Oman' => '阿曼',
        'Other' => '其他',
        'Outlying Oceania' => '大洋洲邊疆群島',
        'P.R. China' => '中國',
        'Pakistan' => '巴基斯坦',
        'Palau' => '帛琉',
        'Palestine' => '巴勒斯坦',
        'Panama' => '巴拿馬',
        'Papua New Guinea' => '巴布亞紐幾內亞',
        'Paraguay' => '巴拉圭',
        'People\'s Republic of China' => '中國',
        'Peru' => '秘魯',
        'Philippines' => '菲律賓',
        'Pitcairn Islands' => '皮特肯群島',
        'Poland' => '波蘭',
        'Portugal' => '葡萄牙',
        'PR China' => '中國',
        'Pseudo-Accents' => '偽區域',
        'Pseudo-Bidi' => '偽比迪',
        'Puerto Rico' => '波多黎各',
        'Qatar' => '卡達',
        'Republic of Belarus' => '白俄羅斯',
        'Republic of China' => '台灣',
        'Republic of Korea' => '南韓',
        'Republic of Macedonia' => '北馬其頓',
        'Réunion' => '留尼旺',
        'Romania' => '羅馬尼亞',
        'Russia' => '俄羅斯',
        'Russian Federation' => '俄羅斯',
        'Russian Renju Association' => '俄羅斯',
        'Russian Renju Federation' => '俄羅斯',
        'Rwanda' => '盧安達',
        'Samoa' => '薩摩亞',
        'San Marino' => '聖馬利諾',
        'São Tomé & Príncipe' => '聖多美普林西比',
        'Sark' => '薩克島',
        'Saudi Arabia' => '沙烏地阿拉伯',
        'Scotland' => '蘇格蘭',
        'Senegal' => '塞內加爾',
        'Serbia' => '塞爾維亞',
        'Serbia and Montenegro' => '塞爾維亞與蒙特內哥羅',
        'Seychelles' => '塞席爾',
        'Sierra Leone' => '獅子山',
        'Singapore' => '新加坡',
        'Sint Maarten' => '荷屬聖馬丁',
        'Slovak Republic' => '斯洛伐克',
        'Slovakia' => '斯洛伐克',
        'Slovenia' => '斯洛維尼亞',
        'Solomon Islands' => '索羅門群島',
        'Somalia' => '索馬利亞',
        'South Africa' => '南非',
        'South Georgia & South Sandwich Islands' => '南喬治亞與南三明治群島',
        'South Korea' => '南韓',
        'South Sudan' => '南蘇丹',
        'Spain' => '西班牙',
        'Sri Lanka' => '斯里蘭卡',
        'St. Barthélemy' => '聖巴瑟米',
        'St. Helena' => '聖赫勒拿島',
        'St. Kitts & Nevis' => '聖克里斯多福及尼維斯',
        'St. Lucia' => '聖露西亞',
        'St. Martin' => '法屬聖馬丁',
        'St. Pierre & Miquelon' => '聖皮埃與密克隆群島',
        'St. Vincent & Grenadines' => '聖文森及格瑞那丁',
        'Sudan' => '蘇丹',
        'Suriname' => '蘇利南',
        'Svalbard & Jan Mayen' => '挪威屬斯瓦巴及尖棉',
        'Swaziland' => '史瓦帝尼',
        'Sweden' => '瑞典',
        'Switzerland' => '瑞士',
        'Syria' => '敘利亞',
        'Taipei, China' => '台灣',
        'Taiwan' => '台灣',
        'Taiwan (Province of China)' => '台灣',
        'Taiwan, China' => '台灣',
        'Tajikistan' => '塔吉克',
        'Tanzania' => '坦尚尼亞',
        'Thailand' => '泰國',
        'Timor-Leste' => '東帝汶',
        'Togo' => '多哥',
        'Tokelau' => '托克勞群島',
        'Tonga' => '東加',
        'Trinidad & Tobago' => '千里達及托巴哥',
        'Tristan da Cunha' => '特里斯坦達庫尼亞群島',
        'Tunisia' => '突尼西亞',
        'Turkey' => '土耳其',
        'Turkiye' => '土耳其',
        'Türkiye' => '土耳其',
        'Turkmenistan' => '土庫曼',
        'Turks & Caicos Islands' => '土克斯及開科斯群島',
        'Tuvalu' => '吐瓦魯',
        'U.S. Outlying Islands' => '美國本土外小島嶼',
        'U.S. Virgin Islands' => '美屬維京群島',
        'U.S.A.' => '美國',
        'UAE' => '阿拉伯聯合大公國',
        'Uganda' => '烏干達',
        'Ukraine' => '烏克蘭',
        'United Arab Emirates' => '阿拉伯聯合大公國',
        'United Kingdom' => '英國',
        'United Nations' => '聯合國',
        'United States' => '美國',
        'United States of America' => '美國',
        'Unknown' => '不詳',
        'Uruguay' => '烏拉圭',
        'USA' => '美國',
        'Uzbekistan' => '烏茲別克',
        'Vanuatu' => '萬那杜',
        'Vatican City' => '梵蒂岡',
        'Venezuela' => '委內瑞拉',
        'Viet Nam' => '越南',
        'Vietnam' => '越南',
        'Wales' => '威爾斯',
        'Wallis & Futuna' => '瓦利斯群島和富圖那群島',
        'Western Sahara' => '西撒哈拉',
        'Yemen' => '葉門',
        'Yugoslavia' => '南斯拉夫',
        'Zambia' => '尚比亞',
        'Zimbabwe' => '辛巴威',
    ];
    static $codes = [
        'AC' => '阿森松島',
        'AD' => '安道爾',
        'AE' => '阿拉伯聯合大公國',
        'AF' => '阿富汗',
        'AG' => '安地卡及巴布達',
        'AI' => '安奎拉',
        'AL' => '阿爾巴尼亞',
        'AM' => '亞美尼亞',
        'AN' => '庫拉索',
        'AO' => '安哥拉',
        'AQ' => '南極洲',
        'AR' => '阿根廷',
        'ARM' => '亞美尼亞',
        'AS' => '美屬薩摩亞',
        'AT' => '奧地利',
        'AU' => '澳洲',
        'AUS' => '澳洲',
        'AUT' => '奧地利',
        'AW' => '荷屬阿魯巴',
        'AX' => '奧蘭群島',
        'AZ' => '亞塞拜然',
        'AZE' => '亞塞拜然',
        'BA' => '波士尼亞與赫塞哥維納',
        'BB' => '巴貝多',
        'BD' => '孟加拉',
        'BE' => '比利時',
        'BEL' => '比利時',
        'BF' => '布吉納法索',
        'BG' => '保加利亞',
        'BH' => '巴林',
        'BI' => '蒲隆地',
        'BJ' => '貝南',
        'BL' => '聖巴瑟米',
        'BLR' => '白俄羅斯',
        'BM' => '百慕達',
        'BN' => '汶萊',
        'BO' => '玻利維亞',
        'BQ' => '荷蘭加勒比區',
        'BR' => '巴西',
        'BRA' => '巴西',
        'BS' => '巴哈馬',
        'BT' => '不丹',
        'BU' => '緬甸',
        'BV' => '布威島',
        'BW' => '波札那',
        'BY' => '白俄羅斯',
        'BZ' => '貝里斯',
        'CA' => '加拿大',
        'CAN' => '加拿大',
        'CC' => '科克斯（基靈）群島',
        'CD' => '剛果（金夏沙）',
        'CF' => '中非共和國',
        'CG' => '剛果（布拉薩）',
        'CH' => '瑞士',
        'CHE' => '瑞士',
        'CHN' => '中國',
        'CI' => '象牙海岸',
        'CK' => '庫克群島',
        'CL' => '智利',
        'CM' => '喀麥隆',
        'CN' => '中國',
        'CO' => '哥倫比亞',
        'CP' => '克里派頓島',
        'CQ' => '薩克島',
        'CR' => '哥斯大黎加',
        'CRO' => '克羅埃西亞',
        'CS' => '塞爾維亞',
        'CU' => '古巴',
        'CV' => '維德角',
        'CW' => '庫拉索',
        'CX' => '聖誕島',
        'CY' => '賽普勒斯',
        'CZ' => '捷克',
        'CZE' => '捷克',
        'CZR' => '捷克',
        'DD' => '德國',
        'DE' => '德國',
        'DEN' => '丹麥',
        'DEU' => '德國',
        'DG' => '迪亞哥加西亞島',
        'DJ' => '吉布地',
        'DK' => '丹麥',
        'DM' => '多米尼克',
        'DNK' => '丹麥',
        'DO' => '多明尼加共和國',
        'DY' => '貝南',
        'DZ' => '阿爾及利亞',
        'EA' => '休達與梅利利亞',
        'EC' => '厄瓜多',
        'EE' => '愛沙尼亞',
        'EG' => '埃及',
        'EH' => '西撒哈拉',
        'ER' => '厄利垂亞',
        'ES' => '西班牙',
        'ESP' => '西班牙',
        'EST' => '愛沙尼亞',
        'ET' => '衣索比亞',
        'EU' => '歐盟',
        'EZ' => '歐元區',
        'FI' => '芬蘭',
        'FIN' => '芬蘭',
        'FJ' => '斐濟',
        'FK' => '福克蘭群島（馬爾維納斯群島）',
        'FM' => '密克羅尼西亞',
        'FO' => '法羅群島',
        'FR' => '法國',
        'FRA' => '法國',
        'FX' => '法國',
        'GA' => '加彭',
        'GB' => '英國',
        'GBR' => '英國',
        'GD' => '格瑞那達',
        'GE' => '喬治亞',
        'GEO' => '喬治亞',
        'GER' => '德國',
        'GF' => '法屬圭亞那',
        'GG' => '根息',
        'GH' => '迦納',
        'GI' => '直布羅陀',
        'GL' => '格陵蘭',
        'GM' => '甘比亞',
        'GN' => '幾內亞',
        'GP' => '瓜地洛普',
        'GQ' => '赤道幾內亞',
        'GR' => '希臘',
        'GRC' => '希臘',
        'GRE' => '希臘',
        'GS' => '南喬治亞與南三明治群島',
        'GT' => '瓜地馬拉',
        'GU' => '關島',
        'GW' => '幾內亞比索',
        'GY' => '蓋亞那',
        'HK' => '香港',
        'HKG' => '香港',
        'HM' => '赫德島及麥唐納群島',
        'HN' => '宏都拉斯',
        'HR' => '克羅埃西亞',
        'HRV' => '克羅埃西亞',
        'HT' => '海地',
        'HU' => '匈牙利',
        'HUN' => '匈牙利',
        'HV' => '布吉納法索',
        'IC' => '加那利群島',
        'ID' => '印尼',
        'IDN' => '印尼',
        'IE' => '愛爾蘭',
        'IL' => '以色列',
        'IM' => '曼島',
        'IN' => '印度',
        'INA' => '印尼',
        'IND' => '印度',
        'IO' => '英屬印度洋領地',
        'IQ' => '伊拉克',
        'IR' => '伊朗',
        'IS' => '冰島',
        'ISR' => '以色列',
        'IT' => '義大利',
        'ITA' => '義大利',
        'JAP' => '日本',
        'JE' => '澤西島',
        'JM' => '牙買加',
        'JO' => '約旦',
        'JP' => '日本',
        'JPN' => '日本',
        'KAZ' => '哈薩克',
        'KE' => '肯亞',
        'KG' => '吉爾吉斯',
        'KH' => '柬埔寨',
        'KI' => '吉里巴斯',
        'KM' => '葛摩',
        'KN' => '聖克里斯多福及尼維斯',
        'KOR' => '南韓',
        'KP' => '北韓',
        'KR' => '南韓',
        'KSA' => '沙烏地阿拉伯',
        'KW' => '科威特',
        'KY' => '開曼群島',
        'KZ' => '哈薩克',
        'LA' => '寮國',
        'LAT' => '拉脫維亞',
        'LB' => '黎巴嫩',
        'LC' => '聖露西亞',
        'LI' => '列支敦斯登',
        'LK' => '斯里蘭卡',
        'LR' => '賴比瑞亞',
        'LS' => '賴索托',
        'LT' => '立陶宛',
        'LTU' => '立陶宛',
        'LU' => '盧森堡',
        'LV' => '拉脫維亞',
        'LVA' => '拉脫維亞',
        'LY' => '利比亞',
        'MA' => '摩洛哥',
        'MAC' => '澳門',
        'MAS' => '馬來西亞',
        'MC' => '摩納哥',
        'MD' => '摩爾多瓦',
        'ME' => '蒙特內哥羅',
        'MEX' => '墨西哥',
        'MF' => '法屬聖馬丁',
        'MG' => '馬達加斯加',
        'MGL' => '蒙古',
        'MH' => '馬紹爾群島',
        'MK' => '北馬其頓',
        'ML' => '馬利',
        'MM' => '緬甸',
        'MN' => '蒙古',
        'MNG' => '蒙古',
        'MO' => '澳門',
        'MP' => '北馬利安納群島',
        'MQ' => '馬丁尼克',
        'MR' => '茅利塔尼亞',
        'MS' => '蒙哲臘',
        'MT' => '馬爾他',
        'MU' => '模里西斯',
        'MV' => '馬爾地夫',
        'MW' => '馬拉威',
        'MX' => '墨西哥',
        'MY' => '馬來西亞',
        'MYS' => '馬來西亞',
        'MZ' => '莫三比克',
        'NA' => '納米比亞',
        'NC' => '新喀里多尼亞',
        'NE' => '尼日',
        'NED' => '荷蘭',
        'NF' => '諾福克島',
        'NG' => '奈及利亞',
        'NH' => '萬那杜',
        'NI' => '尼加拉瓜',
        'NL' => '荷蘭',
        'NLD' => '荷蘭',
        'NO' => '挪威',
        'NOR' => '挪威',
        'NP' => '尼泊爾',
        'NR' => '諾魯',
        'NU' => '紐埃島',
        'NZ' => '紐西蘭',
        'NZL' => '紐西蘭',
        'OM' => '阿曼',
        'PA' => '巴拿馬',
        'PE' => '秘魯',
        'PF' => '法屬玻里尼西亞',
        'PG' => '巴布亞紐幾內亞',
        'PH' => '菲律賓',
        'PK' => '巴基斯坦',
        'PL' => '波蘭',
        'PM' => '聖皮埃與密克隆群島',
        'PN' => '皮特肯群島',
        'POL' => '波蘭',
        'PR' => '波多黎各',
        'PRK' => '北韓',
        'PS' => '巴勒斯坦',
        'PT' => '葡萄牙',
        'PW' => '帛琉',
        'PY' => '巴拉圭',
        'QA' => '卡達',
        'QO' => '大洋洲邊疆群島',
        'RE' => '留尼旺',
        'RH' => '辛巴威',
        'RKA' => '南韓',
        'RO' => '羅馬尼亞',
        'ROK' => '南韓',
        'RRA' => '俄羅斯',
        'RS' => '塞爾維亞',
        'RSA' => '南非',
        'RU' => '俄羅斯',
        'RUS' => '俄羅斯',
        'RW' => '盧安達',
        'SA' => '沙烏地阿拉伯',
        'SB' => '索羅門群島',
        'SC' => '塞席爾',
        'SD' => '蘇丹',
        'SE' => '瑞典',
        'SG' => '新加坡',
        'SGP' => '新加坡',
        'SH' => '聖赫勒拿島',
        'SI' => '斯洛維尼亞',
        'SIN' => '新加坡',
        'SJ' => '挪威屬斯瓦巴及尖棉',
        'SK' => '斯洛伐克',
        'SL' => '獅子山',
        'SLO' => '斯洛維尼亞',
        'SM' => '聖馬利諾',
        'SN' => '塞內加爾',
        'SO' => '索馬利亞',
        'SR' => '蘇利南',
        'SS' => '南蘇丹',
        'ST' => '聖多美普林西比',
        'SU' => '俄羅斯',
        'SUI' => '瑞士',
        'SV' => '薩爾瓦多',
        'SVK' => '斯洛伐克',
        'SVN' => '斯洛維尼亞',
        'SWE' => '瑞典',
        'SX' => '荷屬聖馬丁',
        'SY' => '敘利亞',
        'SZ' => '史瓦帝尼',
        'TA' => '特里斯坦達庫尼亞群島',
        'TC' => '土克斯及開科斯群島',
        'TD' => '查德',
        'TF' => '法屬南部屬地',
        'TG' => '多哥',
        'TH' => '泰國',
        'THA' => '泰國',
        'TJ' => '塔吉克',
        'TK' => '托克勞群島',
        'TL' => '東帝汶',
        'TM' => '土庫曼',
        'TN' => '突尼西亞',
        'TO' => '東加',
        'TP' => '東帝汶',
        'TPE' => '台灣',
        'TR' => '土耳其',
        'TT' => '千里達及托巴哥',
        'TUR' => '土耳其',
        'TV' => '吐瓦魯',
        'TW' => '台灣',
        'TWN' => '台灣',
        'TZ' => '坦尚尼亞',
        'UA' => '烏克蘭',
        'UAE' => '阿拉伯聯合大公國',
        'UG' => '烏干達',
        'UK' => '英國',
        'UKR' => '烏克蘭',
        'UM' => '美國本土外小島嶼',
        'UN' => '聯合國',
        'US' => '美國',
        'USA' => '美國',
        'UY' => '烏拉圭',
        'UZ' => '烏茲別克',
        'UZB' => '烏茲別克',
        'VA' => '梵蒂岡',
        'VC' => '聖文森及格瑞那丁',
        'VD' => '越南',
        'VE' => '委內瑞拉',
        'VG' => '英屬維京群島',
        'VI' => '美屬維京群島',
        'VIE' => '越南',
        'VN' => '越南',
        'VNM' => '越南',
        'VU' => '萬那杜',
        'WF' => '瓦利斯群島和富圖那群島',
        'WS' => '薩摩亞',
        'XA' => '偽區域',
        'XB' => '偽比迪',
        'XK' => '科索沃',
        'YD' => '葉門',
        'YE' => '葉門',
        'YT' => '馬約特島',
        'YU' => '塞爾維亞',
        'ZA' => '南非',
        'ZAF' => '南非',
        'ZM' => '尚比亞',
        'ZR' => '剛果（金夏沙）',
        'ZW' => '辛巴威',
    ];
    $name = trim((string)$english);
    $code = strtoupper(trim((string)$abbr));
    if ($name === '' && $code === '') return '—';
    if (isset($names[$name])) return $names[$name];
    if (isset($codes[$code])) return $codes[$code];
    return $name !== '' ? $name : '—';
}

function wrBuildQuery(string $color, array $baseConditions, array $baseParams): array {
    $base = ' FROM RENJUNET_GAME G INNER JOIN RENJUNET_TOURNAMENT T ON T.id=G.tournament_id WHERE '
        . implode(' AND ', $baseConditions);
    $black = 'SELECT G.black_player_id AS pid, (2*G.black_result) AS points, T.end_date AS played, '
        . 'T.id AS tour, G.round AS round_label, G.id AS gid' . $base;
    $white = 'SELECT G.white_player_id AS pid, (2*(1-G.black_result)) AS points, T.end_date AS played, '
        . 'T.id AS tour, G.round AS round_label, G.id AS gid' . $base;
    if ($color === 'black') return [$black, $baseParams];
    if ($color === 'white') return [$white, $baseParams];
    return [$black . ' UNION ALL ' . $white, array_merge($baseParams, $baseParams)];
}
function wrAggregate(PDO $db, string $source, array $params, int $country): array {
    $extra = $country > 0 ? ' WHERE P.country_id = ?' : '';
    $sql = 'SELECT X.pid, COUNT(*) AS games, SUM(X.points>1) AS wins, '
        . 'SUM(X.points=1) AS draws, SUM(X.points<1) AS losses, '
        . 'SUM(X.points)/2 AS points, P.surname, P.name, P.native_name, P.disp_id, '
        . 'C.name AS country_name, C.abbr AS country_abbr '
        . 'FROM (' . $source . ') X '
        . 'JOIN RENJUNET_PLAYER P ON P.id=X.pid '
        . 'LEFT JOIN RENJUNET_COUNTRY C ON C.id=P.country_id '
        . $extra . ' GROUP BY X.pid, P.surname, P.name, P.native_name, P.disp_id, C.name, C.abbr';
    if ($country > 0) $params[] = $country;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[(int)$row['pid']] = $row;
    }
    $stmt->closeCursor();
    return $results;
}
function wrStreak(PDO $db, string $source, array $params, array $qualified, string $kind): array {
    $sql = 'SELECT X.pid, X.points, X.played, X.tour, X.round_label, X.gid '
        . 'FROM (' . $source . ') X ORDER BY X.pid, X.played, X.tour, '
        . 'CAST(X.round_label AS UNSIGNED), X.round_label, X.gid';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    $currentPlayer = -1; $count = 0; $start = ''; $end = '';
    $best = 0; $bestStart = ''; $bestEnd = '';
    $save = static function () use (&$rows, &$currentPlayer, &$count, &$start, &$end,
        &$best, &$bestStart, &$bestEnd, $qualified): void {
        if ($currentPlayer < 1 || !isset($qualified[$currentPlayer])) return;
        if ($count > $best || ($count === $best && $count >= 2
            && ($bestStart === '' || $start < $bestStart))) {
            $best = $count; $bestStart = $start; $bestEnd = $end;
        }
        if ($best >= 2) {
            $row = $qualified[$currentPlayer];
            $row['streak'] = $best;
            $row['started'] = $bestStart;
            $row['ended'] = $bestEnd;
            $rows[] = $row;
        }
    };
    while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$item['pid'];
        if ($pid !== $currentPlayer) {
            $save();
            $currentPlayer = $pid; $count = 0; $start = ''; $end = '';
            $best = 0; $bestStart = ''; $bestEnd = '';
        }
        $points = (float)$item['points'];
        $good = match ($kind) {
            'streak-win' => $points > 1,
            'streak-unbeaten' => $points >= 1,
            'streak-draw' => abs($points - 1.0) < 0.001,
            'streak-nondraw' => abs($points - 1.0) >= 0.001,
            default => false
        };
        if ($good) {
            if ($count === 0) $start = (string)$item['played'];
            $count++;
            $end = (string)$item['played'];
        } else {
            if ($count > $best || ($count === $best && $count >= 2
                && ($bestStart === '' || $start < $bestStart))) {
                $best = $count; $bestStart = $start; $bestEnd = $end;
            }
            $count = 0; $start = ''; $end = '';
        }
    }
    $save();
    $stmt->closeCursor();
    usort($rows, static function ($a, $b): int {
        return ((int)$b['streak'] <=> (int)$a['streak'])
            ?: strcmp((string)$a['started'], (string)$b['started'])
            ?: ((int)$a['pid'] <=> (int)$b['pid']);
    });
    return $rows;
}
$options = [
    'score' => ['神乎棋技', '期間最高得分率'],
    'wins' => ['只許成功', '期間最高勝率'],
    'draws' => ['以和為貴', '期間最高和棋率'],
    'games' => ['業精於勤', '期間對局最多'],
    'streak-win' => ['連戰皆捷', '個人最高連續獲勝局數'],
    'streak-unbeaten' => ['獨孤求敗', '個人最高連續不敗局數'],
    'streak-draw' => ['和氣一團', '個人最高連續和棋局數'],
    'streak-nondraw' => ['誓不兩立', '個人最高連續不和棋局數'],
];
$input = $_GET;
$record = is_string($input['record'] ?? null) && isset($options[$input['record']])
    ? $input['record'] : 'score';
$color = is_string($input['color'] ?? null) && in_array($input['color'], ['all','black','white'], true)
    ? $input['color'] : 'all';
$country = max(0, wrInt($input['country'] ?? 0));
$rule = max(0, wrInt($input['rule'] ?? 0));
$from = wrDate($input['from'] ?? '');
$to = wrDate($input['to'] ?? '');
$minGames = min(100000, max(0, wrInt($input['min_games'] ?? 10, 10)));
$openingInput = is_array($input['opening'] ?? null) ? $input['opening'] : [];
$openings = array_values(array_unique(array_filter(array_map(
    static fn($value): int => max(0, wrInt($value)), array_slice($openingInput, 0, 80)
))));
sort($openings, SORT_NUMERIC);
$page = max(1, wrInt($input['p'] ?? 1, 1));
$filters = ['record' => $record, 'country' => $country, 'color' => $color,
    'rule' => $rule, 'from' => $from, 'to' => $to, 'min_games' => $minGames];
if ($openings) $filters['opening'] = $openings;
$error = ''; $rows = []; $total = 0; $displayRows = [];
$countries = []; $rules = []; $openingOptions = []; $sourceDate = '';
try {
    $countries = $MYSQL->query('SELECT id, name, abbr FROM RENJUNET_COUNTRY ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $rules = $MYSQL->query('SELECT id, name FROM RENJUNET_RULE ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $openingOptions = $MYSQL->query('SELECT id, abbr, name FROM RENJUNET_OPENING ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $stamp = $MYSQL->query('SELECT database_date FROM RENJUNET_IMPORT ORDER BY imported_at DESC, id DESC LIMIT 1')->fetchColumn();
    $sourceDate = is_string($stamp) ? $stamp : '';
    if ($from !== '' && $to !== '' && $from > $to) {
        $error = '開始日期不可晚於結束日期。';
    } else {
        $conditions = [
            'T.rated=1',
            'T.end_date IS NOT NULL',
            'G.black_player_id>0',
            'G.white_player_id>0',
            'G.black_player_id<>G.white_player_id',
            'G.black_result IN (0, 0.5, 1)'
        ];
        $params = [];
        if ($from !== '') { $conditions[] = 'T.end_date>=?'; $params[] = $from; }
        if ($to !== '') { $conditions[] = 'T.end_date<=?'; $params[] = $to; }
        if ($rule > 0) { $conditions[] = 'G.rule_id=?'; $params[] = $rule; }
        if ($openings) {
            $conditions[] = 'G.opening_id IN (' . implode(',', array_fill(0, count($openings), '?')) . ')';
            array_push($params, ...$openings);
        }
        [$source, $sourceParams] = wrBuildQuery($color, $conditions, $params);
        $all = wrAggregate($MYSQL, $source, $sourceParams, $country);
        $qualified = array_filter($all, static fn($item) => (int)$item['games'] > $minGames);
        if (str_starts_with($record, 'streak-')) {
            $rows = wrStreak($MYSQL, $source, $sourceParams, $qualified, $record);
        } else {
            $rows = array_values($qualified);
            $metric = match ($record) {
                'score' => 'points', 'wins' => 'wins', 'draws' => 'draws', default => 'games'
            };
            usort($rows, static function ($a, $b) use ($record, $metric): int {
                $av = $record === 'games' ? (float)$a['games'] : (float)$a[$metric] / (float)$a['games'];
                $bv = $record === 'games' ? (float)$b['games'] : (float)$b[$metric] / (float)$b['games'];
                return ($bv <=> $av)
                    ?: ((int)$b['games'] <=> (int)$a['games'])
                    ?: ((int)$a['pid'] <=> (int)$b['pid']);
            });
        }
        $total = count($rows);
        $pages = max(1, (int)ceil($total / 50));
        $page = min($page, $pages);
        $displayRows = array_slice($rows, ($page - 1)*50, 50);
    }
} catch (Throwable $exception) {
    error_log('world-record.php: ' . $exception->getMessage());
    $error = '世界紀錄查詢失敗，請確認 RenjuNet 同步資料是否完整。';
}
?><!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>世界紀錄｜五子棋・連珠</title>
<link href="../renju.css" rel="stylesheet">
<style>
.wr-main{max-width:1150px;margin:0 auto;padding:18px 18px 50px;box-sizing:border-box}
.wr-main h1{margin:8px 0 12px}
.wr-note{color:#53677f;font-size:14px;line-height:1.65;margin:6px 0 18px}
.wr-filters{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px 12px;align-items:end;margin:16px 0 24px}
.wr-filters label{display:flex;flex-direction:column;gap:6px;font-size:14px;font-weight:600;color:#23384e;min-width:0}
.wr-filters input,.wr-filters select,.wr-filter-openings summary{font:inherit;box-sizing:border-box;width:100%;height:40px;padding:7px 10px;border:1px solid #c6d1de;border-radius:8px;background:#fff;color:#23384e}
.wr-filters input[type=number]{width:100%}
.wr-form-action{display:flex;gap:9px;align-items:center;height:40px}
.wr-btn{padding:9px 17px;background:#2474b7;border:1px solid #2474b7;border-radius:8px;color:#fff;cursor:pointer;font:inherit;white-space:nowrap}
.wr-reset{padding:8px 10px;border:1px solid #c6d1de;border-radius:8px;background:#fff;text-decoration:none;color:#23384e}
.wr-filter-openings{position:relative;min-width:0}
.wr-filter-openings>label{margin-bottom:6px}
.wr-filters .wr-opening-trigger{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;height:40px;min-height:40px;box-sizing:border-box;padding:7px 11px;border:1px solid #c6d1de;border-radius:8px;background:#fff;color:#23384e;font-size:14px;font-weight:400;text-align:left;cursor:pointer}
.wr-filters .wr-opening-trigger:hover{border-color:#7da6c8;background:#fff}
.wr-filters .wr-opening-trigger[aria-expanded=true]{border-color:#5286b4;box-shadow:0 0 0 3px #5286b41c}
.wr-opening-trigger-text{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.wr-opening-chevron{width:8px;height:8px;flex:0 0 auto;border-right:2px solid #5c7088;border-bottom:2px solid #5c7088;transform:rotate(45deg) translateY(-2px);transition:transform .15s}
.wr-opening-trigger[aria-expanded=true] .wr-opening-chevron{transform:rotate(225deg) translateY(-1px)}
.wr-filter-openings .wr-opening-panel[hidden]{display:none!important}
.wr-filter-openings .wr-opening-panel{position:absolute;z-index:40;top:calc(100% + 6px);left:0;width:340px;max-width:calc(100vw - 32px);box-sizing:border-box;display:flex;flex-direction:column;overflow:hidden;background:#fff;border:1px solid #bbcbdc;border-radius:10px;box-shadow:0 12px 26px #1b385028}
.wr-opening-search{padding:10px 11px 9px;border-bottom:1px solid #e4ebf2}
.wr-filters .wr-opening-search input[type=search]{width:100%;height:36px;min-height:36px;box-sizing:border-box;font-size:14px;padding:7px 10px;background:#fff;color:#23384e;border:1px solid #c6d1de;border-radius:7px}
.wr-opening-list{overflow-y:auto;max-height:250px;padding:5px 8px;overscroll-behavior:contain}
.wr-filters .wr-opening-list label{display:flex;flex-direction:row;align-items:center;gap:9px;min-height:32px;padding:6px 8px;margin:0;color:#273c55;font-size:14px;font-weight:400;white-space:normal;line-height:1.35;border-radius:6px;cursor:pointer}
.wr-filters .wr-opening-list label[hidden]{display:none}
.wr-filters .wr-opening-list label:hover{background:#edf5fc}
.wr-filters .wr-opening-list input[type=checkbox]{appearance:auto;flex:0 0 16px;width:16px;height:16px;min-height:0;margin:0;padding:0;border:initial;border-radius:3px;box-shadow:none;accent-color:#2474b7;cursor:pointer}
.wr-opening-empty{margin:10px;color:#64748b;font-size:13px}
.wr-opening-empty[hidden]{display:none}
.wr-opening-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 11px;background:#f8fafc;border-top:1px solid #e4ebf2}
.wr-filters .wr-opening-all{height:32px;min-height:32px;padding:5px 9px;border:1px solid #cbd8e5;border-radius:6px;background:#fff;color:#3d5976;font-size:13px;font-weight:500;cursor:pointer}
.wr-filters .wr-opening-all:hover{background:#eaf3fb}
.wr-opening-footer-right{display:flex;align-items:center;gap:10px}
.wr-opening-count{font-size:12px;color:#64748b;white-space:nowrap}
.wr-filters .wr-opening-done{height:32px;min-height:32px;padding:5px 15px;border:1px solid #2474b7;border-radius:6px;background:#2474b7;color:#fff;font-size:13px;font-weight:600;cursor:pointer}
.wr-filters .wr-opening-done:hover{background:#195c95}
.wr-pair{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.wr-title{font-size:22px;margin:20px 0 12px}
.wr-tablebox{width:100%;overflow-x:auto}
.wr-tablebox table{width:100%;min-width:680px;border-collapse:collapse;table-layout:auto}
.wr-tablebox th,.wr-tablebox td{border:1px solid #cad4df;padding:9px 10px;text-align:center;white-space:nowrap}
.wr-tablebox th{background:#dce8f4;color:#233e5a}
.wr-tablebox tbody tr:nth-child(even){background:#f6f9fc}
.wr-tablebox a{text-decoration:none}
.wr-name{text-align:left!important;min-width:250px}
.wr-name-pair{display:inline-flex;flex-direction:row;align-items:baseline;gap:14px;white-space:nowrap}
.wr-native{display:inline;color:#66788e;font-size:14px}
.wr-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:16px 0}
.wr-pagination nav{display:flex;gap:5px;align-items:center;flex-wrap:wrap}
.wr-pagination nav a,.wr-pagination nav strong{border:1px solid #c8d4e1;min-width:34px;padding:7px 9px;text-align:center;border-radius:7px;box-sizing:border-box;text-decoration:none;background:white;color:#284058}
.wr-pagination nav strong{background:#dfeaf5}
.wr-error{color:#a32b2b}
@media(max-width:900px){.wr-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.wr-main{padding:12px}.wr-filters{grid-template-columns:1fr}.wr-filter-openings .wr-opening-panel{width:100%;max-width:100%}.wr-pagination{justify-content:center}}
</style>
<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
<script>
$(function(){ $.ajax({url:"https://587.renju.org.tw/menu.html",async:false,success:function(text){$("#myDiv").html(text);}}); });
function wrUpdateLabel(){
    const checks=[...document.querySelectorAll('.wr-opening-list input[type=checkbox]')];
    const selected=checks.filter(x=>x.checked).length;
    const text=selected ? '已選 '+selected+' 種開局' : '全部開局';
    document.getElementById('wr-opening-text').textContent=text;
    document.getElementById('wr-opening-count').textContent=selected ? selected+' 種已選' : '未限制';
}
function wrCloseOpenings(restoreFocus){
    const panel=document.getElementById('wr-opening-panel');
    const trigger=document.getElementById('wr-opening-trigger');
    if(panel.hidden)return;
    panel.hidden=true;
    trigger.setAttribute('aria-expanded','false');
    if(restoreFocus)trigger.focus();
}
function wrToggleOpenings(){
    const panel=document.getElementById('wr-opening-panel');
    const trigger=document.getElementById('wr-opening-trigger');
    if(!panel.hidden){wrCloseOpenings(false);return;}
    panel.hidden=false;
    trigger.setAttribute('aria-expanded','true');
    document.getElementById('wr-opening-search').focus();
}
function wrResetOpenings(){
    document.querySelectorAll('.wr-opening-list input[type=checkbox]').forEach(c=>{c.checked=false;});
    document.getElementById('wr-opening-search').value='';
    wrSearchOpenings('');
    wrUpdateLabel();
    wrCloseOpenings(true);
}
function wrSearchOpenings(term){
    const search=term.trim().toLocaleLowerCase();
    let visible=0;
    document.querySelectorAll('.wr-opening-list label').forEach(function(label){
        const show=label.textContent.toLocaleLowerCase().includes(search);
        label.hidden=!show;
        if(show)visible++;
    });
    document.getElementById('wr-opening-empty').hidden=visible!==0;
}
document.addEventListener('DOMContentLoaded',function(){
    wrUpdateLabel();
    const wrapper=document.getElementById('wr-filter-openings');
    document.addEventListener('pointerdown',function(event){
        if(!wrapper.contains(event.target))wrCloseOpenings(false);
    });
    document.addEventListener('keydown',function(event){
        if(event.key==='Escape')wrCloseOpenings(true);
    });
});
</script>
</head>
<body>
<div id="myDiv"></div>
<main class="wr-main">
<h1>世界紀錄</h1>
<p class="wr-note">資料來源：RenjuNet（<?= wrH($sourceDate !== '' ? '更新至 '.$sourceDate : '本地同步資料') ?>）。僅統計標記為 Rated（計入排名）的比賽對局，期間依比賽結束日篩選，國家依棋士所屬國家篩選；局數門檻採嚴格大於。</p>
<form method="get" class="wr-filters">
<label>選擇紀錄
<select name="record" aria-label="選擇紀錄">
<?php foreach ($options as $key=>$option): ?>
<option value="<?= wrH($key) ?>" <?= $record===$key?'selected':'' ?>><?= wrH($option[0].' - '.$option[1]) ?></option>
<?php endforeach; ?>
</select></label>
<label>國家/地區
<select name="country">
<option value="0">全部國家/地區</option>
<?php foreach ($countries as $option): ?><option value="<?= (int)$option['id'] ?>" <?= $country===(int)$option['id']?'selected':'' ?>><?= wrH(wrCountryName($option['name'], $option['abbr'])) ?></option><?php endforeach; ?>
</select></label>
<label>使用顏色
<select name="color">
<option value="all" <?= $color==='all'?'selected':'' ?>>全部</option>
<option value="black" <?= $color==='black'?'selected':'' ?>>持黑</option>
<option value="white" <?= $color==='white'?'selected':'' ?>>持白</option>
</select></label>
<label>規則
<select name="rule">
<option value="0">全部規則</option>
<?php foreach ($rules as $option): ?><option value="<?= (int)$option['id'] ?>" <?= $rule===(int)$option['id']?'selected':'' ?>><?= wrH($option['name']) ?></option><?php endforeach; ?>
</select></label>
<div class="wr-filter-openings" id="wr-filter-openings">
<label for="wr-opening-trigger">開局</label>
<button type="button" id="wr-opening-trigger" class="wr-opening-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="wr-opening-panel" onclick="wrToggleOpenings()"><span class="wr-opening-trigger-text" id="wr-opening-text">全部開局</span><span class="wr-opening-chevron" aria-hidden="true"></span></button>
<div id="wr-opening-panel" class="wr-opening-panel" hidden>
<div class="wr-opening-search"><input id="wr-opening-search" type="search" placeholder="搜尋開局" aria-label="搜尋開局" oninput="wrSearchOpenings(this.value)" autocomplete="off"></div>
<div class="wr-opening-list" role="group" aria-label="選擇開局（可複選）">
<?php foreach ($openingOptions as $option): ?>
<label><input type="checkbox" name="opening[]" value="<?= (int)$option['id'] ?>" <?= in_array((int)$option['id'],$openings,true)?'checked':'' ?> onchange="wrUpdateLabel()"><span><?= wrH(trim((string)$option['abbr'].' '.(string)$option['name'])) ?></span></label>
<?php endforeach; ?>
<p class="wr-opening-empty" id="wr-opening-empty" hidden>沒有符合的開局</p>
</div>
<div class="wr-opening-footer"><button type="button" class="wr-opening-all" onclick="wrResetOpenings()">全部開局</button><span class="wr-opening-footer-right"><span class="wr-opening-count" id="wr-opening-count">未限制</span><button type="button" class="wr-opening-done" onclick="wrCloseOpenings(true)">完成</button></span></div>
</div>
</div>
<label>開始日期<input type="date" name="from" value="<?= wrH($from) ?>"></label>
<label>結束日期<input type="date" name="to" value="<?= wrH($to) ?>"></label>
<label>局數基準（超過幾局）<input type="number" name="min_games" min="0" max="100000" step="1" value="<?= $minGames ?>"></label>
<div class="wr-form-action"><button class="wr-btn" type="submit">查詢</button><a class="wr-reset" href="world-record.php">清除篩選</a></div>
</form>
<h2 class="wr-title"><?= wrH($options[$record][0]) ?></h2>
<?php if($error!==''): ?><p class="wr-error" role="alert"><?= wrH($error) ?></p>
<?php else: ?>
<div class="wr-tablebox">
<table>
<thead><tr><th>排名</th><th>國家/地區</th><th>棋士</th>
<?php if (in_array($record,['score','wins','draws'],true)): ?>
<th>勝</th><th>和</th><th>負</th><th>局數</th><th><?= wrH(['score'=>'得分率','wins'=>'勝率','draws'=>'和棋率'][$record]) ?></th>
<?php elseif ($record === 'games'): ?>
<th>局數</th><th>勝</th><th>和</th><th>負</th>
<?php else: ?>
<th>連續局數</th><th>開始日</th><th>結束日</th>
<?php endif; ?>
</tr></thead>
<tbody>
<?php foreach ($displayRows as $i=>$r):
    $roman=wrLabel($r);
    $native=trim((string)$r['native_name']);
    $disp=(string)$r['disp_id'];
    $playerHref='https://www.renju.net/people/'.rawurlencode($disp).'/';
?>
<tr>
<td><?= ($page-1)*50+$i+1 ?></td>
<td><?= wrH(wrCountryName($r['country_name']??'', $r['country_abbr']??'')) ?></td>
<td class="wr-name"><span class="wr-name-pair"><a href="<?= wrH($playerHref) ?>" target="_blank" rel="noopener noreferrer"><?= wrH($roman!==''?$roman:('#'.(int)$r['pid'])) ?></a><?php if($native!=='' && strcasecmp($native,$roman)!==0): ?><span class="wr-native"><?= wrH($native) ?></span><?php endif; ?></span></td>
<?php if(in_array($record,['score','wins','draws'],true)):
    $numerator=$record==='score'?(float)$r['points']:($record==='wins'?(float)$r['wins']:(float)$r['draws']);
    $rate=round($numerator/(int)$r['games']*100,2);
?>
<td><?= (int)$r['wins'] ?></td><td><?= (int)$r['draws'] ?></td><td><?= (int)$r['losses'] ?></td>
<td><?= (int)$r['games'] ?></td><td><?= number_format($rate,2) ?>%</td>
<?php elseif($record==='games'): ?>
<td><?= (int)$r['games'] ?></td><td><?= (int)$r['wins'] ?></td><td><?= (int)$r['draws'] ?></td><td><?= (int)$r['losses'] ?></td>
<?php else: ?>
<td><?= (int)$r['streak'] ?></td><td><?= wrH($r['started']) ?></td><td><?= wrH($r['ended']) ?></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
<?php if(!$displayRows): ?><tr><td colspan="9">目前沒有符合篩選條件的紀錄。</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<div class="wr-pagination"><span>共 <?= $total ?> 位棋士，每頁 50 筆</span>
<nav aria-label="世界紀錄分頁">
<?php if($page>1): ?><a href="<?= wrH(wrLink($filters,$page-1)) ?>">上一頁</a><?php endif; ?>
<?php
$pages=max(1,(int)ceil($total/50));
$begin=max(1,$page-2);$last=min($pages,$page+2);
if($begin>1){echo '<a href="'.wrH(wrLink($filters,1)).'">1</a>';if($begin>2)echo '…';}
for($i=$begin;$i<=$last;$i++){
    if($i===$page)echo '<strong aria-current="page">'.$i.'</strong>';
    else echo '<a href="'.wrH(wrLink($filters,$i)).'">'.$i.'</a>';
}
if($last<$pages){if($last<$pages-1)echo '…';echo '<a href="'.wrH(wrLink($filters,$pages)).'">'.$pages.'</a>';}
?>
<?php if($page<$pages): ?><a href="<?= wrH(wrLink($filters,$page+1)) ?>">下一頁</a><?php endif; ?>
</nav></div>
<?php endif; ?>
</main></body></html>
