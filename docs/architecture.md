# 架構與設計

說明前後端分工、照片保存方式，以及目前設計的適用範圍。安裝步驟見[開發指南](development.md)。

## 請求流程

```text
瀏覽器 Vue SPA
  → Caddy（正式環境 HTTPS）
  → Nginx（靜態檔、/storage、/api、/up）
  → PHP-FPM / Laravel
  → SQLite + Laravel Storage
```

本機開發改用 Vite 代理 `/api`、`/storage` 至 Laravel；本機 Docker 只有 Nginx 與 PHP，正式 Compose 才加入 Caddy。Node.js 僅在開發與 Build 階段使用。

前端採 Composition API、`<script setup>`、Vue Router 與 Axios，沒有 Pinia。後端以 Form Request 驗證、Controller 協調操作、Resource 統一輸出；查詢預載照片關聯，避免逐筆查照片。

## 資料與照片

- `reports`：id、site_name、issue_type、severity、description、created_at、updated_at。
- `report_images`：id、report_id、image_path、sort_order。外鍵 cascade delete，`report_id + sort_order` 唯一。
- 一筆 Report 有多張 ReportImage，依 sort_order 排列；最終結構沒有 `reports.image_path`。
- 圖片以系統 UUID 命名，存於 public disk 的 `reports/`。資料庫僅保存相對路徑，API 以 APP_URL 產生圖片 URL。

SQLite 保留 Laravel 骨架的預設資料表，但本專案未實作登入、會員或角色權限。容量與限流使用 file cache，不依賴 Redis。

## 前端照片流程

1. 讀取檔頭辨識 JPEG、PNG、GIF、WebP 或 HEIF，不以副檔名、accept 或 MIME 宣告作為唯一判斷。
2. 一般格式保留圖片內容；HEIF 才動態載入 `heic-to`，轉出 JPEG，確認大小及可預覽後建立 File。
3. 多張照片依序處理；替換失敗保留原圖，刪除、更換及卸載時釋放 Object URL。
4. 排序移動原照片物件，保留穩定 key、File 與 URL；FormData 的 `photos[]` 順序即為後端 sort_order。

拖曳以獨立手柄及 Pointer Events 實作，另有方向鍵替代操作。詳細頁使用原生 dialog 顯示照片，提供 2 倍切換、平移、鍵盤操作、焦點與背景捲動管理；未實作雙指縮放。

## 寫入一致性

新增在共用容量鎖內檢查用量，再以 DB transaction 建立紀錄與照片關聯；任一寫入失敗，回滾資料並嘗試清除本次所有照片，包括部分寫入的檔案。

刪除先驗證管理密碼，再於鎖內備份照片至 private disk。一般失敗會回滾並還原；還原失敗保留備份與錯誤，提交後備份清理失敗也回 500，不誤報成功。

DB 與檔案系統沒有共同原子交易。程序強制終止或底層持續故障，仍需管理者核對；操作方式見[管理與維運](operations.md)。

## 限制與相容性

- 單機 SQLite、共用 file cache lock 的設計，尚未驗證多主機部署；列表目前不分頁。
- iPhone 核心回報、原始 HEIC 轉換，以及照片拖曳排序、燈箱放大與管理密碼刪除，均已有使用者實機驗收通過；不擴大為 Android 或極端情境也已驗證。
- `unci` HEIF 不支援；Android、HDR／色彩專項、大容量、低記憶體與弱網情境未完整驗證。
- 正式代理鏈的真實 IP 尚待確認；不能把程式按 IP 限流的設計，等同於正式站已正確區分每位使用者。見[代理設定限制](operations.md#來源-ip-與代理)。

## 授權與 AI 使用

`heic-to` 固定版本為 1.6.7，授權 LGPL-3.0；授權與來源資訊隨前端 Build 提供於 `/licenses/`，原始檔位於 [frontend/public/licenses](../frontend/public/licenses)。首次載入解碼器較大，不宣稱所有 HEIF 編碼都可轉換。[套件來源](https://github.com/hoppergee/heic-to)

開發過程使用 Codex 協助程式實作、測試與文件整理。功能與驗證結果依實際原始碼、工具輸出及使用者回報記錄；未將型別檢查或桌面模擬當成手機實機驗收。詳細證據見[驗證現況](PROJECT_STATUS.md)。
