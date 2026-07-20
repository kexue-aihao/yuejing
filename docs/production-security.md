# 阅境生产安全部署规范

本文面向 aaPanel 管理员、Linux 运维人员和上线审核人员，说明上线前必须落实或核验的安全配置。文中的“核验”表示需要在目标服务器或代理链路中实际检查，不能把示例配置当成当前环境已验证事实。完整安装步骤请配合 [`aaPanel 完整部署教程`](aapanel-deployment.md)、[`标准 Linux 部署`](linux-deployment.md) 和 [`部署检查清单`](aapanel-deployment-checklist.md) 使用；功能和接口行为分别参阅 [`项目使用手册`](project-usage-manual.md) 与 [`API 接口大全`](api-reference.md)。

## 1. 版本和运行时

- PHP 必须满足 `composer.json` 的 `^8.3`，并确认 CLI 与 PHP-FPM 使用同一版本、扩展和关键 `php.ini` 安全设置。
- Node.js 必须满足 Vite 8 的要求：`^20.19.0` 或 `>=22.12.0`；源码部署需要执行 `npm ci` 和 `npm run build`。
- `composer.lock` 和 `package-lock.json` 必须随发布版本存在。生产依赖使用锁文件安装，不执行 `composer update`：

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
npm ci
npm run build
```

- MySQL/MariaDB 至少启用 `pdo`、`pdo_mysql` 以及 Composer 检查要求的 PHP 扩展；最终以 CLI 检查和 Web 请求共同核验。Redis 不是默认硬依赖，当前默认 Session、Cache、Queue 使用 database 驱动。
- `public/build/manifest.json` 必须存在；没有它时 `@vite` 页面会出现资源错误。

`composer check-platform-reqs --no-dev` 是手工生产安装/核验命令。注意：`scripts/aapanel-update.sh` 实际执行的是不带 `--no-dev` 的 `composer check-platform-reqs`，两者不能混写。

## 2. aaPanel 站点配置

1. 网站根目录设置为 `/www/wwwroot/项目目录/public`，禁止把项目根目录作为 Web 根目录。
2. Nginx 使用 `try_files $uri $uri/ /index.php?$query_string;`；PHP 请求转发到实际 PHP-FPM socket。
3. Apache 的 `DocumentRoot` 指向 `public`，并确认 `public/.htaccess` 生效。
4. 只允许站点运行用户写入 `storage` 和 `bootstrap/cache`；不要使用 `chmod -R 777`。
5. `.env` 建议 `640`，备份放在站点目录之外；确认 `.git`、备份包、日志和配置不能通过 HTTP 访问。
6. aaPanel 防跨站/open_basedir 若启用，允许列表至少要覆盖项目根目录及 Laravel 运行时需要读取的 `vendor`、`public`、`storage` 和 `bootstrap/cache`；修改后验证日志、缓存和上传可写。不要只加入两个写目录后假定 PHP 可以读取整个应用。

推荐的生产 `.env` 核心项：

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=zh_CN

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=生产数据库
DB_USERNAME=独立数据库用户
DB_PASSWORD="长随机密码"

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=smtp
```

`SESSION_SECURE_COOKIE=true` 只有在 HTTPS 已经生效时才能启用。生成 `APP_KEY` 后不要随意更换，否则加密 Session 会失效，用户需要重新登录。

## 3. HTTPS、Cloudflare 和真实 IP

- 生产站点必须使用 HTTPS，并将 HTTP 重定向到 HTTPS。
- Cloudflare 到源站建议使用 `Full (strict)`；源站防火墙只允许 Cloudflare 官方网段回源，或只允许明确的反向代理来源。当前仓库只提供配置格式示例，不代表目标站点已经完成配置。
- `set_real_ip_from` 必须填写官方、最新、精确的代理网段；不要直接信任任意 `X-Forwarded-For`。Cloudflare 的 `CF-Connecting-IP` 只有在源站拒绝绕过 Cloudflare 的直连时才可作为可信来源。
- `TRUSTED_PROXIES` 只填写精确的代理 IP/CIDR，不填写 `*`、`0.0.0.0/0` 或 `::/0`。
- 证书续期后确认 Web 服务器重新加载证书，并通过浏览器检查首页、登录、`/up` 和静态资源。

## 4. 数据库、Session、Cache 和 Queue

- 数据库用户只授予当前数据库权限；MySQL 3306 不对公网开放。
- 首次部署完成迁移后执行 `php artisan migrate:status`，确认当前迁移已完成；database 驱动对应的 `sessions`、`cache`、`jobs` 表也必须存在。
- `QUEUE_CONNECTION=database` 时必须运行持续 worker，例如 aaPanel Supervisor 或 systemd；不运行 worker 会导致异步任务积压。
- 明确不需要异步任务时可使用 `QUEUE_CONNECTION=sync`，但需要接受任务在 Web 请求内执行的延迟和失败影响。
- 不要在生产环境使用 `db:seed`，除非已经审阅当前 seeder 的账号和数据内容。
- 数据库、`.env`、`storage/` 和最近可用代码提交至少每日备份；备份必须在站点目录之外并限制权限。

## 5. 邮件和管理员账号

