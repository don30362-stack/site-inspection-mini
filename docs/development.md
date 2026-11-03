# 開發與測試指南

說明本機安裝、Docker 執行、測試及手機連線。正式主機更新請看[部署文件](deployment.md)，不要套用本機初始化指令。

## 原生開發環境

需求：PHP 8.4、Composer 2、Node.js 24、npm；PHP 需有 SQLite／pdo_sqlite、fileinfo、mbstring、DOM／XML 等 Laravel 與測試依賴。

從專案根目錄依 [README 的首次安裝步驟](../README.md#本機開發) 啟動。首次建立 `.env` 才產生 APP_KEY；若自行建立過 `.env` 但 APP_KEY 仍空白，可手動執行 `php artisan key:generate`，已有金鑰則保留。

- SQLite 預設為 `backend/database/database.sqlite`；照片為 `backend/storage/app/public/reports/`。二者不納入 Git。
- `php artisan migrate --no-interaction` 可套用新 Migration；更新前先備份。不能用 fresh／refresh 更新有資料的 DB。
- `storage:link` 建立 `backend/public/storage`；Windows 需具備建立連結的權限。
- `serve.ps1` 預設只聽 127.0.0.1:8000，設定單檔 20M、POST 120M、max_file_uploads 20、關閉錯誤直接輸出；不修改系統 php.ini。

前端預設使用 `/api`，Vite 代理至 127.0.0.1:8000。可將 [frontend/.env.example](../frontend/.env.example) 複製為私人 `.env.local`：

```dotenv
VITE_API_BASE_URL=/api
API_PROXY_TARGET=http://127.0.0.1:8000
```

後端 `APP_URL` 必須是瀏覽器可連線的照片來源網址，原生開發預設 `http://127.0.0.1:8000`。後端改埠時使用 `.\serve.ps1 -Port 8001`，並同步調整 APP_URL 與 API_PROXY_TARGET。

## 本機 Docker

需求：Linux containers Docker Engine 與 Compose 2.24 以上。根目錄的 [compose.yaml](../compose.yaml) 從原始碼 Build，只有 Nginx／PHP，不提供公開 HTTPS。

於專案根目錄執行：

```powershell
if (-not (Test-Path docker/.env)) { Copy-Item docker/.env.example docker/.env }
docker compose build
docker compose run --rm --no-deps --entrypoint php php artisan key:generate --show
```

最後一個指令僅輸出新金鑰；**首次初始化**時填入私人 `docker/.env` 的 APP_KEY，保留供後續重建使用。已有金鑰不要替換。APP_URL 保持 `http://127.0.0.1:18082`，否則照片 URL 需同步調整。未設定 APP_KEY／APP_URL，容器拒絕啟動。

```powershell
docker compose up -d
docker compose exec -T --user www-data php php artisan migrate --force --no-interaction
```

開啟 `http://127.0.0.1:18082`。Nginx 直接讀 public Volume，這個模式不用 `storage:link`。需要刪除功能時，先依[管理設定](operations.md#管理密碼)產生雜湊。

SQLite、Storage（private 備份與 file cache）、照片分為三個 named volumes；重建容器不會刪除 Volume。此模式與正式 Compose 的預設 project name 相同，勿在同一 Docker Engine 混用；隔離測試可對**每條指令**加 `-p <獨立名稱>`，但仍需避免 18082 埠衝突。既有資料須沿用原 project name，不能改名後誤認資料遺失。

程式變更後以 `docker compose up -d --build` 更新；私人設定變更需重建容器，不只 restart。Migration 明確執行，不會由啟動腳本自動執行。不要刪除 Volume 或將本機 SQLite／照片複製進 Image。

## 自動化檢查

後端，工作目錄 `backend/`：

```powershell
php artisan test
composer check-platform-reqs
php vendor/laravel/pint/builds/pint --test
```

前端，工作目錄 `frontend/`：

```powershell
npm test
npm run type-check
npm run build
```

最後於根目錄執行 `git diff --check`。

Laravel Feature Tests 使用記憶體 SQLite、fake Storage 與隔離 cache；跨行程容量鎖測試另外建立自己的 SQLite／Storage／file cache。前端使用 Node.js 內建測試工具，涵蓋照片集合、URL 清理、排序、查詢競態與刪除狀態。

這些檢查不能代替真實 HEIC 解碼、HTTP 串接或手機驗收。實際瀏覽器測試應使用自己的 DB、Storage、cache 與程序；只清理本次測試資料，不碰本機展示資料或正式環境。

## 手機連線本機

1. 手機與電腦連接可互通的同一區域網路。Windows 執行 `ipconfig`，取目前 Wi-Fi／乙太網路的 IPv4，排除 VPN／虛擬網卡。
2. 後端私人 `.env` 設 `APP_URL=http://<電腦區網IPv4>:5173`；有設定快取時先於 backend/ 執行 `php artisan config:clear`。
3. backend/ 執行 `.\serve.ps1`；frontend/ 使用上方 proxy 設定並執行：

```powershell
npm run dev -- --host 0.0.0.0 --port 5173 --strictPort
```

手機開啟 `http://<電腦區網IPv4>:5173/reports/new`。照片 URL 應經 Vite 的 `/storage` 代理，不能指向手機自己的 localhost。若無法連線，檢查私人網路防火牆與 Wi-Fi 隔離；不需將 Laravel 埠公開到網際網路。

人工檢查：相機、相簿多選、更換／取消／刪除、方向與色彩、原始 HEIC、送出後詳細頁／篩選及重新整理。另驗證排序手柄、鍵盤替代與燈箱。已通過與待驗收範圍見[驗證現況](PROJECT_STATUS.md)，不要將桌面 viewport 當實機測試。
