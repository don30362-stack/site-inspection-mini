# 自動化測試

後端使用 PHPUnit／Laravel Feature Tests；前端使用 Node.js 內建測試工具，測試 TypeScript 工具函式、Vue composable 與記憶體 Router，未使用 Vitest。

## 已確認結果

2026-11-11 描述關鍵字搜尋整合後，主 Agent 在本機 PHP 8.4.27／Node.js 24.22.1 重跑：

| 檢查 | 結果 | 指令／方式 |
| --- | --- | --- |
| PHPUnit | 208 tests／1,244 assertions 通過 | backend：`php artisan test --compact` |
| Laravel Pint | 通過 | backend：`php vendor/bin/pint --test` |
| Node.js 前端測試 | 57 tests 通過，無跳過 | frontend：`npm.cmd test` |
| TypeScript／正式 Build | 通過 | frontend：`npm.cmd run type-check`、`npm.cmd run build`；HEIC chunk 仍有大於 500 kB 警告 |
| Unicode trim 對照 | 1,112,064 個 Unicode scalar values、兩端各 65 個 trim 字元、零差異 | 直接呼叫已安裝 Laravel `Str::trim` 與前端 `normalizeReportKeyword`，排除 surrogate 範圍 |
| 差異格式 | 通過 | 根目錄：`git diff --check` |

Windows PowerShell 的 `npm.ps1` 曾受執行政策阻擋，使用同一套 npm 的 `npm.cmd` 完成檢查。此次沒有執行遠端 CI 或部署；本機通過不代表正式功能已驗收。前次 183／49 的 CI 證據及本階段修正過程見[搜尋階段紀錄](archive/description-search-20261111.md)，更早基準見[開發歷史](archive/development-history.md)。

## 後端覆蓋（208 tests）

| 測試檔案 | 數量 | 主要內容 |
| --- | ---: | --- |
| [ReportTest](../backend/tests/Feature/ReportTest.php) | 16 | 最終 schema、模型關聯與排序、必填／外鍵／唯一限制、升級保留舊照片及拒絕有損 rollback。 |
| [ReportApiTest](../backend/tests/Feature/ReportApiTest.php) | 54 | 新增／查詢／原三條件 AND、文字邊界、圖片內容與 20 MiB 邊界、1～5 張及排序、預載避免 N+1、DB／Storage 失敗回滾與清理。 |
| [DeleteReportTest](../backend/tests/Feature/DeleteReportTest.php) | 15 | 密碼與設定驗證、限流、照片及關聯刪除、共用路徑保護、失敗還原與備份清理。 |
| [ReportRateLimitTest](../backend/tests/Feature/ReportRateLimitTest.php) | 48 | 新增每 IP 每分鐘 10 次、驗證失敗計次／到期／偽造轉送標頭、可信代理與 CIDR 設定邊界。 |
| [ReportPhotoQuotaTest](../backend/tests/Feature/ReportPhotoQuotaTest.php) | 7 | 容量邊界與路徑去重、刪除釋放、容量鎖、跨行程競爭及失敗釋放。 |
| [ReportPhotoLockRecoveryTest](../backend/tests/Feature/ReportPhotoLockRecoveryTest.php) | 1 | file cache 鎖前綴與只解除目標鎖。 |
| [ReportPaginationTest](../backend/tests/Feature/ReportPaginationTest.php) | 15 | 0／1／20／21／41 邊界、固定 20 筆及真實 meta、跨頁不重複、時間／ID 排序、篩選、非法／超範圍頁碼與後頁照片。 |
| [ReportStatusTest](../backend/tests/Feature/ReportStatusTest.php) | 25 | pending 預設及拒絕初始 status、Migration 保留舊資料／照片、NOT NULL、四狀態自由切換、非法值／404、PATCH 欄位隔離、不用容量鎖、四條件篩選與分頁、跨紀錄每 IP 限流。 |
| [ReportSearchTest](../backend/tests/Feature/ReportSearchTest.php) | 25 | 描述字面搜尋、空白與 Unicode trim、中文／emoji 100／101 code points、陣列 422、`0`、特殊字元／注入樣式、SQLite 大小寫、NUL 字面查詢與四條件 AND 防漏、21 筆分頁與排序。 |
| [Feature Example](../backend/tests/Feature/ExampleTest.php)／[Unit Example](../backend/tests/Unit/ExampleTest.php) | 2 | 根路徑回應與框架 assertTrue 範例；後者不代表業務覆蓋。 |
| **合計** | **208** | |

