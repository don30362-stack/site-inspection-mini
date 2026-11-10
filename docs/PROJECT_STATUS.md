# 驗證現況與待辦

供維護者確認目前完成範圍與證據界線；作品介紹請從 [README](../README.md) 閱讀。逐次開發、決策及測試數字完整保留於[歷史歸檔](archive/development-history.md)，不將舊結果當作本次重跑。

## 目前功能與部署

- 已實作多照片巡檢新增、HEIC 自動轉換、拖曳／鍵盤排序、列表四條件（含狀態）AND 篩選與每頁 20 筆伺服器端分頁、詳細頁與燈箱、四種狀態自由切換、管理密碼刪除、全站 404。分頁與狀態管理仍為本機未提交修改，尚未部署。
- SQLite／Storage、容量鎖、限流與錯誤清理均有測試；實際介面及 API 見[架構](architecture.md)與 [API 規格](api.md)。
- 使用者已確認正式 HTTPS 網站與管理密碼刪除功能驗收通過；文件整理時另以公開 HTTPS GET 確認首頁與 /up 為 200，未操作正式資料。
- 使用者已確認 GitHub Actions 的 Laravel、Vue、GHCR 發布及 Lightsail 部署流程通過。Workflow 使用 ubuntu 暫存上傳、sudo -n 正式部署與 Commit SHA Images；此為使用者回報，本次未重新執行遠端驗證。
- Demo 影片已完成，YouTube 連結已加入 [README](../README.md#demo-影片)。

## 已有驗證證據

- 階段 5 完整整合與全新副本安裝已有紀錄；使用者在真實 iPhone 驗收相機、相簿多選、更換／取消／刪除、方向、送出、詳細頁、篩選及重新整理，均通過。
- iPhone 從「檔案」選取原始 HEIC，有自動轉 JPEG 提示並成功上傳；從相簿選取同圖未顯示轉換提示，推測 iOS 已先轉換，沒有直接確認傳入網頁的 MIME。
- 使用者於 2026-11-04 確認照片拖曳排序、照片燈箱放大及管理密碼刪除，皆已在真正的 iPhone 實測通過；此為使用者實機驗收回報，本次未重新操作手機。
- Docker 隔離環境已驗證新增、篩選、刪除、五張各 20 MiB 上傳，以及重建後 SQLite／照片／private 備份／file cache 保留；這是本機 Docker 證據，不代表正式 Caddy 全鏈路極限上傳通過。
- 2026-11-04 重新執行 Laravel 143 tests、前端 32 tests，全部通過；分類與覆蓋範圍見[自動化測試](testing.md)。Type Check／Build／Pint 的通過紀錄沿用先前驗證，未於該次重跑。

## 仍待確認

1. 正式 Caddy → Nginx → Laravel 的真實 IP／HTTPS 後端判斷；目前清除轉送標頭的限制見[維運文件](operations.md#來源-ip-與代理)。
2. Android 的核心流程、觸控排序、燈箱操作與管理刪除仍無實機驗收結果。
3. 大尺寸／大容量、低記憶體、弱網與 HDR／色彩專項；已知 unci HEIF 不支援。
4. 正式 Caddy 憑證續期／重建保留與完整備份還原演練；不以網站或部署流程通過推定這些皆通過。
5. 分頁及狀態管理的真實瀏覽器與手機介面驗收；目前已通過 Router／composable 邏輯測試、型別檢查與 Build，尚未實測手機 DOM 與觸控尺寸。新增 status Migration 已套用本機開發資料庫，正式環境尚未套用。

## 本機狀態更新 500 修復（2026-11-09）

- Laravel log 確認 `no such column: status`；local SQLite 的新增 status Migration 仍為 Pending，並非選單或 PATCH 驗證問題。
- 先以 SQLite VACUUM INTO 建立私人備份（storage/app/private/backups/，受 Git 忽略），僅套用新增 status 欄位的 Migration。3 筆原紀錄各欄位及 12 筆照片關聯逐項比對不變，初始狀態全部 pending；未修改照片檔案或正式環境。
- Migration 狀態已為 Ran；隔離狀態測試重跑 25 tests／231 assertions 全通過。沒有程式變更，未重跑前端 Build；git diff --check 通過。
- 階段 2～4 交付時「未操作現有資料庫」為當時紀錄；本次為修復使用者回報的本機錯誤，已備份並完成增量 Migration，未清空或重建資料庫，未 Commit／Push／部署。

## 本次階段 2～4：巡檢狀態管理（2026-11-09）

- 階段 2：新增 status 非空字串、預設 pending 的 Migration，隔離測試確認既有紀錄／照片保留；公開 PATCH 僅寫 status，四狀態任意切換，獨立每 IP 每分鐘 10 次限流。POST 拒絕自訂初始值，所有 Resource 回傳 status，GET 加入第四個 AND 篩選。
- 階段 3：共用狀態型別／文字／Bootstrap 顏色；列表下拉與桌面／手機 Badge，URL 保留狀態篩選及頁碼。詳細頁選擇即儲存，停用重送、成功提示、失敗還原及離頁取消；保留燈箱、刪除、返回與階段 1 分頁。
- 階段 4：新增後端 25、前端 8 個案例，完整 PHPUnit 183 tests／1,132 assertions、Node 前端 49 tests、Pint、TypeScript、正式 Build、git diff --check 全數通過。曾修正新增測試引用錯誤的鎖常數後重跑完整測試，未刪除或略過案例。
- 無已知未解決的狀態邏輯問題；HEIC 套件體積警告與實機驗收限制保留。未更新依賴或更改 Docker／照片流程，未操作現有／正式資料庫，未 Commit／Push／部署。
- 本次紀錄見[階段 2～4 歸檔](archive/report-status-stages-2-4-20261109.md)。

## 先前階段 1：巡檢列表分頁（2026-11-09）

- API 改用 `paginate(20)`，保留三條件 AND、created_at／id DESC、全部 Resource／照片欄位；驗證正整數 page 並隔離篩選欄位，增加 paginator meta。
- 列表顯示總筆數／頁數、前後頁與最多三個頁碼；44px 操作區域。URL 保存頁碼／篩選，篩選歸一、重新整理／歷史恢復、詳細頁／刪除返回、非法及超範圍頁碼修正，沿用取消請求與版本防護。
- 新增後端 15、前端 9 個測試案例；PHPUnit 158 tests／901 assertions、Node 前端 41 tests、Pint、TypeScript、正式 Build、git diff --check 全數通過。未安裝 Vitest，沿用既有測試架構。驗證細節與沙箱限制見[自動化測試](testing.md)。
- 該階段未新增狀態欄位、Migration、依賴或部署變更；未操作正式資料，未 Commit／Push／部署，也未開始階段 2。Build 仍有 HEIC 套件體積警告，無其他已知未解決的分頁邏輯問題。
- 本次紀錄見[階段 1 歸檔](archive/pagination-stage-1-20261109.md)。

## 先前文件整理

- README 改為作品入口，移除重複 API／部署細節與過時手機狀態。
- 技術、API、開發、部署、維運集中至 docs/；移除前後端框架範本 README，將 backend/ADMINISTRATION.md 整併至 operations.md。
- 原 PROJECT_STATUS.md 完整移至 archive/development-history.md；本文件保留現況摘要，AGENTS.md 同步進度文件位置，保留於根目錄供工具讀取。
- 文件連結、歷史本文完整性與 git diff --check 已檢查；功能說明已對照原始碼／Compose／Workflow。
- 文件整理未修改程式、Docker／CI、環境設定、DB 或正式伺服器，未 Commit／Push。
