# API 接口参考

本文是项目的**完整 API 接口总览**，面向前端、集成脚本、测试人员和运维人员。旧版 [`api-management.md`](api-management.md) 只保留管理端专项说明；若两份文档存在范围或措辞差异，以本文的当前源码核验结果为准。

> **核验状态**：本文档只记录当前代码中已经注册并可由控制器、模型/迁移和 Feature 测试核对的接口。已确认内容与部署后才能确定的事项分开说明；域名、HTTPS、Session Cookie 最终名称、代理、缓存、CORS、邮件和文件存储等运行时行为需部署后核验。没有在源码和测试证据中出现的 Bearer Token、Sanctum、JWT、OAuth 或 OpenAPI 端点不属于本文档。

事实来源为 `routes/api.php`、`routes/web.php`、对应控制器、模型/迁移中的字段约束，以及 `tests/Feature` 中的行为断言。没有在这些来源中出现的接口不属于本文档。

## 1. 基础约定

### 1.1 Base URL 与路由分类

- 应用根地址由 `APP_URL` 决定，以下路径均为同源相对路径。
- `routes/api.php` 由 `bootstrap/app.php` 的 `withRouting(api: ...)` 注册，Laravel 默认 API 前缀为 `/api`。因此该文件中的 `/messages` 实际地址是 `/api/messages`，不是 `/messages`。
- `routes/api.php` 中的路由使用显式 `web` 中间件；状态变更请求走 Session 与 CSRF，而不是无状态 Token。
- `routes/web.php` 中的同名能力是 Web 路由。控制器通过 `Request::expectsJson()` 判断是否返回 JSON；发送 `Accept: application/json`（或 Laravel 判定为 JSON/AJAX 请求）时才进入 JSON 分支，未发送时通常返回页面或重定向。
- `/api/...` 请求本身满足控制器的 `is('api/*')` 判断，即使没有 `Accept: application/json`，也会使用 JSON 响应分支。
- 本项目没有在源码中实现 Bearer Token、Laravel Sanctum、JWT 或 OAuth 认证；不要给请求添加不存在的 `Authorization: Bearer ...`。
- 本文中的 `{id}`、`{conversation}`、`{group}`、`{user}`、`{category}`、`{novel}`、`{chapter}`、`{submission}` 为路由参数。找不到模型通常返回 404；越权访问已存在资源通常返回 403。

### 1.2 Session Cookie 与 CSRF

认证依赖 Laravel 的浏览器 Session：登录后服务端通过 Session Cookie 识别用户。Session Cookie 名称来自 `SESSION_COOKIE`，未配置时由 `APP_NAME` 生成；默认 Session 驱动为 `database`，有效期默认 120 分钟，具体值受环境配置影响。

`routes/api.php` 明确加入 `web` 中间件，因此以下 API 写操作需要有效的 CSRF Token：

- `POST`、`PUT`、`PATCH`、`DELETE` 请求发送 `_token` 表单/JSON 字段，或发送 `X-CSRF-TOKEN` 请求头。
- 同源浏览器通常先取得 Laravel CSRF Cookie/Session，再提交 Token。Feature 测试使用 Session `_token` 与 `X-CSRF-TOKEN` 或 JSON `_token`。
- CSRF 失败通常是 419。GET 查询和 SSE 连接不改变 CSRF 状态，但仍需携带认证 Session（若该路由要求登录）。

### 1.3 JSON、分页和通用错误

除非条目特别注明，JSON 请求建议使用 `Content-Type: application/json`，响应为 `application/json`。Laravel 验证失败通常为 422，结构为：

```json
{
  "message": "验证失败。",
  "errors": {
    "field": ["具体错误信息"]
  }
}
```

分页接口使用 Laravel `LengthAwarePaginator` 的 JSON 结构：顶层包含 `data` 与 `current_page`、`last_page`、`per_page`、`total` 等分页元数据。查询参数使用 `page`；代码只读取 `config('yuejing.pagination')` 作为每页数量，没有实现客户端 `per_page` 参数。

常见状态码：

| 状态码 | 含义 |
|---|---|
| 200 | 查询或操作成功 |
| 201 | 创建资源成功 |
| 202 | 登录已验证密码，但等待二步验证码 |
| 401 | 未认证访问需要登录的 JSON/API 路由 |
| 403 | 角色、资源所有权、群成员资格或签名校验失败 |
| 404 | 路由模型或公开资源不存在；未发布作品也按不存在处理 |
| 409 | 当前业务状态冲突，例如重复评分或重复审核 |
| 419 | CSRF Token 失效，或二步挑战过期时由控制器返回的过期状态 |
| 422 | 字段验证失败或业务输入不合法 |
| 429 | 登录、注册、二步挑战等节流，或二步验证码尝试次数耗尽 |

除明确标注为幂等的接口外，不应假定 POST 会自动去重。接口未实现 `Idempotency-Key`。

## 2. 公开内容与推荐

### 2.1 推荐

