# 自動化測試

後端使用 PHPUnit／Laravel Feature Tests；前端使用 Node.js 內建測試工具，測試 TypeScript 工具函式、Vue composable 與記憶體 Router，未使用 Vitest。

## 已確認結果

2026-11-10 查閱 [PR CI #13](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42778258487) 的工作與日誌：

| 檢查 | 結果 | 證據 |
| --- | --- | --- |
| PHPUnit | 183 tests／1,132 assertions 通過 | [Laravel Tests](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42778258487/job/129701790392) |
| Laravel Pint | 通過 | 同一 Laravel 工作的 Pint 步驟 |
| Node.js 前端測試 | 49 tests 通過，無跳過 | [Vue Tests](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42778258487/job/129701790791) |
| TypeScript／正式 Build | 通過 | 同一 Vue 工作；HEIC chunk 仍有大於 500 kB 警告 |

先前 2026-11-09 本機完整測試同為 183／49；分頁階段的 158／41 與早期 143／32 已是歷史基準，見[歸檔](archive/development-history.md)。本次只更新文件，未重跑程式測試；PR CI 成功不包含正式部署或功能驗收，最新部署證據見[驗證現況](PROJECT_STATUS.md)。

## 後端覆蓋（183 tests）

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
| [Feature Example](../backend/tests/Feature/ExampleTest.php)／[Unit Example](../backend/tests/Unit/ExampleTest.php) | 2 | 根路徑回應與框架 assertTrue 範例；後者不代表業務覆蓋。 |
| **合計** | **183** | |

Data Provider 每組資料各算一個 test；同一 test 的多次請求不額外計數。例如四個狀態起點各測四個終點，共 16 組轉換、4 個執行案例。

## 前端覆蓋（49 tests）

| 測試檔案 | 數量 | 主要內容 |
| --- | ---: | --- |
| [photo](../frontend/tests/photo.test.mjs) | 9 | 真實圖片檔頭、HEIF compatible brands、拒絕偽裝／截斷／AVIF、空檔與大小限制。 |
| [photoCollection](../frontend/tests/photoCollection.test.mjs) | 9 | 五張上限、處理佇列、更換／取消、排序保留物件與 File／URL、清空及晚到結果。 |
| [reports](../frontend/tests/reports.test.mjs) | 4 | 四種 query 篩選、URL 往返、台灣時間及共用選項。 |
| [latestRequest](../frontend/tests/latestRequest.test.mjs) | 5 | 過期成功／錯誤防護、取消、404、Loading 與失敗重試。 |
| [reportDeletion](../frontend/tests/reportDeletion.test.mjs) | 5 | 密碼清理、防重送、各類錯誤、網路結果不明及離頁取消。 |
| [reportPagination](../frontend/tests/reportPagination.test.mjs) | 9 | URL／歷史／重新載入、四條件歸一、詳細／刪除返回、非法頁碼、超範圍修正、快速切頁與取消。 |
| [reportStatus](../frontend/tests/reportStatus.test.mjs) | 8 | 狀態文字／API 值／顏色、URL、任意切換、成功回傳資料、相同狀態／重送略過、錯誤還原及離頁取消。 |
| **合計** | **49** | |

## 人工驗收與證據界線

使用者已確認本機瀏覽器驗收通過：列表分頁、篩選及返回原頁，四種狀態任意切換，以及修改後重新整理仍保留正確狀態。此為使用者驗收回報；本次新增功能在正式網站及手機尚未個別驗收。自動化測試不等於完整瀏覽器或正式環境驗收。

指令見[開發指南](development.md#自動化檢查)。後端依 [phpunit.xml](../backend/phpunit.xml) 使用記憶體 SQLite、fake Storage 與 array cache；跨行程案例使用 UUID 隔離 SQLite／Storage／file cache，只清理自己的資料。不要用正式資料庫測試。

- Laravel Feature Tests 經 HTTP Kernel，沒有經正式 Caddy／Nginx／PHP-FPM；PHP 上傳錯誤及 POST 總長度是模擬，圖片大小邊界使用實際補齊大小的 PNG。
- 前端自動化測試未掛載完整 Vue DOM；照片集合使用假的轉換與 Object URL。這些測試不驗證真實 HEIC 解碼、燈箱 DOM／手勢、完整表單送出或手機版面。
- 本機瀏覽器的分頁與狀態功能驗收不代表正式網站、手機、照片或弱網情境也已驗收。既有 iPhone 回報與待確認事項見[驗證現況](PROJECT_STATUS.md)。
- Pint、型別與 Build 不算入測試案例數；Actions 部署及 HTTPS GET 成功也不等於正式功能、代理來源 IP、憑證續期或備份還原通過。
- Windows 受限沙箱曾將 autoload.php 的 is_readable 判為 false，導致 PHPUnit 無法啟動；先前於沙箱外使用同一隔離設定通過，不能因此改用業務資料庫。
