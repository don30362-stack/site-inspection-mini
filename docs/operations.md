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

### Lightsail：首次設定或更換密碼

以下全部在 **Lightsail 正式主機的 SSH 終端機**操作，不是在本機 PowerShell、GitHub Actions 或容器內操作。請先以 ubuntu 登入主機，避開使用者上傳；重建 PHP 時會短暫中斷 API。不要錄影或分享終端畫面。

請按順序執行，不要把整份教學一次貼上。Hash 是後端用來驗證密碼的雜湊值，不是要輸入網站的密碼；網站仍輸入你自己設定的原始密碼。

**1. 取得管理員權限。** 在 ubuntu 的 SSH 終端機，複製下面這一行並按 Enter：

```sh
sudo -n bash
```

預期提示字元改為類似 `root@主機名稱:...#`，表示進入管理員的 Bash 命令環境。`sudo -n` 不會要求密碼；若出現權限錯誤或仍停在 ubuntu，停止操作，先請主機管理者確認權限。

**2. 鎖定目前版本並產生 Hash。** 在剛才的 **同一個 root 終端機**，完整複製以下整段；最後會停下來等你輸入密碼：

```sh
set -euo pipefail
cd /srv/site-inspection-mini
exec 9>.deploy.lock
flock -w 600 9
compose=(docker compose --project-name site-inspection-mini -f compose.production.yaml)
php_container=$("${compose[@]}" ps -q php)
test -n "$php_container"
php_image=$(docker inspect --format '{{.Config.Image}}' "$php_container")
[[ "$php_image" =~ ^ghcr\.io/don30362-stack/site-inspection-mini-php:([0-9a-f]{40})$ ]]
export SITE_IMAGE_TAG="${BASH_REMATCH[1]}"
"${compose[@]}" exec --user www-data php php artisan reports:admin-password-hash
```

這段指令會進入部署目錄、取得與自動部署共用的鎖，再記住目前 PHP 的 Commit SHA（40 字元的程式版本識別碼）。鎖可避免你修改密碼時，自動部署同時變更容器；若已有部署進行，最多等待 600 秒。`compose` 是後續 Docker 指令的簡寫，只在這個終端機有效。

依提示輸入新密碼，再輸入一次確認。輸入時不顯示字元是正常的；完成後會顯示一行通常以 `$2y$` 開頭的 Hash。**只複製這一行 Hash**，供下一步貼入設定檔，不要複製提示文字。

預期產生 Hash 後回到 root 提示字元。若密碼不符、長度不符、找不到容器、等鎖逾時，或版本不是完整 SHA，立即停止；不要猜 SHA 或改用 latest。任何步驟出錯、回到 ubuntu 或 SSH 斷線時，都不要繼續貼後面的指令，排除原因後再從步驟 1 開始。不要自行加 `-T` 或 `--no-interaction`，以免無法互動輸入密碼。

**3. 用 nano 更新私人設定。** 仍在 **同一個 root 終端機**，複製這一行，開啟主機原有的設定檔：

```sh
nano /srv/site-inspection-mini/app.env
```

預期 nano 顯示原有的多行設定。若顯示新檔案／空檔，按 Ctrl+X 退出；若詢問是否儲存，按 N 不儲存，並停止操作。看到原有設定後才繼續（`Ctrl` 表示按住 Ctrl 再按指定字母）：

1. 按 **Ctrl+W**，輸入 `ADMIN_REPORT_PASSWORD_HASH`，按 Enter 搜尋。
2. 用方向鍵移到該行，只替換等號右邊的值，貼上剛才的完整 Hash，外面加一對英文單引號。若找不到此設定，移至檔案末尾新增一行；不要留下重複的同名設定。
3. 按 **Ctrl+O** 儲存，確認底部檔名仍是 `/srv/site-inspection-mini/app.env`，再按 Enter。
4. 看到寫入完成訊息後，按 **Ctrl+X** 退出 nano，回到 root 提示字元。

完成後該行的格式如下；**這只是示意，必須換成你剛產生的 Hash**：

```dotenv
ADMIN_REPORT_PASSWORD_HASH='貼上剛產生的完整 Hash'
```

保留 Hash 內所有 `$` 字元，不要改 APP_KEY 或其他設定。不要用 echo 把 Hash 寫進命令歷史。退出 nano 後，在同一終端機複製這一行，讓設定檔只允許 root 讀寫：

```sh
chmod 600 /srv/site-inspection-mini/app.env
```

預期 nano 已成功寫入並退出，chmod 沒有錯誤訊息。若 nano 無法啟動、檔案顯示為全新的空檔、儲存路徑不對或寫入失敗，停止，不要進入下一步，也不要用 chmod 777 解決權限問題。

**4. 只重建 PHP，讓新設定生效。** 在 **同一個 root 終端機**，完整複製以下整段；它會使用步驟 2 記住的 SHA，不會改用 latest：

```sh
"${compose[@]}" config --quiet
"${compose[@]}" up -d --no-deps --force-recreate --no-build --pull never --wait --wait-timeout 180 php
"${compose[@]}" exec -T nginx nginx -t
"${compose[@]}" exec -T nginx nginx -s reload
curl --fail --silent --show-error --output /dev/null https://site-inspection-mini.ddnsfree.com/up
```

預期 Compose 完成 PHP 重建及健康檢查；Nginx 顯示設定檢查成功，reload 後最後的 curl 沒有錯誤並回到提示字元。`config --quiet` 與成功的 curl 不顯示內容是正常的。若出現 unhealthy、逾時、設定錯誤或 HTTP 錯誤，停止並請管理者檢查，不要反覆重建。

PHP 容器是執行後端程式的環境，這次只重建它以重新讀取 app.env；不重新 Build 或 Pull Image，也不重建 Nginx／Caddy。Nginx reload 是重新載入設定，讓它找到重建後的 PHP 位址。資料保存在容器外的 named volumes，沿用原專案名稱與掛載，SQLite、照片、private 備份、file cache 及 Caddy 憑證都會保留；不要使用 down -v 或刪除 Volume。

目前 Image 未預建 config:cache，bootstrap/cache 也未持久化，因此不用另外清設定快取或執行 Migration。

**5. 結束管理操作並驗證密碼。** 步驟 4 成功後，在 root 終端機複製這一行：

```sh
exit
```

預期回到 ubuntu 提示字元，並釋放步驟 2 的鎖。接著在瀏覽器開啟正式網站，以可捨棄的測試紀錄驗證：使用**新設定的原始密碼**，不是 Hash，應能完成管理刪除。健康檢查成功只代表服務可用，不代表密碼一定填寫正確；若驗證失敗，停止並核對設定，不要對重要紀錄反覆試刪。

### 日常部署

日常 Push 不需重新產生管理密碼。Workflow 只同步 Compose／Caddyfile，保留主機 app.env，再以本次 Commit SHA 部署；既有管理雜湊會由 PHP 容器重新載入。上方手動流程僅供首次設定或更換密碼，不取代日常部署。

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
