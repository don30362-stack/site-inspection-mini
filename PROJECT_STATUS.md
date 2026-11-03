# Site Inspection Mini｜開發進度

> 本文件記錄內部開發階段、驗證與問題；`README.md` 僅保留對外專案說明。
> Git Commit 與 Push 一律由使用者操作，Codex 不代為執行。

## 階段總覽

| 階段 | 工作 | 狀態 |
| --- | --- | --- |
| 0 | 環境檢查、前後端初始化 | 已完成（2026-11-02） |
| 1 | SQLite 資料表、Migration、Model | 已完成（2026-11-02） |
| 2 | 巡檢 API、圖片上傳與後端驗證 | 核心完成；前端轉換已於階段 3 整合（2026-11-02） |
| 3 | 手機版巡檢回報表單 | 已完成多照片調整；手機實機待驗證（2026-11-02） |
| 4 | 桌機列表、篩選、詳細頁 | 已完成；手機實機待驗證（2026-11-02） |
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

以下為階段 2 完成時的紀錄；前端自動轉換的目前結果與限制見階段 3。

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

## 階段 3：Vue 手機版巡檢回報表單

本節為原始單張版本的歷史紀錄；目前資料結構、API 與照片操作以後續「階段 3 調整」為準。

**完成日期：**2026-11-02（Asia/Taipei）。功能已實作並在桌面 Chromium 以 390×844 手機寬度實測；尚未使用真實手機。

### 已完成與變更檔案
- 新增 `frontend/src/views/NewReportView.vue`，提供 `/reports/new`。三個示範工地為信義住宅 A 棟、板橋辦公室 3 樓、新店社區公共區；問題類型與後端一致，嚴重度顯示低／中／高、送出 low／medium／high。
- 使用 Vue 3、TypeScript、Composition API、script setup、Bootstrap 5；欄位有可點擊標籤、至少 48px 的主要輸入控制、全寬送出按鈕、嚴重度選項、描述字數與欄位錯誤，成功後可建立下一筆並保留工地。
- 新增 `frontend/src/lib/photo.ts`：由內容簽章辨識 JPEG／PNG／GIF／WebP 與 HEIF ftyp brands，不依賴檔名、accept 或客戶端 MIME；一般格式保持原始 bytes，正規化 File 的 MIME／名稱，並以瀏覽器實際載入確認可預覽。
- HEIF 內容才動態 import heic-to，使用 `heicTo({ blob, type: 'image/jpeg', quality: 0.85 })`，確認轉換內容為 JPEG 並建立 File 後再預覽與送出。來源與轉換後檔案皆限制 20 MiB；失敗顯示中文錯誤且不送出原始 HEIC。解碼在套件內建 Worker 中處理。
- 選檔／轉換與送出期間鎖定操作，函式入口也阻擋重複提交；新選圖、移除、成功送出與元件卸載時清理 Object URL；非同步選圖用版本標記避免卸載後重建預覽。卸載時中止待處理的 Axios 請求。
- 沿用既有 Axios client，以 FormData POST /reports，不手動設定 multipart boundary。處理前端必填／長度、Laravel 422 各欄位、500 與網路失敗；失敗時保留文字和照片，不自動重送。
- 調整 App.vue、main.ts、router/index.ts：App 作為外框、RouterView 顯示表單，首頁導向 /reports/new。調整 index.html 的繁體中文、標題與 theme-color。
- 調整 vite.config.ts 與 .env.example：以 server-only API_PROXY_TARGET 設定後端目標，代理 /api 與 /storage；不硬編碼區域網路 IP。README 補充實際功能、啟動與手機連線設定，沒有加入開發進度。
- package.json / package-lock.json 唯一新增執行期依賴為鎖定版本 heic-to 1.6.7；安裝前已確認官方 API、內建 TypeScript 宣告與 LGPL-3.0 授權。保留未修改的套件授權、所引用的官方 GPL-3.0 及來源資訊於 frontend/public/licenses/（三個檔案），隨 Build 發佈。
- 新增 frontend/tests/photo.test.mjs，使用 Node 24 內建測試工具；沒有新增測試框架、UI 套件、Pinia、列表、詳細頁或登入。後端來源、資料模型、Migration 與 API 格式均未修改。

### 實際驗證
- `npm test`：9 項通過，涵蓋四種實際 fixture 的內容辨識、偽裝文字／SVG、HEIF compatible brands、AVIF 排除、截斷標頭、空檔案與超過 20 MiB 的來源檔。
- `npm run type-check` 與 `npm run build`：通過。heic-to 拆為獨立動態 chunk，約 3,225.48 kB（gzip 809.25 kB）；Vite 提示超過 500 kB 的 chunk 警告，未隱藏警告，沒有把解碼器放入初始主程式。
- `php artisan test --compact`：既有 56 tests / 332 assertions 全部通過。
- 真實瀏覽器／真實 API：使用隔離的實體 SQLite 與原本 Laravel public disk，表單分別送出 JPEG、PNG、WebP 及自動轉換的真實 HEIC，共成功建立 4 筆。送出時欄位與按鈕 disabled，成功顯示紀錄編號與建立下一筆；下一筆會清除描述、問題類型、嚴重度、照片，保留工地。
- SQLite 與 Storage 檢查：前三種保存照片的 SHA-256 均與原 fixture 相同；HEIC 成功存為 image/jpeg，352,459 bytes、1280×854，非原始 HEIC 或只改副檔名。
- 真實 HEIC 樣本來自 https://raw.githubusercontent.com/strukturag/libheif/master/examples/example.heic（718,114 bytes、多影像 HEIF 容器），套件選取主影像，預覽顯示 1280×854 與「已自動轉為 JPEG」。不是假的 .heic 檔案或 mocked 轉換。
- 另一份獨立真實 HEIF 樣本 https://raw.githubusercontent.com/strukturag/libheif/master/tests/data/uncompressed_comp_RGB.heif：unci 編碼不受解碼器支援。瀏覽器顯示中文轉換失敗，沒有原檔上傳，也沒有新增紀錄；未宣稱該樣本轉換成功。
- 實際錯誤操作：缺少必填、1,001 中文字（顯示 1,001 / 1,000）、全形空白、偽裝 JPEG、超限檔案與損壞 HEIC 都被拒絕。3 MiB 真圖片在測試程序 2M 限制下回傳 Laravel 422，photo 錯誤顯示在照片欄位；測試伺服器指向不存在資料庫時，500 顯示中文提示；停止測試前端伺服器後，實際連線失敗顯示尚未確認是否成功的提示。這些失敗均保留表單及可用照片。
- 測試後停止本次 PHP／Vite 程序並恢復瀏覽器 viewport。僅清理該隔離 SQLite 與四張測試上傳照；原本 database/database.sqlite 的 reports 仍為 0 筆，public reports 檔案為 0 個。測試截圖、紀錄與官方樣本留在 Git 已忽略的 backend/storage/framework/testing/stage3/，不納入提交。
- `git diff --check` 通過，未 Commit 或 Push。

### 相容性限制與待驗證
- **尚未在真實 iPhone Safari、Android Chrome 或手機相簿／相機選檔測試**。390×844 桌面 viewport 並非真實手機驗證，不宣稱完全相容。
- 常見 HEVC HEIC 真實樣本成功；HEIF 是容器，未壓縮 unci 範例已確認不支援，不保證所有 HEIF 編碼。未知格式／轉換失敗會阻止原檔上傳，使用者不需手動操作轉檔。
- 手機照片方向、HDR／色彩、超大解析度的記憶體占用、原始大型照片轉換後超過 20 MiB，以及真實手機長時間反覆選圖仍待實機驗證；輸出超限檢查已實作，但沒有用實際超過 20 MiB 的轉換輸出宣稱通過。
- 第一次遇 HEIC 的解碼器下載較大，弱網載入體驗待手機測試。未為通過 Build 而隱藏 chunk 警告。
- 手機連線所需的 Vite host、API_PROXY_TARGET、APP_URL 與 /storage proxy 已在 README 說明；未修改系統防火牆、區網設定或寫入本機 IP。
- 未加入冪等鍵／自動重送；網路中斷可能已在伺服器建立資料，提示使用者先確認紀錄再重試。列表與詳細頁留待階段 4。

## 階段 3 調整：多照片上傳與 UI／UX

**完成日期：**2026-11-02（Asia/Taipei）。單張版已改為每筆 1～5 張照片；未開發階段 4。

