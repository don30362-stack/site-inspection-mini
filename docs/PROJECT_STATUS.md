# 驗證現況與待辦

更新日期：2026-11-13（台灣時間）。本文件區分整合、自動化檢查與人工驗收；歷史保留於 [archive/](archive/development-history.md)。

## 已完成功能

- 手機新增巡檢，1～5 張照片、每張最多 20 MiB；照片預覽、更換、刪除、排序及 HEIC／HEIF 轉 JPEG。
- 列表依工地、問題類型、嚴重度、狀態及描述關鍵字做 AND 篩選；描述採字面包含搜尋，trim 後最多 100 個 Unicode code points。固定每頁 20 筆，以 created_at／id DESC 排序。
- 第一頁／最後一頁按鈕保留全部五個條件；邊界原生 disabled，分頁列可換行。搜尋／清除採 replace、切頁採 push，URL 支援重整、前後頁與詳細頁返回。
- 待處理、處理中、已完成、已審核四種狀態可切換；詳細頁自動儲存，並以 API 完整回傳紀錄更新最後更新時間，使用台灣時區與分鐘精度。空值顯示「未提供時間」。
- 詳細頁與照片燈箱、管理密碼刪除、容量鎖、限流、失敗清理及全站 404。欄位與限制見 [API](api.md)、[架構](architecture.md)與[維運](operations.md)。

## Codex 練習整合狀態

- fetch origin 後確認 integration/codex-practice、origin/main 與原基底皆為 e9fd1d428368e6c1fdea5d19c009040b00b2cee4，起始工作目錄乾淨。
- 已依序以 cherry-pick --no-commit 套用 3c2d05f、2d76738、0ddce87、5774d92、bdc60b5；本次依使用者明確授權收斂為一筆本機整合 Commit，訊息為 `feat: integrate codex practice improvements`。未 push、merge 或部署，也未操作正式資料。
- 只有 PROJECT_STATUS.md 在首尾頁與搜尋套用時發生預期衝突；逐段保留功能與歷史，未整份採用 ours／theirs。列表頁與分頁測試自動合併；沒有非預期衝突。
- 5774d92／bdc60b5 分別與 e89bef9／748f662 patch 相同，僅套用指定版本，沒有重複整合。
- 補強既有首尾頁測試：101 筆符合五條件，另五筆各僅違反一項；驗證第 3 → 6 → 1 頁及前後頁時的 URL、API params、keyword 草稿、metadata 與實際結果。描述含內部雙空白、特殊符號及 emoji。
- 保留照片預覽解碼失敗釋放 Object URL，以及拒絕稀疏照片索引且不持久化的兩項回歸測試；未新增套件、Migration 或部署設定。

## 本次驗證

- Laravel 完整測試：209 tests／1,255 assertions 通過；Pint --test 通過。
- 前端完整測試：59 tests 通過，無失敗、跳過或取消；TypeScript 型別檢查與正式 Build 通過。
- 整合 Code Review 已完成，未發現需修正缺陷；Review 回報後端 209 tests、前端 59 tests、型別檢查與正式 Build 通過，保留既有 HEIC chunk 警告。此為本次整合 Review 結果，不代表瀏覽器或正式環境驗收通過。
- 收尾僅更新文件與核對 17 個檔案及暫存內容，沒有修改功能或測試程式，未重跑完整自動化檢查；以上測試數沿用本次整合實測及 Review 證據。
- Git 暫存與 HEAD 差異格式檢查通過；無未解衝突。已核對 12 個無交集的來源檔案與原 Commit 一致，並檢查列表頁的兩組功能、補強測試、文件編碼與本機連結。
- Build 仍有既有 HEIC chunk 大於 500 kB 警告。測試範圍與指令見[測試文件](testing.md)。
- 後端使用 phpunit.xml 的記憶體 SQLite、fake Storage 與隔離資源；本輪未操作展示資料或正式資料，也未執行歷史 seed／cleanup 工具。

## GitHub 與部署歷史證據

| 項目 | 歷史結果 |
| --- | --- |
| [PR #1](https://github.com/don30362-stack/site-inspection-mini/pull/1) | 2026-11-10 合併至 main，commit 11fbc9eb6cc83c4a75cf3fd16628d5ba9b8fe61a。 |
| [PR CI #13](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42778258487) | Laravel／Vue 檢查成功；PR 不發布映像或部署。 |
| [main CI #14](https://github.com/don30362-stack/site-inspection-mini/actions/runs/42782086120) | Laravel、Vue、映像發布與 Lightsail 部署成功；status Migration 與 HTTPS 檢查成功。 |

以上沿用原文件的歷史證據，本輪未重新查核遠端 Actions 或正式容器 SHA；不能當成本次整合已上線的證據。[部署文件](deployment.md)說明核對方式。

## 人工驗收與部署前待辦

1. **瀏覽器整合驗收：待完成，依使用者決定延後。** 待桌面與手機檢查四條件＋關鍵字的首尾頁、邊界停用、換行、詳細返回、重整、前後頁、零結果與超範圍修正，以及搜尋／清除回第一頁；不宣稱已通過。
2. **手機原生 IME、弱網競態：待完成。** 待確認中文組字不誤送、弱網快速搜尋／切頁沒有過期結果；前端測試僅涵蓋 composable／memory Router，未掛載完整 DOM。
3. 狀態修改後核對 updated_at 與 API，跨分鐘驗證顯示；同分鐘顯示可能相同，初始建立與更新時間也可能一致。
4. **本次整合正式部署：待完成，尚未執行。** 未來合併 main 前需另行確認部署授權：main push 會自動測試、發布 SHA 映像並部署；PR 只測試。先核對最新 main、PR CI、上一版映像與一致性備份及還原方式。Workflow 不會自動 rollback。
5. 部署後核對實際容器 SHA、Migration、原資料與照片及完整功能；HTTP 200 不取代功能驗收。正式來源 IP／HTTPS 判斷、Caddy 憑證續期與備份還原仍需確認。
6. Android、低記憶體、大容量、HDR／色彩與大量資料搜尋效能仍未完成專項驗證；已知 unci HEIF 不支援。未來 Laravel 升級需重新對照 keyword trim 集合。

原功能各自的本機人工驗收與測試紀錄見[最後更新時間／首尾頁](archive/codex-practice-20261110.md)、[描述搜尋](archive/description-search-20261111.md)；既有開發歷史見[完整紀錄](archive/development-history.md)、[分頁](archive/pagination-stage-1-20261109.md)與[狀態管理](archive/report-status-stages-2-4-20261109.md)。原分支的驗收回報不代表本次整合已人工驗收。
