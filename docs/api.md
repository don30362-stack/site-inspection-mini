# 巡檢 API

說明現有 `/api/reports` 介面，供前端串接或以 HTTP 工具測試。格式與儲存設計見[架構文件](architecture.md)。

## Endpoint

| 方法 | 路徑 | 用途 | 成功狀態 |
| --- | --- | --- | --- |
| POST | `/api/reports` | 新增紀錄與照片 | 201 |
| GET | `/api/reports` | 列表與篩選 | 200 |
| GET | `/api/reports/{id}` | 單筆紀錄 | 200 |
| PATCH | `/api/reports/{id}/status` | 修改處理狀態 | 200 |
| DELETE | `/api/reports/{id}` | 管理密碼刪除紀錄與照片 | 200 |

沒有登入流程。新增、讀取及修改狀態公開；刪除需要伺服器端雜湊驗證的管理密碼。正式站請使用 HTTPS。

## 新增

使用 `multipart/form-data`，並傳送 `Accept: application/json`。

| 欄位 | 規則 |
| --- | --- |
| site_name | 必填字串，最多 100 字 |
| issue_type | 系統櫃安裝、水電、裝修、安全防護、其他 |
| severity | low、medium、high；介面顯示低、中、高 |
| description | 必填字串，最多 1,000 字 |
| photos[] | 必填，1～5 張真正的 JPEG／PNG／GIF／WebP，每張最多 20 MiB |

新增狀態固定為 `pending`；不得傳送 `status` 欄位，包含 null／空字串，否則回 422。

文字去除頭尾空白，拒絕全空白。舊版 `photo` 欄位禁止使用。前端提供三個工地選項，但 API 的 site_name 是符合長度規則的字串，不限制為這三個工地。

HEIC／HEIF 必須由表單自動轉 JPEG 後提交；API 不接受未轉換的 HEIC、SVG 或偽裝圖片。每張上限為 20 × 1,024 × 1,024 bytes，PHP／Nginx POST 總上限為 120 MiB。

以下為本機範例，請替換照片路徑；不要為測試對正式站新增業務資料：

```powershell
curl.exe -H "Accept: application/json" -F "site_name=範例工地" -F "issue_type=安全防護" -F "severity=medium" -F "description=施工區護欄需修復" -F "photos[]=@C:/photos/example.jpg" http://127.0.0.1:8000/api/reports
```

## 篩選、排序與分頁

GET 列表接受可選參數 `site_name`、`issue_type`、`severity`、`status`。前三者規則與新增對應欄位一致，status 限下表四個儲存值；非法值或型別回 422。空條件不篩選，多條件為完全相等的 AND 查詢。

列表使用 Laravel `paginate(20)`，固定每頁最多 20 筆，不支援自訂 `per_page`（傳入會忽略）。可選 `page` 必須為正整數，省略時為 1；空字串、0、負數、小數、非數字或陣列回 422，`errors.page` 提供驗證錯誤。

先套用四種 AND 篩選，再以 `created_at DESC`、`id DESC` 穩定排序並分頁。例如：`GET /api/reports?site_name=範例工地&severity=high&status=pending&page=2`。`page` 不作為資料庫篩選欄位。

成功回應的 `data` 維持紀錄陣列，所有紀錄及照片欄位不變；新增 `meta`，全部由 Laravel paginator 計算。沒有符合紀錄時：

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "last_page": 1,
    "total": 0,
    "from": null,
    "to": null
  },
  "message": "查詢成功。",
  "errors": {}
}
```

`total` 為篩選後總筆數；`from`／`to` 是當頁首尾紀錄的序號（不是 ID）。合法但超出總頁數的 page 仍回 200、空 `data`、請求的 `current_page`、實際 `last_page`／`total`，且 `from`／`to` 為 null。

前端以 URL 保存頁碼與篩選，第一頁省略 page；變更／清除篩選回第一頁，切頁保留條件，支援重新整理與瀏覽器前後頁。詳細頁返回與刪除成功皆沿用原 URL 條件。非法 URL 頁碼先改回第一頁；超範圍依 API 的 last_page 改至最後有效頁，零筆資料回第一頁顯示空資料畫面。每次只請求一頁，不在前端切割完整資料集。

## JSON 回應

基本外層結構如下；列表另外含上述 `meta`，data 為陣列；新增／單筆為物件，錯誤及刪除成功為 null：

```json
{
  "data": null,
  "message": "回應說明",
  "errors": {}
}
```

紀錄包含 `id`、`site_name`、`issue_type`、`severity`、`status`、`description`、`images`、`created_at`、`updated_at`。新增、列表、詳細及狀態更新的 ReportResource 均含 status。

`images` 依順序回傳 `{ id, image_url, sort_order }`，sort_order 從 0 起算；沒有舊版頂層 image_url／image_path。時間為 ISO 格式，介面轉為 Asia/Taipei。

## 處理狀態

| 儲存值 | 顯示文字 |
| --- | --- |
| pending | 待處理 |
| in_progress | 處理中 |
| completed | 已完成 |
| reviewed | 已審核 |

`PATCH /api/reports/{id}/status` 傳送 JSON，例如 `{ "status": "in_progress" }`。任何人都能修改，不需要登入或管理密碼。四種狀態可任意切換，沒有歷程或轉換順序限制。

只寫入經驗證的 status，其他欄位即使一併傳送也會忽略；更新 updated_at，保留 created_at、照片與其他欄位，不取得照片容量鎖。成功回 200，格式為 `{ "data": <更新後完整紀錄>, "message": "處理狀態已更新。", "errors": {} }`。缺少／非法 status 回 422；紀錄不存在回 404；每來源 IP 每分鐘 10 次嘗試，包含驗證失敗，超限回 429 與 Retry-After，額度跨紀錄共用並獨立於新增／刪除。

前端列表顯示狀態 Badge 與全部狀態篩選；選擇狀態篩選回第一頁，返回列表保留 status 與原頁碼。詳細頁選擇其他狀態即自動儲存，期間停用選單；成功顯示提示，失敗恢復前次已儲存選項並顯示錯誤，相同狀態不送出。網路／伺服器錯誤可能使結果不明，應重新載入確認。

## 刪除與錯誤

DELETE 的 JSON body 為 `{ "admin_password": "輸入的管理密碼" }`；不接受 URL query 密碼，不應將真正密碼寫在命令歷史、前端 Bundle 或 localStorage。設定方法見[管理與維運](operations.md)。

- 403：管理密碼錯誤。
- 404：紀錄不存在或已刪除。
- 409：照片容量鎖或同筆刪除正在處理。
- 422：欄位、圖片、張數、大小或照片總容量驗證失敗；errors 使用欄位名稱，單張照片為 photos.0 等。
- 429：POST 與 PATCH 各每來源 IP 每分鐘 10 次、DELETE 每分鐘 5 次嘗試；各自獨立，含驗證失敗，回 Retry-After。正式代理來源限制見[維運文件](operations.md#來源-ip-與代理)。
- 500：資料庫／Storage 等故障；可能需人工核對，不能直接當成未建立或未刪除。
- 503：刪除管理雜湊未正確設定。

Nginx 在 Laravel 前拒絕超過 120 MiB 的請求時回 413。網路中斷可能使結果不明，先查列表／詳細確認，不自動重送新增或刪除。