| 方法与路径 | 权限/中间件 | 请求参数 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /api/recommendations` | 公开；`web` | 查询 `limit`，默认 6，实际强制限制为 1 至 12 | `200`：`{"data":[{"id","title","slug","author","categories", "cover_url","views_count"}],"generated_at":"ISO-8601","next_poll_after":null}`。无请求体；推荐结果按当前 Session/用户兴趣计算。查询是只读的，没有写入幂等性问题。 |
| `GET /api/recommendations/stream` | 公开；`web` | 查询 `limit`，默认 6，限制为 1 至 12 | `200`，`Content-Type: text/event-stream`，发送 `retry: 60000`，随后发送 `event: recommendations`；事件 `data` 是与普通推荐相同的对象，但 `next_poll_after` 为 `60`。这是一次生成后结束的 SSE 响应，不是持续推送；只读。 |

推荐结果中的 `categories` 是分类名称数组；`generated_at` 为生成时间。Feature 测试确认普通推荐使用 JSON 轮询，主页并不依赖一次性 `EventSource`。

### 2.2 公开小说、章节和评分数据 JSON

以下是 Web 路由的 JSON 变体，不是 `routes/api.php` 中新增的同路径 API。必须使用 `Accept: application/json`（或 JSON/AJAX 请求）；否则返回 Blade 页面。

| 方法与路径 | 权限/中间件 | 请求参数 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /` | 公开；`web` | `q`、`genre`、`sort` 会影响小说分页；`sort=hot` 按浏览量，否则按发布时间 | `200` JSON：小说分页对象。每个 `data` 项至少包含 `id,title,slug,author,genre,desc,description,chapters,chapters_count,status,cover_url,cover_a,cover_b` 及统计字段。无 JSON 时返回主页 HTML；只读。 |
| `GET /novels` | 公开；`web` | `q`、`genre`、`sort`；搜索词和分类输入在查询中截断到 160 字符；`page` 分页 | `200` JSON：发布中小说分页对象；同时记录搜索兴趣事件（有 `q` 或 `genre` 时）。无 JSON 时返回小说列表 HTML；只读，但搜索会产生内部兴趣记录。 |
| `GET /novels/{novel:slug}` | 公开；`web` | 路径使用 `slug`；无请求体 | `200` JSON：小说摘要、统计、`active_ratings`（最多 20 条）和 `statistics`。包括 `views_count`、`published_chapters_count`、`favorites_count`、`reviews_count`、`word_count`、`last_updated_at`。JSON 分支不会增加浏览量；HTML 分支会增加浏览量。未发布/不存在为 404；只读。 |
| `GET /novels/{novel:slug}/chapters/{chapter:chapter_number}` | 公开；`web`、`scopeBindings`、章节数字约束 | `chapter` 必须为数字；路径中的小说使用 slug | `200` JSON：`{"chapter": <Chapter>, "reading_record": <ReadingRecord|null>}`。已登录用户每次阅读都会 `updateOrCreate` 该小说的阅读记录并置为当前章节、进度 100；未登录为 `null`。小说或章节未发布、章节不属于小说为 404。 |
| `GET /chapters/{novel:slug}/{chapter:chapter_number}` | 与上一个章节路由相同 | 同上 | 与上一个接口完全相同，是另一个 Web 路径别名；已登录阅读记录更新，因此对同一用户/小说重复请求是更新型幂等操作。 |
| `GET /api/novels/{novel:slug}/reviews` | 公开；`web` | `slug`；无请求体 | `200`：`{"statistics":{"average_rating","average_rating_level","rating_count",...},"reviews":[{"id","rating","level","review","criteria","user","created_at"}]}`。`reviews` 数组中的每项是评分记录，`review` 只是该评分附带的可选文字；源码没有独立评论、回复或评论审核接口。只返回已发布小说的有效评分，最多 20 条；未发布/不存在为 404；只读。 |

公开小说分页的 `data` 只包含 `status=published` 的作品。评分数据接口和小说详情均排除已经撤回的评分；Feature 测试确认撤回后聚合数量会下降。

## 3. 认证与账户状态

### 3.1 注册、登录、登出和当前用户

`/auth/...` 与不带 `/auth` 的登录/注册 Web 路径均来自 `routes/web.php`。对于 POST 请求，使用 `Accept: application/json` 可取得以下 JSON；不使用时多数返回页面重定向。

| 方法与路径 | 权限/中间件 | 请求字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `POST /auth/register` | 公开；`throttle:register` | `name` 必填字符串最大 100；`email` 必填邮箱且唯一；`password` 必填且需 `password_confirmation`，使用 Laravel `Password::defaults()`；`role` 可选，仅 `user` 或 `author` | `201`：`{"message","email_verification_required":bool,"user":<User>}`，随后登录并重生成 Session。非法角色、重复邮箱或密码规则失败为 422；节流为 429。创建操作非幂等；没有幂等键。 |
| `POST /register` | 公开；`guest`、`throttle:register` | 与 `/auth/register` 相同 | 与 `/auth/register` 相同；无 JSON 时重定向到 Dashboard。 |
| `POST /auth/login` | 公开；`throttle:login` | `email` 必填邮箱；`password` 必填字符串；`remember` 可选布尔值 | 成功 `200`：`{"message","user":<User>}`；启用二步验证时 `202`：`{"message","two_factor_required":true,"challenge_url":...}`，只建立待验证 Session，不建立登录态。凭据错误为 422，节流为 429。成功登录会重生成 Session；重复提交不是幂等。 |
| `POST /login` | 公开；`throttle:login` | 与 `/auth/login` 相同 | 与 `/auth/login` 相同；无 JSON 时返回表单错误或重定向。当前控制器还会在已有登录态时先登出并重生成 Session。 |
| `POST /auth/logout` | `auth` | 无业务字段；需 CSRF | `200`：`{"message":...}`，注销、失效当前 Session、重生成 CSRF Token，并带 `no-store` 缓存头。未认证为 401；无 JSON 时重定向首页。重复调用不应被视为幂等，因为第一次会使原 Session 失效。 |
| `POST /logout` | `auth` | 无业务字段；需 CSRF | 与 `/auth/logout` 相同，是 Web 别名。 |
| `GET /auth/me` | `auth` | 无 | `200`：`{"user":<User including twoFactorSetting>}`，带私有不可缓存响应头；未认证为 401。只读。 |

