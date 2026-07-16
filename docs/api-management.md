# API 管理文档

> 本文依据当前项目的 `routes/web.php`、`routes/api.php`、控制器校验规则和中间件整理。项目当前使用 **Laravel Session + Cookie** 认证，没有配置 Bearer Token、OAuth 或 Sanctum Token。

## 1. API 范围和地址

生产环境将 `https://example.com` 替换为实际域名：

| 类型 | 地址 | 说明 |
| --- | --- | --- |
| 认证与账户 API | `/auth/*`、`/two-factor/challenge` | 定义在 `routes/web.php`，必须通过 `Accept: application/json` 请求以获得 JSON 响应 |
| 管理 API | `/api/admin/*` | 定义在 `routes/api.php`，Laravel 自动添加 `/api` 前缀 |
| Web 管理后台 | `/admin/*` | 面向浏览器页面，不作为第三方 API 使用 |
| 作者操作 | `/author/*` | 面向已登录作者、编辑和管理员的 Web 操作 |
| 健康检查 | `/up` | 部署和监控使用，正常返回 HTTP 200 |

管理 API 的 `/api/admin/*` 路由显式使用 `web`、`auth` 和 `role:admin` 中间件，因此它是**有状态的浏览器会话 API**，同时受 CSRF 保护。不要把它误认为无状态 REST API。

## 2. 请求约定

### 2.1 请求头和会话

JSON 请求建议统一携带：

```http
Accept: application/json
Content-Type: application/json
```

认证方式：

1. 先通过浏览器或客户端访问 `/login`（或其他 Web 页面）建立 Session，并取得 CSRF Token。
2. 后续请求持续携带 Laravel Session Cookie。
3. 写操作同时通过 `X-CSRF-TOKEN`、`X-XSRF-TOKEN` 或请求体 `_token` 传递 CSRF Token。
4. 登录成功后，继续使用同一组 Cookie 调用需要登录的接口。

当前没有 `/sanctum/csrf-cookie` 和 API Token 接口。若要支持移动端、服务间调用或第三方集成，应先引入独立的 Token 认证方案，再同步修改路由、中间件和本文档。

### 2.2 通用响应

- 成功响应使用 JSON；创建资源通常返回 `201`。
- 参数校验失败返回 `422`，Laravel 默认结构包含 `message` 和 `errors`。
- 未登录通常返回 `401`；已登录但不是管理员返回 `403`。
- 二步验证登录待挑战返回 `202`，登录挑战过期返回 `419`。
- 列表接口使用 Laravel 分页结构，常见字段包括 `current_page`、`data`、`per_page`、`total`、`last_page` 和分页链接。
- 路径中的 `{category}`、`{novel}`、`{chapter}`、`{submission}` 均使用数据库主键 ID；公开阅读页面使用小说 `slug`，不要混用。

## 3. 认证与账户 API

除特别说明外，以下接口都使用 Session Cookie。`/auth/*` 位于 Web 路由中，客户端必须带 `Accept: application/json` 才能稳定获得 JSON 响应。

| 方法 | 路径 | 登录 | 说明 |
| --- | --- | --- | --- |
| POST | `/auth/register` | 否 | 注册并创建普通用户会话 |
| POST | `/auth/login` | 否 | 登录；启用二步验证时返回 `202` |
| POST | `/auth/logout` | 是 | 注销并销毁当前会话 |
| GET | `/auth/me` | 是 | 返回当前用户及二步验证设置 |
| POST | `/auth/email/verification-notification` | 是 | 重新发送验证邮件，受 `6 次/分钟` 限流 |
| GET | `/auth/email/verify/{id}/{hash}` | 否 | 签名验证链接，需使用邮件中的完整 URL |
| GET | `/auth/two-factor` | 是 | 查看当前二步验证状态 |
| POST | `/auth/two-factor/enable` | 是 | 首次调用生成密钥和恢复码；带 `code` 再次调用确认启用 |
| DELETE | `/auth/two-factor` | 是 | 使用当前密码或 TOTP 验证码禁用二步验证 |
| GET | `/two-factor/challenge` | 否* | 查看待完成的登录挑战 |
| POST | `/two-factor/challenge` | 否* | 提交 TOTP 验证码或恢复码完成登录 |
| POST | `/forgot-password` | 否 | 请求密码重置邮件 |
| POST | `/reset-password` | 否 | 使用 `token`、`email`、新密码重置密码 |

`*` 登录接口返回二步验证待挑战后，挑战状态保存在当前 Session 中；客户端必须继续使用该 Session Cookie，但此时尚未完成普通登录。

### 3.1 注册

```json
POST /auth/register
{
  "_token": "<csrf-token>",
  "name": "阅读者",
  "email": "reader@example.com",
  "password": "至少满足 Laravel 默认密码规则的密码",
  "password_confirmation": "至少满足 Laravel 默认密码规则的密码"
}
```

