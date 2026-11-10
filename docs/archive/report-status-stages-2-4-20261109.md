# 階段 2～4：巡檢狀態管理 — 2026-11-09

- 保留階段 1 的全部未提交分頁修改；僅擴充 status 篩選與對應測試，未重寫分頁。
- 新增 Migration 加入 reports.status（非空字串、預設 pending）；舊紀錄與圖片關聯保留。Migration 只在隔離測試資料庫執行，未套用至現有開發或正式資料庫，未修改舊 Migration。
- 公開 PATCH /api/reports/{id}/status，只修改 status 與 updated_at，四種狀態自由切換；獨立每 IP 每分鐘 10 次限流。新增固定 pending 且拒絕傳入 status，GET 四條件 AND 篩選與既有 20 筆分頁並用，全部 ReportResource 包含 status。
- Vue 共用狀態定義與 Badge，詳細頁原生選單自動儲存、停用重送、失敗還原、離頁取消。保留列表頁碼／篩選／返回、照片、燈箱與刪除。
- 新增後端 25、前端 8 案例；實跑完整 PHPUnit 183 tests／1,132 assertions、Node 前端 49 tests、Pint --test、TypeScript、正式 Build、git diff --check 全數通過。新增測試的 LOCK_NAME 誤植已修為既有 LOCK_KEY，修正後重跑完整 PHPUnit。沙箱外使用既有隔離設定測試，未略過測試。
- HEIC 解碼套件大體積警告保留；本次沒有真實瀏覽器／手機 DOM、部署或正式鏈路驗收。未加入套件、登入、角色、流程限制或歷程；未改照片／部署架構，未 Commit／Push／部署。