注册只允许公开创建 `user` 或 `author`，不能通过 `role` 创建 `editor` 或 `admin`；Feature 测试明确覆盖了该授权边界。用户 JSON 使用模型序列化结果，模型隐藏 `password` 与 `remember_token`。

### 3.2 邮箱验证与密码找回

| 方法与路径 | 权限/中间件 | 请求字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `POST /auth/email/verification-notification` | `auth`、`throttle:6,1` | 无 | 已验证：`200 {"message":...}`；发送成功：`200 {"message":...}`；邮件发送异常的 JSON 分支为 422。重复发送可能受 6 次/分钟节流限制；发送本身不是幂等。 |
| `GET /auth/email/verify/{id}/{hash}` | `signed`、`throttle:6,1` | 路径 `id` 与邮箱 SHA-1 `hash`；必须是有效签名 URL | `200 {"message":...}`；已验证用户返回“已验证”消息。用户不存在为 404，哈希不匹配为 403，签名/节流失败按中间件处理。对已验证用户状态上是幂等的。 |
| `POST /forgot-password` | 公开；`guest`、`throttle:password-reset` | `email` 必填邮箱 | JSON `200 {"message":...}`。代码对成功和密码代理返回状态均使用统一成功消息；验证失败 422，节流为 429。不会在响应中暴露重置 Token；重复请求不是幂等保证。 |
| `POST /reset-password` | 公开；`guest`、`throttle:password-reset` | `token` 必填字符串；`email` 必填邮箱；`password` 必填、确认字段必填并满足 `Password::defaults()` | 成功 JSON `200 {"message":...}`；Token/邮箱无效为 422；验证失败 422；成功时在 database Session 驱动下删除该用户其他 Session。重置 Token 只能按密码代理规则使用，不能重放。 |

`GET /login`、`GET /register`、`GET /forgot-password`、`GET /reset-password/{token}` 是页面路由；本文不把页面 HTML 当作 JSON 接口。

### 3.3 二步验证

