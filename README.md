# Site Inspection Mini｜工地巡檢回報系統

以 Vue 3 與 Laravel 開發的小型全端 Web 專案，示範工地現場回報問題，以及辦公室查看巡檢紀錄的流程。

## 設計目標

- 手機版：選擇工地、問題類型、嚴重度，填寫描述並上傳照片。
- 桌機版：查看巡檢列表、依條件篩選、開啟詳細頁。
- 後端：透過 REST API 儲存及讀取資料，使用 SQLite 與 Laravel Storage 保存紀錄和照片。

## 技術選型

| 層級 | 技術 | 理由 |
| --- | --- | --- |
| 前端 | Vue 3、TypeScript、Vite | 元件化開發、型別檢查、快速建置 |
| UI | Bootstrap 5 | 快速製作手機與桌機響應式介面 |
| API | Axios、Laravel 13 | 將前後端資料傳遞與驗證分開處理 |
| 資料 | SQLite、Laravel Storage | 降低本機啟動門檻，檔案與資料分開保存 |

## 專案結構

```text
site-inspection-mini/
├── backend/         # Laravel API
├── frontend/        # Vue 3 + TypeScript
├── README.md
├── AGENTS.md         # Codex 開發規則
└── PROJECT_STATUS.md # 開發階段與驗證紀錄
```

## 本機啟動

需求：PHP 8.4（含 SQLite、fileinfo 等 Laravel 所需擴充）、Composer 2、Node.js 24、npm。

首次取得專案，於 `backend/` 執行：

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
if (-not (Test-Path database/database.sqlite)) { New-Item -ItemType File database/database.sqlite | Out-Null }
php artisan migrate --no-interaction
php artisan storage:link
.\serve.ps1
```

如果已存在 `backend/.env`，不要重新複製覆蓋，也不必重新產生 APP_KEY。

確認 `.env` 使用 `DB_CONNECTION=sqlite`，不設定 `DB_DATABASE` 時會自動使用 `backend/database/database.sqlite`。此資料庫檔不納入 Git，首次 Clone 後需以上述步驟建立並執行 Migration；後續更新只需再次執行 `php artisan migrate --no-interaction`，保留既有資料。Migration 會建立 `reports`、`report_images` 及 Laravel 骨架既有的預設資料表。多照片 Migration 先將既有照片路徑搬入新表，再移除 `reports.image_path`，無須重建資料庫；更新前請先備份並確認既有資料。不要以 `migrate:fresh` 或 `migrate:refresh` 更新含有資料的資料庫。

`serve.ps1` 使用 PHP 內建開發伺服器與 Laravel 的 router，僅對該程序設定 `upload_max_filesize=20M`、`post_max_size=120M`、`max_file_uploads=20`、`display_errors=0`、`log_errors=1`，不修改系統 `php.ini`。可用 `.\serve.ps1 -Port 8001` 指定其他連接埠，並同步調整 `.env` 的 `APP_URL` 與 Vite proxy。一般 `php artisan serve` 不會自動套用這些設定；PHP 的 POST 總大小限制必須高於照片上限，預留 multipart 欄位空間。錯誤應記錄而非直接輸出，以免 PHP 上傳警告破壞 JSON 回應。正式部署需在實際 PHP／Web Server 設定對應限制；此腳本僅供本機開發。

照片存放於 `backend/storage/app/public/reports/`，檔名由系統產生。`storage:link` 建立 `public/storage` 連結；Windows 若拒絕建立連結，需使用允許建立連結的終端機。`APP_URL` 必須是瀏覽器可連線的後端網址（預設 `http://127.0.0.1:8000`），供 API 產生 `image_url`。

另一個終端機於 `frontend/` 執行：

```powershell
npm ci
npm run dev
```

使用終端機顯示的 Vite 網址開啟前端。開發環境會將 `/api` 請求代理到 `http://127.0.0.1:8000`。

### 手機版新增回報

開啟 `/reports/new`（首頁會導向此頁）。選擇三個示範工地之一、問題類型與中文嚴重度，拍照或從相簿選取至少 1 張、最多 5 張照片，再填寫最多 1,000 字描述。表單提供兩欄正方形照片網格，可多選新增、點縮圖更換、獨立垃圾桶刪除；照片自然遞補，未滿五張時保留新增格。更換取消或失敗會保留原圖。表單也提供字數與欄位錯誤；送出成功後可建立下一筆並保留工地選擇。

JPEG、PNG、GIF、WebP 原內容直接上傳；HEIC／HEIF 先在瀏覽器自動轉成 JPEG，成功後才預覽及上傳。每張來源檔與轉換後照片均限制 20 MiB，多張照片逐張處理以降低記憶體負擔，轉換失敗不會上傳原檔。轉換或送出期間會鎖定操作，避免重複送出；送出失敗保留已填資料。網路中斷時無法確定伺服器是否已收到，請先確認紀錄再重試。

