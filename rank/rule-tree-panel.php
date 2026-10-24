<?php
/** Two-level administration panel. Included inside authenticated rank/index.php. */
$treeJson = json_encode($ruleTreeRows ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE |
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<section class="panel rule-tree-admin" id="rule-tree-admin"
    data-csrf="<?= h((string)$_SESSION['rank_rebuild_csrf']) ?>"
    data-selected="<?= (int)($_GET['selected'] ?? 0) ?>">
    <div class="panel-head rule-tree-admin-head">
        <div>
            <h2>規則內容</h2>
        </div>
        <div class="panel-tools rule-tree-toolbar">
            <button class="btn primary" type="button" data-tree-action="add-root">＋ 新增行棋規則</button>
            <button class="btn" type="button" data-tree-action="add-child">＋ 新增子規則</button>
            <a class="btn" href="../rule.htm" target="_blank" rel="noopener">檢視前台</a>
        </div>
    </div>
    <div class="rule-tree-grid">
        <svg class="rule-tree-admin-connectors" id="rule-tree-admin-connectors" aria-hidden="true" focusable="false" preserveAspectRatio="none"></svg>
        <section class="rule-tree-column rule-tree-column--root" aria-label="第一層行棋規則">
            <header class="rule-tree-column-head">
                <h3>行棋規則</h3>
                <span class="rule-tree-count" id="rule-tree-root-count"></span>
            </header>
            <div class="rule-tree-stack" id="rule-tree-root-list"></div>
        </section>
        <section class="rule-tree-column rule-tree-column--child" aria-label="第二層開局規則">
            <header class="rule-tree-column-head">
                <h3>開局規則</h3>
                <span class="rule-tree-count" id="rule-tree-child-count"></span>
            </header>
            <div class="rule-tree-stack" id="rule-tree-child-list"></div>
        </section>
    </div>
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
                    <option value="">第一層｜行棋規則</option>
                </select>
            </label>
            <label>規則名稱
                <input name="title" id="rule-tree-field-title" type="text" maxlength="200" required>
            </label>
            <label>規則內容（純文字，可換行）
                <textarea name="content_text" id="rule-tree-field-content" rows="9"></textarea>
            </label>
            <label>公開狀態
                <select name="is_visible" id="rule-tree-field-visible">
                    <option value="1">顯示</option><option value="0">隱藏</option>
                </select>
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