| 方法与路径 | 权限/中间件 | 请求字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /auth/two-factor` | `auth` | 无 | 使用 `Accept: application/json` 时 `200 {"two_factor":<setting>}`；否则返回设置页面。只读。 |
| `POST /auth/two-factor/enable` | `auth`、`throttle:two-factor` | 首次建立设置时无必填字段；已有 Secret 时可提交 `code` 完成确认 | 首次 JSON `201`：`{"message","enabled":false,...setup result}`；确认成功 `200 {"message":...}`。已启用为 422；错误验证码触发验证错误 422；节流为 429。首次调用可重复生成设置，不是幂等保证。 |
| `DELETE /auth/two-factor` | `auth`、`throttle:two-factor` | `current_password` 或 `code` 至少一个有效；两者都可传 | 成功 JSON `200 {"message":...}`；密码和验证码均无效为 422；节流为 429。禁用后重复调用会因凭据校验失败，不能视为无条件幂等。 |
| `GET /two-factor/challenge` | 公开路由，但依赖登录前置 Session；`throttle:two-factor` | 无 | 有待验证挑战且请求 JSON 时 `200 {"message":...}`；无挑战 JSON 为 404；过期为 419。无 JSON 返回页面/登录重定向。 |
| `POST /two-factor/challenge` | 同上 | `code` 或 `recovery_code` 二选一，字符串最大 64；不可同时为空或同时提供 | 成功 JSON `200 {"message","user":<User>}` 并建立登录态；无效验证码为 422；连续达到配置的最大尝试次数（默认 5）为 429 并清除挑战；挑战过期为 419，用户不存在为 404。验证码成功后挑战不可重放。 |

## 4. 语言与时区

| 方法与路径 | 权限/中间件 | 请求字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `POST /language` | 公开；`web` | `locale` 必填，必须是 `LocaleManager::supported()` 中的语言键 | 这是 Web 重定向接口，不返回 JSON：成功设置 Session、已登录用户的 `preferred_locale` 和 `yuejing_locale` Cookie，然后重定向到同源 Referer/首页并附 `_locale_refresh`。非法语言为 422；Referer 不同源时回退到站内首页，不能形成开放重定向。对同一语言重复提交在状态上幂等，但每次 URL 查询参数不同。 |
| `POST /language/timezone` | 公开；`web` | `timezone` 必填，最大 64，必须属于 PHP `DateTimeZone::listIdentifiers()` | `200 {"locale":string|null,"changed":bool}`，并设置 `yuejing_timezone` Cookie；已登录用户同时保存用户时区。非法时区为 422。重复提交同一时区可重复写 Cookie，结果状态基本幂等。 |

语言切换路由没有 `auth`，所以访客也能使用；时区路由在源码中始终返回 JSON，即使没有 `Accept: application/json`。

## 5. 收藏、评分与阅读记录

以下路径全部是 `routes/web.php` 中的 Web 路由，均要求 `auth`；使用 JSON/AJAX 请求和 CSRF 后返回 JSON，不使用时返回页面重定向或 HTML。

| 方法与路径 | 权限/中间件 | 请求字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `POST /novels/{novel}/rating` | `auth`；`novel` 使用模型绑定 ID | `rating` 必填；允许 1.0-5.0、6.0-7.0、8.0-9.0、9.1-9.9 的一位小数值，间隔值拒绝；`review` 可选字符串最大 2000；`criteria` 可选数组，键 `plot/writing/characters/originality` 各为整数 1-10 | `200 {"message","rating":<Rating>,"level":"standard|bronze|diamond|supreme_diamond","criteria":{...}}`。作品未发布为 404；评分格式/范围为 422；同一用户已有未撤回评分时为 409，必须先撤回。评分不是幂等更新接口；重复评分明确拒绝。 |
| `DELETE /novels/{novel}/rating` | `auth`；ID 模型绑定 | 无 | `200 {"message":...}`；将当前用户该作品的有效评分设置 `withdrawn_at`。未发布为 404；没有有效评分时仍返回成功，因此撤回操作幂等。 |
| `POST /novels/{novel:slug}/favorite` | `auth`；slug 模型绑定 | 无 | `201 {"message","favorite":<Favorite>}`。未发布为 404。使用 `firstOrCreate`，数据库唯一键为用户/作品组合；重复收藏不会新增记录，但控制器仍返回 201，因此业务效果幂等、HTTP 状态不区分重复。 |
| `DELETE /novels/{novel:slug}/favorite` | `auth`；slug 模型绑定 | 无 | `200 {"message":...}`；删除当前用户的收藏。不存在时仍成功，幂等。 |
| `GET /account/favorites` | `auth` | `page`；无 JSON 时页面 | JSON `200`：收藏分页对象，带已发布小说及作者；只读。 |
| `GET /account/reading-records` | `auth` | `page`；无 JSON 时页面 | JSON `200`：阅读记录分页对象，带小说、作者、章节；只读。 |
| `GET /reading-records` | `auth` | `page` | 与 `/account/reading-records` 相同，是页面路由别名；JSON 请求返回分页对象。 |

阅读记录没有独立的 POST/PUT 路由。它由公开章节 JSON/HTML 阅读路径在用户已登录时自动 `updateOrCreate`，唯一键是 `user_id + novel_id`，当前实现每次记录进度 100 和最近阅读章节。

## 6. 投稿

投稿能力是 Web 路由，不是 `routes/api.php` 的独立资源路由。`/submissions` 与 `/author/submissions` 使用同一控制器；只有带 JSON 期望的请求才返回 JSON。

| 方法与路径 | 权限/中间件 | 请求字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /submissions` | `auth`、`email.required`、`role:author,editor,admin` | `page` | JSON `200`：当前用户投稿分页，带 `reviewer:id,name`；无 JSON 为投稿页。普通 `user` 为 403；配置要求验证邮箱而用户未验证时为 403。只读。 |
| `GET /author/submissions` | 同上 | `page` | JSON 与 `/submissions` 相同；无 JSON 时重定向到 Dashboard 的投稿区。 |
| `POST /submissions` | 同上，需 CSRF | `title` 必填字符串最大 255；`category_id` 可选且必须存在；`synopsis` 可选字符串最大 5000；`manuscript` 或兼容字段 `content` 可选字符串；`manuscript_format` 只能 `markdown/text`；`manuscript_file` 可选文件最大 5120 KB；`summary` 可作为 synopsis 兼容字段；`cover` 可选 jpeg/jpg/png/webp 图片最大 5120 KB；`cover_url` 可选 URL 最大 500 | 成功 JSON `201 {"message","submission":<Submission>}`，初始状态为 `pending`，上传封面会保存为公开 Storage URL。正文为空为 422；首次投稿缺少 synopsis 为 422；封面文件和 URL 至少一个，否则 422；编辑正文和文件同时提供为 422；其它字段验证为 422。每次请求都会创建新投稿，不幂等。 |
| `POST /author/submissions` | 同上 | 与 `/submissions` 相同 | 与 `/submissions` 相同；无 JSON 重定向到 Dashboard 投稿区。 |
| `GET /submissions/{submission}` | `auth`、`email.required`；投稿作者本人或 `editor/admin` | 路径投稿 ID | 控制器始终返回 `200` JSON 投稿对象（不依赖 Accept），并加载 `reviewer:id,name`。作者之外的普通用户为 403；不存在为 404。只读。 |

