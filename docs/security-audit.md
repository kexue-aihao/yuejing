# 阅境安全审计说明

本文面向开发、运维和安全审核人员，是基于当前代码和路由的静态安全审计，目标是说明已有控制、需要关注的风险和生产环境的核验项。部署操作请参阅 [`生产安全部署规范`](production-security.md)；本文不是渗透测试报告，也不代表已经完成动态扫描、依赖漏洞扫描或线上配置核验。

## 1. 审计范围和结论口径

审计范围包括：认证与 Session、CSRF、角色和对象授权、TOTP、投稿与文件上传、Markdown 渲染、私信与群组、SSE、管理员接口、日志、生产配置和依赖边界。

结论使用以下分类，避免把建议或未运行的测试写成漏洞确认：

- **已确认控制**：可由当前源码直接确认的安全行为。
- **需要部署核验**：代码提供了控制或约束，但效果依赖目标服务器、代理、PHP-FPM 或运行时测试。
- **潜在风险**：代码允许该行为，但是否构成问题取决于业务和部署场景，不应直接当作已确认漏洞。

当前未在本工作区运行 PHP、Artisan、PHPUnit、浏览器动态测试、SSE 反向代理测试或 Composer 漏洞扫描，因此运行时结论仍需上线前验证。

## 2. 已确认的安全控制

### 2.1 认证、Session 和 CSRF

- 登录、注册、退出、密码重置和 TOTP 挑战均通过 Laravel Web/Session 语义处理；没有发现 Bearer Token、Sanctum、JWT 或 OAuth API 契约。
- 登录成功后重新生成 Session；退出时注销用户、使 Session 失效并重新生成 CSRF Token。
- 表单使用 `@csrf`；`/api/*` 路由显式使用 `web` 中间件，因此同源 JSON 写请求仍需要 Session Cookie 和 CSRF Token。
- 登录、注册、密码重置和 TOTP 路由配置了节流；TOTP 挑战有过期时间和最大失败次数。
- 密码重置成功后，在 database Session 驱动下会清理同一用户的其他 Session。
- 生产环境应保持 `SESSION_HTTP_ONLY=true`、`SESSION_SAME_SITE=lax`，并在 HTTPS 站点启用 `SESSION_SECURE_COOKIE=true`。

### 2.2 角色和对象授权

- 管理路由统一要求 `auth`、邮箱验证条件和 `role:admin`。
- 作者路由只允许 `author`、`editor`、`admin`；作者只能修改自己名下的作品，编辑和管理员可管理全部作品。
- 私信会话在读取、标记已读和 SSE 订阅前校验当前用户是否为会话成员。
- 群组读取、发消息、已读标记和 SSE 订阅要求群组成员；加成员需要 owner/admin，移除成员需要 owner，不能移除群主。
- 投稿详情只允许投稿人本人或 editor/admin 查看。
- 审核使用数据库事务和行锁，并只处理 `pending` 投稿，避免同一投稿被重复审核。

### 2.3 投稿、上传和 Markdown

- 稿件文件只接受 `.md`、`.markdown`、`.txt`，最大 5 MB；文件会读取并解析为文本，不保存原始稿件文件。
- 封面限制为图片、jpeg/jpg/png/webp，最大 5 MB；保存到 public disk 后通过 URL 使用。
- 编辑器正文和文件上传不能同时提交；空正文、首份投稿缺少简介、缺少封面都会被拒绝。
- `MarkdownRenderer` 使用 CommonMark，配置了 `html_input=strip` 和 `allow_unsafe_links=false`。
- text 格式会先进行 HTML 转义；章节阅读模板中的 `{!! $chapter['content_html'] !!}` 依赖服务端渲染结果，不能绕过 `MarkdownRenderer` 直接传入原始用户内容。

### 2.4 消息、SSE 和审计日志

- 私信正文上限为 5,000 字符，群消息正文上限为 5,000 字符，并拒绝空白消息。
- SSE 响应设置了 `Content-Type: text/event-stream`、禁止缓存和 `X-Accel-Buffering: no`；连接查询参数有时间上限。
- 私信和群组消息按递增 ID 返回，支持 `after_id`，私信还支持 `Last-Event-ID`。
- 认证、TOTP、投稿、审核和作品更新等行为写入审计日志；管理员投稿审计页只查询 `submission.*` 事件。
- `UserTwoFactorSetting` 模型已将 `secret` 和 `recovery_codes` 放入 `$hidden`；仍需核对其他 Resource、控制器或序列化路径没有绕过该模型保护。

## 3. 需要部署核验

### 3.1 管理员环境设置是高影响运维能力

管理员设置接口允许修改数据库、Session、Cache、Queue、邮件和部分应用环境配置。这是高影响的运维能力：错误配置可能导致数据库断连、Session 失效、队列停止或邮件凭据暴露。

这不是未认证权限绕过的确认；相关后台路由仍需 `auth`、邮箱条件和 `admin` 角色。部署时应核验：

1. 只授予可信管理员访问后台，并启用 TOTP。
2. 管理员账号使用独立强密码，禁止共享账号。
3. 生产环境优先通过受控发布流程修改 `.env`；如保留在线修改，记录操作者、变更前后摘要，并对敏感字段脱敏。
4. 变更后立即执行健康检查、登录验证、队列检查和邮件测试，不要把 SMTP 密码写入工单或截图。

### 3.2 代理真实 IP

应用审计日志使用 `$request->ip()`。如果部署在 Cloudflare 或反向代理后，必须在 Nginx/Apache 中仅信任官方代理网段，并正确配置 `CF-Connecting-IP` 或 `X-Forwarded-For`。这需要在真实链路中验证；不能把任意公网请求配置为可信代理，否则攻击者可以伪造来源 IP，影响审计和限流判断。

