# Site Inspection Mini｜開發進度

> 本文件記錄內部開發階段、驗證與問題；`README.md` 僅保留對外專案說明。
> Git Commit 與 Push 一律由使用者操作，Codex 不代為執行。

## 階段總覽

| 階段 | 工作 | 狀態 |
| --- | --- | --- |
| 0 | 環境檢查、前後端初始化 | 已完成（2026-11-02） |
| 1 | SQLite 資料表、Migration、Model | 已完成（2026-11-02） |
| 2 | 巡檢 API、圖片上傳與後端驗證 | 核心完成；HEIC／HEIF 待整合（2026-11-02） |
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

## 階段 2：Laravel 巡檢 API、輸入驗證與照片上傳

**完成日期：**2026-11-02（Asia/Taipei）；核心 API 完成，HEIC／HEIF 自動轉換尚未提供。

### 已完成
- 新增 `ReportController`、`StoreReportRequest`、`IndexReportRequest`、`ReportResource`，接上三個 API：POST /api/reports、GET /api/reports、GET /api/reports/{id}。
- 列表支援 site_name、issue_type、severity 完全相等篩選，多條件以 AND 組合；依 created_at DESC、id DESC 排列，現階段不分頁。
- 所有 API 成功及錯誤均採 data / message / errors JSON 結構；不存在回傳 404、驗證／上傳超限回傳 422、寫入失敗回傳不含內部細節的 500，不依賴 Accept header 才回 JSON。
- site_name 必填字串且最多 100 字；issue_type 固定為系統櫃安裝／水電／裝修／安全防護／其他；severity 為 low／medium／high；description 必填字串且最多 1,000 字。去除頭尾含全形的空白並拒絕空白輸入。
- photo 必填，最多 20 MiB；以實際 MIME、Laravel image 規則及圖片尺寸驗證 JPEG／PNG／GIF／WebP，拒絕偽裝圖片、SVG 與不支援格式。伺服器用 UUID 與內容判斷的副檔名命名，忽略客戶端傳入的 image_path。
- 照片使用 public disk，資料庫只存相對路徑；Resource 產生 image_url，兼容既有沒有照片的資料（URL 為 null）。已執行 storage:link，Windows 建立 public/storage junction，該連結不納入 Git。
- public disk 啟用寫入例外。檔案寫入與 Report 建立放在資料庫 transaction 中；捕捉寫入／資料庫例外後刪除該唯一照片路徑，包含部分寫入，避免留下不完整紀錄或可清理的孤兒檔案。
- 新增 `serve.ps1`，僅對開發伺服器程序設定 upload_max_filesize=20M、post_max_size=24M、display_errors=0、log_errors=1；保留系統 php.ini，不透過父 artisan 程序的 -d 假設子程序會繼承設定。
- 新增 `ReportApiTest` 與四種實際小型圖片 fixture，不需要 GD 產生測試圖。README 僅補充實際 API、照片相容性、啟動與測試說明。
- 未修改既有 Migration、未新增資料表或套件、未實作登入及前端畫面；未 Commit 或 Push。

### 圖片能力與上傳設定評估
- 系統 PHP 8.4.27 使用 C:/php84/php.ini；GD、Imagick、EXIF 未啟用，fileinfo 可用；upload_max_filesize=2M、post_max_size=8M、memory_limit=128M、max_file_uploads=20。
- 系統原始上傳上限不足 20 MiB，未修改；使用 README 的 serve.ps1 才會對該次開發伺服器套用足夠限制。正式部署仍需依實際 PHP／Web Server 設定。
- Codex 私有工具快取提供 heif-convert / libheif 1.23.2 與內建 libde265 1.1.1 HEVC decoder。實際下載 libheif 官方 examples/example.heic 至系統暫存目錄評估，該真實檔案含兩張影像；工具輸出兩張 JPEG，均檢測為 image/jpeg、1280×854。
- 此工具位於 Codex 快取，並非可由本專案 Clone 重現的部署依賴；多影像輸出也需要主影像選擇及清理設計。因此**沒有將該工具接入 API，也沒有宣稱 HEIC／HEIF 上傳顯示或自動轉換已完成**。官方範例及輸出不納入版本控制。

### 驗證結果
- `php artisan test --compact`：**56 tests / 332 assertions 通過**（含既有 13 項）。
- 涵蓋正常新增與資料庫／Storage 內容保存、四種圖片、三個篩選條件及 AND 組合、最新排序、單筆與 JSON 404、必填／照片缺漏、ASCII／全形空白、中文 100／1,000 字邊界、超長文字、非法選項、非字串、非圖片／偽裝副檔名／SVG／截斷檔、實際 20 MiB 與超過 1 byte、PHP 上傳失敗與 POST 總大小錯誤。
- 一致性測試：實際 INSERT 後模擬資料庫例外，確認 rollback 且照片刪除；模擬部分 Storage 寫入後拋例外或回傳 false，確認資料庫無紀錄、無殘留照片。
- 真實 HTTP smoke test：另建隔離 SQLite，啟動相同 PHP 程序設定；JPEG／PNG／GIF／WebP multipart POST 均回 201，image_url GET 均回 200、Content-Type 為 image/*，下載 bytes 與來源完全一致；建立後單筆 GET 均回 200。
- 真實 HTTP：20,971,520 bytes 照片成功 201；20,971,521 bytes 照片回 JSON 422；26,214,400 bytes 超過 POST 限制仍回 JSON 422；皆有 photo 欄位錯誤。
- 真實 HEIC：上述官方有效範例上傳 API 回 422，未保存未轉換檔案；這是明確拒絕驗證，**不是 HEIC 自動轉換測試**。
- smoke test 的隔離資料庫、上傳照片與伺服器已清理；原本本機 reports 仍為 0 筆、public reports 檔案為 0 個。
- Pint 檢查、serve.ps1 語法、前端 type-check / build、git diff --check 均通過；系統 PHP 上傳設定仍為 2M / 8M。未變更前端來源或 lockfile。

### 待處理問題與下一階段整合
- HEIC／HEIF 自動轉 JPEG 尚未完成。可評估階段 3 在選檔後使用瀏覽器 HEIF/WASM 解碼器自動產出 JPEG，沿用目前 photo 欄位；或另行規劃固定版本 libheif／HEVC decoder 的後端安裝與可設定執行檔路徑，不依賴 Codex 快取。
- 無論選哪個方案，須用真實 iPhone／HEIF 樣本驗證主影像選擇、方向、色彩、多影像、失敗清理及 20 MiB 邊界；不能要求使用者手動轉檔作為產品流程。
- PHP 未提供 GD／Imagick，本階段只驗證檔案 MIME 與尺寸並直接儲存支援格式，沒有重新編碼或宣稱可完整解碼任意損壞圖檔。
- 系統設定及正式部署限制仍需部署時確認；本機應依 README 使用 serve.ps1，避免以原始 2M / 8M 的 artisan serve 測試大照片。
- 程序被強制終止或儲存裝置連刪除都失敗時，跨檔案系統與 SQLite 無法提供同一個原子交易；一般可捕捉的失敗已有 rollback 與清理測試，清理失敗會拋例外並記錄，未靜默吞掉。

## 下一步

待使用者指示開始階段 3：手機巡檢表單與 HEIC／HEIF 自動轉換方案；現有核心 API 可供串接，不自行 Commit／Push。