投稿文件解析结果会覆盖 `manuscript`/`manuscript_format`；代码要求正文来源在编辑器与上传文件之间二选一。`email.required` 是否拦截由应用设置和环境开关共同决定，不能仅根据数据库 Setting 推断已开启。

## 7. 作者作品与章节

作者管理 Web 路由要求 `auth`、`email.required`、`role:author,editor,admin`。作者只能管理自己的作品；`editor` 和 `admin` 可管理所有作品。发送 `Accept: application/json` 后返回以下 JSON。

| 方法与路径 | 权限/中间件 | 请求字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /author/novels` | 作者/编辑/管理员角色 | `page` | `200`：作品分页，含作者、分类和章节计数；普通用户 403。只读。 |
| `GET /author/novels/{novel}/edit` | 同上；另检查作品所有权/编辑权限 | 路径作品 ID | `200 {"novel":<Novel>,"categories":[...],"statusOptions":["draft","published","archived"]}`；越权 403，不存在 404。只读。 |
| `PUT /author/novels/{novel}` 或 `PATCH /author/novels/{novel}` | 同上；需 CSRF | `title` 可选但有值时必填字符串最大 255；`synopsis` 可选最大 5000；`category_id` 可选存在；`category_ids` 可选数组，元素整数、去重、分类必须存在；`status` 可选 `draft/published/archived`；`cover` 可选 jpeg/jpg/png/webp 图片最大 5120 KB；`cover_url` 可选 URL 最大 500 | `200`：更新后的 Novel JSON，含作者/分类。验证失败 422，越权 403，不存在 404。发布/草稿会自动维护 `published_at`；分类数组会同步替换关系；文件替换时会删除旧的本地封面。重复提交相同状态通常效果幂等，但没有并发幂等键。 |
| `GET /author/novels/{novel}/chapters` | 同上；检查作品权限 | `page` | `200 {"novel":<Novel>,"chapters":<分页对象>}`；越权 403，不存在 404。只读。 |
| `POST /author/novels/{novel}/chapters` | 同上；需 CSRF | `chapter_number` 必填整数最小 1；`title` 必填字符串最大 255；`content` 可选字符串；`content_format` 可选 `markdown/text`；`chapter_file` 可选文件最大 5120 KB；`status` 可选 `draft/published` | `201`：新 Chapter JSON。创建时正文必须来自 `content` 或文件之一；两者同时提供或正文为空为 422；越权 403；不存在 404。章节号受数据库同一作品唯一约束影响，冲突可能由数据库异常表现，代码未定义专门响应。重复 POST 会尝试创建新章节，不幂等。 |
| `PUT /author/novels/{novel}/chapters/{chapter}` 或 `PATCH ...` | 同上；还检查章节属于该作品 | `chapter_number` 可选整数最小 1；`title` 可选字符串最大 255；`content` 可选字符串；`content_format` 可选 `markdown/text`；`chapter_file` 可选文件最大 5120 KB；`status` 可选 `draft/published` | `200`：更新后的 Chapter JSON。只要提交 `content` 就必须是非空正文；编辑器与文件冲突为 422；章节不属于作品为 404；越权为 403。发布/草稿自动维护 `published_at`；相同更新可重复执行。 |
| `DELETE /author/novels/{novel}/chapters/{chapter}` | 同上；需 CSRF | 无 | `200 {"message":...}`；章节删除成功；不属于作品为 404，越权为 403。对已删除章节重复调用不是稳定幂等，模型绑定会返回 404。 |

作者作品更新允许编辑器或上传文件，但不允许同时使用两种正文来源。`category_id` 是兼容的单分类输入，控制器会转换为 `category_ids`；响应中使用已加载的分类关系。

## 8. 私信 API

以下接口全部是 `routes/api.php`，实际前缀为 `/api`，中间件为 `web`、`auth`。写操作需要 CSRF。私信会话按较小用户 ID/较大用户 ID 规范化，并由数据库唯一键复用。

| 方法与路径 | 权限/中间件 | 请求参数/字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /api/messages/users` | `web`、`auth` | 查询 `q` 或 `search`，可选字符串最大 100；实际优先使用 `q`；按名称模糊搜索，最多 20 人 | `200 {"data":[{"id","name","role"}]}`，排除当前用户，不返回 email。未认证 401；只读。 |
| `GET /api/messages` | `web`、`auth` | 无 | `200 {"data":[{"id","user_low_id","user_high_id","participant","last_message","unread_count"}]}`。无会话时 `data=[]`；按最近消息排序。只读。 |
| `GET /api/messages/{conversation}` | `web`、`auth` | 路径会话 ID | `200 {"conversation":{"id","user_low_id","user_high_id"},"messages":[{"id","private_conversation_id","sender_id","body","read_at","created_at"}]}`。非会话成员 403，不存在 404。只读。 |
| `POST /api/messages` | `web`、`auth`；需 CSRF | `recipient_id` 必填整数且必须存在；`body` 可选字符串最大 5000，但 trim 后不能为空 | `201 {"conversation":{...},"message":{...}}`。不能给自己发信、收件人不存在、正文为空或超长均为 422；系统复用同一两人会话，但每次请求新增一条消息，不幂等。 |
| `POST /api/messages/{conversation}/read` | `web`、`auth`；需 CSRF；必须是会话成员 | 无 | `200 {"updated_count":number}`，只标记对方发送且尚未阅读的消息；非成员 403，不存在 404。重复调用可返回 0，幂等。 |
| `GET /api/messages/{conversation}/stream` | `web`、`auth`；必须是会话成员 | 查询 `after_id` 可选整数最小 0；`timeout` 可选 1 至 30 秒，默认 15；也可用 `Last-Event-ID` 作为游标 | `200`，`Content-Type: text/event-stream`。新消息以 `id`、`event: message`、JSON `data` 发送；空闲期间发送 `: keep-alive`。非成员 403，不存在 404，参数错误 422。连接按 timeout 结束；只读。 |

