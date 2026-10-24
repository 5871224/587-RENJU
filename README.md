# 台灣連珠網（587）

這是 `https://587.renju.org.tw/` 的網站原始碼。主分支為 `main`；每次 push 都會由 GitHub Actions 先檢查 PHP 語法與安全邊界，再透過 FTPS 更新正式網站。

## 本機設定

需要 PHP 與 PowerShell：

```powershell
./scripts/check-project.ps1
```

需要執行資料庫相關頁面時，複製 `config.local.php.example` 為 `config.local.php`，填入本機或伺服器設定。`config.local.php` 已被 Git 忽略，不可提交。

`config.local.php` 可設定：

- MySQL 主機、帳號、密碼
- ImgBB API Key
- 短網址 API Key
- `/rank/` 管理帳號與 `password_hash()` 產生的密碼雜湊

所有密碼、API Key 與正式環境憑證都必須留在伺服器設定或 GitHub Actions Secrets，不可寫入 repository。


## RenjuNet 手動同步

`/rank/?view=rating-tools&tool=renjunet` 的「更新 RenjuNet」連結會開啟儲存庫既有的 GitHub Actions 工作流程：`renjunet-sync.yml`。使用者在 GitHub 登入後點選 **Run workflow** 即可立即排入執行；不必在網站設定 GitHub Personal Access Token，也不會提供免驗證的公共觸發端點。

整個下載與資料庫更新仍在 GitHub Actions 執行。網站繼續顯示 `RENJUNET_IMPORT` 最後實際資料變更日期，來源 RIF 沒變化時不會新增匯入紀錄。需要重建 `RENJUNET_ELO` 時，另按頁面的「完整重算 RENJUNET Elo」。


## 開發規範：禁止補丁式寫法

所有前台、後台、CSS、JavaScript、PHP、SQL、資料庫遷移及部署程式的修改，都必須以**找出根本原因、修改既有權威實作、清除被取代的重複程式**為原則。

- 不得在 CSS 尾端追加同一選擇器或提高優先權來蓋過舊樣式；應直接整理原本的宣告，跨頁共用樣式須有單一來源。
- 不得以重複事件監聽、額外 DOM 操作、計時器、臨時條件、平行函式或重複 API 作為正式修正方式。
- 不得使用硬編碼資料、暫時 SQL 修補、跳過權限／驗證、隱藏例外或保留無期限的新舊雙軌邏輯。
- 資料表變動應使用正式、可驗證且保留既有資料的遷移；跨頁功能應抽取共用模組。
- 每次修正須一併移除失效分支、重複規則、過時引用及註解，並執行相關測試、核對 Git diff 與部署結果。

修改量小並不代表補丁式寫法；允許直接在正確位置進行最小必要修改，也允許有明確理由的媒體查詢、瀏覽器相容與資料庫過渡流程，但不得形成互相覆蓋的永久結構。

規則樹專用約束：`rule-tree-visual.css` 是前後台連線及數量圓標樣式的唯一來源，不得在 `rule-tree.css` 或 `rank/rule-tree-admin.css` 再設定相同的線條顏色或圓標底色；由 `scripts/check-rule-tree-visual.js` 驗證。

## GitHub 自動部署

在 repository 的 `Settings > Secrets and variables > Actions` 設定：

- `FTP_SERVER`
- `FTP_USERNAME`
- `FTP_PASSWORD`

並設定 Variable：

- `FTP_SERVER_DIR`

部署流程只上傳 GitHub 管理的網站檔案，不主動清除 FTP 上其他檔案。

## 安全原則

- 公開端點不可接受任意 SQL。
- 管理功能必須經過登入驗證。
- 動態資料表名稱必須使用固定白名單。
- 使用者上傳檔案不可直接寫入可執行的網站目錄。
- `config.local.php`、密碼、API Key 與其他秘密不得提交到 Git。
