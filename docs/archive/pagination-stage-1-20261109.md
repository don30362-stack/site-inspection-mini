# 階段 1：巡檢列表分頁 — 2026-11-09

- 後端 GET /api/reports 改用 Laravel paginate(20)，固定 20 筆，保留 AND 篩選與 created_at／id DESC；page 正整數驗證，僅允許既有三欄位進入 where。data 保持完整 Resource 陣列，meta 使用 paginator 原始計算值。
- 前端 URL 保存頁碼／篩選，以 useReportList 整合 Router 與既有 useLatestRequest。篩選歸一、歷史恢復、詳細／刪除返回、非法及超範圍頁碼修正均有邏輯測試。Bootstrap 分頁最多三個頁碼，觸控控制至少 44px。
- 新增後端 15、前端 9 案例。實跑 PHPUnit 158 tests／901 assertions、Node.js 前端 41 tests、Pint --test、TypeScript、正式 Build、git diff --check 通過。未加入 Vitest，沿用既有機制。Build 的 HEIC chunk 體積警告保留。
- 本機 Composer autoload 經 dump-autoload --no-scripts 重建，未更新依賴；Windows 沙箱的 is_readable 判定阻擋 PHPUnit，沙箱外以原 phpunit.xml 隔離設定完成測試。
- 未進行瀏覽器／手機實測、正式部署或正式資料操作；未加入狀態管理、狀態欄位、Migration，未 Commit／Push。後續可先以隔離測試資料驗收手機分頁，再由使用者決定提交與後續階段。
