# Site Inspection Mini｜開發進度

> 本文件記錄內部開發階段、驗證與問題；`README.md` 僅保留對外專案說明。
> Git Commit 與 Push 一律由使用者操作，Codex 不代為執行。

## 階段總覽

| 階段 | 工作 | 狀態 |
| --- | --- | --- |
| 0 | 環境檢查、前後端初始化 | 已完成（2026-11-02） |
| 1 | SQLite 資料表、Migration、Model | 已完成（2026-11-02） |
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

## 階段 1：SQLite 資料庫與巡檢資料模型

**完成日期：**2026-11-02（Asia/Taipei）

### 已完成
- 確認 `config/database.php` 與本機 `.env` 使用 SQLite；未設定 `DB_DATABASE` 時由 Laravel 解析為 `backend/database/database.sqlite`，不需更動既有連線設定。
- 僅在不存在時建立本機 SQLite 檔案，執行一般 `php artisan migrate --no-interaction`，未刪除 Migration、未使用 fresh/refresh、未移除既有資料。
- 新增 `Report` Model，沿用 Laravel 13 的 `Fillable` attribute，開放五個巡檢輸入欄位的批次寫入；ID 與時間由框架管理。
- 新增 `2026_11_02_000000_create_reports_table.php`；本階段唯一新增的業務資料表為 `reports`。Laravel 原有三份 Migration 保留且隨一般 migrate 執行，並建立其預設資料表與 migrations 紀錄表。
- 新增 `ReportTest`，以 `RefreshDatabase` 和 PHPUnit 既有的 SQLite `:memory:` 設定測試，不寫入本機資料庫。
- 更新 `.env.example` 的 SQLite 預設路徑註解；README 僅補充全新 Clone 後建立 SQLite、執行 Migration 及保留現有資料的安裝說明，未加入開發進度。
- 未新增 API、Controller、Form Request、前端畫面或套件。

### reports 資料表

| 欄位 | SQLite 實際型別與限制 |
| --- | --- |
| id | INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL |
| site_name | VARCHAR NOT NULL |
| issue_type | VARCHAR NOT NULL |
| severity | VARCHAR NOT NULL，CHECK 僅允許 low / medium / high，無預設值 |
| description | TEXT NOT NULL |
| image_path | VARCHAR，可為 NULL，僅存 Storage 相對路徑 |
| created_at / updated_at | DATETIME，沿用 Laravel timestamps（資料庫可為 NULL，Eloquent 建立資料時自動填入） |

### 驗證結果
- `php artisan migrate --no-interaction`：4 份 Migration 全部成功；`migrate:status` 全部顯示 Ran。
- 再次執行 migrate：`Nothing to migrate`，確認可重複執行。
- 唯讀檢查本機 SQLite 的 `sqlite_master`：欄位、主鍵、NOT NULL 與 severity CHECK 限制符合預期；Model 可查詢本機 reports，目前為 0 筆，未留下測試資料。
- `php artisan test`：**13 tests / 52 assertions 通過**（含原有 2 項）。新測試涵蓋實際 SQLite 結構、三種嚴重度的 Model 寫入／讀取、NULL 照片路徑、時間自動產生、必填欄位拒絕 NULL，以及 critical / LOW / 空字串嚴重度被資料庫拒絕。
- Pint：本次新增的三份 PHP 檔案格式檢查通過。
- `npm run type-check`、`npm run build`：通過；未修改前端來源。
- Git 忽略規則已確認本機 database.sqlite 不納入版本控制；未 Commit 或 Push。

### 待處理問題與決策
- 無阻擋問題。沙箱內 PHP 將既有 vendor/autoload.php 判定為不可讀；使用核准的正常權限完成測試及本機 Migration，未變更系統 ACL。
- 照片路徑目前允許 NULL；上傳必填規則、輸入長度、空白字串與問題類型的允許值留待階段 2 驗證設計。SQLite 的 VARCHAR 不強制字串長度，NOT NULL 也不排除空字串；severity 空字串則已由 CHECK 拒絕。
- 本階段沒有實際圖片檔案與上傳流程；資料庫僅保存可選的路徑字串。

## 下一步

待使用者指示開始階段 2：巡檢 API、圖片上傳與後端驗證；不自行 Commit／Push。
