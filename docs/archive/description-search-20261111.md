# 描述關鍵字搜尋：Multi-Agent 實作與複核

日期：2026-11-11（Asia/Taipei）。本階段程式、測試資料與文件操作均限本機；收尾由使用者授權建立單一 Git Commit，未 push、merge、部署或修改正式資料。現行契約見 [API](../api.md)，最新驗證範圍見 [PROJECT_STATUS](../PROJECT_STATUS.md)。

## 分工與實作

- Agent A 僅編輯後端：IndexReportRequest、ReportController、新增 ReportSearchTest；新增 nullable/string/max:100 的 keyword 驗證及描述字面包含搜尋，保留四條件 AND、排序、20 筆分頁與照片預載。
- Agent B 僅編輯前端：reports.ts、useReportList.ts、ReportListView.vue，以及 reports／reportPagination 測試；加入 Unicode 正規化、搜尋草稿與明確提交、URL guard、歷史同步及 IME 狀態保護。搜尋／清除 replace，切頁 push，不新增套件或路由。
- Agent C 在 A／B 停止編輯後只讀獨立複核，僅用記憶體 SQLite 與無檔案輸出的 Node 探測；找到兩個 P2，交由原負責 Agent 修正，再次複核確認兩項均可關閉，未發現新的阻擋問題。
- 主 Agent 統一契約、檢查差異、直接跨 PHP／JavaScript 對照 trim、重跑完整檢查並更新文件；沒有多人同時編輯同一檔案。

## 發現及修正

1. 前端最初尾端 regex 在長內部空白後接一般字元時平方回溯；40k 空白只讀量測約 904ms。改用 Array.from code points 與頭尾雙指標線性掃描，保留相同 65 字元集合，新增 100k 內部／全空白案例，不用脆弱的耗時斷言。
2. SQLite LIKE 遇到內部 NUL 會截斷，可能使 `abc` 錯配 `abc\0xyz`，也可能漏掉描述 NUL 後的一般關鍵字。含 NUL keyword 改用綁定的 instr/lower；一般 keyword 保留 LIKE／ESCAPE，另以完整括號 OR 補足描述含 NUL 的字面查詢。新測試驗證完整字串、ASCII／非 ASCII 大小寫、特殊字元及四條件不會被 OR 繞過，未改 API 的接受字元契約。
3. B 的首次 Build 發現 Vue template inline if 語法無法解析，改成布林短路表達式後通過。PowerShell npm.ps1 受執行政策阻擋，改用 npm.cmd，未變更系統政策。

## 最終整合驗證

環境：Windows、PHP 8.4.27、Node.js 24.22.1、npm 11.19.1。

| 檢查 | 主 Agent 實際結果 |
| --- | --- |
| backend `php artisan test --compact` | 208 tests／1,244 assertions 通過 |
| backend `php vendor/bin/pint --test` | 通過 |
| frontend `npm.cmd test` | 57 tests 通過，0 failed／skipped |
| frontend `npm.cmd run type-check` | 通過 |
| frontend `npm.cmd run build` | 通過；既有 heic-to chunk 約 3,225 kB，仍有 >500 kB 警告 |
| 根目錄 `git diff --check` | 通過 |
| PHP Str::trim／JS normalizeReportKeyword 對照 | 排除 U+D800～DFFF，逐一比較全部 1,112,064 個 Unicode scalar values；兩端各 65 個 trim 字元，零差異；線性修正後已重跑 |

PHPUnit 使用 phpunit.xml 的記憶體 SQLite、fake Storage、隔離 cache；跨行程既有測試仍只操作自己的測試資源。Unicode 對照直接呼叫安裝版本的 Laravel 與前端函式，不以 JavaScript trim() 代替 PHP 結果；這是本次本機額外驗證，尚非跨平台 CI 固定工作。

後端新增 25 個 Feature Test 案例；前端由 49 增至 57 個，擴充現有四條件／分頁案例並新增 trim、長度、URL 與草稿案例。沒有新增測試框架、Migration、FTS、索引或服務。

## 前次基準與證據

