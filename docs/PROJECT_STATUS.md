# 驗證現況與待辦

更新日期：2026-11-11（台灣時間）。本文件區分程式完成、自動化檢查與正式環境驗收；歷史保留於 [archive/](archive/development-history.md)。

## 已完成功能

- 手機新增巡檢，1～5 張照片、每張最多 20 MiB；照片預覽、更換、刪除、排序及 HEIC／HEIF 轉 JPEG。
- 列表依工地、問題類型、嚴重度及狀態做 AND 篩選，並支援描述關鍵字字面包含搜尋（trim 後最多 100 個 Unicode code points）。Laravel 固定每頁 20 筆，以 created_at／id DESC 排序。URL 保留全部條件與頁碼；搜尋／清除採 replace、切頁採 push，支援詳細頁返回、重新整理及瀏覽器前後頁。搜尋已完成本機程式、自動化檢查及使用者回報的瀏覽器人工驗收，尚未部署。
- 待處理、處理中、已完成、已審核四種狀態可任意切換。新增與舊資料升級時預設 pending；詳細頁自動儲存，無登入、管理密碼、主管權限或修改歷程。
- 詳細頁與照片燈箱、管理密碼刪除、容量鎖、限流、失敗清理及全站 404。欄位與限制見 [API](api.md)、[架構](architecture.md)與[維運](operations.md)。

## GitHub 與部署證據

| 項目 | 已確認結果 |
| --- | --- |
| [PR #1](https://github.com/don30362-stack/site-inspection-mini/pull/1) | 2026-11-10 16:22:06 合併至 main；合併 commit 為 11fbc9eb6cc83c4a75cf3fd16628d5ba9b8fe61a。 |
| [PR CI #13](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42778258487) | success：Laravel／Vue 檢查通過；映像發布與部署依 PR 條件跳過。 |
| [main CI #14](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42782086120) | 2026-11-10 16:24:42 整體 success；Laravel、Vue、Publish Docker Images、Deploy to Lightsail 全成功。 |

[部署工作](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42782086120/job/129715127700)日誌確認容器健康，status Migration 於 16:24:35 執行 DONE，HTTPS 檢查步驟成功（依 Workflow 要求 /up、/api/reports 皆回 200）。這是部署流程證據；本次沒有登入正式主機獨立核對實際容器 SHA、資料庫內容或操作網站。後續核對方式見[部署文件](deployment.md)。

## 測試與驗收範圍

- 2026-11-11 主 Agent 整合後本機完整檢查：PHPUnit **208 tests／1,244 assertions**、Node.js 前端 **57 tests** 全通過；Pint、TypeScript、正式 Build、git diff --check 通過。仍有既有 HEIC 解碼套件大於 500 kB 的體積警告。分類與指令見[測試文件](testing.md)。
- A／B 分別實作後端及前端，C 只讀獨立複核；已修正其發現的長內部空白 trim 平方回溯及 SQLite LIKE 內部 NUL 錯配／漏配。主 Agent 逐一對照 1,112,064 個 Unicode scalar values，兩端相同 65 個 trim 字元、零差異。詳細紀錄見[搜尋階段](archive/description-search-20261111.md)。
- 使用者已確認描述關鍵字搜尋的本機瀏覽器人工驗收完成；Code Review 未發現 staged／unstaged／untracked 變更中的可修正缺陷，並確認 208 個後端測試、57 個前端測試與型別／Build 通過。此為使用者與 Review 的回報，不延伸為未提供證據的手機原生 IME 或正式環境驗收。本次未 push、merge、部署或修改正式資料。
- 人工驗收批次已清理：重新確認 APP_ENV=local、實際 database/database.sqlite 與原批次清單後，僅移除 ID 61～86 的 26 筆／26 張照片；cleanup 後再 verify，批次紀錄／照片關聯／照片檔案均為 0。總數回到原有 7 筆／16 張關聯，原始資料列、其他資料表與 17 個既有 public 檔案雜湊全部一致。私人工具、清單及備份受 Git 忽略，未納入提交；詳見[階段紀錄](archive/description-search-20261111.md#人工驗收確認與批次清理)。
- 先前使用者已回報 iPhone 核心新增、原始 HEIC 轉換、照片排序、燈箱與管理刪除通過；不包含本次新增分頁及狀態功能的正式實機驗收。
- 先前本機 Docker 隔離驗證包括五張各 20 MiB 上傳、增刪查及重建後持久資料保留；不代表正式 Caddy 全鏈路極限上傳通過。
- 2026-11-09 本機曾因漏套用 status Migration 回 500；已先私人備份再增量升級，核對 3 筆紀錄與 12 筆照片關聯保留，狀態測試 25 tests／231 assertions 通過；正式升級另以上述部署日誌為證。

## 仍需人工驗證

1. 手機原生中文 IME 事件順序與組字不誤送、手機實機版面及弱網快速搜尋／切頁仍未個別確認。使用者已回報瀏覽器驗收完成，但未提供逐項裝置／案例證據，不擴大宣稱這些專項已通過；前端自動化目前未掛載完整 DOM。
2. 獨立核對正式容器 SHA、Migration 與原資料保留；既有分頁、篩選、返回原頁、狀態切換及重整保留在正式網站及手機仍需個別驗收。既有本機瀏覽器已由使用者驗收通過，部署日誌成功不取代操作驗收；本次搜尋未部署。
3. 正式代理鏈的真實 IP 與 HTTPS 後端判斷；目前代理流量可能共用限流額度，見[來源 IP 與代理](operations.md#來源-ip-與代理)。
4. Android、弱網、低記憶體、大容量及 HDR／色彩專項；已知 unci HEIF 不支援。
5. Caddy 憑證續期、重建保留與完整備份還原演練；網站或 CI 成功不等於上述項目通過。未來 Laravel 升級亦須重新對照 keyword trim 集合。

開發紀錄見[完整歷史](archive/development-history.md)、[分頁](archive/pagination-stage-1-20261109.md)、[狀態管理](archive/report-status-stages-2-4-20261109.md)及[描述搜尋](archive/description-search-20261111.md)。
