# 自動化測試

本文件依實際測試程式及執行結果整理，說明每項功能測了什麼。後端使用 PHPUnit／Laravel Feature Tests，前端使用 Node.js 內建測試工具，直接測試 Vue composable 與 TypeScript 工具函式。

## 測試總覽

2026-11-04 實際執行結果：後端 **143 tests**、前端 **32 tests**，全部通過，與歷史紀錄一致。

| 範圍 | 測試數量 | 工作目錄 | 執行指令 |
| --- | ---: | --- | --- |
| 後端 | 143 | backend/ | `php artisan test` |
| 前端 | 32 | frontend/ | `npm test` |

計數以執行時展開的案例為準：PHPUnit Data Provider 的每組資料各算一個 test；前端四種圖片格式在迴圈中各註冊一個 test。單一 test 內的多次請求、錯誤狀態或斷言不額外計次，每個案例只歸入一個主要分類。

## 後端測試（Laravel）

| 功能分類 | 測試數量 | 測試情境 | 測試檔案 |
| --- | ---: | --- | --- |
| 資料庫與模型 | 16 | 最終欄位及照片外鍵／非空限制；三種嚴重度建立與讀取、時間欄位及雙向關聯、照片排序；拒絕孤兒照片、重複順序、4 個必填欄位的 null 與 3 種非法嚴重度；關聯刪除；升級保留舊照片，拒絕有多張照片時的有損 rollback。 | [ReportTest.php](../backend/tests/Feature/ReportTest.php) |
| 巡檢 API 與欄位驗證 | 20 | 單筆查詢、照片 URL、缺失／非數字 ID 的 JSON 404；全部必填缺漏、僅缺照片；16 組 null、一般／全形空白、超長、非法選項及錯誤型別；工地 100 字、描述 1,000 字邊界與去除頭尾空白。 | [ReportApiTest.php](../backend/tests/Feature/ReportApiTest.php) |
| 照片上傳與驗證 | 15 | JPEG／PNG／GIF／WebP 真實內容保存、UUID 路徑、不採用外來路徑、JSON 結構及無舊欄位；文字、偽裝 JPG／HEIC、損壞 PNG、SVG；恰好 20 MiB／超過 1 byte；模擬 PHP 上傳錯誤及 POST 長度超限；1／5 張成功、0／6 張與舊 photo 欄位拒絕；混合五張的內容、ID、URL、順序及後續讀取一致。 | [ReportApiTest.php](../backend/tests/Feature/ReportApiTest.php) |
| 列表查詢與篩選 | 8 | 最新優先、同時間以 ID 排序、空照片陣列；三條件分別查詢及組合完全相等篩選；AND 未全符合時空結果；非法篩選值；預載照片只查一次，避免 N+1。 | [ReportApiTest.php](../backend/tests/Feature/ReportApiTest.php) |
| 管理密碼與刪除 | 8 | 刪除紀錄、關聯及全部照片並保留其他紀錄，重複刪除 404；密碼必填、只接受 body、拒絕陣列／超長／錯誤密碼；未設定或非法雜湊停用；保留密碼空白且 API 不洩漏欄位；雜湊指令的短密碼／確認不符／成功；bcrypt 72 bytes 與多位元組邊界；共用路徑不誤刪；缺失照片可刪，同筆鎖定時拒絕並行刪除。 | [DeleteReportTest.php](../backend/tests/Feature/DeleteReportTest.php) |
| 限流與可信代理 | 49 | 44 組代理設定：8 組合法空設定、IP、CIDR 邊界／混合清單，36 組非法遮罩、範圍、斜線、IP／wildcard；10 次新增後限流；驗證失敗也計次、GET 不受影響、到期恢復；舊 hourly counter 不延長新分鐘額度；偽造轉送標頭不能繞過，僅可信代理可提供來源 IP；刪除 5 次額度跨紀錄共用且到期恢復。 | [ReportRateLimitTest.php](../backend/tests/Feature/ReportRateLimitTest.php)、[DeleteReportTest.php](../backend/tests/Feature/DeleteReportTest.php) |
| 照片容量與鎖 | 8 | 預設 2 GiB、剛好滿額及下一筆超額；五張總和超額整筆拒絕、0 上限；同路徑去重，缺失／未關聯／其他專案／private 備份不計；刪除後可新增；共用鎖阻擋新增及刪除；寫入失敗釋放鎖、不占容量；真實跨 PHP 行程爭取最後額度；舊解除鎖指令無效，帶前綴鍵可解除且保留其他快取。 | [ReportPhotoQuotaTest.php](../backend/tests/Feature/ReportPhotoQuotaTest.php)、[ReportPhotoLockRecoveryTest.php](../backend/tests/Feature/ReportPhotoLockRecoveryTest.php) |
| 交易一致性與失敗處理 | 17 | 新增第 3 筆照片關聯後 DB 失敗；照片部分寫入後拋例外／回 false，以及第 2～5 張各自失敗，均回滾紀錄並清理檔案。刪除第 3 張失敗、DB 刪除失敗會還原；備份建立失敗不刪資料；還原失敗保留私人備份；提交後清理備份失敗仍回 500，不誤報成功。 | [ReportApiTest.php](../backend/tests/Feature/ReportApiTest.php)、[DeleteReportTest.php](../backend/tests/Feature/DeleteReportTest.php) |
| 基礎與框架範例 | 2 | Laravel 根路徑回 200，以及保留的 assertTrue 範例；後者不代表業務功能覆蓋。 | [Feature/ExampleTest.php](../backend/tests/Feature/ExampleTest.php)、[Unit/ExampleTest.php](../backend/tests/Unit/ExampleTest.php) |
| **合計** | **143** | 每個執行案例只計一次。 | |

