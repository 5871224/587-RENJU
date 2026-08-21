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