### 本機資料與 Migration
- 修改前唯讀確認本機 reports 為 0 筆、既有照片路徑 0 筆，系統 upload_max_filesize=2M、post_max_size=8M、max_file_uploads=20、memory_limit=128M。
- 新增 `backend/database/migrations/2026_11_02_000001_create_report_images_and_remove_report_image_path.php`，原有 Migration 未改動。先建 report_images、搬移可能存在的舊照片路徑，再移除 reports.image_path；沒有執行 migrate:fresh／refresh 或重建整個資料庫。
- 先完成 Migration 與記憶體隔離測試，再向使用者確認；使用者明確回覆「允許套用本機 Migration」後，先備份 database.sqlite 至已忽略的 storage/framework/testing/before-multi-photos-*.sqlite，再執行本機一般 migrate。新 Migration 已在 batch 2 成功套用，既有四份 Migration 保留。
- 最終 reports：id、site_name、issue_type、severity、description、created_at、updated_at；不保留 image_path。
- 最終 report_images：id（整數自增主鍵）、report_id（NOT NULL，外鍵 reports.id，ON DELETE CASCADE）、image_path（VARCHAR NOT NULL）、sort_order（INTEGER NOT NULL）。report_id + sort_order 有 UNIQUE 索引；API 產生順序為 0～4。
- down() 若發現一筆回報有多張照片，會拒絕無法保留資料的單張回滾；只有能安全表示為單張時才搬回路徑並還原結構。此保護已在記憶體測試驗證，未對本機資料執行 rollback。

### 修改與新增檔案
- 新增 ReportImage Model 與 ReportImageResource；Report 新增依 sort_order 排列的 images()，ReportImage 有 report()，圖片表不加未要求的 timestamps。
- 更新 ReportController、StoreReportRequest、ReportResource、bootstrap/app.php：新增改用 photos[]，驗證 1～5 張與每張 20 MiB；拒絕舊 photo 欄位。每張沿用內容 MIME／尺寸驗證及 UUID 安全檔名。
- API 的 images 陣列每張只回 id、image_url、sort_order；移除頂層單張 image_path／image_url。列表 with('images')、單筆與新增 load('images')，避免 N+1。
- Report 與全部 ReportImage 在同一 transaction 建立。寫入前記錄每個唯一路徑，任一檔案或資料庫操作失敗時 rollback，逐一清理已寫入及部分寫入照片；即使某次刪除拋例外仍繼續嘗試其他路徑。
- 更新 serve.ps1：程序限定 upload_max_filesize=20M、post_max_size=120M、max_file_uploads=20；保留 display_errors=0／log_errors=1。未修改系統 php.ini。
- 更新原有 ReportApiTest、ReportTest，取代失效的單張欄位與 schema 斷言；新增多照片邊界、排序、外鍵、唯一限制、N+1、升級搬移與拒絕有損回滾的驗證。
- 更新 NewReportView.vue：兩欄正方形動態網格、張數、一個起始新增格、滿五張隱藏新增格、縮圖更換、獨立 44px 垃圾桶、刪除遞補、個別照片處理狀態與 photos.N 錯誤。取消或失敗保留原圖，送出失敗保留全部照片及文字。
- 新增 frontend/src/lib/photoCollection.ts 與 frontend/tests/photoCollection.test.mjs，重用既有 preparePhoto()；所有選圖逐張處理，待處理照片顯示排隊狀態。增刪、更換、卸載清理 Object URL，卸載後的轉換完成不會建立遲到預覽。
- 更新 frontend/package.json 的 test 指令以執行全部 .test.mjs；未安裝任何新套件。README 僅更新多照片功能、API、資料庫升級與程序大小設定。

### 測試結果
- Laravel：**72 tests / 473 assertions 通過**。涵蓋 1／5 張成功、0／6 張拒絕、單張實際超過 20 MiB 拒絕、JPEG／PNG／WebP／GIF 混合、images ID／URL／sort_order、重讀持續保存、篩選、JSON 404／422、欄位限制與外鍵／唯一限制。
- 第 2、3、4、5 張各模擬部分寫入後拋例外或回傳 false，共八種情境，確認 Report、ReportImage、Storage 全部清空；第 3 張圖片 INSERT 後資料庫例外同樣完整 rollback／清理。
- 升級測試以舊結構及真實舊路徑建立資料，再跑新增 Migration，確認舊回報／路徑保留、新欄位正確且舊欄位消失。全新記憶體與全新實體隔離 SQLite 執行全部 Migration 均得到最終結構。
- 前端：**16 項測試通過**，包含原有內容辨識、五張限制、超量整批拒絕、刪除遞補、新增、逐張處理、取消／失敗保留原圖、更換成功才回收舊 URL、卸載時阻止遲到預覽。
- Type Check、Build、Pint 與 git diff --check 通過。HEIC 動態 chunk 仍約 3.23 MB，既有大小警告保留，未為通過檢查而隱藏。
- 真實桌面 Chromium（390×844 viewport）操作：起始僅一個新增格；六張明確整批拒絕；三張混合照片後再加入兩張真實 HEIC。觀察到一張處理中、下一張等待，不同時大量解碼。五張時新增按鈕數為 0，照片格實測為 153.5×153.5px，兩欄等比例裁切。
- 更換損壞 HEIC 後，原照片的 blob URL 與張數均不變；成功換 GIF、垃圾桶刪除、剩餘照片遞補及再新增均實測通過，垃圾桶沒有觸發選檔更換。
- 表單實際送出五張（含兩張同一官方有效 HEIC 範例的副本，自動產生 JPEG）成功；重新整理後由 API 讀取，五張、0～4 順序與 URL 仍正確，五個公開圖片 URL 皆 HTTP 200。未將假 HEIC 或改副檔名當成轉換成功。
- 真實 HTTP **五張各 20 MiB、總照片內容 100 MiB** 上傳成功，每個 Storage 檔案均為 20,971,520 bytes；系統 PHP memory_limit=128M 未另調高。0／6 張及單張超過 1 byte 的實際 HTTP 上傳均回 JSON 422，索引錯誤為 photos.0。
- 前端第二張實際 3 MiB 圖片在測試伺服器 2M 限制下得到 photos.1 的 422：錯誤出現在第二格，三張照片與文字完整保留。恢復 20M 限制後直接重送成功，另以單張 PNG 實際送出成功。
- 測試共建立 4 筆回報／14 張照片（含 100 MiB 邊界組），已先核對僅屬本次隔離資料，再清理測試 SQLite、上傳照片及大型 fixture，停止本次 PHP／Vite 程序並恢復 viewport。原本本機資料庫仍為 reports 0、report_images 0；既有資料沒有被刪除。
- 截圖、測試紀錄與備份保留於 Git 忽略的 backend/storage/framework/testing/，不納入提交。本次未 Commit／Push，原有尚未提交的階段 3 變更亦保留。

### 尚未驗證與限制
- 尚未在真實 iPhone Safari／Android Chrome 驗證相機、相簿多選、原生選檔取消、方向／HDR／色彩、大尺寸 HEIC、低記憶體與弱網。桌面 viewport 不代表手機實機驗證。
- 取消更換的無檔案分支已有單元測試；瀏覽器工具不支援以空 FileChooser 直接取消，因此未把該失敗的自動化嘗試宣稱為原生視窗取消實測。
- HEIC 常見範例可轉換；先前確認不支援的 unci HEIF 編碼限制仍存在，轉換失敗會阻止原檔上傳並保留更換前的照片。
- 檔案系統與 SQLite 不共用原子交易；可捕捉的寫入／資料庫失敗已完整測試。程序強制終止或儲存裝置連刪除都失敗仍須由 log 排查，未新增無關維運系統。

## 階段 4：巡檢列表、篩選與詳細頁

**完成日期：**2026-11-02（Asia/Taipei）。沿用多照片 API，沒有修改後端 Endpoint、Model、Migration 或新增套件。

### 已完成與檔案
- 新增 `frontend/src/views/ReportListView.vue`：/reports 桌機表格、手機卡片，顯示建立時間、工地、類型、中文嚴重度、照片數及詳細入口。直接使用 API 的最新排序，沒有新增分頁或另一套前端篩選。
- 工地、類型、嚴重度可單獨或 AND 組合篩選，皆有全部選項與清除按鈕；以網址 query 保存條件，詳細頁連結與返回列表都保留條件。空資料、無符合結果、Loading、API／網路錯誤與重新載入皆有介面。
- 新增 `frontend/src/views/ReportDetailView.vue`：/reports/:id 顯示編號、工地、類型、嚴重度、完整描述（保留換行）、建立時間與 images 陣列；404 顯示「找不到巡檢紀錄」，其他失敗可重試。
- 新增 `frontend/src/components/ReportGallery.vue`：預設第一張，大圖 object-fit: contain，不裁切；多張顯示可切換縮圖與明顯選取框，單張沒有切換控制。圖片載入／失敗有提示，縮圖為可鍵盤操作的按鈕並有 aria-pressed。
- 新增 `frontend/src/components/SeverityBadge.vue`：低／中／高文字配合顏色，不只依靠顏色辨識。
- 更新 App.vue 與 router/index.ts：共用導覽「新增回報／巡檢紀錄」、目前頁面狀態、44px 觸控目標與較寬的桌機外框；所有裝置可進入所有功能。首頁仍導向 /reports/new，新增表單自身 680px 寬度未改。頁面標題隨路由更新。
- 更新 NewReportView.vue：抽出共用選項與 API 型別，成功畫面新增「查看這筆紀錄」並保留下一筆；照片處理、驗證及 FormData 提交流程未修改。
- 新增 `frontend/src/lib/reportOptions.ts`：保留任務開始時工作區已修改的七期住宅 A 棟／南屯辦公室 3 樓／逢甲社區公共區，供新增與列表共用；沒有改回 HEAD 的舊工地名稱。
- 新增 `frontend/src/lib/reports.ts`：Report／ReportImage／ApiResponse／Severity／ReportFilters 型別、篩選網址及日期工具；時間明確使用 zh-TW、Asia/Taipei、24 小時制，缺失／錯誤日期有替代文字。
- 新增 `frontend/src/lib/useLatestRequest.ts`：AbortController 加版本檢查，即使舊 transport 忽略取消，其成功／失敗也不能覆蓋最新資料、錯誤或 Loading；元件卸載中止請求。
- 新增 `frontend/tests/reports.test.mjs`、`frontend/tests/latestRequest.test.mjs`；README 僅補充實際列表、篩選、詳細頁與使用說明。

