# Site Inspection Mini｜開發進度

> 本文件記錄內部開發階段、驗證與問題；`README.md` 僅保留對外專案說明。
> Git Commit 與 Push 一律由使用者操作，Codex 不代為執行。

## 階段總覽

| 階段 | 工作 | 狀態 |
| --- | --- | --- |
| 0 | 環境檢查、前後端初始化 | 已完成（2026-11-02） |
| 1 | SQLite 資料表、Migration、Model | 待開始 |
| 2 | 巡檢 API、圖片上傳與後端驗證 | 待開始 |
| 3 | 手機版巡檢回報表單 | 待開始 |
| 4 | 桌機列表、篩選、詳細頁 | 待開始 |
| 5 | 整合測試與錯誤修正 | 待開始 |
| 6 | README、GitHub 交件整理 | 待開始 |
| 7 | 三分鐘 Demo 影片 | 待開始 |

## 階段 0：環境檢查與專案初始化

**完成日期：**2026-11-02（Asia/Taipei）

### 已完成
- `backend/`：Laravel 13，註冊空的 `routes/api.php`，預設 `/api` 前綴。
- `frontend/`：Vue 3 + TypeScript + Vite、Composition API、`<script setup lang="ts">`、Vue Router、Axios、Bootstrap 5；未安裝 Pinia。
- Router 已接初始化畫面；Axios client 位於 `frontend/src/lib/http.ts`；Bootstrap CSS 已載入。
- 根目錄 `.gitignore` 已排除 `.env`、依賴目錄、Build 產物、資料庫實體檔、Laravel 快取／Log 等；`.env.example`、lockfile 可提交。
- Laravel 官方骨架的示範檔及預設 users/cache/jobs Migration 保留；**尚未執行 Migration，尚未實作巡檢功能**。
- 後端原有前端骨架保留，但本專案前端工作只在 `frontend/`；無須於 `backend/` 執行 npm。

### 環境版本

| 項目 | 實際版本 |
| --- | --- |
| PHP / Composer | 8.4.27 / 2.10.2 |
| Node.js / npm | 24.22.1 / 11.19.1 |
| Git | 2.52.0.windows.1 |
| SQLite（PHP PDO） | 3.53.4 |
| Laravel framework / skeleton | 13.39.0 / 13.0.0 |
| Vue / TypeScript / Vite | 3.5.44 / 6.0.3 / 8.3.5 |
| vue-tsc / Vue Router | 3.3.14 / 4.6.4 |
| Axios / Bootstrap | 1.21.0 / 5.3.8 |

已確認 `pdo_sqlite`、`sqlite3`、`fileinfo`、`ctype`、`curl`、`dom`、`filter`、`hash`、`mbstring`、`openssl`、`pcre`、`PDO`、`session`、`tokenizer`、`xml`、`xmlwriter`、`zip` 可使用；SQLite 記憶體查詢及 fileinfo MIME 偵測通過。

### 驗證結果
- `composer check-platform-reqs`：通過。
- `php artisan test`：2 tests／2 assertions 通過（Laravel 官方預設測試）。
- Laravel 實際啟動後：`GET /up`、`GET /` 均回傳 HTTP 200，測試後停止伺服器。
- `npm run type-check`、`npm run build`：通過。
- Composer、npm 安裝時未回報已知安全漏洞。
- `git check-ignore`：已確認敏感與產生檔案排除規則。

### 備註與待處理
- 套件安裝時曾遇 DNS 解析失敗與沙箱下 PHP 快取可寫性誤判；以核准權限執行成功，未變更系統 ACL。
- `backend/.env` 與本機 APP_KEY 已建立，不納入 Git；初始 session/cache 使用 file、queue 使用 sync。
- 初始回報時 Git 位於 `main`，**無 remote、無 commit、無 staged 檔案**；來源檔案為 untracked。此為 2026-11-02 的狀態快照，之後以 `git status` 為準。
- README 使用面向面試官的固定說明，不放上述進度、環境排錯歷史或 Commit 紀錄。

## 下一步

開始階段 1：確認最小化的 `reports` 資料欄位，建立 SQLite、Migration 與 Model；完成後更新本檔並回報測試結果，不自行 Commit／Push。