成功返回 `201`，响应包含 `message`、`email_verification_required` 和 `user`。新用户角色固定为 `user`，不能通过请求体提升为管理员或作者。

### 3.2 登录和二步验证

```json
POST /auth/login
{
  "_token": "<csrf-token>",
  "email": "admin@example.com",
  "password": "<password>",
  "remember": true
}
```

- 普通登录成功返回 `200`，包含 `user`。
- 账号启用二步验证时返回 `202`，包含 `two_factor_required: true` 和 `challenge_url`。
- 然后向 `/two-factor/challenge` 提交二者之一：

```json
{ "_token": "<csrf-token>", "code": "123456" }
```

或：

```json
{ "_token": "<csrf-token>", "recovery_code": "ABCD-1234" }
```

`code` 和 `recovery_code` 不能同时提交。默认最多允许 5 次失败尝试，挑战默认 10 分钟有效；实际值由 `YUEJING_TOTP_MAX_ATTEMPTS` 和 `YUEJING_TOTP_CHALLENGE_LIFETIME` 控制。

### 3.3 启用和禁用二步验证

首次启用：

```http
POST /auth/two-factor/enable
```

响应 `201` 返回 `secret` 和 `recovery_codes`。客户端应立即将恢复码安全展示并保存；服务端不会再次明文返回已保存的恢复码。使用验证器生成六位 TOTP 后，再次调用：

```json
{ "_token": "<csrf-token>", "code": "123456" }
```

禁用时至少提供一个有效字段：

```json
DELETE /auth/two-factor
{
  "_token": "<csrf-token>",
  "current_password": "<current-password>"
}
```

也可以使用 `code` 代替 `current_password`。二步验证写操作受 `two-factor` 限流。

## 4. 管理 API

所有 `/api/admin/*` 接口都要求：

- 已登录；
- 当前用户 `role=admin`；
- Session Cookie 有效；
- 写操作带 CSRF Token；
- 请求体使用 JSON 时带 `Accept: application/json`。

### 4.1 仪表盘、站点设置和邮件测试

| 方法 | 路径 | 请求字段 | 成功响应 |
| --- | --- | --- | --- |
| GET | `/api/admin` | 无 | `users`、`novels`、`chapters`、`pending_submissions` |
| GET | `/api/admin/settings` | 无 | `{ "settings": [...] }`，按 key 排序 |
| PUT | `/api/admin/settings` | 所有字段可选：`email_verification_required`、`site_name`、`site_tagline`、`contact_email`、`accent_color`、`show_rank`、`show_new`、`allow_comments` | `{ "message": "Settings updated." }` |
| POST | `/api/admin/settings/email-test` | `email`，必填且必须为邮箱地址 | `{ "message": "SMTP test sent.", "success": true }` |

设置更新是部分更新，只提交需要变更的字段即可。每次设置变更会写入审计日志；修改 `.env` 后仍需在服务器执行 `php artisan config:cache`。

### 4.2 分类管理

| 方法 | 路径 | 请求字段 | 成功响应 |
| --- | --- | --- | --- |
| GET | `/api/admin/categories` | `page` 分页参数 | 分页分类列表，并包含 `novels_count` |
| POST | `/api/admin/categories` | `name` 必填；`slug`、`description`、`is_active` 可选 | `201`，返回分类对象 |
| PUT/PATCH | `/api/admin/categories/{category}` | `name`、`slug`、`description`、`is_active` 均可选 | 返回更新后的分类对象 |
| DELETE | `/api/admin/categories/{category}` | 无 | `{ "message": "Category deleted." }` |

`name` 最大 100 字符，`slug` 最大 100 字符且必须唯一；未提供 slug 时由名称自动生成。

### 4.3 小说管理

| 方法 | 路径 | 请求字段 | 成功响应 |
| --- | --- | --- | --- |
| GET | `/api/admin/novels` | `page` 分页参数 | 分页小说列表，包含作者、分类和 `chapters_count` |
| POST | `/api/admin/novels` | `author_id`、`title` 必填；`slug`、`synopsis`、`cover_url`、`status`、`category_ids` 可选 | `201`，返回小说及分类 |
| PUT/PATCH | `/api/admin/novels/{novel}` | 所有字段可选 | 返回更新后的小说及分类 |
| DELETE | `/api/admin/novels/{novel}` | 无 | `{ "message": "Novel deleted." }` |

字段规则：

- `author_id` 必须存在于 `users.id`。
- `title` 最大 255 字符；`cover_url` 必须是合法 URL，最大 500 字符。
- `status` 只能是 `draft`、`published` 或 `archived`。
- `category_ids` 是分类 ID 数组，数组中的每个 ID 必须存在。
- 创建时未提供 slug 会自动生成；显式提供的 slug 必须唯一。
- 状态变为 `published` 时自动设置 `published_at`；状态变为 `draft` 时清空 `published_at`。

