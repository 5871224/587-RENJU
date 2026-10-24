<?php
/** Two-level administration panel. Included inside authenticated rank/index.php. */
$treeJson = json_encode($ruleTreeRows ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE |
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<section class="panel rule-tree-admin" id="rule-tree-admin"
    data-csrf="<?= h((string)$_SESSION['rank_rebuild_csrf']) ?>"
    data-selected="<?= (int)($_GET['selected'] ?? 0) ?>"
    data-root-section-title="<?= h($ruleTreeSections['game']['title']) ?>"
    data-child-section-title="<?= h($ruleTreeSections['opening']['title']) ?>"
    data-edit-icon="<?= h(rankActionIcon('edit')) ?>"
    data-delete-icon="<?= h(rankActionIcon('delete')) ?>">
    <div class="panel-head rule-tree-admin-head">
        <div>
            <h2>規則內容</h2>
        </div>
        <div class="panel-tools rule-tree-toolbar">
            <button class="btn primary" type="button" data-tree-action="add-root">＋ 新增<?= h($ruleTreeSections['game']['title']) ?></button>
            <button class="btn" type="button" data-tree-action="add-child">＋ 新增<?= h($ruleTreeSections['opening']['title']) ?></button>
            <a class="btn" href="../rule.htm" target="_blank" rel="noopener">檢視前台</a>
        </div>
    </div>
    <div class="rule-tree-grid">
        <svg class="rule-tree-admin-connectors" id="rule-tree-admin-connectors" aria-hidden="true" focusable="false" preserveAspectRatio="none"></svg>
        <section class="rule-tree-column rule-tree-column--root" aria-label="第一層行棋規則">
            <header class="rule-tree-column-head">
                <h3><?= h($ruleTreeSections['game']['title']) ?></h3>
                <span class="rule-tree-section-note"><?= h($ruleTreeSections['game']['note']) ?></span>
                <span class="rule-tree-count" id="rule-tree-root-count"></span>
                <button type="button" class="icon-action rule-tree-section-edit" data-tree-action="edit-section" data-section="game"
                    data-section-title="<?= h($ruleTreeSections['game']['title']) ?>"
                    data-section-note="<?= h($ruleTreeSections['game']['note']) ?>"
                    aria-label="編輯行棋規則區塊名稱與註記" title="編輯區塊名稱與註記"><?= rankActionIcon('edit') ?></button>
            </header>
            <div class="rule-tree-stack" id="rule-tree-root-list"></div>
        </section>
        <section class="rule-tree-column rule-tree-column--child" aria-label="第二層開局規則">
            <header class="rule-tree-column-head">
                <h3><?= h($ruleTreeSections['opening']['title']) ?></h3>
                <span class="rule-tree-section-note"><?= h($ruleTreeSections['opening']['note']) ?></span>
                <span class="rule-tree-count" id="rule-tree-child-count"></span>
                <button type="button" class="icon-action rule-tree-section-edit" data-tree-action="edit-section" data-section="opening"
                    data-section-title="<?= h($ruleTreeSections['opening']['title']) ?>"
                    data-section-note="<?= h($ruleTreeSections['opening']['note']) ?>"
                    aria-label="編輯開局規則區塊名稱與註記" title="編輯區塊名稱與註記"><?= rankActionIcon('edit') ?></button>
            </header>
            <div class="rule-tree-stack" id="rule-tree-child-list"></div>
        </section>
    </div>
    <dialog class="rule-tree-dialog rule-tree-section-dialog" id="rule-tree-section-dialog" aria-labelledby="rule-tree-section-dialog-title">
        <form method="post" action="?view=site-rules" id="rule-tree-section-form">
            <header class="rule-tree-dialog-head">
                <h3 id="rule-tree-section-dialog-title">編輯規則區塊</h3>
                <button type="button" class="rule-tree-close" data-tree-action="close-section" aria-label="關閉">×</button>
            </header>
            <input type="hidden" name="view" value="site-rules">
            <input type="hidden" name="action" value="tree_section_save">
            <input type="hidden" name="csrf" value="<?= h((string)$_SESSION['rank_rebuild_csrf']) ?>">
            <input type="hidden" name="section_key" id="rule-tree-section-key">
            <input type="hidden" name="selected" id="rule-tree-section-selected">
            <label>區塊名稱
                <input type="text" name="section_title" id="rule-tree-section-title" maxlength="100" required>
            </label>
            <label>註記（選填，顯示於名稱右側）
                <input type="text" name="section_note" id="rule-tree-section-note" maxlength="500">
            </label>
            <div class="rule-tree-dialog-actions">
                <button type="button" class="btn" data-tree-action="close-section">取消</button>
                <button type="submit" class="btn primary">儲存</button>
            </div>
        </form>
    </dialog>
    <dialog class="rule-tree-dialog" id="rule-tree-dialog" aria-labelledby="rule-tree-dialog-title">
        <form method="post" action="?view=site-rules" id="rule-tree-form">
            <header class="rule-tree-dialog-head">
                <h3 id="rule-tree-dialog-title">規則內容</h3>
                <button type="button" class="rule-tree-close" data-tree-action="close" aria-label="關閉">×</button>
            </header>
            <input type="hidden" name="view" value="site-rules">
            <input type="hidden" name="action" value="tree_save">
            <input type="hidden" name="csrf" value="<?= h((string)$_SESSION['rank_rebuild_csrf']) ?>">
            <input type="hidden" name="id" id="rule-tree-field-id" value="0">
            <label>所屬層級
                <select name="parent_id" id="rule-tree-field-parent">
                    <option value="">第一層｜<?= h($ruleTreeSections['game']['title']) ?></option>
                </select>
            </label>
            <label>規則名稱
                <input name="title" id="rule-tree-field-title" type="text" maxlength="200" required>
            </label>
            <label>註記（選填，單行）
                <input type="text" name="note" id="rule-tree-field-note" maxlength="500">
            </label>
            <label>規則內容（純文字，可換行）
                <textarea name="content_text" id="rule-tree-field-content" rows="9"></textarea>
            </label>
            <div class="rule-tree-dialog-actions">
                <button type="button" class="btn" data-tree-action="close">取消</button>
                <button type="submit" class="btn primary">儲存</button>
            </div>
        </form>
    </dialog>
    <form method="post" action="?view=site-rules" id="rule-tree-delete-form" hidden>
        <input name="view" value="site-rules">
        <input name="action" value="tree_delete">
        <input name="csrf" value="<?= h((string)$_SESSION['rank_rebuild_csrf']) ?>">
        <input name="id" id="rule-tree-delete-id">
    </form>
    <script type="application/json" id="rule-tree-data"><?= $treeJson ?></script>
</section>