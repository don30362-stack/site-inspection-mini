# 驗證現況與待辦

更新日期：2026-11-10（台灣時間）。本文件區分程式完成、自動化檢查與正式環境驗收；歷史保留於 [archive/](archive/development-history.md)。

## 已完成功能

- 手機新增巡檢，1～5 張照片、每張最多 20 MiB；照片預覽、更換、刪除、排序及 HEIC／HEIF 轉 JPEG。
- 列表依工地、問題類型、嚴重度及狀態做 AND 篩選，Laravel 固定每頁 20 筆，以 created_at／id DESC 排序。URL 保留條件與頁碼，支援詳細頁返回、重新整理及瀏覽器前後頁。
- 待處理、處理中、已完成、已審核四種狀態可任意切換。新增與舊資料升級時預設 pending；詳細頁自動儲存，無登入、管理密碼、主管權限或修改歷程。
- 詳細頁與照片燈箱、管理密碼刪除、容量鎖、限流、失敗清理及全站 404。欄位與限制見 [API](api.md)、[架構](architecture.md)與[維運](operations.md)。
- 列表新增第一頁／最後一頁按鈕，沿用 URL 分頁與四條件篩選；邊界使用原生 disabled，分頁列可換行並保留至少 44px 觸控尺寸。使用者已回報全部瀏覽器人工驗收通過，不擴大為所有手機實機皆已驗證。

## 本機練習：首尾分頁 Multi-Agent 協作（2026-11-10）

- Agent A（`pagination_flow`）只修改 `frontend/src/views/ReportListView.vue`：加入首尾按鈕、停用條件及 Bootstrap `flex-wrap gap-2`；沿用 `setPage()`，未修改 Router、API 或後端。
- Agent B（`pagination_tests`）只修改 `frontend/tests/reportPagination.test.mjs`：新增第 3 頁 → 第 6 頁 → 第 1 頁及前進／返回測試。資料包含 101 筆符合四條件與 4 筆各有一條件不符的紀錄，依請求實際篩選後分頁，驗證 URL、API params、metadata、各頁 ID 與四條件保留。
- 主 Agent 等待兩者完成後審查整合，確認無修改衝突；檢查通過後才更新本文件。未新增套件或測試框架。
- 本次本機檢查：`php artisan test --compact` **183 tests／1,132 assertions 通過**；Pint `--test` 通過；`npm.cmd test` **50 tests 通過**（其中分頁 10 tests）；`npm.cmd run type-check`、`npm.cmd run build`、`git diff --check` 均通過。未遇測試失敗，Build 仍有既有 HEIC chunk 大於 500 kB 警告。
- 使用者已回報分頁功能全部瀏覽器人工驗收通過；此為使用者驗收結果，非本次代理另行操作瀏覽器。前端自動化測試涵蓋 composable 與記憶體 Router，未掛載完整 Vue DOM，仍保留此測試範圍限制。
- 獨立 Code Review 已完成，未發現可修正缺陷；Review 回報前端 50 tests、型別、Build 與 diff 檢查通過。Review 當時未確認的瀏覽器與手機版面，後續以上述使用者瀏覽器驗收回報補充，不推論所有手機實機已驗證。
- 本次依使用者明確授權建立本機 Git Commit，不 Push、Merge 或部署；下方 CI 與部署紀錄屬先前版本，不能作為此功能已上線的證據。

### 本機人工驗收資料

- 執行前確認 Laravel 執行時 `APP_ENV=local`、SQLite `PRAGMA database_list` 指向本專案 `backend/database/database.sqlite`，public Storage 為本專案本機目錄。未操作正式環境、清空資料庫、修改既有紀錄或執行 Migration。
- 新增 49 筆帶唯一批次描述標記的巡檢，每筆一張既有 PNG 測試圖片：45 筆符合「七期住宅 A 棟／系統櫃安裝／高／處理中」，另 4 筆各有工地、類型、嚴重度或狀態其中一項不符。原有 7 筆巡檢與 16 筆照片關聯仍保留；新增後共 56 筆巡檢、65 筆照片關聯。
- 本機 Laravel HTTP Kernel 驗證三頁 API 皆 200，分別 20／20／5 筆、total 45、last_page 3，跨頁 45 個 ID 不重複，皆符合四條件並有圖片 URL；抽查一筆詳細 API 回 200。這不代表瀏覽器／Vite 代理或實際圖片載入已驗收。
- 臨時工具與精確 ID／描述／照片路徑清單位於 Git 已忽略的 `backend/storage/app/private/pagination-practice/`，SQLite 與照片也不納入 Git。專案根目錄執行 `php backend/storage/app/private/pagination-practice/tool.php verify` 可只讀核對；驗收結束後才執行同一路徑 `cleanup`，會先確認本機環境、資料庫及整批存活紀錄的描述／照片關聯，再按清單 ID 呼叫既有刪除 Action。若描述或照片關聯被修改則停止，需先人工核對。批次清單保留供核對，禁止以篩選條件批次刪除。
- 使用者回報已清理 49 筆臨時資料；提交前執行只讀 verify 與額外核對：本批 ID／描述標記的巡檢、照片關聯及 49 個照片路徑均無殘留，恢復原先 7 筆巡檢與 16 筆照片關聯。現存巡檢皆有照片，16 筆關聯的照片皆存在且可辨識；SQLite integrity_check 為 ok、foreign_key_check 無違規。
- 原始資料核對範圍為批次清單、建立前筆數、現存關聯及照片可讀性；未保存建立前的完整欄位／檔案雜湊快照，不宣稱已完成逐欄位或逐位元比對。臨時工具與清單保留於私人 Storage，仍被 Git 忽略，不納入 Commit。