- 生产环境不要使用 `MAIL_MAILER=log`；使用 SMTP 时通过实际密码重置、邮箱验证或后台测试验证投递。
- SMTP 密码、数据库密码、APP_KEY、管理员初始密码不得进入 Git、命令历史、脚本参数、截图或公开日志。
- 执行 `php artisan yuejing:admin` 创建管理员后，建议删除 `.env` 中的 `YUEJING_ADMIN_PASSWORD`，然后重建配置缓存。
- 管理员设置页可修改数据库、Session、Cache、Queue、邮件和部分应用环境配置；这是已认证管理员的高影响运维能力，只向可信管理员开放，并强制启用 TOTP。生产变更优先走受控发布流程。
- 管理员、aaPanel、SSH 和数据库使用不同的强密码；能启用多因素认证时应启用。

## 6. 上传、Markdown 和外链

- 稿件文件只允许 `.md`、`.markdown`、`.txt`，最大 5 MB；封面只允许 jpeg/jpg/png/webp，最大 5 MB。
- 上传原始稿件不直接作为可执行文件提供；正文应经过 `MarkdownRenderer` 再输出。
- 新增正文预览、审核或导出功能时，不能绕过服务端渲染和 HTML 转义。
- 外部 `cover_url` 会带来第三方追踪、失效、混合内容和资源替换风险；当前页面是浏览器加载外部图片，不应据此声称存在已确认的服务器端 SSRF。生产环境优先使用本站存储或域名白名单。
- `public/storage` 只指向 `storage/app/public`，不要把 `.env`、数据库转储或日志放入 `public`。

## 7. SSE 和反向代理

私信、群组和推荐使用 SSE。应用控制器设置了 `text/event-stream`、禁止缓存和 `X-Accel-Buffering: no`，但反向代理、CDN 和容量行为仍需部署核验：

- 禁止响应缓冲，保留应用返回的 `X-Accel-Buffering: no`。
- FastCGI、代理和 CDN 超时要覆盖实际 SSE 请求时长。
- 不缓存 `/api/*/stream` 响应，不对 SSE 响应做压缩或静态资源缓存规则复用；示例 Nginx/Apache 文件本身不等于这些条件已经在服务器生效。
- 监控 PHP-FPM worker、连接数、CPU、内存和慢请求；大量长连接时评估独立实时服务。
- 使用浏览器开发者工具或 `curl -N` 验证事件、心跳、`Last-Event-ID`/`after_id` 和断线重连行为。

## 8. 发布、更新和回滚

发布前：

```bash
git status --short
php artisan down --retry=60
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

更新分支必须以实际线上分支为准。`scripts/aapanel-update.sh` 当前默认值是 `main`，如果线上使用其他分支，必须显式设置：

```bash
DEPLOY_BRANCH="<实际线上分支>" /bin/bash scripts/aapanel-update.sh
```

执行前确认工作区干净、脚本路径正确、数据库备份命令可用。脚本只检查已跟踪文件的工作区和暂存区差异，不会主动拒绝所有未跟踪文件，因此仍需人工确认未跟踪文件不会被更新覆盖。脚本默认在项目父目录创建 `<项目父目录>/yuejing-backups/<时间戳>`，备份 `.env`、当前提交号、`storage/` 和数据库；`SKIP_DB_BACKUP=1` 会跳过数据库备份，只能作为已确认独立备份后的人工覆盖项。脚本执行 `git pull --ff-only`、Composer 安装、前进迁移、Laravel 缓存和 `/up` 检查，但不执行 `npm ci` 或 `npm run build`。失败时只尝试回滚代码提交和 Composer/缓存，不回滚数据库迁移、`.env` 或 `storage/`。不要在生产执行 `composer update`、未经确认的 `git reset --hard`、`git clean -fd` 或 `migrate:rollback`。

## 9. 日志、监控和应急

- 监控 `/up`、首页、登录页、PHP-FPM、Nginx/Apache、Laravel 和队列日志。
- 生产 `APP_DEBUG` 永远为 `false`；错误详情只从服务器日志获取，并定期轮转和限制权限。
- 出现异常时先启用维护模式、保留日志和备份，再检查依赖、配置缓存、数据库迁移、文件权限和前端 manifest。
- 若怀疑凭据泄露，立即轮换管理员、数据库、SMTP、Cloudflare、aaPanel 和 SSH 凭据，并使旧 Session 失效；不要只删除日志。
- 发生安全事件时记录时间线、受影响账号、请求来源、日志证据、已采取措施和恢复结果。

## 10. 上线前最低检查

- [ ] `/up`、首页、登录和静态资源返回预期的 `2xx` 状态；仓库健康检查脚本接受任意 `2xx`，不会单独验证 SMTP、数据库写入、Session、Queue 或 SSE。
- [ ] `APP_DEBUG=false`，网站根目录为 `public`，`.env` 不可下载。
- [ ] CLI/PHP-FPM 版本和扩展一致，Composer 平台检查通过。
- [ ] 数据库、Session、Cache、Queue 迁移完成；队列 worker 已运行或明确使用 `sync`。
- [ ] SMTP、密码重置、邮箱验证和管理员登录已验证。
- [ ] 普通用户、作者、编辑和管理员的权限边界已验证。
- [ ] Markdown、上传、外链、SSE 和 Cloudflare 真实 IP 已完成针对性测试。
- [ ] 数据库、`.env`、`storage/` 和代码版本备份可定位、可读取且不在 Web 根目录内。
