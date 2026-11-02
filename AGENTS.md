# Site Inspection Mini — Codex 工作規則

## 專案與範圍
- 目標：完成工地巡檢回報面試作業，2026-11-04 晚上前交件；優先確保核心流程可操作。
- 前端：Vue 3、TypeScript、Composition API、`<script setup>`、Vite、Bootstrap 5、Axios。
- 後端：Laravel 13 REST API、SQLite、Laravel Storage；盡量沿用現有架構。
- 核心功能：手機新增巡檢（工地、類型、嚴重度、照片、描述）；桌機列表篩選與詳細頁。
- 不主動加入登入、Pinia、Docker、AI 辨識或其他非必要功能。

## 工作方式
- 修改前先閱讀相關現有檔案，避免猜測結構、覆蓋既有實作。
- 一次只完成目前指定階段；以小範圍、易理解的修改為主，不做無關重構。
- 後端負責驗證輸入與圖片格式／大小；圖片存 Laravel Storage，資料庫只存路徑。
- 重要行為要測試：Laravel Feature Tests、前端型別檢查與 Build；不能執行的測試要說明原因。
- 每階段完成後更新 `PROJECT_STATUS.md`（完成項目、驗證結果、待處理問題），不要把進度寫入 `README.md`。
- `README.md` 僅寫對外專案介紹、實際功能、安裝啟動、技術選型、測試及 AI 使用說明，勿宣稱未完成的功能已完成。
- 回覆精簡列出變更檔案、測試結果、問題與建議下一步。

## Git 與安全
- **不要執行 Git Commit 或 Push**；只回報 `git status`，由使用者自行提交。
- 不提交 `.env`、金鑰、密碼、真實個資、資料庫檔案或非公開照片。