### 自動檢查
- 前端 **25 項測試全部通過**（既有照片 16 項 + 日期／選項／篩選 4 項 + 請求競態／錯誤／取消 5 項）。
- 測試包含舊成功回應與舊錯誤晚到但不覆蓋新資料、忽略 Abort 的 transport、重試清除錯誤、404 狀態、卸載後不更新資料、URL 往返、UTC 跨午夜轉為台灣日期。
- `npm run type-check`、`npm run build` 通過。既有 heic-to 3.23 MB 動態 chunk 大小警告保留，沒有隱藏警告或改動 HEIC 流程。
- Laravel 既有 **72 tests / 473 assertions 通過**；`git diff --check` 通過。後端來源、Migration、照片工具與 package／lockfile 均無本次變更。

### 真實瀏覽器與 API 整合結果
- 使用全新隔離 SQLite，先驗證空資料顯示「還沒有巡檢紀錄」，再透過既有 POST API 建立三筆測試回報（1／5／1 張），不是寫死在頁面中的紀錄。只調整隔離測試資料的建立時間以核對排序與時區。
- 桌面 Chromium 1280×900：列表依 #3、#2、#1 的建立時間排序，時間顯示 2026/11/02 10:00、02:30、2026/11/01 08:00，中文嚴重度及 1／5／1 張照片數正確，導覽 aria-current 與視覺狀態正確。
- 真實 API 篩選：七期工地回 #3/#1、水電回 #3/#2、中回 #2；三條件組合可得到 #2，改為互斥條件顯示無符合結果；清除後恢復三筆。從三條件列表進入 #2 詳細頁，返回後三個選項與唯一結果完整保留。
- 測試路由曾對實際 API 加入短延遲（只在 Git 忽略的測試 wrapper），確認 Loading 並可快速選高／低／中；最後 URL 與結果為 medium/#2。延遲未加入正式 API；單元測試另外確定舊回應即使忽略 Abort 仍無法覆蓋最新結果。
- 五張詳細頁：預設第一張，切換第五張後 main img 真實載入 480×720 直式照片，object-fit 為 contain、選取框與 5/5 正確；五張縮圖均實際載入。一張紀錄的切換群組數為 0。
- 手機寬度 390×844：表格在可及性樹中不存在，改為三張可點擊卡片；document clientWidth 與 scrollWidth 同為 375px，沒有水平捲動。可進入五張詳細頁並切第三張，縮圖觸控區約 57×57px。
- 真實 GET /reports/999999 回 API 404，介面顯示「找不到巡檢紀錄」及返回入口。暫時改名本次自建的 #3 測試照片，圖片失敗顯示中文提示；之後已還原測試照片。
- 僅將測試伺服器指向不存在的隔離 DB，驗證列表與詳細頁真實 500；還原連線後點重新載入可恢復。最後停止本次 Vite 程序，已載入的列表遇到真實連線失敗，正確顯示中文提示與重試按鈕。
- 新增回歸：首頁 / 仍導向 /reports/new；透過共用導覽進入新增，實際送出五張（四張 JPEG + 官方真實 HEIC 自動轉 JPEG）成功建立 #4，下一筆按鈕仍存在。「查看這筆紀錄」連到 /reports/4，五張完整顯示，第五張 JPEG 真實尺寸 1280×854；返回列表時 #4 位於最新一筆。
- 整合測試共建立 **4 筆／12 張**。已核對 HEIC 產物並僅清理本次隔離 DB 與照片，測試 PHP／Vite 程序均已停止、viewport 已恢復。原本本機 reports 0、report_images 0，未寫入或刪除本機使用者資料。
- 截圖與測試紀錄保留在 Git 已忽略的 backend/storage/framework/testing/stage4/（reports-desktop.png、reports-mobile.png、detail-desktop.png），不納入提交。

### 尚未驗證
- 尚未在真實 iPhone Safari／Android Chrome 實機操作；桌面手機寬度測試不代表實機驗證。實機相機／相簿、原生取消、HDR／方向、低記憶體與弱網的前階段待辦仍保留。
- 舊版未壓縮 unci HEIF 不支援與大型 HEIC 動態檔的限制未改動。此次未加入不必要的替代轉檔流程或新套件。
- 列表依既有 API 不分頁；未加入登入、角色、匯出、圖片編輯或其他階段外功能。未 Commit／Push。

## 階段 4 補強：全站前端 404

**完成日期：**2026-11-02（Asia/Taipei）。

- 新增 frontend/src/views/NotFoundView.vue：置中的簡潔卡片、404 數字、裝飾性頁面 SVG、指定標題與說明，以及 RouterLink「查看巡檢紀錄／新增巡檢回報」。沿用共用 Header、Bootstrap 5、深藍／青綠，不新增套件、插畫或動畫。
- router/index.ts 最後新增 /:pathMatch(.*)* catch-all，meta.title 為「找不到頁面」，原有路由與 / 導向 /reports/new 保留，沿用 afterEach 的 document.title 機制。
- App.vue 的紀錄導覽改以實際 matched route 判斷，避免 /reports/123/extra 被誤標為目前巡檢紀錄頁；未重新建立 Header。
- 前端未知網址與 API 紀錄 404 分開處理；ReportDetailView、Laravel API 404 格式、資料庫／Migration、照片與其他業務功能未修改。README 不另加冗長說明。
- `npm test` 25 項、`npm run type-check`、`npm run build`、`git diff --check` 全部通過。既有 HEIC 動態 chunk 大小警告保留；此次小型路由補強以實際瀏覽器驗證，沒有新增重複實作的字串比對測試或測試框架。
- 桌面 Chromium 實測 /unknown、/reports/123/extra 顯示「找不到這個頁面」，標題為「找不到頁面 · Site Inspection Mini」；未知網址重新整理仍正常。
- 兩個 RouterLink 按鈕分別到 /reports、/reports/new；/ 仍導向 /reports/new。真實 API 的正常 /reports/1 詳細頁可讀取，/reports/999999 仍顯示「找不到巡檢紀錄」，不被 catch-all 接管。
- 1280×900 桌機 clientWidth/scrollWidth 均為 1280；390×844 手機寬度下均為 375，無水平捲動，按鈕高度約 48px。截圖位於 Git 忽略的 backend/storage/framework/testing/global404/not-found-mobile.png。
- 本機有既有資料，測試沒有複製使用者紀錄；僅唯讀取得 reports/report_images 結構，新建隔離 SQLite 並插入一筆測試紀錄，未執行 Migration、未使用使用者照片。驗證後已停止本次程序、清理該隔離 DB 並恢復 viewport，本機資料未改動。
- 真實手機 iPhone Safari／Android Chrome 仍未實機驗證；此次是桌面瀏覽器不同 viewport。未 Commit／Push；先前階段 4 尚未提交的檔案均保留。

## 階段 5：完整整合測試與品質驗收

**狀態：已完成。** 自動化、隔離瀏覽器整合測試與使用者回報的真實 iPhone 核心流程驗收均通過；未測試的極端情境仍列為限制。

**驗證日期：**2026-11-02（Asia/Taipei）。本次未發現需修改業務程式的已確認缺陷，未更動前後端來源、Migration、套件或系統 php.ini；只補充 README 必要手機啟動與檢查指令，以及本驗收紀錄。未 Commit／Push。

### 資料保護與全新安裝
- 開始時 Git 工作區乾淨。以 `git ls-files --cached --others --exclude-standard` 收集目前工作區的 116 個來源檔，複製至本次專用 `backend/storage/qa5/source`，逐檔 SHA-256 核對；使用當時實際來源，不以舊 HEAD 代替。未複製原本 .env、資料庫、照片或相依套件。
- 在該全新副本執行 Composer install（108 個鎖定套件）、npm ci（85 個套件、audit 無弱點）、複製 .env.example、產生 APP_KEY、建立獨立 SQLite、執行全部五個 Migration、storage:link，均成功。public/storage 實際指向副本自己的 Storage。
- 使用副本的 `serve.ps1 -Port 18080` 與 Vite 15173，另以僅監聽 loopback 的測試代理 18081 記錄 HTTP／注入傳輸故障。APP_URL 指向該 Vite；/api 及 /storage 代理與照片網址皆實際可存取。原服務與資料完全分離。
- 原本 Storage／備份等 92 個檔案 SHA-256 全部不變，原本 .env 雜湊不變。原 database.sqlite 被既有程序鎖定，未終止該程序、未操作原 DB；檔案大小 110592 bytes 與 LastWriteTimeUtc 均不變，但無法取得原 DB 完整雜湊，不宣稱已完成逐位元比對。