### 3.3 SSE 反向代理和容量

私信、群组和推荐控制器设置了 `text/event-stream`、禁止缓存和 `X-Accel-Buffering: no`，但当前静态检查没有证明 Nginx/Apache/Cloudflare 会保留这些行为。上线前需要验证缓冲、压缩、超时、连接数、PHP-FPM worker、断线重连和 CDN 缓存；当前也没有运行容量测试。

## 4. 潜在风险

### 4.1 外部 `cover_url` 的供应链和隐私风险

作品和投稿允许填写外部 `cover_url`。当前字段有 URL 和长度校验，Blade 属性使用转义输出；审计没有据此确认 XSS。应用代码也没有显示出服务端主动抓取该 URL 的流程，因此不能把它写成已确认 SSRF。浏览器加载外部图片仍可能造成第三方请求追踪、图片失效、混合内容、供应商变更或用户 IP 泄露。

建议在生产产品策略中选择其一：

- 只允许上传后由本站存储和分发；或
- 使用允许域名白名单，仅接受 HTTPS，并通过下载、类型检查和重新编码后存储；或
- 明确接受外部资源的隐私和可用性影响，并在隐私说明中告知用户。

### 4.2 Markdown 原始 HTML 输出的维护风险

当前阅读页使用 Blade 原始 HTML 输出。静态证据显示 `MarkdownRenderer` 设置了 `html_input=strip` 和 `allow_unsafe_links=false`，因此不能把 `{!! $chapter['content_html'] !!}` 本身写成已确认 XSS；安全性依赖所有正文都经过该服务，以及后续代码不把数据库正文直接赋给 `content_html`。新增阅读、预览、审核页面时必须复用该服务，并补充危险标签、危险链接、空正文、代码块和嵌套列表测试。动态测试尚未运行。

## 5. 生产部署安全基线

- 网站根目录必须是项目的 `public`，不能指向项目根目录。
- `.env` 不提交 Git，权限建议为 `640`，站点运行用户只能读取，备份放在 Web 根目录之外。
- `APP_ENV=production`、`APP_DEBUG=false`、`APP_URL=https://...`；修改 `.env` 后清理并重建配置缓存。
- PHP CLI 与 PHP-FPM 版本、扩展和 `php.ini` 必须一致；以 `composer check-platform-reqs --no-dev`、实际 Web 请求和目标站点配置共同核验。当前工作区没有 PHP CLI，未完成该核验。
- 只给 `storage` 和 `bootstrap/cache` 写权限，不使用项目目录 `777`。
- 数据库仅允许本机或明确白名单访问，不开放 MySQL 3306 到公网。
- 使用 Composer lock 文件安装依赖，不在生产执行 `composer update`。
- 定期备份数据库、`.env`、`storage/` 和可用代码提交，并验证可以恢复。
- aaPanel、SSH、数据库和管理员账号使用强密码并启用可用的多因素认证；面板端口和 SSH 来源应受防火墙限制。
- 生产日志不得包含 SMTP 密码、APP_KEY、TOTP secret、recovery code 或完整 Session 内容。

## 6. 建议的验证清单

### 静态、依赖和运行时检查

```bash
composer check-platform-reqs --no-dev
composer audit --locked
php artisan route:list
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

上述命令是待执行清单，不是本次审计已完成的结果。本次没有运行 PHP、Artisan、PHPUnit、浏览器动态测试、SSE 代理/容量测试、线上 Cloudflare/真实 IP 核验，也没有运行 `composer audit --locked` 或其他 Composer/npm 依赖漏洞扫描。

### 功能和授权检查

- 未登录用户不能访问私信、群组、评分、收藏、阅读记录和管理端点。
- 普通 user 不能访问作者投稿、作者作品管理和后台。
- 作者不能读取或修改其他作者的作品和章节。
- 非成员不能读取私信会话、群组内容或订阅对应 SSE。
- 已处理投稿不能被重复批准或拒绝。
- TOTP 正确码、错误码、过期挑战、恢复码和失败次数均符合预期。

### 输入和输出检查

- Markdown 中的 `<script>`、事件属性、`javascript:`、`data:` 和危险 HTML 不会进入最终页面。
- `.md`、`.markdown`、`.txt` 以外的稿件文件、超过 5 MB 的文件和空文件会被拒绝。
- 图片上传验证扩展名、MIME、尺寸和存储路径；不接受可执行文件伪装成图片。
- `cover_url` 的外链策略符合产品隐私要求。
- SSE 返回正确的事件类型、ID、心跳、缓存和代理缓冲头。

## 7. 证据路径

- 路由和认证：`routes/web.php`、`routes/api.php`、`app/Http/Controllers/AuthController.php`、`app/Http/Controllers/TwoFactorController.php`
- 角色和邮箱条件：`app/Http/Middleware/RoleMiddleware.php`、`app/Http/Middleware/EnsureEmailVerifiedIfRequired.php`
- Markdown 和上传：`app/Services/MarkdownRenderer.php`、`app/Services/ManuscriptFileParser.php`、`app/Http/Controllers/SubmissionController.php`、`app/Http/Controllers/ChapterController.php`
- 作品授权：`app/Http/Controllers/AuthorNovelController.php`、`app/Http/Controllers/ChapterController.php`
- 私信和群组：`app/Http/Controllers/PrivateMessageController.php`、`app/Http/Controllers/GroupChatController.php`
- 管理端点：`app/Http/Controllers/AdminController.php`
- 阅读输出：`app/Http/Controllers/PublicController.php`、`resources/views/pages/novels/read.blade.php`
- 生产配置：`.env.example`、`docs/aapanel-deployment.md`、`docs/linux-deployment.md`、`scripts/aapanel-update.sh`