Data Provider 每組資料各算一個 test；同一 test 的多次請求不額外計數。例如四個狀態起點各測四個終點，共 16 組轉換、4 個執行案例。

## 前端覆蓋（57 tests）

| 測試檔案 | 數量 | 主要內容 |
| --- | ---: | --- |
| [photo](../frontend/tests/photo.test.mjs) | 9 | 真實圖片檔頭、HEIF compatible brands、拒絕偽裝／截斷／AVIF、空檔與大小限制。 |
| [photoCollection](../frontend/tests/photoCollection.test.mjs) | 9 | 五張上限、處理佇列、更換／取消、排序保留物件與 File／URL、清空及晚到結果。 |
| [reports](../frontend/tests/reports.test.mjs) | 8 | 四種 query 篩選及 keyword、完整 trim 集合、100／101 code points、10 萬個內部／全空白、特殊字元 URL 往返、台灣時間及共用選項。 |
| [latestRequest](../frontend/tests/latestRequest.test.mjs) | 5 | 過期成功／錯誤防護、取消、404、Loading 與失敗重試。 |
| [reportDeletion](../frontend/tests/reportDeletion.test.mjs) | 5 | 密碼清理、防重送、各類錯誤、網路結果不明及離頁取消。 |
| [reportPagination](../frontend/tests/reportPagination.test.mjs) | 13 | URL／歷史／重新載入、四條件與 keyword、提交／組字狀態／草稿清除、replace／push、超長 URL 防請求、詳細／刪除返回、非法頁碼、超範圍修正、快速搜尋／切頁與取消。 |
| [reportStatus](../frontend/tests/reportStatus.test.mjs) | 8 | 狀態文字／API 值／顏色、URL、任意切換、成功回傳資料、相同狀態／重送略過、錯誤還原及離頁取消。 |
| **合計** | **57** | |

## 人工驗收與證據界線

使用者已確認描述關鍵字搜尋完成本機瀏覽器人工驗收；先前亦回報既有列表分頁、篩選及返回原頁，四種狀態切換與重整保留通過。Code Review 未發現可修正缺陷，並確認 208 個後端測試、57 個前端測試及前端型別／Build 通過。這些是各自的驗證證據，不能延伸為正式環境或全部手機專項通過。

使用者未提供逐項裝置／案例證據；手機原生中文 IME 組字不誤送、手機實機版面及慢網路快速搜尋／切頁仍列未個別確認。IME 自動化僅驗證 composable 的組字狀態 guard，沒有驗證原生 DOM 事件順序。

收尾已核對 local 環境與批次清單，精確移除 26 筆驗收紀錄／26 張照片，再以 verify 確認批次全數為 0、原始 7 筆／16 張關聯及既有 public 檔案雜湊一致；此輪只更新驗收文件與清理本機測試批次，未修改功能程式，也未重跑完整程式測試。完整測試結果沿用上表與 Review 確認，清理證據見[階段紀錄](archive/description-search-20261111.md#人工驗收確認與批次清理)。

前端 trim 使用線性 code point 掃描，避免長內部空白觸發 regex 平方回溯；自動化案例驗證長內容保留與驗證結果，不設脆弱的毫秒門檻。trim 集合對齊目前安裝的 Laravel，框架升級時須重新對照。

指令見[開發指南](development.md#自動化檢查)。後端依 [phpunit.xml](../backend/phpunit.xml) 使用記憶體 SQLite、fake Storage 與 array cache；跨行程案例使用 UUID 隔離 SQLite／Storage／file cache，只清理自己的資料。不要用正式資料庫測試。

- Laravel Feature Tests 經 HTTP Kernel，沒有經正式 Caddy／Nginx／PHP-FPM；PHP 上傳錯誤及 POST 總長度是模擬，圖片大小邊界使用實際補齊大小的 PNG。
- 前端自動化測試未掛載完整 Vue DOM；照片集合使用假的轉換與 Object URL。這些測試不驗證真實 HEIC 解碼、燈箱 DOM／手勢、完整表單送出或手機版面。
- 本機瀏覽器的分頁與狀態功能驗收不代表正式網站、手機、照片或弱網情境也已驗收。既有 iPhone 回報與待確認事項見[驗證現況](PROJECT_STATUS.md)。
- Pint、型別與 Build 不算入測試案例數；Actions 部署及 HTTPS GET 成功也不等於正式功能、代理來源 IP、憑證續期或備份還原通過。
- Windows 受限沙箱曾將 autoload.php 的 is_readable 判為 false，導致 PHPUnit 無法啟動；先前於沙箱外使用同一隔離設定通過，不能因此改用業務資料庫。