### 自動化檢查
- `php artisan test`：**72 tests / 473 assertions 全數通過**，SQLite :memory: 與隔離 Storage；包括 1／5 張、0／6 張、20 MiB 邊界、四種真實圖片內容、輸入／篩選、照片排序、N+1，以及第 2～5 張寫入失敗、部分寫入與 DB 失敗回滾／清理。
- `composer check-platform-reqs` 與 `php vendor/laravel/pint/builds/pint --test`：通過。
- `npm test`：**25 項通過**；照片序列處理、更換失敗／無檔案取消分支、URL revoke／卸載途中完成、篩選網址、台灣時區、請求競態（含忽略 Abort 的舊成功與舊失敗）、404／重試皆通過。
- `npm run type-check`、`npm run build`：通過。既有 heic-to 約 3.23 MB 動態 chunk 警告保留，沒有加套件或為消除警告重構。
- `git diff --check`：通過。測試執行於上述全新副本；驗證期間交付程式碼沒有變更，最後僅文件有修改。

### 真實瀏覽器、HTTP 與資料一致性
- 桌面 Chromium 開啟首頁，確認 / 導向 /reports/new；實際選工地／類型／嚴重度、輸入描述、選檔、送出，從成功畫面進入詳細頁，再返回列表。建立下一筆會清空照片、類型、嚴重度與描述並保留工地。
- 表單分別成功送出 1 張 JPEG、5 張混合照片、1 張 GIF。五張操作包含 PNG／GIF／WebP／兩張真實 HEIC 的初始選取；HEIC 明顯逐張 processing／queued，轉為 JPEG；更換第 2 張為損壞 HEIC 失敗時，原 Blob URL 與預覽保留；之後成功更換、刪除第 1 張及補入 PNG，最終依 JPEG／WebP／JPEG（HEIC）／JPEG（HEIC）／PNG 順序保存。
- 真實 HEIC 解碼產物在詳細頁實際載入為 **1280×854 JPEG**。不是改副檔名；兩張均有轉換標示與實際 JPEG 檔。已知不支援的真實 unci HEIF 顯示中文錯誤、不加入、不上傳。
- 表單六張同選明確拒絕整批，沒有靜默截斷；偽裝 JPEG、超過 20 MiB 與不支援 HEIF 皆顯示中文錯誤且不留下照片。必填、全形空白、1001 字描述皆有前端錯誤。
- 獨立真實 multipart HTTP：0 張、6 張、超大檔、偽裝圖片、原始 HEIC 均為 422；JPEG／PNG／GIF／WebP 四張混合為 201。照片 URL 實際回 200 與正確 image MIME。
- 以原版 serve.ps1 實際送出 **5 張各 20 MiB** 的有效 PNG（小圖加尾端 padding），總計 100 MiB，回 201 並保存五張；耗時約 **72.9 秒**。確認 upload_max_filesize=20M、post_max_size=120M、max_file_uploads=20 足夠；此合成極限樣本不代表手機照片速度或記憶體表現。
- 所有本次 HTTP／UI 測試合計 **7 筆 Report、18 筆 ReportImage、18 個實體照片檔**。核對 SQLite、Storage、API 的 ID／相對路徑／照片 URL／sort_order 完全一致；排序最新優先。刷新詳細頁後資料與照片仍存在；單張無多餘切換，五張可點第 3／5 張且 aria-pressed 正確，大圖 contain 不裁切。
- 三個條件分別取得正確資料，三條件 AND 可得唯一紀錄，互斥條件顯示空結果；清除恢復列表。從篩選列表進詳細再返回、或重新整理，query 與選項皆保留。初始空資料顯示「還沒有巡檢紀錄」。
- 測試代理延遲實際 GET，確認 Loading、快速高／低／中後顯示 medium 的最新結果；取消的舊代理請求在停止服務時產生預期的 ECONNRESET。舊回應即使不理 Abort 而晚到的完整防護由上述競態單元測試另外驗證，未將測試代理的斷線當成產品缺陷。
- /reports/999999 顯示「找不到巡檢紀錄」；/unknown 與 /reports/123/extra 顯示全站「找不到這個頁面」，兩個入口可導航；未知網址重新整理正常，document.title 正確。

### 故障、操作鎖定與介面
- 僅讓副本暫時指向不存在的測試 DB，真實 POST／列表／詳細頁 500 皆有中文提示；POST 保留文字、選項及照片，還原連線後詳細頁重試與列表重載成功。Laravel log 只有這三次故意注入的不存在 DB 例外，正常流程沒有新增未解釋的例外。
- 僅在臨時測試 PHP 程序將單檔限制降至 2M，以已選的合法 3 MiB PNG 觸發真實 Laravel 422；整體訊息與 photos.0 欄位錯誤都顯示，內容／照片保留。隨即恢復副本原版 serve.ps1；系統 php.ini 完全未變。
- 測試代理在上傳送達 API 前中斷，顯示「網路連線失敗，尚未確認是否送出成功」，DB／照片數不變。另在 API 201 成功後丟棄回應，Vite 回傳代理錯誤，表單顯示伺服器失敗並保留內容；核對僅增加一筆、沒有自動重送。網路結果不明時仍需使用者先查紀錄再手動重試，未新增超出範圍的冪等機制。
- 暫時對本次 SQLite 持有交易鎖，觀察送出中的按鈕及表單全部 disabled；釋放後正常成功且只建立一筆。照片解碼期間操作也 disabled。
- 暫時改名本次自建照片，詳細頁顯示中文讀取失敗提示，隨即還原；沒有動到原使用者照片。瀏覽器所收集的 Console error／warn 為空；HTTP 與 Vite log 中的 404／422／500／中斷皆能對應本次刻意情境。
- 表單（含錯誤提示）、列表、五張詳細頁與全站 404 在 **390×844、768×1024、1280×900、844×390、320×568** 檢查，scrollWidth 與 clientWidth 一致。手機使用卡片、桌機表格；照片格保持正方形、大圖 contain；導覽／垃圾桶至少 44px，最窄尺寸縮圖 44px，404 操作約 48px。
- 截圖與 JSON 核對紀錄保留於 Git 忽略的 `backend/storage/qa5/`（reports-mobile.png、reports-desktop.png、detail-desktop.png 等）。已停止本次 PHP／Vite／代理程序，恢復 viewport，清理自行建立的全新副本（含其 DB、18 張照片、相依套件及金鑰）；只保留測試證據與工具，不保留使用者資料副本。

### 真實 iPhone 驗收（使用者回報）
- 使用者已在真實 iPhone 完成相機拍照、相簿多選、照片更換／取消／刪除、照片方向、送出、詳細頁、列表篩選與重新整理測試，結果均通過。此為使用者實機操作結果，不以桌面 viewport 測試代替。
- 從照片圖庫選取同一張 HEIC，未顯示轉換提示但上傳成功；**推測 iOS 選取器已先轉換格式**，尚未直接確認傳入網頁的 MIME，因此不將此案例單獨當成網頁 HEIC 轉換的證據。
- 從「檔案」App 選取原始 HEIC，明確出現「已自動轉為 JPEG」提示且上傳成功，**確認本次 iPhone 實機 HEIC 自動轉換功能正常**；不擴大宣稱所有 HEIF 編碼皆相容。

### 問題分類與尚未驗證
- **阻擋交件：**本次已測範圍未發現，真實 iPhone 核心流程驗收已通過。
- **一般問題：**未發現需修正的業務缺陷。README 原缺少 Windows 查 IPv4、固定連接埠、手機照片 URL 檢查及可直接操作的實機清單，已補齊。
- **可延後改善／限制：**已知 unci HEIF 不支援；首次 HEIC 解碼器下載較大；100 MiB 極限上傳耗時需在實機評估。未安裝解碼器、加入燈箱、冪等、分頁或其他新功能。
- **仍未驗證的限制：**真實手機的大容量照片、低記憶體、弱網／斷線等極端情境；Android 實機、HDR／色彩的專項檢查亦未有驗收結果。不將一般 iPhone 操作通過或桌面合成 100 MiB 上傳，擴大為上述情境皆已驗證。
- 原生選檔取消已由使用者在 iPhone 實際驗收通過；既有 FileChooser 工具本身仍無法自動關閉原生選檔視窗。無檔案取消與 URL 清理有單元測試，但沒有以記憶體分析器宣稱已完成手機瀏覽器的實際 URL／記憶體驗收。

## 功能補強：照片放大與拖曳排序

**完成日期：**2026-11-02（Asia/Taipei）。實作與桌面瀏覽器／真實 API 驗證完成；本次新操作的 iPhone／Android 實機驗收待使用者進行。未修改 README、後端 API、Resource、資料庫結構或 Migration；未 Commit／Push。

