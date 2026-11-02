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

## 下一步

待使用者指示開始階段 4：桌機列表、篩選與詳細頁；另安排真實手機相機／相簿、HEIC 方向與大尺寸照片測試。不自行 Commit／Push。
