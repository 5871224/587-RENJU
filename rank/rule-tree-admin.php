<?php
/** Authenticated fixed-depth rule-tree editor. Included by rank/index.php. */
declare(strict_types=1);

function rankRuleTreeRows(PDO $db): array
{
    return $db->query('SELECT id,parent_id,page_slug,title,note,content_text,sort_order
        FROM SITE_RULE_ITEM ORDER BY (parent_id IS NOT NULL),sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
}

function rankRuleTreeValidParent(PDO $db, int $parentId): array
{
    $get = $db->prepare('SELECT id,parent_id,page_slug FROM SITE_RULE_ITEM WHERE id=?');
    $get->execute([$parentId]);
    $parent = $get->fetch(PDO::FETCH_ASSOC);
    if (!$parent || $parent['parent_id'] !== null) {
        throw new RuntimeException('第二層規則只能隸屬第一層行棋規則。');
    }
    return $parent;
}

function rankRuleTreeLegacySlug(PDO $db, ?int $parentId, string $previous = 'rulehome'): string
{
    if ($parentId === null) return $previous === 'other' ? 'other' : 'rulehome';
    $get = $db->prepare("SELECT page_slug FROM SITE_RULE_ITEM
        WHERE parent_id=? AND page_slug<>'rulehome' ORDER BY sort_order,id LIMIT 1");
    $get->execute([$parentId]);
    return (string)($get->fetchColumn() ?: 'rulehome');
}

function rankRuleTreeHandlePost(PDO $db, string $csrf): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $action = (string)($_POST['action'] ?? '');
    $json = $action === 'tree_reorder';
    try {
        $sent = (string)($_POST['csrf'] ?? '');
        if ($sent === '' || !hash_equals($csrf, $sent)) {
            throw new RuntimeException('表單驗證失效，請重新整理再試。');
        }

        if ($action === 'tree_reorder') {
            $parentString = (string)($_POST['parent_id'] ?? '');
            if ($parentString !== '' && !preg_match('/^[1-9][0-9]*$/D', $parentString)) {
                throw new RuntimeException('無效的父節點。');
            }
            $parentId = $parentString === '' ? null : (int)$parentString;
            if ($parentId !== null) rankRuleTreeValidParent($db, $parentId);
            $raw = $_POST['ids'] ?? null;
            if (!is_array($raw) || !$raw || count($raw) > 1000) throw new RuntimeException('排序清單格式不正確。');
            $ids = [];
            foreach ($raw as $id) {
                if (!is_scalar($id) || !preg_match('/^[1-9][0-9]*$/D', (string)$id)) {
                    throw new RuntimeException('排序編號不正確。');
                }
                $ids[] = (int)$id;
            }
            if (count(array_unique($ids)) !== count($ids)) throw new RuntimeException('排序含重複資料。');
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT id FROM SITE_RULE_ITEM
                WHERE parent_id <=> ? ORDER BY sort_order,id FOR UPDATE');
            $stmt->execute([$parentId]);
            $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $sortedIds = $ids;
            sort($sortedIds, SORT_NUMERIC);
            sort($existing, SORT_NUMERIC);
            if ($sortedIds !== $existing) throw new RuntimeException('目前規則清單已變動，請重新整理。');
            $update = $db->prepare('UPDATE SITE_RULE_ITEM SET sort_order=? WHERE id=? AND parent_id <=> ?');
            foreach ($ids as $index => $id) $update->execute([($index + 1) * 10,$id,$parentId]);
            $db->commit();
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'tree_section_save') {
            $section = (string)($_POST['section_key'] ?? '');
            if (!in_array($section, ['game', 'opening'], true)) {
                throw new RuntimeException('無效的規則區塊。');
            }
            $title = trim((string)($_POST['section_title'] ?? ''));
            $note = trim((string)preg_replace('/\\s+/u', ' ', (string)($_POST['section_note'] ?? '')));
            if ($title === '' || mb_strlen($title, 'UTF-8') > 100) {
                throw new RuntimeException('區塊名稱不可空白，最多 100 字。');
            }
            if (mb_strlen($note, 'UTF-8') > 500) {
                throw new RuntimeException('區塊註記最多 500 字。');
            }
            $update = $db->prepare('UPDATE SITE_RULE_TREE_SECTION
                SET title=?,note=? WHERE section_key=?');
            $update->execute([$title,$note,$section]);
            header('Location: ?view=site-rules&saved=1&selected=' .
                max(0, (int)($_POST['selected'] ?? 0)), true, 303);
            exit;
        }

        if ($action === 'tree_save') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if ($id === false || $id < 0) throw new RuntimeException('資料編號不正確。');
            $name = trim((string)($_POST['title'] ?? ''));
            if ($name === '' || mb_strlen($name, 'UTF-8') > 200) {
                throw new RuntimeException('規則名稱不可空白，且最多 200 字。');
            }
            $note = trim((string)preg_replace('/\\s+/u', ' ', (string)($_POST['note'] ?? '')));
            if (mb_strlen($note, 'UTF-8') > 500) throw new RuntimeException('規則註記最多 500 字。');
            $body = str_replace(["\r\n","\r"], "\n", (string)($_POST['content_text'] ?? ''));
            if (strlen($body) > 1200000) throw new RuntimeException('內容長度超過限制。');
            $parentRaw = (string)($_POST['parent_id'] ?? '');
            if ($parentRaw !== '' && !preg_match('/^[1-9][0-9]*$/D', $parentRaw)) {
                throw new RuntimeException('請選擇正確的第一層規則。');
            }
            $parentId = $parentRaw === '' ? null : (int)$parentRaw;
            $db->beginTransaction();
            if ($parentId !== null) rankRuleTreeValidParent($db, $parentId);
            if ($id) {
                $get = $db->prepare('SELECT id,parent_id,page_slug FROM SITE_RULE_ITEM WHERE id=? FOR UPDATE');
                $get->execute([$id]);
                $original = $get->fetch(PDO::FETCH_ASSOC);
                if (!$original) throw new RuntimeException('找不到規則資料。');
                if ($parentId === $id) throw new RuntimeException('不能將規則設為自己的子節點。');
                if ($parentId !== null) {
                    $count = $db->prepare('SELECT COUNT(*) FROM SITE_RULE_ITEM WHERE parent_id=?');
                    $count->execute([$id]);
                    if ((int)$count->fetchColumn() !== 0) throw new RuntimeException('這項規則含有子規則，不能改成第二層。');
                }
                $slug = $parentId !== null && $parentId !== ($original['parent_id'] === null ? null : (int)$original['parent_id'])
                    ? rankRuleTreeLegacySlug($db,$parentId) : (string)$original['page_slug'];
                if ($parentId === null) $slug = rankRuleTreeLegacySlug($db,null,(string)$original['page_slug']);
                $save = $db->prepare('UPDATE SITE_RULE_ITEM SET
                    parent_id=?,page_slug=?,title=?,note=?,content_text=?,is_visible=1 WHERE id=?');
                $save->execute([$parentId,$slug,$name,$note,$body,$id]);
            } else {
                $slug = rankRuleTreeLegacySlug($db,$parentId);
                $next = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM SITE_RULE_ITEM WHERE parent_id <=> ?');
                $next->execute([$parentId]);
                $save = $db->prepare('INSERT INTO SITE_RULE_ITEM
                    (parent_id,page_slug,title,note,content_text,sort_order,is_visible) VALUES (?,?,?,?,?,?,1)');
                $save->execute([$parentId,$slug,$name,$note,$body,(int)$next->fetchColumn()]);
                $id = (int)$db->lastInsertId();
            }
            $db->commit();
            header('Location: ?view=site-rules&saved=1&selected=' . ($parentId ?? $id), true, 303);
            exit;
        }

        if ($action === 'tree_delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new RuntimeException('無效的規則編號。');
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT parent_id FROM SITE_RULE_ITEM WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $parent = $stmt->fetchColumn();
            if ($parent === false) throw new RuntimeException('規則已不存在。');
            $children = $db->prepare('SELECT COUNT(*) FROM SITE_RULE_ITEM WHERE parent_id=?');
            $children->execute([$id]);
            if ((int)$children->fetchColumn() !== 0) {
                throw new RuntimeException('請先移動或刪除這項規則底下的所有子規則。');
            }
            $del = $db->prepare('DELETE FROM SITE_RULE_ITEM WHERE id=?');
            $del->execute([$id]);
            $db->commit();
            header('Location: ?view=site-rules&deleted=1' . ($parent === null ? '' : '&selected=' . (int)$parent), true, 303);
            exit;
        }

        throw new RuntimeException('無效的規則操作。');
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        if ($json) {
            http_response_code(409);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok'=>false,'error'=>$error->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        throw $error;
    }
}