### 實作與檔案
- `frontend/src/components/ReportGallery.vue`：沿用既有大圖、縮圖、順序及錯誤狀態，新增原生 dialog 燈箱。大圖載入成功後才可點擊開啟，深色全畫面、完整 contain 顯示、張數、關閉、多張上一張／下一張；單張隱藏切換控制。提供適合畫面／2 倍切換、滑鼠拖曳與原生捲動平移，瀏覽器限制捲動範圍，工具列不隨圖片移動。
- 燈箱支援 Escape、左右方向鍵、正反向 Tab 焦點循環；開啟聚焦關閉按鈕，背景捲動鎖定，關閉恢復原 overflow 與焦點，元件卸載清理 dialog。實測發現原生 dialog 的 Tab 可離開燈箱，已補明確循環並重測通過。失敗圖片無放大入口；燈箱切換遇到失敗仍保留錯誤、切換及關閉控制。
- `frontend/src/views/NewReportView.vue`：維持兩欄正方形網格，增加獨立 44px Grip 手柄、位置編號、拖曳目標外框及位置提示；新增格永遠在最後且不參與排序。手柄使用 Pointer Events 與 pointer capture，只有手柄 touch-action:none，靠近視窗上下邊緣時自動捲動，放開至有效照片格才重排；拖出照片區、pointercancel／失去 capture／Escape 不提交重排。照片點擊更換、右上角刪除保持獨立。
- 手柄支援左右鍵一格、上下鍵一列的鍵盤替代，移動後保留焦點並以 live status 告知位置。照片處理／上傳時停用排序，拖曳中亦阻止表單送出；離開頁面停止拖曳的 animation frame。
- `frontend/src/lib/photoCollection.ts`：新增 `movePhoto(key, to)`，直接移動既有 reactive item；處理中、非法位置與未改變位置時不操作。保留穩定 key、File、Blob URL、converted、error，不呼叫 preparePhoto、不重新轉 HEIC、不建立或 revoke URL。既有 FormData 仍依 items 陣列順序加入 photos[]。
- `frontend/tests/photoCollection.test.mjs`：新增跨列重排、物件／File／URL／轉換／錯誤狀態不變、解碼呼叫次數不增加、FormData 順序、排序後更換／刪除／新增及 URL 最終清理、非法／原位／處理中拒絕重排測試。
- **沒有新增依賴。** 照片最多五張，使用小範圍 Pointer Events 手柄及原生 dialog 即可完成，沒有引入 HTML5 draggable 或大型拖曳／UI 框架；package.json 與 lockfile 不變。手機觸控設計不等同實機驗收已通過。

### 自動化與實際瀏覽器結果
- 前端 `npm test`：**27 項全數通過**（原 25 項 + 2 項照片重排測試）；`npm run type-check`、`npm run build`、`git diff --check` 通過。既有 HEIC 約 3.23 MB 動態 chunk 警告保留。Laravel `php artisan test`：**72 tests / 473 assertions 通過**，SQLite :memory: 與測試 Storage；包含既有驗證、篩選、交易失敗清理回歸。
- 實際瀏覽器使用本次獨立 SQLite／Storage 與原本 Laravel 程式，loopback API 18090、Vite 15183；測試 wrapper 僅覆寫該程序的 DB／Storage／APP_URL，未操作原 database.sqlite 或 public Storage。
- 真實選取 JPEG／PNG／GIF／WebP／官方 HEIC 五張；HEIC 自動轉 JPEG 成功。桌機滑鼠把 [1,2,3,4,5] 跨列拖成 **[5,1,2,3,4]**，再把第二張拖到最後成 **[5,2,3,4,1]**；重排前後原 Blob URL 相同，沒有誤開更換選檔或刪除照片。
- 390×844 手機寬度下以桌面滑鼠跨列拖曳成功；鍵盤 ArrowDown 移動一列，焦點跟隨原照片。排序後指定更換、刪除及新增正確；拖出照片格或原位置放開皆保持原順序。此為桌面瀏覽器操作，不宣稱是 iPhone 觸控測試。
- 從實際表單送出五張，最終為 **HEIC 轉 JPEG／GIF／JPEG／WebP／PNG**。核對 SQLite／實體檔／API，images[].sort_order 為 **0、1、2、3、4**，ID、相對路徑及 image_url 一致；未轉檔的四張與對應測試圖 SHA-256 相同，HEIC 產物為真實 **1280×854 JPEG**。五個照片 URL 皆 200，詳細頁縮圖與大圖順序一致。
- 另以獨立 DB 短暫交易鎖延後實際 POST，確認送出中五個 Grip 與送出按鈕全部 disabled，完成後只建立一筆。處理中不重排另由照片集合單元測試及共用 busy 控制驗證。
- 單張燈箱開啟、2 倍／還原、關閉成功且無上一張／下一張。五張的按鈕、方向鍵與張數正確；放大後實際拖曳使 scrollLeft／scrollTop 改變；切換重設倍率。正向 Tab 由最後按鈕回第一個控制，Shift+Tab 反向循環；Escape 關閉後焦點回原放大按鈕，overflow 復原。
- 測試 wrapper 將照片回應延遲 1.2 秒，確認 Loading 時放大按鈕 disabled、無開啟 dialog。僅改名本次測試照片，驗證失敗圖片沒有放大入口；燈箱內遇到照片失敗仍可切下一張或關閉，隨即還原檔案。燈箱開啟時用瀏覽器返回離開頁面，dialog 與 body 捲動鎖定均清除。
- 燈箱於 **390×844、1280×900、320×568、768×1024** 無水平溢出，照片 contain，關閉按鈕始終在畫面內、至少 44px。回歸列表三條件 AND 篩選、清除、API 紀錄 404、全站 404 均正常。瀏覽器收集的 Console error／warn 為空，隔離 Laravel log 無未解釋例外。
- 合計建立 **3 筆／11 張**隔離資料，結束後已停止本次 API／Vite 程序、清理其 DB 與 Storage，恢復 viewport。原本 **13 個照片檔案雜湊均未變**，原 SQLite 大小及修改時間未變。截圖及核對 JSON 保留於 Git 忽略的 `backend/storage/framework/testing/photo-tools-20261102/`，包含 sort-mobile.png、lightbox-mobile.png、lightbox-desktop.png、order-db.json、order-http.json。

### 待實機驗證與限制
- 等待使用者以 **iPhone Safari／Android** 驗證新手柄的觸控跨列拖曳、靠邊自動捲動、取消手勢、拖曳與更換／刪除的區隔，以及燈箱 2 倍放大後的觸控滑動、關閉與橫向畫面。
- 本次提供按鈕 2 倍切換與平移，**未實作或宣稱雙指縮放已通過**。大容量、低記憶體與弱網極端情境仍是原有限制。
- 前次使用者回報的 iPhone 相機／相簿／HEIC 與核心流程驗收結果保留，但不擴大為本次新增拖曳與燈箱操作已實機驗證。

## 功能補強：管理密碼刪除與簡單限流

**完成日期：**2026-11-03（Asia/Taipei）。新增紀錄刪除與兩項原生限流；未修改既有 Migration、照片規格、排序／HEIC／燈箱、Model／Resource、README 或套件。未開始 Docker／CI/CD、未連線 Lightsail、未操作其他專案。未 Commit／Push。

### 後端與設定
- `DELETE /api/reports/{report}` 接收 JSON body 的 `admin_password`，使用 `DeleteReportRequest` 驗證，不接受 URL query 的密碼。Controller 以 `Hash::check` 比對伺服器 `ADMIN_REPORT_PASSWORD_HASH`，空值／非法雜湊回 503，錯誤密碼 403、缺少／非法輸入 422、缺失／重複刪除 404、同筆正在處理 409。
- 新增 `config/inspection.php`、`.env.example` 空白設定與 `reports:admin-password-hash` 指令。指令隱藏輸入並要求兩次一致，至少 12 字元、最多 72 個 UTF-8 位元組，只輸出雜湊、不寫 .env。實際確認 bcrypt 會忽略第 72 byte 後的內容，因此產生與 API 驗證都拒絕超長密碼，避免截斷比對。密碼不 trim、不 flash、不寫入前端 Bundle 或 localStorage；未設定本機真實管理密碼。
- `app/Actions/DeleteReport.php`：同筆 cache lock，重新讀取資料；串流備份待刪照片至 private disk，DB 交易中移除 public 照片與紀錄，關聯依既有外鍵 cascade 清理。一般照片刪除／DB 失敗會回滾與還原照片；不刪其他紀錄引用的路徑。成功必須完成暫存備份清理。
- 若還原本身也失敗，回 500、保留 private 備份並記錄排查路徑；若 DB 已提交但備份清理失敗，明確回 500 說明「紀錄已移除，但照片備份清理未完成」，不誤報 200。檔案系統／SQLite 無法共用原子交易，強制終止或底層持續故障仍需管理者核對恢復。
- `AppServiceProvider` 定義原生 limiter，POST 每 IP 每小時 5 次嘗試（含表單驗證失敗），DELETE 每 IP 每分鐘 5 次嘗試、跨紀錄共用額度；429 保留一致 JSON、中文訊息與 Retry-After，GET 不受這兩項額度限制。`config/cache.php` 的 limiter 預設持久 file store，phpunit.xml 另指定 array 隔離測試，不引入 Redis。
- 修正既有 `bootstrap/app.php` 例外處理，保留 Laravel `HttpResponseException` 的自訂回應，避免原生 limiter 的 429 被改成 500；亦排除 admin_password 的 trim／flash。
- 新增 `app/Http/Middleware/TrustProxies.php`：預設不信任 forwarding；只對明列 `TRUSTED_PROXIES` 的 socket peer 接受 X-Forwarded-For，沒有依 Host 自動信任或 forwarded host／proto 信任。設定拒絕 wildcard／全網段，Vite /api proxy 覆寫 X-Forwarded-For 為連線 socket IP 並移除 Forwarded。單層代理的必要覆寫、loopback 設定與密碼操作見新增 `backend/ADMINISTRATION.md`；未宣稱已驗證未來雲端／多層代理。

