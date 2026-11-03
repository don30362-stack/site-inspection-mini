# 管理與維運

說明管理密碼、照片容量與故障排查。API 欄位見 [API 規格](api.md)，發布操作見[部署文件](deployment.md)。

## 管理密碼

刪除使用共享管理密碼，沒有登入或角色系統。後端以 Hash::check 驗證伺服器雜湊；空白或無效設定會停用刪除並回 503。

原生開發於 backend/ 執行：

```powershell
php artisan reports:admin-password-hash
```

本機 Docker 於根目錄執行：

```powershell
docker compose run --rm --no-deps --entrypoint php php artisan reports:admin-password-hash
```

指令要求兩次隱藏輸入，至少 12 字元、最多 72 個 UTF-8 位元組，只輸出雜湊、不寫入環境檔。將輸出用單引號包住，填入對應私人檔案的 ADMIN_REPORT_PASSWORD_HASH：原生為 backend/.env、本機 Docker 為 docker/.env、正式為主機 app.env。不提交真密碼或雜湊，不放入 URL、前端環境變數、Bundle 或 localStorage。

原生環境變更後清除設定快取並重啟；容器環境需 recreate PHP 載入新環境，若使用 config:cache 再依流程重建快取。不要順便重新產生 APP_KEY。前端每次嘗試或關閉確認視窗都會清空密碼，失敗不直接重送。

## 容量與限流

- POST 每來源 IP 每分鐘 10 次新增嘗試，DELETE 每分鐘 5 次，含驗證失敗；GET 不受這兩項限制。超限回 429 與 Retry-After。
- 每筆 1～5 張、每張 20 MiB；PHP 20M／POST 120M／max_file_uploads 20，Nginx 120m。
- 照片總容量預設 2 GiB；只計 DB 關聯且實際存在的 public 照片，相同路徑只計一次。不含 private 備份、孤兒檔或其他專案，因此不是整台磁碟配額。

私人環境可設定 `REPORT_PHOTO_STORAGE_LIMIT_BYTES=2147483648`，接受非負整數 bytes；0 拒絕新增，等於上限可存，超過則整筆 422。刪除釋放空間後可再新增。設定變更的載入方式同上。

HTTP 需保持持久的 CACHE_STORE=file、RATE_LIMITER_STORE=file。正式 Compose 已固定為 file，勿改 array／null store，也不要在請求處理中清空 cache。

### 殘留容量鎖

新增、刪除與失敗清理共用永久 file lock；忙碌回 409，正常與例外都會釋放。若程序被強制終止，可能需人工解除。

**先停止並確認沒有新增／刪除請求，也沒有仍存活的 writer**，再於 backend/ 執行：

```powershell
php artisan cache:forget file-store-lock:report-photo-capacity file
```

Laravel 13 會加 `file-store-lock:` 前綴；只刪 `report-photo-capacity` 不會解除鎖。此指令適用目前 file store path 與 lock_path 相同的設定，只移除該鎖，不清除其他 cache。正式容器須透過已確認的 Compose 對 PHP 執行同一 Artisan 指令，勿操作其他專案的 cache。

## 刪除失敗與備份

刪除先串流備份至 private disk 的 report-deletions/，一般 DB／照片刪除失敗會回滾並嘗試還原。不移除其他紀錄仍引用的照片。

還原也失敗時保留 private 備份，回 500 並在受限 log 記錄排查路徑；若 DB 已提交但備份清理失敗，明確回報「紀錄已移除，但照片備份清理未完成」。不能將所有 500 當作未刪除，也不要反覆按刪除。先重新讀取紀錄，再核對 DB、public 檔案與私人備份。

不要刪除不明來源的備份、照片或 Volume。DB 與檔案系統沒有跨系統原子交易；備份存在不代表已完成整站還原演練。

## 來源 IP 與代理

原生 Vite proxy 會以 socket IP 覆寫 X-Forwarded-For 並移除 Forwarded。此模式可在 backend/.env 設 `TRUSTED_PROXIES=127.0.0.1,::1`；未設定時使用者會共用 loopback 配額。

Laravel 僅對明列可信 peer 接受 X-Forwarded-For，不信任 forwarded host／proto。可設定合法單一 IP 或 CIDR（IPv4 遮罩 1～32、IPv6 1～128），禁止 wildcard 或全網段信任。

**目前 Docker 的 Nginx 會清除 X-Forwarded-For，FastCGI 直接傳入 Nginx 的 socket peer；Compose 又固定 TRUSTED_PROXIES 為空。** Caddy 代理流量因而可能共用代理 IP 的限流額度；僅在 app.env 填 TRUSTED_PROXIES 不能修正這條鏈。

正式站使用 HTTPS 不代表 Laravel 已取得 HTTPS 協定資訊。目前 FastCGI 未設定來自可信 TLS 入口的 HTTPS 標記；圖片 URL 依 APP_URL 產生，須設為正式 HTTPS 網址。未來需另行驗證每一跳的來源 IP／協定清洗與信任邊界，不能直接接受外來 X-Forwarded-For／Proto，也不能以全信任代理解決。