私信接口不会返回密码、邮箱等非公开用户字段。Feature 测试确认非会话成员不能查看、标记已读或建立该会话的 SSE。

## 9. 群组与群组 SSE

以下接口全部要求 `web`、`auth`，写操作要求 CSRF。除列出的角色外，群组角色是成员记录中的 `owner`、`admin`、`member`，不是全局用户角色。

| 方法与路径 | 权限/中间件 | 请求参数/字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /api/groups` | `web`、`auth` | 无 | `200 {"groups":[{"id","creator_id","name","description","role","joined_at","last_read_at","unread_count","created_at","updated_at"}]}`。只返回当前用户所属群组；非成员用户得到空数组；只读。 |
| `GET /api/groups/{group}` | `web`、`auth`；必须是群成员 | 路径群组 ID | `200 {"group":{...},"members":[{"id","name","role","joined_at","last_read_at"}],"messages":[{"id","chat_group_id","sender_id","sender","body","created_at","updated_at","read_count","read_by","reads"}]}`。非成员 403，不存在 404。只读。 |
| `POST /api/groups` | `web`、`auth`；需 CSRF | `name` 必填字符串最大 100；`description` 可选字符串最大 2000；`member_ids` 可选数组最大 100，元素必须为存在的用户 ID | `201 {"message","group":{"id","creator_id","name","description"},"member_ids":[...]}`。当前用户自动加入并成为 owner；输入 ID 去重。验证失败 422；每次创建新群，不幂等。 |
| `POST /api/groups/{group}/members` | `web`、`auth`；需 CSRF；当前成员角色必须 `owner` 或 `admin` | `user_id` 必填整数且必须存在 | 新成员 `201 {"message","member":{...}}`；已是成员 `200` 且返回同一 member，不新增重复关系。非管理员成员 403，群不存在 404，用户验证失败 422。重复添加幂等，状态码 201/200 有区别。 |
| `DELETE /api/groups/{group}/members/{user}` | `web`、`auth`；需 CSRF；当前成员必须是 owner | 路径群组与用户 ID | `200 {"message":...}`。不能移除群创建者，返回 422；目标不是成员 404；非 owner 403。成功删除成员及其在该群消息的阅读记录；重复删除不保证幂等，第二次通常 404。 |
| `POST /api/groups/{group}/messages` | `web`、`auth`；需 CSRF；必须是群成员 | `body` 必填字符串最大 5000，trim 后不能为空 | `201 {"message","data":{"id","chat_group_id","sender_id","sender","body","created_at","updated_at","read_count","read_by"}}`。非成员 403；空白或超长正文 422；每次新增消息，不幂等。发送者自身的阅读状态会立即记录。 |
| `POST /api/groups/{group}/read` | `web`、`auth`；需 CSRF；必须是群成员 | `message_id` 可选整数最小 1；或 `latest` 可选布尔值；两者至少提供一个。`message_id` 必须属于该群 | 有目标时 `200 {"message","message_id":id,"marked_count":number}`；空群使用 `latest=true` 时返回 `message_id:null,marked_count:0`。缺少两者为 422，目标不存在为 404，非成员 403。阅读记录使用唯一键 upsert，重复标记幂等。 |
| `GET /api/groups/{group}/stream` | `web`、`auth`；必须是群成员 | `after_id` 可选整数最小 0，默认 0；`timeout` 可选 1 至 10 秒，默认 2；`poll_ms` 可选 50 至 1000，默认 250 | `200`，`Content-Type: text/event-stream; charset=UTF-8`。有新消息时按 ID 发送 `event: message` 与 JSON `data`，无消息发送 `: heartbeat`，超时发送 `event: end` 与 `data: {}`。非成员 403，参数错误 422；只读、连接按 timeout 结束。 |

群组创建时 `member_ids` 中重复 ID 和当前用户 ID 会被去重。Feature 测试确认普通成员可发送和标记已读，`admin` 成员可以添加但不能移除，只有群 owner 能移除。

## 10. 管理员 API

### 10.1 认证、响应和 Web 对应路由

`routes/api.php` 的管理员接口统一前缀为 `/api/admin`，中间件为 `web`、`auth`、`role:admin`；写操作需要 CSRF。普通用户或非管理员为 403，未登录为 401。

`routes/web.php` 还注册了相同控制器的 `/admin/...` 页面路由。对这些 Web 路由发送 `Accept: application/json` 会得到 JSON；未发送时返回管理员 Blade 页面或重定向。下面每一行先列 `/api/admin` 正式 API，给出对应 Web JSON 路径；若 Web 路由未注册相同 HTTP 方法，会明确标注“无 Web 对应路由”。

### 10.2 仪表盘、设置与邮件测试

| 方法与路径 | Web JSON 对应路径 | 请求字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /api/admin` | `GET /admin` | 无 | `200 {"users":number,"novels":number,"chapters":number,"pending_submissions":number}`；只读。 |
| `GET /api/admin/settings` | `GET /admin/settings` | 无 | `200 {"settings":[<Setting>]}`；只读。页面分支会额外整理环境配置，但 JSON 分支返回数据库 Settings 集合。 |
| `PUT /api/admin/settings` | `PUT /admin/settings` | 可选设置：`email_verification_required`、`site_name`、`site_tagline`、`contact_email`、`accent_color`、`show_rank`、`show_new`、`allow_comments`；可选 `environment` 对象。环境字段按控制器规则验证，例如 `APP_ENV` 为 `local/production/testing`，`APP_URL` 为 URL，数据库/Session/邮件/队列等值有白名单，数值配置有范围 | `200 {"message":...}`。字段验证或环境更新异常为 422。写入设置和允许的环境配置；`allow_comments` 虽可读写，但当前评分控制器不使用它来启用或阻断评分附带文字；重复提交相同值在结果上幂等，但没有版本/幂等键。 |
| `POST /api/admin/settings/email-test` | `POST /admin/settings/email-test` | `email` 必填邮箱最大 255 | 成功 `200 {"message","success":true}`；邮件发送异常 `422 {"message","success":false}`；字段验证 422。每次请求都会尝试发信，不幂等。 |