### 前端
- 詳細頁加入 `DeleteReportDialog.vue`：刪除按鈕、不可復原說明、密碼欄位、取消／確認、Loading、防重送、焦點循環、Escape 及背景捲動處理。成功後由 ReportDetailView 返回原篩選 query 的列表，沒有改動照片圖庫。
- `useReportDeletion.ts` 管理單次請求與中文錯誤：403／422／404／409／429／503／500／網路失敗不視為成功；每次嘗試與關閉時清空密碼，頁面卸載取消請求並防止晚到回應觸發成功導航。網路結果不明時不自動重試，提示先重新載入確認。
- NewReportView 只補 POST 429 的中文提示，保留既有欄位與照片；沒有改動排序、照片處理或 FormData 流程。

### 測試與實際驗證
- Laravel **90 tests／599 assertions 全通過**（SQLite :memory:、fake public／local Storage、array limiter）；Pint 通過。新增 DeleteReportTest、ReportRateLimitTest，涵蓋密碼／未設定／非法雜湊／query 拒絕／空白保留／bcrypt byte 邊界、密碼指令、刪除與外鍵照片清理、其他紀錄與共用照片保護、重複 404、並行 lock、備份失敗、第 3 張刪除失敗（false／exception）、DB 失敗回復、回復失敗保留備份、提交後備份清理失敗 500，以及兩項限流／到期／來源 IP spoofing。
- 前端 **31 項測試全通過**（既有 27 + 刪除 4），涵蓋必填、防重送、密碼清空、錯誤訊息、網路結果不明不重試、卸載後晚到回應不成功。`npm run type-check`、`npm run build`、`git diff --check` 通過；既有 HEIC 動態 chunk 大小警告保留。
- 真實瀏覽器／Laravel 以本次獨立 `test.sqlite`、Storage 與 file cache 運作，API 18100／Vite 15193；透過真實 POST 建立 3 筆（1／5／1 張），沒有操作原資料。測試管理密碼／雜湊只存在 Git 忽略的隔離工具與暫存檔。
- 實測刪除確認、空密碼、錯誤密碼 403、未設定雜湊 503、暫時連到缺失測試 DB 的真實 500、累積嘗試 429：都顯示中文訊息、不導航、不誤報成功，密碼清空。取消／Escape 不刪除，正向 Tab 回密碼欄位、關閉焦點回按鈕；390×844 視窗無水平溢出，1280×900 可操作。真實 iPhone 虛擬鍵盤尚未驗收。
- QA router 僅延遲真實 DELETE 1.5 秒，確認送出與取消皆 disabled。實際刪除五張的 #2 後返回列表，site_name／issue_type／severity 三條件保留，只剩符合的 #3。SQLite 剩 #1/#3、2 個照片關聯、2 個 public 檔案，其他照片 SHA-256 未變，private 備份為 0。五個已刪照片 URL 均 404、兩個保留照片 URL 均 200，重複 DELETE 為 404。
- 實際 Vite/API HTTP 傳入不同偽造 X-Forwarded-For／Forwarded，前五次非法 POST 為 422、第六次為 429，Retry-After 約 3598 秒，證明本機單層代理不能用換標頭繞過配額。瀏覽器新增表單真正收到 POST 429 時，已填文字與照片保留。
- 瀏覽器收集的 Console error／warn 為空；隔離 Laravel log 只有故意注入的缺失 DB 例外，未含測試密碼。原 `.env` 雜湊不變、13 個原照片雜湊不變、原 SQLite 大小／修改時間不變；未操作原 DB、照片或備份。
- 已停止本次 API／Vite 程序、清理隔離 DB／剩餘照片／cache／暫存雜湊，恢復 viewport。截圖與 JSON 證據保留於 Git 忽略的 `backend/storage/framework/testing/report-delete-20261103/`，包含 delete-confirm-mobile.png、deleted-filtered-list.png、delete-db-result.json、http-result.json、protected-result.json。

### 待處理／限制
- 由使用者在本機依 `backend/ADMINISTRATION.md` 產生並設定真正管理雜湊；本次沒有替使用者選擇或寫入真實密碼。未設定時刪除預設停用。
- 真實 iPhone／Android 的刪除確認、虛擬鍵盤與弱網操作，以及實際部署反向代理的來源 IP／HTTPS，仍需在對應環境驗收；本機 Vite 單層驗證不能代替部署驗收。
- 檔案回復或暫存備份清理也失敗時須管理者依 log 處理，不宣稱具有跨 DB／檔案系統原子性；未加入角色、稽核、配額或背景清理系統。

## TRUSTED_PROXIES CIDR 驗證補強

**完成日期：**2026-11-03（Asia/Taipei）。本次僅修改 `backend/config/inspection.php`、`backend/tests/Feature/ReportRateLimitTest.php` 與本紀錄，保留既有未提交工作。

- CIDR 遮罩必須為純數字，IPv4 限 1～32、IPv6 限 1～128；拒絕空遮罩、0、負數、正負號、非數字、小數／指數、超出範圍與多餘斜線。合法單一 IP、合法 CIDR、混合清單與既有清單空白處理均保留。
- 新增 44 個設定案例，直接載入實際 config 驗證；測試暫設的環境變數於 finally 還原，不寫入 .env。涵蓋兩種 IP 的上下邊界、一般 CIDR、合法清單及非法遮罩／斜線／混合清單。
- Laravel 全部 **134 tests／643 assertions 通過**（SQLite :memory:、fake Storage），包含既有來源 IP、限流與刪除測試；全專案 Pint 檢查及 `git diff --check` 通過。
- 未修改 TrustProxies Middleware、正常來源 IP 判斷、限流／刪除邏輯、README 或本機資料；未 Commit／Push。

## 公開上傳保護：每分鐘限流與照片總容量

**完成日期：**2026-11-03（Asia/Taipei）。本次只調整上傳保護與必要的刪除互斥；既有未提交工作保留。未修改 Migration、照片每張 20 MiB／每筆 1～5 張、格式驗證、拖曳／HEIC／燈箱；沒有新套件、額外資料表、排程清理、監控或配額介面，未操作 Home Fitness／Docker／雲端，也未 Commit／Push。

### 實作與設定
- `AppServiceProvider` 的 POST 限流改為每 IP 每分鐘 **10 次嘗試**，429 中文訊息及前端提示同步更新；DELETE 保持每 IP 每分鐘 5 次。POST 使用新的分鐘 counter key，避免沿用舊一小時 cache 期限；未清除正式 cache，也未修改可信代理來源判斷。
- `config/inspection.php` 與 `.env.example` 新增 `REPORT_PHOTO_STORAGE_LIMIT_BYTES`，預設 **2147483648（2 GiB）**，以非負整數 bytes 設定，0 拒絕所有新增照片；不寫入現有 .env。
- 新增 `app/Support/ReportPhotoQuota.php`：逐一累計本專案 `report_images` 關聯且實際存在的 public 檔案大小，distinct 路徑只計一次，不計其他專案／未關聯檔案／private 備份；加上本次全部照片大小，等於上限可新增，超過則在建立紀錄／寫檔前回 422 的 photos 中文錯誤，拒絕整筆。
- ReportController 的容量檢查、交易、保存與失敗清理均位於同一把 cache lock 內；DeleteReport 也共用此鎖，直到刪除或失敗還原完成，避免新增在照片暫時移除時低估容量。刪除原有密碼驗證、每筆 lock、交易／備份回復與限流邏輯保留。鎖忙碌回 409，新增與刪除前端皆提供相應提示，不自動重試。
- HTTP 沿用單機 `CACHE_STORE=file`，不新增 Redis。容量鎖不自動到期，避免慢請求尚未完成就被另一請求取得；正常／例外皆 finally 釋放。若程序遭強制終止留下鎖，需先停止並確認無新增／刪除請求，再由管理者執行 `php artisan cache:forget file-store-lock:report-photo-capacity file`（鍵名前綴已於後續隔離測試確認並修正）；沒有自動清理服務。此限制與設定已記入 README。
- README 僅新增「公開上傳保護」段落，說明速率、容量計算／設定、422／409、刪除釋放後再新增及必要操作；backend/ADMINISTRATION.md 只同步 POST 次數。

