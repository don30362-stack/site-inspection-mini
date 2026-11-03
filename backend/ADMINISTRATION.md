# 刪除密碼與來源 IP 設定

此功能以共享管理密碼保護 `DELETE /api/reports/{id}`，沒有使用者／角色系統。前端以 JSON body 的 `admin_password` 傳送密碼；部署時使用 HTTPS，不將密碼放在 URL、前端環境變數或 localStorage。

在 backend/ 執行 `php artisan reports:admin-password-hash`，互動輸入至少 12 字元、最多 72 個 UTF-8 位元組的密碼兩次（避免 bcrypt 超長截斷）。輸入不回顯，指令只輸出安全雜湊，不自動修改設定。將輸出貼入未提交的 backend/.env：

```dotenv
ADMIN_REPORT_PASSWORD_HASH='貼上指令輸出的雜湊'
RATE_LIMITER_STORE=file
```

空值或不合法雜湊會停用刪除並回 503。不要提交真正密碼、雜湊或 .env。變更後執行 `php artisan config:clear`，重新啟動 Laravel；正式環境若使用 config:cache，應重新建立設定快取。密碼比對保留頭尾空白；前端每次嘗試或關閉確認視窗後清空密碼。

POST 每來源 IP 每分鐘最多 10 次嘗試（含驗證失敗）；DELETE 每來源 IP 每分鐘最多 5 次嘗試，跨紀錄共用額度；GET 不受這兩個限制影響。429 有中文訊息與 Retry-After。單機使用持久 file limiter cache，不需 Redis；不要在執行 HTTP 服務時改成 array／null store。

## 反向代理

預設 `TRUSTED_PROXIES` 空白，忽略外來 X-Forwarded-For。使用目前 Vite proxy 時，在 backend/.env 明確設定：

```dotenv
TRUSTED_PROXIES=127.0.0.1,::1
```

Vite 對 /api 覆寫 X-Forwarded-For 為連入 socket 的 IP，移除 Forwarded，不沿用使用者提供的來源宣稱。Laravel 只對可信 socket peer 接受 X-Forwarded-For，不信任 forwarded host／proto。未設定可信代理時，Vite 的使用者會共用 loopback IP 配額。

未來單層反向代理亦須覆寫 X-Forwarded-For（例如 Nginx `proxy_set_header X-Forwarded-For $remote_addr;`），只讓該代理連入 Laravel，TRUSTED_PROXIES 只列該代理實際 IP／必要的受限 CIDR。不可設定 `*`、`REMOTE_ADDR`、0.0.0.0/0 或 ::/0。多層代理須另外確認每一跳及邊界清洗，不能直接套用此單層假設；本次未部署或連線任何雲端主機。

## 刪除失敗處理

刪除前將需要清理的照片串流備份至 private disk 的 report-deletions/，一般照片刪除或資料庫失敗會回滾資料、還原照片；其他紀錄引用的照片不刪除。同筆刪除以 cache lock 避免並行操作。成功須完成紀錄、public 照片及暫存備份清理。

檔案系統與 SQLite 無法共用原子交易。若回復失敗，private 備份保留，Laravel log 記錄可供排查的路徑；若紀錄已刪除但備份清理失敗，API 明確回 500 說明，不能當成成功。程序強制終止／儲存裝置無法寫入等情況需要管理者依 log 與 private 備份核對恢復；不要盲目重送刪除。前端遇到網路中斷也不自動重試，應先重新載入確認。