管理员不能通过普通注册接口创建；权限来自用户已有 `role=admin`。环境配置字段的完整白名单以 `AdminController::updateSettings()` 为准，文档不扩展未出现的环境变量。

### 10.3 分类

| 方法与路径 | Web JSON 对应路径 | 请求字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /api/admin/categories` | `GET /admin/categories` | `page` | `200`：分类分页对象，含小说计数；只读。 |
| `POST /api/admin/categories` | `POST /admin/categories` | `name` 必填字符串最大 100；`slug` 必填、最大 100、格式小写字母/数字与短横线（`^[a-z0-9]+(?:-[a-z0-9]+)*$`）且唯一；`description` 可选字符串；`is_active` 可选布尔 | `201`：新 Category JSON。验证/slug 冲突 422；每次创建新分类，不幂等。 |
| `PUT /api/admin/categories/{category}` 或 `PATCH ...` | 对应 `/admin/categories/{category}` | `name`、`slug`、`description`、`is_active` 均可选；提供 `slug` 时沿用格式且除自身外唯一 | `200`：更新后的 Category JSON；验证 422，不存在 404。重复提交相同字段效果幂等。 |
| `DELETE /api/admin/categories/{category}` | 无 Web 对应 DELETE 路由 | 无 | `200 {"message":...}`；删除分类。不存在为 404；重复删除不保证幂等。分类 Web 页面注册了 GET/POST/PUT/PATCH，但没有注册该 DELETE 方法。 |

### 10.4 管理员作品与章节

| 方法与路径 | Web JSON 对应路径 | 请求字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /api/admin/novels` | `GET /admin/novels` | `page` | `200`：作品分页，含作者、分类和章节计数；只读。 |
| `POST /api/admin/novels` | `POST /admin/novels` | `author_id` 必填且存在；`title` 必填字符串最大 255；`slug` 可选字符串最大 255 且唯一；`synopsis` 可选字符串；`cover_url` 可选 URL 最大 500；`status` 可选 `draft/published/archived`；`category_ids` 可选数组，元素必须存在 | `201`：新 Novel JSON，含 categories。未提供 slug 时由标题生成并加随机后缀；验证 422；每次创建新作品，不幂等。 |
| `PUT /api/admin/novels/{novel}` 或 `PATCH ...` | 对应 `/admin/novels/{novel}` | `author_id`、`title`、`slug`、`synopsis`、`cover_url`、`status`、`category_ids` 可选；提供值时遵循创建规则，slug 排除当前记录唯一校验 | `200`：更新后的 Novel JSON，含 categories；验证 422，不存在 404。发布/草稿自动设置或清空 `published_at`；重复相同更新效果幂等。 |
| `DELETE /api/admin/novels/{novel}` | 对应 `/admin/novels/{novel}` | 无 | `200 {"message":...}`；删除作品及受外键约束影响的内容；不存在 404，重复删除不保证幂等。 |
| `GET /api/admin/novels/{novel}/chapters` | `GET /admin/novels/{novel}/chapters` | `page` | `200`：该作品章节分页对象；不存在 404；只读。 |
| `POST /api/admin/novels/{novel}/chapters` | `POST /admin/novels/{novel}/chapters` | `chapter_number` 必填整数最小 1；`title` 必填字符串最大 255；`content` 或 `chapter_file` 二选一；`content_format` 可选 `markdown/text`；文件最大 5120 KB；`status` 可选 `draft/published` | `201`：新 Chapter JSON；正文缺失、来源冲突或验证失败为 422；章节号冲突受数据库唯一键约束；每次创建不幂等。 |
| `PUT /api/admin/novels/{novel}/chapters/{chapter}` 或 `PATCH ...` | 对应 `/admin/novels/{novel}/chapters/{chapter}` | `chapter_number`、`title`、`content`、`content_format`、`chapter_file`、`status` 均可选；若提供正文则不能为空且文件不能同时提供 | `200`：更新后的 Chapter JSON；章节不属于作品为 404；验证 422。发布/草稿自动维护 `published_at`；重复相同更新效果幂等。 |
| `DELETE /api/admin/novels/{novel}/chapters/{chapter}` | 对应 `/admin/novels/{novel}/chapters/{chapter}` | 无 | `200 {"message":...}`；章节不属于作品为 404；重复删除通常 404。 |

