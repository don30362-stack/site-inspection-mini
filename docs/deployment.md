# 部署與 CI/CD

說明目前的正式 Compose 與 Workflow，以及更新時必須保留的資料。這是操作參考，不代表每次發布或所有故障情境已驗證。

## 正式環境

正式網址：[https://site-inspection-mini.ddnsfree.com](https://site-inspection-mini.ddnsfree.com)。使用者回報部署於獨立 Lightsail，目錄 `/srv/site-inspection-mini`；此處只記部署路徑，不記密鑰或私人設定值。

主機上的檔案對應：

- [deploy/compose.production.yaml](../deploy/compose.production.yaml) → `compose.production.yaml`。
- [deploy/Caddyfile](../deploy/Caddyfile) → `Caddyfile`，唯讀 bind mount 至 Caddy。
- `app.env`：伺服器私有檔案，不由部署 Workflow 上傳或覆蓋。

Caddy 使用官方 `caddy:2` 並發布 TCP 80／443，透過 default 網路代理 `nginx:80`。Nginx／PHP 使用 GHCR Images，只有 Nginx 額外保留 `127.0.0.1:18082`。PHP 不發布連接埠。

Caddy、Nginx 與 PHP 使用 Compose 預設網路互相通訊。Caddy 透過 nginx:80 轉送請求，不需要額外的外部 Docker 網路。

## 持久資料與前置條件

固定 Compose project name 為 `site-inspection-mini`。五個 named volumes：

- `sqlite`：SQLite 與 sidecar 檔案。
- `storage`：Laravel Storage、private 刪除備份、file cache。
- `photos`：public 照片；Nginx 唯讀掛載。
- `caddy_data`、`caddy_config`：Caddy 憑證與設定狀態。

正式主機需已完成 Docker／Compose、DNS、80／443、防火牆、外部網路、app.env 與初次 Migration。APP_KEY 必須穩定，APP_URL 應為正式 HTTPS 網址；管理雜湊選填，見[管理與維運](operations.md)。不要將本機環境檔、DB 或照片包入 Image。

`app.env` 與正式設定可以是 root-owned；部署使用者 ubuntu 需能以 `sudo -n` 執行工作。root 必須可拉取 GHCR Images（Images 公開，或已有適當登入）。不要為修正權限使用 chmod 777 或公開密鑰。

## GitHub Actions 流程

來源：[.github/workflows/ci.yml](../.github/workflows/ci.yml)。

```text
Push main → backend + frontend → publish → deploy → HTTPS GET
Pull Request → backend + frontend
```

- backend：PHP 8.4、Composer、Laravel Tests、Pint。
- frontend：Node.js 24、npm ci、Tests、Type Check、Build。
- publish：兩項測試成功後，Build linux/amd64 PHP／Nginx Images 至 GHCR，同時標記 Commit SHA 與 latest。
- deploy：只在 main push 且 publish 成功後執行，使用當次 SHA，不能由 PR 部署。

Secrets 僅使用 `LIGHTSAIL_SSH_PRIVATE_KEY`、`LIGHTSAIL_SSH_KNOWN_HOSTS`；Runner 檔案權限為 600，強制 Host Key 驗證，不以 keyscan 取代事先核對。不要輸出完整環境、SSH 私鑰或包含秘密的 Compose config。

部署先檢查非互動 sudo，再 SCP 兩份設定到 ubuntu 家目錄的獨立暫存目錄。root 腳本依序驗證候選 Compose／Caddy、拉取 PHP／Nginx SHA Images、原檔寫回設定、`up -d --no-build --wait`、以 www-data 執行非互動 `migrate --force`，最後驗證並 reload Caddy。

Caddyfile 必須原地寫入，不能以新 inode 取代被掛載的檔案。GitHub concurrency 與遠端 flock 避免部署重疊；暫存於成功／失敗時清理，但完全斷線仍可能留下暫存，需核對再處理。

最後由 Runner 對 `/up`、`/api/reports` 發出驗證 TLS 的 HTTPS GET，要求 200；不輸出巡檢內容。這不是完整的照片／刪除功能驗收，也不證明本次 SHA 一定已由所有端點服務；仍需核對容器 Image。

## 更新後核對與失敗處理

以下只適用**已初始化的正式主機**，由具權限的操作者於部署目錄執行；本文件整理未執行遠端操作：

```sh
cd /srv/site-inspection-mini
sudo -n docker compose -p site-inspection-mini -f compose.production.yaml config --quiet
sudo -n docker compose -p site-inspection-mini -f compose.production.yaml ps
```

核對本次 SHA Images、服務健康、HTTPS、照片 URL 及原有資料。必要時查看受限日誌，不公開 request body、管理密碼或完整私人設定。

更新前保留上一版 Image Tag、Compose／Caddyfile 與受保護的資料備份。備份需包含 SQLite（含一致性處理）、照片、private 備份、APP_KEY／runtime、Caddy Volume；分別複製正在寫入的 DB 和照片，不等於一致備份或已驗證可還原。

Workflow 失敗會回報失敗，**不自動 rollback**。先確認服務／資料狀態；恢復上一版 Image 只適用資料結構相容的情況，Migration 已執行時不能假設降版安全。不得用舊 DB 覆蓋新資料，亦不要使用 fresh／refresh／rollback、down -v 或 Volume prune 當排錯步驟。

本機 Docker 操作另見[開發指南](development.md#本機-docker)。正式代理來源 IP 與 HTTPS 後端判斷的限制，見[維運文件](operations.md#來源-ip-與代理)。