## GitHub 與部署證據

| 項目 | 已確認結果 |
| --- | --- |
| [PR #1](https://github.com/don30362-stack/site-inspection-mini/pull/1) | 2026-11-10 16:22:06 合併至 main；合併 commit 為 11fbc9eb6cc83c4a75cf3fd16628d5ba9b8fe61a。 |
| [PR CI #13](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42778258487) | success：Laravel／Vue 檢查通過；映像發布與部署依 PR 條件跳過。 |
| [main CI #14](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42782086120) | 2026-11-10 16:24:42 整體 success；Laravel、Vue、Publish Docker Images、Deploy to Lightsail 全成功。 |

[部署工作](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42782086120/job/129715127700)日誌確認容器健康，status Migration 於 16:24:35 執行 DONE，HTTPS 檢查步驟成功（依 Workflow 要求 /up、/api/reports 皆回 200）。這是部署流程證據；本次沒有登入正式主機獨立核對實際容器 SHA、資料庫內容或操作網站。後續核對方式見[部署文件](deployment.md)。

## 測試與驗收範圍

- 2026-11-10 PR CI 日誌確認：PHPUnit **183 tests／1,132 assertions**、Node.js 前端 **49 tests** 全通過；Pint、TypeScript、正式 Build 通過。仍有 HEIC 解碼套件大於 500 kB 的體積警告。分類與證據連結見[測試文件](testing.md)。
- 先前文件整理未重跑程式測試，只查核 GitHub 結果、程式與文件；本次首尾分頁練習的本機檢查結果見上節。
- 使用者已確認本機瀏覽器驗收通過：列表分頁、篩選及返回原頁，四種狀態任意切換，以及修改後重新整理仍保留正確狀態。此為使用者驗收回報；本次新增功能在正式網站及手機尚未個別驗收，自動化測試不等於完整瀏覽器或正式環境驗收。
- 先前使用者已回報 iPhone 核心新增、原始 HEIC 轉換、照片排序、燈箱與管理刪除通過；不包含本次新增分頁及狀態功能的正式實機驗收。
- 先前本機 Docker 隔離驗證包括五張各 20 MiB 上傳、增刪查及重建後持久資料保留；不代表正式 Caddy 全鏈路極限上傳通過。
- 2026-11-09 本機曾因漏套用 status Migration 回 500；已先私人備份再增量升級，核對 3 筆紀錄與 12 筆照片關聯保留，狀態測試 25 tests／231 assertions 通過；正式升級另以上述部署日誌為證。

## 仍需人工驗證

1. 獨立核對正式容器 SHA、Migration 與原資料保留；本次新增的分頁、篩選、返回原頁、狀態切換及重新整理後保留，在正式網站及手機尚需個別驗收。本機瀏覽器已由使用者驗收通過，部署日誌成功不取代操作驗收。
2. 正式代理鏈的真實 IP 與 HTTPS 後端判斷；目前代理流量可能共用限流額度，見[來源 IP 與代理](operations.md#來源-ip-與代理)。
3. Android、弱網、低記憶體、大容量及 HDR／色彩專項；已知 unci HEIF 不支援。
4. Caddy 憑證續期、重建保留與完整備份還原演練；網站或 CI 成功不等於上述項目通過。

既有開發紀錄見[完整歷史](archive/development-history.md)、[分頁](archive/pagination-stage-1-20261109.md)及[狀態管理](archive/report-status-stages-2-4-20261109.md)；本次不重寫歸檔。