### 4.4 章节管理

| 方法 | 路径 | 请求字段 | 成功响应 |
| --- | --- | --- | --- |
| GET | `/api/admin/novels/{novel}/chapters` | `page` 分页参数 | 该小说的章节分页列表 |
| POST | `/api/admin/novels/{novel}/chapters` | `chapter_number`、`title`、`content` 必填；`status` 可选 | `201`，返回章节对象 |
| PUT/PATCH | `/api/admin/novels/{novel}/chapters/{chapter}` | `chapter_number`、`title`、`content`、`status` 可选 | 返回更新后的章节对象 |
| DELETE | `/api/admin/novels/{novel}/chapters/{chapter}` | 无 | `{ "message": "Chapter deleted." }` |

`chapter_number` 必须是大于等于 1 的整数，`status` 只能是 `draft` 或 `published`。更新和删除时，章节必须属于 URL 中的小说，否则返回 `404`。发布规则与小说相同：发布时设置 `published_at`，改回草稿时清空。

### 4.5 投稿审核

| 方法 | 路径 | 请求字段 | 成功响应 |
| --- | --- | --- | --- |
| GET | `/api/admin/submissions` | 可选 `status`、`page` | 投稿分页列表，包含用户、小说和分类 |
| PUT | `/api/admin/submissions/{submission}/review` | `status` 必填，只能是 `approved` 或 `rejected`；`review_note` 可选，最大 5000 字符 | `{ "message": "Submission reviewed.", "submission": {...} }` |

批准投稿时：

1. 如果投稿尚未关联小说，会创建一篇已发布小说；
2. 如果小说没有章节，会用投稿正文创建第 1 章并发布；
3. 投稿分类会同步到小说；
4. 审核人、审核时间和审核备注会写入投稿；
5. 审核动作写入审计日志。

拒绝投稿不会自动删除投稿或正文。

### 4.6 审计日志

```http
GET /api/admin/audit-logs?page=1
```

返回分页投稿审计日志，包含投稿标题、投稿作者、审核人、审核结果、投稿来源 IP 和时间。接口只返回 `submission.created`、`submission.approved`、`submission.rejected` 等 `submission.*` 事件，不再混入登录、二步验证、设置或管理员初始化日志。日志查询接口只读，不提供删除或修改接口。

## 5. 常用管理流程

### 5.1 管理员登录后修改站点设置

1. `POST /auth/login` 登录；
2. 若返回 `202`，完成 `POST /two-factor/challenge`；
3. `GET /api/admin/settings` 读取现有配置；
4. `PUT /api/admin/settings` 只提交变更字段；
5. `GET /api/admin/audit-logs` 核对审计记录；
6. 如果修改的是 `.env` 而不是数据库设置，执行 `php artisan config:cache` 并访问 `/up`。

### 5.2 发布小说和章节

1. 创建或更新分类；
2. `POST /api/admin/novels` 创建小说并传入 `category_ids`；
3. 逐章调用章节创建接口；
4. 确认章节内容后把小说和章节的 `status` 更新为 `published`；
5. 访问公开的 `/novels/{slug}` 和 `/novels/{slug}/chapters/{chapter_number}` 验证读者可见性。

### 5.3 审核投稿

先用 `GET /api/admin/submissions?status=pending` 获取待审核投稿，再调用审核接口。批准操作会在事务中同步小说和首章；若接口失败，应先检查 `storage/logs/laravel.log`、数据库事务状态和审计日志，不要直接手工重复创建内容。

## 6. 安全和运维要求

- 不要把 Session Cookie、CSRF Token、管理员密码、SMTP 密码、二步验证 `secret` 或恢复码写入日志、工单和 Git。
- 生产环境保持 `APP_DEBUG=false`，并把站点根目录指向 `public/`。
- 管理 API 不支持跨站无 Cookie的 Bearer 调用；跨域客户端需在引入 Token 认证前完成安全评审。
- 登录、注册、密码重置和二步验证接口均有节流策略。批量脚本不要绕过限流，也不要并发重试。
- 发布后执行配置、路由和视图缓存构建，并用 `/up` 做健康检查。
- 管理员权限由数据库 `users.role` 控制，生产初始化/同步使用 `php artisan yuejing:admin`，不要通过 API 传入角色字段。

## 7. 路由来源和维护规则

- 修改 `routes/api.php` 后必须同步更新本文档的管理 API 表格和示例。
- 修改 `routes/web.php` 中 `/auth/*`、`/two-factor/challenge` 或管理页面路由后，检查本文档的认证流程和地址。
- 修改控制器的 `validate()`、状态值、权限中间件或响应码后，必须更新对应字段规则和错误处理说明。
- 发布前建议执行：

```bash
php artisan route:list
php artisan test
php artisan config:clear
php artisan config:cache
```

部署流程请参阅 [`README.md`](../README.md) 的 aaPanel 章节。