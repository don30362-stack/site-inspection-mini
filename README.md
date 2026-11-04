# Site Inspection Mini｜工地巡檢回報系統

讓現場人員用手機拍照回報問題，辦公室透過列表篩選、詳細頁與照片掌握工地狀況。

正式網站：[https://site-inspection-mini.ddnsfree.com](https://site-inspection-mini.ddnsfree.com)

## 核心功能

- 新增巡檢：工地、問題類型、嚴重度、描述，以及 1～5 張照片，每張最多 20 MiB。
- 照片操作：相機／相簿選取、預覽、更換、刪除、拖曳或鍵盤排序；HEIC／HEIF 自動轉 JPEG。
- 紀錄查詢：依工地、類型、嚴重度組合篩選；桌機表格與手機卡片皆可使用。
- 詳細檢視：照片切換、全螢幕檢視、2 倍放大與平移，時間以台灣時區顯示。
- 管理刪除：輸入管理密碼後，由後端驗證並移除紀錄與對應照片。

## 技術架構

前端使用 **Vue 3、TypeScript、Vite、Bootstrap 5、Axios**；後端使用 **Laravel 13、PHP 8.4、SQLite、Laravel Storage**。

正式環境部署於 **AWS Lightsail**：Caddy 提供 HTTPS，Nginx 提供 Vue 靜態檔並將 API 交給 PHP-FPM。GitHub Actions 執行測試、發布 GHCR Images，再透過 SSH 部署。

### 技術選型理由

- **Vue 3 + TypeScript**：之前有學過 Vue，想繼續練習前端開發，並透過 TypeScript 減少寫錯資料的機會。
- **Laravel**：之前做過 Laravel 電商專案，對它比較熟悉，可以把時間放在功能開發。
- **SQLite**：這次專案規模小，使用 SQLite 不需要另外架設資料庫伺服器。
- **Docker Compose**：希望換一台電腦或伺服器時，可以用相同方式啟動網站。
- **GitHub Actions**：希望每次 Push 後自動測試與部署，不用一直手動更新伺服器。

## 專案亮點

- **照片順序一致**：重排保留原 File 與預覽 URL，提交順序直接對應資料庫 `sort_order`。
- **手機照片處理**：按內容辨識格式，僅遇 HEIC 才動態載入解碼器，逐張轉換以控制記憶體用量。
- **失敗處理**：新增失敗回滾資料並清理照片；刪除前建立私人備份，失敗時嘗試還原。
- **上傳保護**：後端驗證圖片內容與大小、原生限流，以及共用 file lock 保護的照片容量檢查。
- **可重現部署**：相依套件 lock 檔、多階段 Docker Build、Commit SHA Image Tag 與持久化 Volume。

## Demo 影片

[▶ 觀看 3 分鐘 Demo 影片](https://youtu.be/x2PP9Yu19t8)

## 本機開發

需求：PHP 8.4（含 SQLite、fileinfo 等擴充）、Composer 2、Node.js 24、npm。首次安裝於專案根目錄執行：

```powershell
cd backend
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env; php artisan key:generate }
if (-not (Test-Path database/database.sqlite)) { New-Item -ItemType File database/database.sqlite | Out-Null }
php artisan migrate --no-interaction
php artisan storage:link
.\serve.ps1
```

另一個終端機：

```powershell
cd frontend
npm ci
npm run dev
```

開啟 Vite 顯示的網址；首頁導向新增巡檢。不要覆蓋既有 `.env`、重新產生既有 APP_KEY 或重建含資料的資料庫。Docker、測試與手機連線步驟見[開發指南](docs/development.md)。

## AI 工具使用

開發時使用 Codex 協助程式實作、撰寫測試、排查問題與整理文件。我負責確認需求及功能設計，並透過自動化測試、瀏覽器操作、iPhone 實測和正式環境驗收確認結果。AI 產生的程式碼並非直接視為正確，而是依實際測試結果調整。

## 文件導覽

- [架構與設計](docs/architecture.md)：資料模型、照片流程、技術限制與 AI 使用說明。
- [API 規格](docs/api.md)：欄位、篩選、回應與錯誤狀態。
- [自動化測試](docs/testing.md)：查看前後端的測試案例與覆蓋範圍。
- [開發指南](docs/development.md)：安裝、Docker 本機執行、測試與手機連線。
- [部署與 CI/CD](docs/deployment.md)：正式環境、GitHub Actions、更新與回復注意事項。
- [管理與維運](docs/operations.md)：刪除密碼、容量、代理來源與故障排查。
- [驗證現況](docs/PROJECT_STATUS.md)：已驗證範圍、待辦及歷史紀錄入口。
