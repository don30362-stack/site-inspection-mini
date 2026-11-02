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

確認 `.env` 使用 `DB_CONNECTION=sqlite`，不設定 `DB_DATABASE` 時會自動使用 `backend/database/database.sqlite`。此資料庫檔不納入 Git，首次 Clone 後需以上述步驟建立並執行 Migration；後續更新只需再次執行 `php artisan migrate --no-interaction`，保留既有資料。Migration 會建立 `reports` 及 Laravel 骨架既有的預設資料表。不要以 `migrate:fresh` 或 `migrate:refresh` 更新含有資料的資料庫。

`serve.ps1` 使用 PHP 內建開發伺服器與 Laravel 的 router，僅對該程序設定 `upload_max_filesize=20M`、`post_max_size=24M`、`display_errors=0`、`log_errors=1`，不修改系統 `php.ini`。可用 `.\serve.ps1 -Port 8001` 指定其他連接埠，並同步調整 `.env` 的 `APP_URL` 與 Vite proxy。一般 `php artisan serve` 不會自動套用這些設定；PHP 的 POST 總大小限制必須高於照片上限，預留 multipart 欄位空間。錯誤應記錄而非直接輸出，以免 PHP 上傳警告破壞 JSON 回應。正式部署需在實際 PHP／Web Server 設定對應限制；此腳本僅供本機開發。

照片存放於 `backend/storage/app/public/reports/`，檔名由系統產生。`storage:link` 建立 `public/storage` 連結；Windows 若拒絕建立連結，需使用允許建立連結的終端機。`APP_URL` 必須是瀏覽器可連線的後端網址（預設 `http://127.0.0.1:8000`），供 API 產生 `image_url`。

另一個終端機於 `frontend/` 執行：

```powershell
npm ci
npm run dev
```

使用終端機顯示的 Vite 網址開啟前端。開發環境會將 `/api` 請求代理到 `http://127.0.0.1:8000`。

## 巡檢 API

| 方法與路徑 | 行為 | 成功狀態 |
| --- | --- | --- |
| `POST /api/reports` | multipart/form-data 新增紀錄與一張照片 | 201 |
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
| photo | 真正的 JPEG／PNG／GIF／WebP 圖片，最多 20 MiB（20 × 1,024 × 1,024 bytes） |

輸入文字會去除頭尾空白（含全形空白），拒絕全空白欄位。照片以內容 MIME 與圖片尺寸資訊驗證，不信任檔名或用戶端 Content-Type；不接受 SVG、偽裝圖片或客戶端指定儲存路徑。資料庫只存相對 `image_path`。

所有 API 回應均為 `{ "data": ..., "message": "...", "errors": {} }`。成功時 data 是單筆物件或列表陣列；每筆包含 id、site_name、issue_type、severity、description、image_path、image_url、created_at、updated_at。舊資料沒有照片時 image_url 為 null。驗證失敗回傳 422，data 為 null，errors 以欄位名稱對應錯誤訊息陣列；404 與 500 同樣使用此結構，500 不回傳內部例外細節。

PowerShell 上傳範例（替換為本機照片路徑）：

```powershell
curl.exe -H "Accept: application/json" -F "site_name=範例工地" -F "issue_type=安全防護" -F "severity=medium" -F "description=施工區護欄需修復" -F "photo=@C:/photos/example.jpg" http://127.0.0.1:8000/api/reports
```

### 照片相容性

JPEG、PNG、GIF、WebP 直接保存至 public disk，無須轉檔。**目前 API 不支援 HEIC／HEIF 自動轉 JPEG**，會回傳 photo 的 422 錯誤，不保存未轉換檔案。手機端尚未提供自動轉換流程，不需自行手動轉檔作為正式操作步驟。

可採用的自動化整合方向為前端選檔後以 HEIF 解碼器自動轉為 JPEG，再上傳同一個 photo 欄位；或部署固定版本的 libheif 與 HEVC decoder，於後端驗證轉換結果後保存 JPEG。須使用真實手機照片驗證方向、多影像與解碼失敗情況，才可宣告支援。[libheif 官方說明](https://github.com/strukturag/libheif)列有其解碼器依賴；[PHP 上傳設定](https://www.php.net/manual/en/ini.core.php)說明檔案與 POST 大小限制。

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
```
