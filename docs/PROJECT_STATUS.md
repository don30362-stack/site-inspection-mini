# 驗證現況與待辦

更新日期：2026-11-10（台灣時間）。本文件區分程式完成、自動化檢查與正式環境驗收；歷史保留於 [archive/](archive/development-history.md)。

## 已完成功能

- 手機新增巡檢，1～5 張照片、每張最多 20 MiB；照片預覽、更換、刪除、排序及 HEIC／HEIF 轉 JPEG。
- 列表依工地、問題類型、嚴重度及狀態做 AND 篩選，Laravel 固定每頁 20 筆，以 created_at／id DESC 排序。URL 保留條件與頁碼，支援詳細頁返回、重新整理及瀏覽器前後頁。
- 待處理、處理中、已完成、已審核四種狀態可任意切換。新增與舊資料升級時預設 pending；詳細頁自動儲存，無登入、管理密碼、主管權限或修改歷程。
- 詳細頁與照片燈箱、管理密碼刪除、容量鎖、限流、失敗清理及全站 404。欄位與限制見 [API](api.md)、[架構](architecture.md)與[維運](operations.md)。

## GitHub 與部署證據

| 項目 | 已確認結果 |
| --- | --- |
| [PR #1](https://github.com/don30362-stack/site-inspection-mini/pull/1) | 2026-11-10 16:22:06 合併至 main；合併 commit 為 11fbc9eb6cc83c4a75cf3fd16628d5ba9b8fe61a。 |
| [PR CI #13](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42778258487) | success：Laravel／Vue 檢查通過；映像發布與部署依 PR 條件跳過。 |
| [main CI #14](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42782086120) | 2026-11-10 16:24:42 整體 success；Laravel、Vue、Publish Docker Images、Deploy to Lightsail 全成功。 |

本機目前位於 feature/report-status 的 7fc9215；查核開始時工作目錄乾淨。GitHub 比對顯示 PR head 與合併 commit 無檔案內容差異。本次只更新文件，未執行 Commit、Push、Merge、部署或資料庫操作。

[部署工作](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42782086120/job/129715127700)日誌確認容器健康，status Migration 於 16:24:35 執行 DONE，HTTPS 檢查步驟成功（依 Workflow 要求 /up、/api/reports 皆回 200）。這是部署流程證據；本次沒有登入正式主機獨立核對實際容器 SHA、資料庫內容或操作網站。後續核對方式見[部署文件](deployment.md)。

## 測試與驗收範圍

- 2026-11-10 PR CI 日誌確認：PHPUnit **183 tests／1,132 assertions**、Node.js 前端 **49 tests** 全通過；Pint、TypeScript、正式 Build 通過。仍有 HEIC 解碼套件大於 500 kB 的體積警告。分類與證據連結見[測試文件](testing.md)。
- 本次文件整理未重跑程式測試；只查核 GitHub 結果、程式與文件，並檢查文件連結及 git diff --check。
- 先前使用者已回報 iPhone 核心新增、原始 HEIC 轉換、照片排序、燈箱與管理刪除通過；不包含本次新增分頁及狀態功能的正式實機驗收。
- 先前本機 Docker 隔離驗證包括五張各 20 MiB 上傳、增刪查及重建後持久資料保留；不代表正式 Caddy 全鏈路極限上傳通過。
- 2026-11-09 本機曾因漏套用 status Migration 回 500；已先私人備份再增量升級，核對 3 筆紀錄與 12 筆照片關聯保留，狀態測試 25 tests／231 assertions 通過；正式升級另以上述部署日誌為證。

## 仍需人工驗證

1. 獨立核對正式容器 SHA、Migration 與原資料保留；以及分頁、四條件篩選、狀態更新、返回原頁的瀏覽器／手機驗收。部署日誌成功不取代操作驗收。
2. 正式代理鏈的真實 IP 與 HTTPS 後端判斷；目前代理流量可能共用限流額度，見[來源 IP 與代理](operations.md#來源-ip-與代理)。
3. Android、弱網、低記憶體、大容量及 HDR／色彩專項；已知 unci HEIF 不支援。
4. Caddy 憑證續期、重建保留與完整備份還原演練；網站或 CI 成功不等於上述項目通過。

既有開發紀錄見[完整歷史](archive/development-history.md)、[分頁](archive/pagination-stage-1-20261109.md)及[狀態管理](archive/report-status-stages-2-4-20261109.md)；本次不重寫歸檔。
