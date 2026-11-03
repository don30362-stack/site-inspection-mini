# 巡檢 API

說明現有 `/api/reports` 介面，供前端串接或以 HTTP 工具測試。格式與儲存設計見[架構文件](architecture.md)。

## Endpoint

| 方法 | 路徑 | 用途 | 成功狀態 |
| --- | --- | --- | --- |
| POST | `/api/reports` | 新增紀錄與照片 | 201 |
| GET | `/api/reports` | 列表與篩選 | 200 |
| GET | `/api/reports/{id}` | 單筆紀錄 | 200 |
| DELETE | `/api/reports/{id}` | 管理密碼刪除紀錄與照片 | 200 |

沒有登入流程。新增、讀取公開；刪除需要伺服器端雜湊驗證的管理密碼。正式站請使用 HTTPS。

## 新增

使用 `multipart/form-data`，並傳送 `Accept: application/json`。

| 欄位 | 規則 |
| --- | --- |
| site_name | 必填字串，最多 100 字 |
| issue_type | 系統櫃安裝、水電、裝修、安全防護、其他 |
| severity | low、medium、high；介面顯示低、中、高 |
| description | 必填字串，最多 1,000 字 |
| photos[] | 必填，1～5 張真正的 JPEG／PNG／GIF／WebP，每張最多 20 MiB |

文字去除頭尾空白，拒絕全空白。舊版 `photo` 欄位禁止使用。前端提供三個工地選項，但 API 的 site_name 是符合長度規則的字串，不限制為這三個工地。

HEIC／HEIF 必須由表單自動轉 JPEG 後提交；API 不接受未轉換的 HEIC、SVG 或偽裝圖片。每張上限為 20 × 1,024 × 1,024 bytes，PHP／Nginx POST 總上限為 120 MiB。

以下為本機範例，請替換照片路徑；不要為測試對正式站新增業務資料：

```powershell
curl.exe -H "Accept: application/json" -F "site_name=範例工地" -F "issue_type=安全防護" -F "severity=medium" -F "description=施工區護欄需修復" -F "photos[]=@C:/photos/example.jpg" http://127.0.0.1:8000/api/reports
```

## 篩選與排序

GET 列表接受可選參數 `site_name`、`issue_type`、`severity`，規則與新增對應欄位一致。空條件不篩選，多條件為完全相等的 AND 查詢。

結果依 created_at、id 由新到舊排列，目前不分頁；無符合資料時 data 為空陣列。前端把篩選保留於網址，從詳細頁可返回原條件。

## JSON 回應

固定外層結構如下；列表 data 為陣列，新增／單筆為物件，錯誤及刪除成功為 null：

```json
{
  "data": null,
  "message": "回應說明",
  "errors": {}
}
```

紀錄包含 `id`、`site_name`、`issue_type`、`severity`、`description`、`images`、`created_at`、`updated_at`。

`images` 依順序回傳 `{ id, image_url, sort_order }`，sort_order 從 0 起算；沒有舊版頂層 image_url／image_path。時間為 ISO 格式，介面轉為 Asia/Taipei。

## 刪除與錯誤

DELETE 的 JSON body 為 `{ "admin_password": "輸入的管理密碼" }`；不接受 URL query 密碼，不應將真正密碼寫在命令歷史、前端 Bundle 或 localStorage。設定方法見[管理與維運](operations.md)。

- 403：管理密碼錯誤。
- 404：紀錄不存在或已刪除。
- 409：照片容量鎖或同筆刪除正在處理。
- 422：欄位、圖片、張數、大小或照片總容量驗證失敗；errors 使用欄位名稱，單張照片為 photos.0 等。
- 429：POST 每來源 IP 每分鐘 10 次、DELETE 每分鐘 5 次嘗試；含驗證失敗，回 Retry-After。正式代理來源限制見[維運文件](operations.md#來源-ip-與代理)。
- 500：資料庫／Storage 等故障；可能需人工核對，不能直接當成未建立或未刪除。
- 503：刪除管理雜湊未正確設定。

Nginx 在 Laravel 前拒絕超過 120 MiB 的請求時回 413。網路中斷可能使結果不明，先查列表／詳細確認，不自動重送新增或刪除。