### 分類計數依據

依測試執行產生的 JUnit 案例清單核對，全部 8 個測試類別皆已納入：

- ReportTest：16，全部歸資料庫與模型。
- ReportApiTest：54，分為 API／欄位 20、照片 15、列表 8、一致性 11。
- DeleteReportTest：15，分為管理／刪除 8、限流 1、一致性 6。
- ReportRateLimitTest：48，全部歸限流與可信代理；其中 44 個來自兩個設定 Data Provider。
- ReportPhotoQuotaTest：7、ReportPhotoLockRecoveryTest：1，全部歸容量與鎖。
- 兩個 ExampleTest：各 1，歸基礎與框架範例。

同一方法內可能檢查多個情境，例如 0／6 張／舊欄位共用一個 test；不將這些情境拆成三次計數。`quota-worker.php` 是跨行程測試的輔助程序，不另算 test。

## 前端測試（Vue / TypeScript）

| 功能分類 | 測試數量 | 測試情境 | 測試檔案 |
| --- | ---: | --- | --- |
| 照片格式與前置檢查 | 9 | 四種真實圖片檔頭辨識，不依賴檔名／MIME；拒絕文字、SVG、空／截斷檔頭；辨識 HEIF compatible brands、拒絕 AVIF；空檔與超過 20 MiB 在載入解碼器前拒絕。 | [photo.test.mjs](../frontend/tests/photo.test.mjs) |
| 照片集合與排序 | 9 | 加到五張、拒絕第六張、刪除遞補再新增；超出剩餘格數整批拒絕；逐張處理及 queued／processing 狀態；替換失敗保留原 File／URL、成功才釋放舊 URL、取消不變；新增失敗保留有效照片；清空時釋放 URL 並阻止晚到結果；跨列重排保留物件、key、File、URL、converted、error，不重新解碼，FormData 順序一致，排序後更換／刪除／新增正確；非法／原位／處理中重排不改陣列。 | [photoCollection.test.mjs](../frontend/tests/photoCollection.test.mjs) |
| 日期、篩選與共用選項 | 4 | 僅保留合法型別的三個 query 欄位、忽略無關參數、全部選項不送出；組合條件經 URL 往返不變；台灣時區跨日、空／非法日期提示；工地、問題與中英文嚴重度選項。 | [reports.test.mjs](../frontend/tests/reports.test.mjs) |
| 非同步請求 | 5 | 舊成功／錯誤不覆蓋新結果，即使請求忽略 abort；網路失敗後重試清除錯誤並管理 Loading；404 狀態與成功後重設；取消後晚到結果不改狀態。 | [latestRequest.test.mjs](../frontend/tests/latestRequest.test.mjs) |
| 刪除操作 | 5 | 必填密碼、防重送、成功並清空密碼；403／404／422／429／503／409／500 正確失敗提示；網路結果不明時不自動重試、取消清除密碼；容量鎖 409 專用訊息；離頁後晚到成功不回報成功。 | [reportDeletion.test.mjs](../frontend/tests/reportDeletion.test.mjs) |
| **合計** | **32** | 五個檔案的全部案例均已納入。 | |

## 測試範圍與限制

- 後端依 [phpunit.xml](../backend/phpunit.xml) 使用記憶體 SQLite、fake Storage 與 array cache；跨行程及解除鎖測試另外建立 UUID 隔離 SQLite／Storage／file cache，完成只清理自己的目錄。本次原本 SQLite 大小／修改時間與 public 檔案雜湊未變。
- Laravel Feature Tests 經應用程式 HTTP Kernel 測 API，不等於經 Caddy／Nginx／PHP-FPM 的真實 HTTP。PHP 上傳錯誤及 POST 總長度是模擬；照片大小邊界則使用實際補齊大小的 PNG 檔案。
- 前端直接測邏輯，未掛載 Vue 畫面；照片集合注入假的轉換與 Object URL，HEIF 檔頭測試也不代表真實解碼成功。沒有在這 32 個案例中測燈箱 DOM、觸控手勢、完整表單驗證／送出、真正圖片預覽或全站 Router 404 畫面。
- 真實 HEIC 轉 JPEG、前後端完整串接、燈箱、拖曳手勢與手機操作需另做瀏覽器／實機驗收。iPhone 已有使用者回報，Android、弱網、低記憶體、HDR／色彩與極端上傳等限制仍保留，見[驗證現況](PROJECT_STATUS.md)。
- 正式部署、憑證續期、備份還原及實際代理來源 IP 不在上述案例內。Type Check、Build、Pint 是另外的型別／建置／格式檢查，不算入 143／32；指令見[開發指南](development.md#自動化檢查)。