前端使用 `heic-to` **1.6.7**，僅辨識到 HEIF 檔案內容時才動態載入解碼器。授權為 LGPL-3.0，原始授權與來源資訊隨 Build 放在 `/licenses/`；套件來源與建置方式見 [heic-to 官方專案](https://github.com/hoppergee/heic-to)。解碼器是獨立較大的下載檔，第一次處理 HEIC 需要額外載入時間。

### 使用真實手機連線本機

手機與電腦須在同一個可互通的區域網路。前端以 `npm run dev -- --host 0.0.0.0` 啟動，手機開啟 `http://<電腦的區域網路位址>:5173/reports/new`；請用實際位址替換，不能使用手機自己的 localhost。系統若阻擋連線，需由使用者確認區網與防火牆設定。

前端維持 `VITE_API_BASE_URL=/api`，可將 `.env.example` 複製為未納入 Git 的 `.env.local`，以 `API_PROXY_TARGET` 設定 Vite 所連線的後端（預設 `http://127.0.0.1:8000`）。Vite 同時代理 `/api` 與 `/storage`，後端可維持只聽本機。若要讓手機開啟 API 的照片 URL，後端 `.env` 的 `APP_URL` 應設定為手機可連線的 Vite 網址 `http://<電腦的區域網路位址>:5173`，然後重新啟動服務；正式部署則設為實際對外網址。程式未硬編碼任何區域網路 IP。

## 巡檢 API

| 方法與路徑 | 行為 | 成功狀態 |
| --- | --- | --- |
| `POST /api/reports` | multipart/form-data 新增紀錄與 1～5 張照片 | 201 |
| `GET /api/reports` | 列表，可用 site_name、issue_type、severity 篩選 | 200 |
| `GET /api/reports/{id}` | 讀取單筆，不存在回傳 404 | 200 |

列表採完全相等篩選，多個條件以 AND 組合；依 `created_at`、`id` 由新到舊排列。未符合條件時回傳空陣列，目前不分頁。非法篩選值回傳 422。

新增必填欄位：

| 欄位 | 規則 |
| --- | --- |
| site_name | 字串，最多 100 字 |
| issue_type | 系統櫃安裝、水電、裝修、安全防護、其他 |
| severity | low、medium、high |
| description | 字串，最多 1,000 字 |
| photos[] | 必填陣列，至少 1 張、最多 5 張；每張為真正的 JPEG／PNG／GIF／WebP，最多 20 MiB（20 × 1,024 × 1,024 bytes） |

輸入文字會去除頭尾空白（含全形空白），拒絕全空白欄位。照片以內容 MIME 與圖片尺寸資訊驗證，不信任檔名或用戶端 Content-Type；不接受 SVG、偽裝圖片或客戶端指定儲存路徑。照片相對路徑只存於 `report_images.image_path`，同一回報依 `sort_order` 排列；資料庫交易或任一照片寫入失敗時，回滾整筆並清理本次所有已寫入及部分寫入照片。

所有 API 回應均為 `{ "data": ..., "message": "...", "errors": {} }`。成功時 data 是單筆物件或列表陣列；每筆包含 id、site_name、issue_type、severity、description、images、created_at、updated_at。images 為陣列，每張包含 id、image_url、sort_order（從 0 起算），依序排列；不回傳舊版頂層 image_path／image_url。驗證失敗回傳 422，data 為 null，errors 以欄位名稱對應錯誤訊息陣列；照片整組錯誤使用 photos，個別照片錯誤使用 photos.0、photos.1 等索引；404 與 500 同樣使用此結構，500 不回傳內部例外細節。

PowerShell 上傳範例（替換為本機照片路徑）：

```powershell
curl.exe -H "Accept: application/json" -F "site_name=範例工地" -F "issue_type=安全防護" -F "severity=medium" -F "description=施工區護欄需修復" -F "photos[]=@C:/photos/example.jpg" -F "photos[]=@C:/photos/another.png" http://127.0.0.1:8000/api/reports
```

### 照片相容性

JPEG、PNG、GIF、WebP 直接保存至 public disk。**API 本身仍只接受這四種格式**；使用新增回報表單時，HEIC／HEIF 由瀏覽器先自動轉 JPEG 再上傳。直接把未轉換的 HEIC／HEIF 傳給 API，仍會回傳 photos 或 photos.索引 的 422 錯誤，不保存原檔。

桌面 Chromium 已用 libheif 官方真實 HEIC／HEIF 容器範例驗證自動轉出 1280×854 JPEG、預覽及 API 保存；多影像容器使用解碼器選出的主影像。另一份獨立的未壓縮 `unci` HEIF 樣本不受此解碼器支援，會顯示中文錯誤而不送出。**尚未在真實 iPhone Safari 或 Android 手機瀏覽器測試**，不保證所有 HEIF 編碼、手機方向／色彩或大尺寸照片皆相容；不要求手動轉檔作為產品操作步驟。[libheif 官方說明](https://github.com/strukturag/libheif)列有其解碼器依賴；[PHP 上傳設定](https://www.php.net/manual/en/ini.core.php)說明檔案與 POST 大小限制。

## 基本檢查

後端（`backend/`）：

```powershell
php artisan test
```

Feature Tests 使用 SQLite 記憶體資料庫與隔離的本機 Storage，涵蓋 Model、API、篩選、輸入限制、四種實際圖片內容、20 MiB 邊界及寫入失敗清理。圖片 fixture 已隨專案提供，不依賴 GD 產生測試圖。不包含 HEIC／HEIF 自動轉換測試。

前端（`frontend/`）：

```powershell
npm run type-check
npm run build
npm test
```

`npm test` 使用 Node.js 內建測試工具檢查照片內容辨識、檔案限制、逐張處理、增刪更換、取消／失敗保留原圖及預覽 URL 清理，不額外安裝測試套件。瀏覽器操作、HEIC 真實解碼及送出流程需另外以實際頁面驗證，不能只以 Type Check 或此單元測試宣稱相容。