管理员章节接口复用 `ChapterController`，因此章节内容可用 Markdown 或 text，上传文件会由 `ManuscriptFileParser` 解析。管理员作品接口与作者接口的权限差异仅在资源所有权：管理员不受作者归属限制。

### 10.5 投稿审核与审计日志

| 方法与路径 | Web JSON 对应路径 | 请求参数/字段 | 成功响应、错误、限制与幂等性 |
|---|---|---|---|
| `GET /api/admin/submissions` | `GET /admin/submissions` | `page`；可选查询 `status`，代码直接按值筛选，没有额外 `in` 校验 | `200`：投稿分页对象；每个投稿会附加安全渲染的 `manuscript_html`，并带用户、小说、分类关系。只读。 |
| `PUT /api/admin/submissions/{submission}/review` | `PUT /admin/submissions/{submission}/review` | `status` 必填，仅 `approved` 或 `rejected`；`review_note` 可选字符串最大 5000 | `200 {"message","submission":<Submission>}`。投稿不存在 404；已非 `pending` 为 409；字段验证 422。批准会在没有关联小说时创建已发布 Novel 和第一章，并记录审核人/时间；拒绝不创建作品。审核是一次性状态迁移，重复审核不幂等。 |
| `GET /api/admin/audit-logs` | `GET /admin/audit-logs` | `page` | `200`：只包含 `action like submission.%` 的审计日志分页，并加载投稿相关关系；只读。 |

Feature 测试确认批准投稿会创建 `published` 作品和第一章，拒绝不会创建作品，已审核投稿再次审核返回 409；审计日志接口不会返回登录等非投稿事件。

## 11. 不属于本 API 清单的路由

以下路由在 `routes/web.php` 中存在，但当前控制器只返回页面 HTML，或没有可确认的 JSON 分支，因此本文不把它们描述为 JSON API：

- 首页信息页：`GET /categories`、`GET /about`、`GET /reading-guide`、`GET /contact`。
- 个人中心页面：`GET /dashboard`、`GET /messages`、`GET /groups`、`GET /account/settings`。
- 账户资料更新：`PUT /account/settings` 只验证 `name/email` 后重定向，不是 JSON API。
- 登录/注册/密码找回的 GET 页面，以及 `GET /admin/...`、`GET /author/...` 在不请求 JSON 时的 HTML 页面。

## 12. 源码依据与未确认事项

主要依据：

- 路由与前缀：`bootstrap/app.php`、`routes/api.php`、`routes/web.php`。
- JSON 判断、异常 JSON 化与角色/邮箱中间件：`app/Http/Controllers/Controller.php`、`bootstrap/app.php`、`app/Http/Middleware/RoleMiddleware.php`、`app/Http/Middleware/EnsureEmailVerifiedIfRequired.php`。
- 具体响应和校验：`AuthController`、`VerificationController`、`TwoFactorController`、`PublicController`、`RecommendationController`、`LanguageController`、`InteractionController`、`SubmissionController`、`AuthorNovelController`、`ChapterController`、`PrivateMessageController`、`GroupChatController`、`AdminController`。
- 数据约束：`app/Models`、`database/migrations` 中的 `$fillable`、casts、唯一键、外键和索引。
- 行为验证：`tests/Feature/AuthenticationTest.php`、`PublicContentTest.php`、`PersonalizationAndReviewsTest.php`、`InteractionsAndSubmissionTest.php`、`PrivateMessagingTest.php`、`GroupChatTest.php`、`MessagingSecurityTest.php`、`AuthorWorkManagementTest.php`、`AdminAndAccountTest.php`、`RoleAuthorizationTest.php` 等。

未确认事项：

1. 当前执行环境没有 `php` 命令，因此本次无法运行 `php artisan route:list` 或 PHPUnit；`/api` 前缀依据 Laravel `withRouting(api: ...)` 配置和 Feature 测试中的实际 `/api/...` 请求确认。
2. 运行环境中的实际域名、HTTPS、Session Cookie 最终名称、代理和生产级 CORS/缓存配置由 `.env`/部署配置决定，本文只记录源码中的默认与可配置行为。
3. Laravel 框架统一异常处理（例如未认证响应的最终 JSON/重定向细节、数据库唯一键异常的具体响应）未在应用控制器中逐一重写；本文对这些情况只列源码可确认的常见状态范围。