### 測試結果
- Laravel 全部 **142 tests／726 assertions 通過**。新增 `ReportPhotoQuotaTest` 及 `tests/Fixtures/quota-worker.php`；一般 Feature Tests 用 SQLite :memory: 與 fake Storage，獨立行程測試用自行建立的空 SQLite／Storage／file cache，結束只清理該 UUID 測試目錄，不操作本機正式 DB／照片。
- 容量測試包含：預設 2 GiB、正常新增、剛好達上限、下一筆超額、五張總和超額整筆拒絕、0 上限、重複路徑去重、缺失檔案不計、其他專案／未關聯檔案／private 備份不計、刪除後可再新增、寫入失敗清理後不占額度，以及共用鎖阻擋新增與刪除且不修改資料。phpunit.xml 固定測試容量設定，避免私人 .env 影響預設值測試。
- **真實跨行程互斥驗證：**先讓第一個 PHP 行程在已取得 file lock、完成容量檢查後暫停；第二個行程透過實際 Laravel HTTP Kernel 新增時回 409，不能同時消耗最後空間。釋放第一個後保存成功 201；第二個重試回容量 422。獨立 DB 最終只有 1 筆／1 張、實體大小等於容量上限，沒有超額；並非只 mock lock 或順序呼叫兩次。
- POST 第 10 次成功、第 11 次 429、非法輸入計次、偽造 forwarding 無法繞過、可信代理 IP 隔離、61 秒後恢復，以及舊 hourly counter 不影響新 minute window 均通過；DELETE 原本的 5 次限制與失敗回復回歸通過。
- 前端 **32 tests 通過**，包含共用容量鎖忙碌訊息與原本照片／刪除／篩選測試；`npm run type-check`、`npm run build`、全專案 Pint、`git diff --check` 通過。既有 HEIC 大型 chunk 警告保留。本次未另做真實手機或瀏覽器表單操作，不將型別／單元測試當成實機驗收。

### 限制
- 容量為目前正式關聯照片的邏輯上限，不是整台磁碟／其他專案的用量配額；private 刪除備份與孤兒檔案不計入，也沒有新增排程清理。
- 互斥依賴單機共用持久 file cache；不要在請求處理中清 cache 或使用 HTTP array／null store。多主機部署未在此階段處理，強制終止的殘留鎖需依 README 人工排查。
- 先前真實手機拖曳／燈箱／刪除及部署代理的待驗收事項仍保留，未宣稱本次已補做。

## 容量鎖異常解除指令修正

**完成日期：**2026-11-03（Asia/Taipei）。本次僅修改 README、本紀錄及新增 `backend/tests/Feature/ReportPhotoLockRecoveryTest.php`，保留既有未提交工作。

- 檢查目前 Laravel 13 vendor 原碼：`FileStore::lock()` 會將名稱改為 `file-store-lock:{$name}`；因此 `ReportPhotoQuota::LOCK_KEY` 雖為 report-photo-capacity，實際 file cache 鍵為 **file-store-lock:report-photo-capacity**。檔案路徑再由 FileStore::path() 對實際鍵做 SHA-1 分層；cache:forget 不會自動補上 lock 前綴。
- README 原指令 `php artisan cache:forget report-photo-capacity` 即使顯示成功，仍未移除容量鎖。已改為 **`php artisan cache:forget file-store-lock:report-photo-capacity file`**，明確指定 file store；適用目前 path 與 lock_path 相同的設定，仍須先停止並確認沒有新增／刪除請求，不清空其他快取。
- 新增一個最小測試，僅將測試 app 的 cache.default 設為 file，path／lock_path 指向自行建立的 UUID 隔離目錄。實際取得永久鎖、確認帶前綴鍵的檔案與 owner，再透過 Artisan 執行原指令，驗證仍無法取得鎖；執行修正後指令後可重新取得鎖，無關測試快取仍保留，finally 只清理該隔離目錄。
- 相關測試（新解除鎖測試＋ReportPhotoQuotaTest）**8 tests／55 assertions 全通過**；全專案 Pint 與 `git diff --check` 通過。未執行正式 cache:forget／cache:clear，未操作實際使用者快取、照片或資料庫；ReportPhotoQuota、Controller、DeleteReport、Migration 與限流程式皆未修改。未 Commit／Push。

## 階段 A：Docker 執行環境

**完成日期：**2026-11-03（Asia/Taipei）。依使用者最後補充，本階段只完成 Docker，GitHub Actions 由使用者自行建立；沒有新增或修改 `.github/workflows/`，未 Commit／Push、未連線 AWS／Lightsail，未修改業務程式、Migration 或套件。

### 檔案與執行設定
- 新增根目錄 `Dockerfile`、`compose.yaml`、`.dockerignore`，以及 `docker/.env.example`、`entrypoint.sh`、`php.ini`、`fpm.conf`、`nginx.conf`、`fastcgi.conf`。README 局部新增 Docker 首次建置、私人金鑰／雜湊設定、Migration、重建與持久化說明。
- 多階段建置：PHP 8.4 FPM／Composer production dependencies，Node.js 24 建置 Vue，Nginx 提供靜態檔。官方基底 Image 固定本次實際建置 digest；未在執行環境增加 Node、Composer、資料庫或 Redis 服務。
- Compose 僅 Nginx、PHP 兩個服務，只有 `127.0.0.1:18082:80` 對外；PHP 9000 僅容器網路。預設專案名稱 site-inspection-mini，SQLite／Storage（含 private 備份及 file cache）／public 照片使用三個 project-scoped named volumes；Nginx 僅唯讀掛載 public 照片。
- 啟動腳本只在 SQLite 檔不存在時建立，設定 www-data 權限，不自動 migrate 或匯入本機資料。APP_KEY／APP_URL 必填，private env 在執行時注入；PHP production/debug、SQLite 路徑及持久 file cache 由 Compose 固定。
- PHP upload 20M、POST 120M、max_file_uploads 20、Nginx 120m；Nginx 路由支援 SPA fallback、API、照片與檔案 404。移除客戶端 forwarding 標頭、保留實際 REMOTE_ADDR，Laravel 不盲目信任外部代理。
- 初次建置發現主機 bootstrap/cache 的 dev provider cache 被納入 context，造成 production 無 Pail 的啟動失敗；僅修正 `.dockerignore` 明確排除該 cache，未刪主機檔案。後續兩個 Image 均成功；環境檔、SQLite、照片、金鑰與測試資料排除於 context，runtime Image 稽核確認沒有 `.env`、本機 SQLite、測試、dev Pail 或主機 config cache，Image Config.Env 未含 APP_KEY／管理雜湊。

### 實際驗證
- 本機既有 Docker Engine **29.8.2**、Compose **v5.5.1** 可用，未安裝或修改系統。容器實際為 **PHP 8.4.27／Node.js 24.22.2（建置階段）／Nginx 1.28.0**。兩個 target 建置、production Composer platform requirements、`nginx -t`、Compose config 檢查均通過。
- Laravel 全部 **143 tests／736 assertions**、Pint 通過；前端 **32 tests**、Type Check 通過，Docker 內 npm ci／Vue production Build 通過。既有 HEIC 大型 chunk 警告保留。`git diff --check` 通過。
- 使用獨立專案 **site-inspection-mini-qa-20261103**、新 SQLite／Storage／photos volumes 與一次性測試環境設定。只對此空資料庫執行 migrate --force，全部 Migration 成功；未操作原本 SQLite 或私人設定。
- 真實 HTTP 經 Nginx／FPM 建立單張及五張混合 JPEG／PNG／GIF／WebP；列表最新優先、三條件 AND 篩選、單筆資料、images 數量／順序／照片實體 SHA-256 及 URL 200 正確。錯誤密碼刪除回 403，正確密碼回 200；被刪紀錄五個照片 URL 404，其他紀錄照片仍 200。
- 額外上傳 **五張各 20 MiB** 的真實 PNG 內容（尾端補齊邊界大小），整筆 **100 MiB** 經 Nginx／PHP 回 201、五個關聯正確，確認請求大小設定足夠；不將此視為真實手機記憶體壓力驗收。
- 兩次 `up -d --force-recreate --wait` 後紀錄與照片保留；第二次仍有 **2 筆／6 張**（含上述 100 MiB），private 測試備份內容與 file cache marker 正確。Nginx 使用者可讀 public 圖片，沒有 backend 程式碼或 private 掛載；private 測試備份無法從 /storage 讀取。
- HTTP 確認 `/`、列表、新增、詳細與未知網址重新整理都取得 SPA，API 不存在紀錄／未知 API、缺失照片／assets、`.env`、PHP 路徑均 404。瀏覽器實際開啟 Docker Vue 首頁導向新增、導航列表取得 2 筆真實 API 資料、詳細頁重新整理後照片仍載入；未知頁顯示全站 404。截圖保留於忽略目錄 `backend/storage/framework/testing/docker-20261103/docker-detail.png`。
- 使用不同偽造 X-Forwarded-For／Forwarded 連續送出非法 POST，前 10 次 422、第 11 次 429，未繞過來源額度；未改業務限流設定。服務 log 未見未解釋的 Laravel 例外。
- 原本 **13 個 public 檔案雜湊未變**，原 SQLite 大小／修改時間未變，原 `.env` 雜湊未變。Home Fitness 三個容器的 ID／啟動時間與測試前相同，沒有重建／停止或變更其資料。