2026-11-10 [PR CI #13](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42778258487) 曾確認 [Laravel 183 tests／1,132 assertions 與 Pint](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42778258487/job/129701790392)、[Vue 49 tests／型別／Build](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42778258487/job/129701790791) 通過。2026-11-09 本機基準同為 183／49，更早紀錄見[開發歷史](development-history.md)。這些舊 CI 結果不包含本次搜尋變更；本次沒有觸發新 CI 或部署。

## 待驗收與維護注意

- 使用者後續已確認本機瀏覽器人工驗收完成（見下方收尾紀錄）；尚未提供手機原生中文 IME、手機實機版面、弱網及逐項案例證據，這些專項仍未個別確認。
- 現有 Node 測試只覆蓋 composable／memory Router，不能代表完整 DOM、Axios 連至 API 或正式環境通過。
- trim 集合對齊目前 Laravel；未來框架升級須重新對照。單機 SQLite 包含搜尋未新增索引或 FTS，不宣稱已完成大量資料效能驗收。

## 本機人工驗收資料準備

依使用者要求，先唯讀確認執行環境為 `local`、設定未快取，實際 SQLite 為 `C:\Projects\site-inspection-mini\backend\database\database.sqlite`，quick_check 為 ok；public／private disk 均位於本專案本機目錄。原有 reports 7 筆、report_images 16 筆；前次 pagination-practice 的 49 筆已不存在，未重用或改動其 manifest。

沿用前次私人批次工具的 seed／verify／cleanup 模式，新增 Git 已忽略的 `backend/storage/app/private/keyword-practice/tool.php`、`manifest.json`、`baseline.json`、`before.sqlite`。SQLite 備份透過 SQLite backup API 一致複製，不覆蓋既有檔案；baseline 僅記錄原資料列、其他業務資料表及既有 public 檔案雜湊。工具每次重新檢查 local／實際 DB／Storage，拒絕重複 seed、原始資料變動、批次身分改變、照片被共用或雜湊改變；cleanup 只使用 manifest 的確切 ID／照片路徑，沿用 DeleteReport 處理，沒有按 keyword 或範圍批次刪除。

- 批次關鍵字：`KWS-20261111-f2964a50`。
- 四條件：七期住宅 A 棟／系統櫃安裝／high／in_progress（高／處理中）。
- 符合條件 ID 61～82 共 22 筆；對照 ID 83／84／85／86 分別只改工地／類型／嚴重度／狀態，四筆也含共同關鍵字。
- 共新增 26 張真實 PNG，使用既有 photo.png fixture，逐筆經現行 StoreReportRequest 的欄位、格式、張數、大小與圖片規則驗證，status 另經 UpdateReportStatusRequest 規則驗證；沿用照片配額鎖及容量檢查。CLI 建立資料，不以 HTTP POST 繞過公開端點的限流。
- 現有總數 33 筆／42 張關聯；原先 7 筆／16 張關聯逐列 SHA-256、其他資料表及 17 個既有 public 檔案（包含非照片檔）雜湊均相同。此為原始內容的邏輯完整性，新增／清理後 SQLite 檔案位元組與自動遞增計數不要求恢復原狀，不自動還原整庫備份。

所有下表結果保持四條件；案例查詢時以表中的文字替換 keyword，不把共同關鍵字與案例文字拼接：

| 搜尋文字 | 預期筆數 |
| --- | ---: |
| KWS-20261111-f2964a50 | 22，第 1 頁 20／第 2 頁 2 |
| 中文護欄 | 1（ID 61） |
| `內部  空白`（兩空白） | 1（ID 62） |
| `內部 空白`（一空白） | 1（ID 68），與兩空白不同筆 |
| `%`／`_`／`!`／反斜線，各自單獨搜尋 | 各 2 |
| `!%_\` | 1（ID 67） |

共同關鍵字搭配只取消任一項篩選，預期 23；全部四項取消則 26。已用本機 Laravel Http Kernel 程序內 GET 核對 15 組預期結果，未發出遠端 HTTP 請求；不代表真實瀏覽器、圖片 URL 或手機已驗收。原設定 APP_URL 為區網 `http://192.168.68.101:5173`，工具沒有修改設定或啟動服務。

於專案根目錄執行，預設 verify 不刪資料：

```powershell
php backend/storage/app/private/keyword-practice/tool.php verify
php backend/storage/app/private/keyword-practice/tool.php plan-cleanup
```

`plan-cleanup` 列出 manifest 的確切 ID、描述、照片 ID／路徑供核對，不執行刪除。驗收完成後才由使用者清理，再驗證：

```powershell
php backend/storage/app/private/keyword-practice/tool.php cleanup
php backend/storage/app/private/keyword-practice/tool.php verify
```

清理後預期 `batch_reports`、`batch_images`、`batch_photo_files` 均為 0，`all_original_row_and_file_hashes_match` 為 true；若沒有新增其他資料，總數回到 7／16。工具、manifest、baseline 與私人備份保留供追溯，不遞迴刪除目錄、不清空資料庫、不執行 Migration。資料準備當時僅執行 seed、verify 與清理預覽；後續已依使用者授權清理，結果如下。原始備份與測試圖片不納入 Git。

## 人工驗收確認與批次清理

使用者明確回報描述關鍵字搜尋已完成瀏覽器人工驗收；Code Review 對 staged、unstaged、untracked 變更未發現可修正缺陷，確認 208 個後端測試、57 個前端測試與型別／Build 通過。未提供手機原生 IME 的確認，因此仍保留未驗證狀態，不將瀏覽器整體驗收擴大為所有手機、弱網或正式環境案例通過。

收尾先確認分支為 `practice/codex-keyword-search`。執行私人工具 `plan-cleanup`，確認 APP_ENV=local、實際 SQLite 路徑仍為上述專案 database.sqlite、manifest 批次仍為 `KWS-20261111-f2964a50`；剩餘 26 筆／26 張關聯／26 個照片檔案，所有原始資料雜湊一致，批次身分與照片所有權檢查均通過。之後執行 `cleanup`，再獨立執行 `verify`：

| 核對項目 | 實際結果 |
| --- | --- |
| batch_reports／batch_images／batch_photo_files | 0／0／0 |
| reports_now／images_now | 7／16 |
| 原始巡檢／照片關聯逐列雜湊 | 全部一致 |
| 其他資料表與 17 個既有 public 檔案雜湊 | 全部一致 |
| 原始私人 SQLite 備份 SHA-256、資料庫 quick_check | 通過 |

僅依 manifest 精確清理 ID 61～86 與其照片，不還原整庫、不重置 SQLite 自動遞增計數，也未刪除原始巡檢或照片。私人工具、manifest、baseline、備份及驗證證據保留在 Git 忽略目錄，均排除於提交。此輪沒有再修改功能程式或重跑完整程式測試，完整檢查結果沿用前述整合與 Review 證據；提交前另檢查 git status、完整 git diff、暫存差異與 git diff --check，僅納入搜尋功能、測試與相關文件。
