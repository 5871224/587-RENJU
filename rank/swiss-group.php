<?php

function swissVisibleGroupData(array $groupData, string $label): array {
    $visible = [];
    $challengeIndex = null;
    $challengeCount = 0;

    foreach ($groupData as $data) {
        $isChallenge = !isset($data['error']) && ($data['format'] ?? '') === '挑戰賽';
        if (!$isChallenge) {
            $visible[] = $data;
            continue;
        }

        $challengeCount++;
        if ($challengeIndex === null) {
            $challengeIndex = count($visible);
            $visible[] = $data;
            continue;
        }

        $visible[$challengeIndex]['history'] = array_merge(
            $visible[$challengeIndex]['history'] ?? [],
            $data['history'] ?? []
        );
        $visible[$challengeIndex]['promotions'] = array_merge(
            $visible[$challengeIndex]['promotions'] ?? [],
            $data['promotions'] ?? []
        );
    }

    $groupedChallenge = $challengeCount > 1 && $challengeIndex !== null;
    if ($groupedChallenge) {
        $title = trim($label);
        if ($title === '') {
            $title = trim((string)($visible[$challengeIndex]['tournament']['賽標'] ?? ''));
        }
        if ($title === '') {
            $title = trim((string)($visible[$challengeIndex]['tournament']['賽名'] ?? ''));
        }
        if ($title !== '' && !preg_match('/挑戰賽$/u', $title)) $title .= '挑戰賽';
        $visible[$challengeIndex]['_title_override'] = $title;
    }

    return [$visible, $groupedChallenge];
}
