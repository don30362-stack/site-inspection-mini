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
Copy-Item .env.example .env
php artisan key:generate
php artisan serve --host=127.0.0.1 --port=8000
```

如果已存在 `backend/.env`，不要重新複製覆蓋，也不必重新產生 APP_KEY。

另一個終端機於 `frontend/` 執行：

```powershell
npm ci
npm run dev
```

使用終端機顯示的 Vite 網址開啟前端。開發環境會將 `/api` 請求代理到 `http://127.0.0.1:8000`。

## 基本檢查

後端（`backend/`）：

```powershell
php artisan test
```

前端（`frontend/`）：

```powershell
npm run type-check
npm run build
```
