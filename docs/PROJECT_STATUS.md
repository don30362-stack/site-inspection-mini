# 驗證現況與待辦

供維護者確認目前完成範圍與證據界線；作品介紹請從 [README](../README.md) 閱讀。逐次開發、決策及測試數字完整保留於[歷史歸檔](archive/development-history.md)，不將舊結果當作本次重跑。

## 目前功能與部署

- 已實作多照片巡檢新增、HEIC 自動轉換、拖曳／鍵盤排序、列表三條件 AND 篩選、詳細頁與燈箱、管理密碼刪除、全站 404。
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

## 本次文件整理

- README 改為作品入口，移除重複 API／部署細節與過時手機狀態。
- 技術、API、開發、部署、維運集中至 docs/；移除前後端框架範本 README，將 backend/ADMINISTRATION.md 整併至 operations.md。
- 原 PROJECT_STATUS.md 完整移至 archive/development-history.md；本文件保留現況摘要，AGENTS.md 同步進度文件位置，保留於根目錄供工具讀取。
- 文件連結、歷史本文完整性與 git diff --check 已檢查；功能說明已對照原始碼／Compose／Workflow。
- 文件整理未修改程式、Docker／CI、環境設定、DB 或正式伺服器，未 Commit／Push。