### 保留與限制
- 測試結束只移除本次 QA 容器與網路，保留專屬 **site-inspection-mini-qa-20261103_sqlite／_storage／_photos** 三個 Volume、建置 Image 及忽略的測試證據／私人 QA 設定；未執行 prune 或 down -v。正式預設專案的 Volume 尚未建立。
- 尚未測試真實手機連線 Docker、雲端 Linux 主機、外部反向代理／HTTPS／來源 IP 鏈、GHCR 或 SSH 部署；此次 loopback HTTP 結果不能取代上述驗收。原有手機拖曳／燈箱／刪除的待驗收項目仍保留。
- GitHub Actions Workflow／遠端 CI 執行由使用者處理，本次未建立亦未宣稱通過。下一階段仍需準備 Registry 權限與 Image 命名、部署主機與 SSH known_hosts／金鑰、正式 APP_KEY／管理雜湊、網域／TLS／可信代理及資料備份策略。

## 正式 Compose：獨立代理網路

**完成日期：**2026-11-03（Asia/Taipei）。僅修改 `deploy/compose.production.yaml` 與本紀錄。

- Nginx 保留 default 網路，另加入 external `site-inspection-ingress`，別名 `site-inspection-upstream`；PHP 仍僅使用 default。保留 GHCR Images、`127.0.0.1:18082:80`、SQLite 與三個持久化 Volume，未新增服務。
- `docker compose -f deploy/compose.production.yaml config --quiet`、`config --images` 與 `git diff --check` 通過。解析後確認 Nginx 連接兩個網路、PHP 僅 default，外部網路名稱／別名、loopback 埠及三個 Volume 正確；Images 仍為原 GHCR php／nginx，預設 latest。
- 僅驗證設定解析，尚未驗證跨網路實際代理／HTTPS 流量；未建立或修改 Docker 網路，未執行 Up／Down／Restart。未修改 README、GitHub Actions 或 Home Fitness，未連線 Lightsail，未 Commit／Push。

## 正式 Compose：Caddy HTTPS 入口設定

**完成日期：**2026-11-03（Asia/Taipei）。僅新增 `deploy/Caddyfile`、修改 `deploy/compose.production.yaml` 與本紀錄。

- 新增官方 `caddy:2` 服務，發布 TCP 80／443，僅加入 Compose default 網路，反向代理 `nginx:80`。Caddyfile 唯讀掛載，`caddy_data`／`caddy_config` 分別持久化 /data／/config；既有 PHP／Nginx、GHCR Images、SQLite／Storage／photos 三個 Volume、loopback 18082 與 inspection_ingress 設定不變。
- Compose `config --quiet`、`config --services`、`config --images` 與解析後的服務／埠／網路／掛載／五個 named volumes 檢查通過；`git diff --check` 通過。未啟動 Caddy，尚未驗證 Caddy runtime 設定、正式 ACME 簽發／續期、HTTPS、100 MiB 上傳、容器重建後憑證保留或真實來源 IP。
- 依使用者提供的伺服器現況，Home Fitness 已占用主機 80／443，直接在同機啟動本設定會有埠衝突，正式啟用前須另行確認入口安排。Site Inspection 現有 Nginx 清除轉送 IP 標頭，因此加入 Caddy 不代表已完成真實 IP／HTTPS 的後端信任設定；此階段未擴大修改。
- 未連線 Lightsail、未執行 Docker Up／Down／Restart，未修改 Home Fitness、GitHub Actions、業務程式或其他 Docker 設定；未新增 Certbot／其他代理服務，未 Commit／Push。

## GitHub Actions：正式部署 Job

**完成日期：**2026-11-03（Asia/Taipei）。依使用者提供的新環境（13.210.231.8、ubuntu、/srv/site-inspection-mini）新增 `.github/workflows/ci.yml` 的 deploy Job；既有 backend／frontend／publish Jobs、測試與 Image 建置流程不變。

- deploy 僅接受 main push，needs publish 成功；固定 production concurrency group、cancel-in-progress=false，遠端另以 flock 保護部署寫入。Runner 用既有兩項 SSH Secrets 建立暫存私鑰／known_hosts（目錄 700、檔案 600），強制 Host Key 驗證、BatchMode／IdentitiesOnly，不執行 keyscan、不輸出金鑰，結束清理 Runner 暫存憑證。
- SCP 僅同步當次 Repository 的 Compose／Caddyfile 到獨立暫存目錄。遠端先確認既有 app.env／Compose／Caddyfile，驗證候選 Compose 與 Caddy 設定、拉取本次 github.sha 的 PHP／Nginx Images，再寫回兩份設定。Caddyfile 原 inode 保留，避免 bind mount 仍讀到舊檔；不覆寫 app.env、不建立 APP_KEY、不刪除或重建資料／憑證 Volume。
- 遠端以固定 site-inspection-mini 專案及 SITE_IMAGE_TAG=github.sha 執行 up -d --no-build --wait，再以 www-data 非互動 migrate --force，驗證並 reload Caddy。最後由 Runner 以驗證 TLS 的 HTTPS GET 確認 /up、/api/reports 都為 200；只重試讀取，不重試資料異動，不將 API 內容寫入部署日誌。任一步失敗即讓 Job 失敗，未加入自動資料庫回滾。
- 本機 YAML parser 通過，程式化核對既有三個 Jobs／triggers／permissions 完全不變，deploy 依賴／main push guard／concurrency／SSH 設定正確；兩個 Runner shell 區塊與遠端 heredoc 共三份 Bash -n 檢查通過，git diff --check 通過。驗證工具保留於 Git 忽略的 backend/storage/framework/testing/ci-deploy-20261103/，未安裝套件。
- 未實際執行 GitHub Workflow、SSH／SCP、遠端 Compose／Migration、Caddy reload 或 HTTPS 請求；未連線 Lightsail、修改 Secrets／Home Fitness／Dockerfile／業務程式，未 Commit／Push。伺服器使用者的目錄／Docker 權限、GHCR pull 權限（公開 Images 或既有登入）、Compose --wait 支援、外部網路與既有專案／Volume 名稱、Host Key 匹配及實際部署／migration／HTTPS 結果仍待首次受控執行確認。
- 新環境獨立使用 Caddy 且正式站正常為使用者回報；前段「與 Home Fitness 共用舊主機 80／443」屬歷史環境限制，不代表新主機現況。本次未補做來源 IP／HTTPS 後端信任設定。

## Deploy Job：root-owned 正式檔案權限修正

**完成日期：**2026-11-03（Asia/Taipei）。僅修改 `.github/workflows/ci.yml` 與本紀錄，保留既有未提交工作。

- SCP 暫存目錄改為 `/home/ubuntu/.site-inspection-deploy-<run_id>-<attempt>`；上傳前以 `sudo -n true` 檢查非互動 sudo，正式腳本以 `sudo -n bash -s --` 執行。sudo 不可用立即失敗、不要求密碼；flock／正式設定更新／Compose／Migration／Caddy reload 在 root 腳本內，容器 Migration 仍指定 www-data。
- 遠端清理 trap 提前至 cd／flock 前，正常完成或部署失敗皆清除本次兩個暫存檔及目錄。Runner EXIT trap 另以 ubuntu 執行限定路徑的清理，涵蓋 SCP／sudo 失敗，並清除 Runner 私鑰；保留原部署失敗退出碼，若部署成功但清理失敗則回報失敗。連線完全中斷時只能回報清理失敗，不能保證遠端暫存已移除；未使用遞迴刪除、chmod 777 或變更 docker 群組。
- YAML 解析、backend／frontend／publish Jobs 與既有 triggers／permissions 不變比對、main push／needs publish／互斥及資料保護檢查通過；兩份 Runner shell 與遠端腳本共 3 份 Bash 語法檢查通過。另以 mock ssh／檔案操作驗證 4 種清理退出碼情境（正常、原部署失敗、僅清理失敗、兩者失敗），均通過；git diff --check 通過。
- 保留 Host Key 驗證、SHA Tag、Caddyfile 原 inode、Migration、HTTPS 檢查與全部正式資料保護。本次未執行實際 SSH／sudo／Docker 或連線 Lightsail，伺服器 sudo 權限、root 的 GHCR 存取與首次部署結果仍待遠端確認；未 Commit／Push。

## 下一步

由使用者確認並提交／Push Workflow，觀察首次 main 部署與正式 HTTPS 驗收；本次不觸發遠端工作。先前手機拖曳／燈箱／刪除實機驗收項目仍保留。
