# 阅境

这是基于 Laravel 13 的阅境阅读平台，使用 Composer 管理 PHP 依赖，默认支持 MySQL/MariaDB；前端资源由 Vite 构建。Laravel 基础信息与学习资源保留如下。

## 项目文档

- [aaPanel 生产部署](#aapanel-最简生产部署)
- [aaPanel 部署检查清单](docs/aapanel-deployment-checklist.md)
- [API 管理文档](docs/api-management.md)
- [Nginx 站点配置示例](docs/aapanel-nginx.conf.example)
- [Apache 虚拟主机配置示例](docs/aapanel-apache-vhost.conf.example)

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. The framework provides:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## aaPanel 最简生产部署

本方案面向一台 aaPanel 服务器，默认不要求 Redis 或 Docker，以 PHP 8.5、MySQL/MariaDB，以及 aaPanel 的 Nginx 或 Apache 为示例。项目当前 `composer.json` 要求 PHP `^8.3`、Laravel `^13.8`，因此也可以使用满足 Composer 平台约束的其他 PHP 8.x 版本。不要把 `.env` 提交到 Git，也不要把站点根目录指向项目根目录。

### 1. aaPanel 准备工作

在 aaPanel 中安装并确认以下组件：

- PHP 8.5，并将站点和 CLI 都切换到 PHP 8.5。
- MySQL 或 MariaDB，创建独立数据库、用户和强密码，字符集使用 `utf8mb4`。
- Nginx 或 Apache，二选一。
- Composer 2。
- **生产必需的 PHP 扩展**：Laravel 及其运行时依赖要求 `ctype`、`filter`、`hash`、`mbstring`、`openssl`、`session`、`tokenizer`、`dom`、`libxml`、`fileinfo` 和 `pcre`。`json` 在 PHP 8.3 中属于内置能力；`hash`、`pcre` 等通常随 PHP 发行版提供，但仍应以 `composer check-platform-reqs --no-dev` 的结果为准。
- **数据库扩展**：本部署示例使用 MySQL/MariaDB，必须启用 `pdo` 和 `pdo_mysql`。如果改用 SQLite、PostgreSQL 或 SQL Server，应分别启用对应的 PDO 驱动，并用实际 `.env` 配置验证；不要只安装 `pdo` 而忽略具体驱动。
- **部署命令行工具**：`aapanel-healthcheck.sh` 和 `aapanel-update.sh` 需要系统命令 `curl`、`tar`，数据库备份还需要 `mysqldump` 或 `mariadb-dump`；这不是 PHP 的 `ext-curl` 扩展。PHP `ext-curl` 只有在应用或 Composer 包实际选择 cURL 网络处理器时才需要。
- **按功能启用的扩展**：使用 Redis 缓存、Session 或队列时启用 `redis`（phpredis），使用 Memcached 时启用 `memcached`；本方案默认使用 database 驱动，不要求这两个扩展。`zip` 可帮助 Composer 和发布工具处理压缩包，但不是本项目生产代码的硬性运行时要求。
- **开发/测试扩展**：`xml`、`xmlwriter`、`phar` 以及 `pcov`/`xdebug` 主要由 PHPUnit、Pint 或其他开发工具使用；`intl`、`gd`、`gmp`、`pcntl`、`posix` 只有在对应功能、开发工具或队列管理方案实际使用时才启用，不要将 Composer 的 `suggest` 项当作生产硬要求。
- 若要编译前端资源，再安装 Node.js 20 LTS 或更高版本；本最简方案不要求 Node.js，使用仓库中已构建的资源或项目实际构建流程即可。

建议先在 aaPanel 的终端执行：

```bash
php -v
php --ini
composer --version
php -m
composer check-platform-reqs --no-dev
```

`php --ini` 和 `php -m` 检查的是 CLI。还必须在 aaPanel 站点使用的 PHP-FPM 版本和 `php.ini` 中启用同一组扩展；如果 CLI 与网站 PHP 版本不同，Composer 检查通过也不能证明浏览器请求可用。可在站点临时放置受保护的 PHP 信息页核对 `PHP_VERSION`、`Loaded Configuration File` 和 `extension_loaded()`，确认后立即删除，不要公开 `phpinfo()`。

### 2. 创建站点和上传代码

1. 在 aaPanel 创建站点，例如 `example.com`，PHP 版本选择 `8.5`。
2. 创建 MySQL/MariaDB 数据库，并记下数据库名、用户名、密码、主机和端口。
3. 将代码放到例如 `/www/wwwroot/yuejing`。可以使用 Git 克隆，也可以上传发行包；生产环境不要上传 `.env`、`vendor/` 和本地测试数据。
4. 在站点设置中把**运行目录/网站根目录**设置为：

   `/www/wwwroot/yuejing/public`

   这一步是必须的，项目根目录不能作为 Web 根目录，否则 `.env`、`composer.json` 等文件可能暴露。
5. Nginx 配置参见 [`docs/aapanel-nginx.conf.example`](docs/aapanel-nginx.conf.example)，Apache 配置参见 [`docs/aapanel-apache-vhost.conf.example`](docs/aapanel-apache-vhost.conf.example)。Apache 需要允许 `public/.htaccess` 生效；Nginx 需要 `try_files` 转发到 `index.php`。

### 3. 配置环境变量

在项目根目录执行：

```bash
cd /www/wwwroot/yuejing
cp .env.example .env
```

编辑 `.env`，至少填写以下值：

```dotenv
APP_NAME="阅境"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=实际数据库名
DB_USERNAME=实际数据库用户
DB_PASSWORD="实际数据库密码"

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=实际邮箱账号
MAIL_PASSWORD="实际邮箱密码或授权码"
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

`APP_KEY` 留空时由后续命令生成。生产环境必须保持 `APP_DEBUG=false`，`APP_URL` 使用最终 HTTPS 地址。没有 Redis 时保持 `SESSION_DRIVER=database`、`CACHE_STORE=database`、`QUEUE_CONNECTION=database` 即可；项目已有对应数据库迁移。若暂时不需要队列，可根据业务改为 `QUEUE_CONNECTION=sync`，不要为了部署额外安装 Redis。

### 4. 安装依赖和初始化数据库

以项目目录为当前目录执行：

```bash
cd /www/wwwroot/yuejing
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan yuejing:admin
```

`yuejing:admin` 会读取 `.env` 中的 `YUEJING_ADMIN_NAME`、`YUEJING_ADMIN_EMAIL` 和 `YUEJING_ADMIN_PASSWORD`：首次创建管理员时密码必填且至少 12 位；已有管理员不会在普通同步时重置密码，确需重置时使用 `php artisan yuejing:admin --reset-password`。不要在生产环境使用 `password` 等弱密码。

`YUEJING_EMAIL_VERIFICATION_REQUIRED=false` 是默认值，只有管理员在“站点设置”中开启后，邮箱验证要求才对新注册和受保护功能生效。

如果 PHP 禁用了 `exec()`，`php artisan storage:link` 可能无法执行，可手动创建链接：

```bash
ln -s ../storage/app/public public/storage
chown -h www:www public/storage
```

### PHP 函数和 `disable_functions`

源码扫描未发现应用直接调用 `system()`、`shell_exec()`、`passthru()`、`proc_open()`、`popen()`、`pcntl_exec()` 或 PHP 原生 `mail()`。二步验证使用的 `random_bytes()`、`hash_hmac()`、`hash_equals()`，以及 JSON、过滤、字符串和编码函数属于 PHP 核心/标准运行时能力，不是需要在 aaPanel 中额外安装的扩展，也不要把它们加入 `disable_functions`。

`exec()` 只与 Laravel 的 `php artisan storage:link` 创建符号链接有关，不是浏览器请求的业务必需函数。CLI 和 PHP-FPM 可能加载不同的 `php.ini`，请分别检查：

```bash
php -r 'echo "CLI disable_functions: ", (ini_get("disable_functions") ?: "(none)"), PHP_EOL;'
```

如果站点 PHP-FPM 禁用了 `exec()`，使用上面的 `ln -s` 手工创建 `public/storage`，不要为了这个命令放开 `system()`、`shell_exec()` 等更高权限的函数。修改 `disable_functions` 前应遵循服务器安全基线，并重启对应的 PHP-FPM 服务。

生产环境优先使用 `smtp` 邮件驱动。项目的密码重置、邮箱验证和后台 SMTP 测试都通过 Laravel/Symfony 邮件抽象发送，不是应用直接调用 `mail()`；如果改用 `sendmail`，还必须确认服务器存在 `MAIL_SENDMAIL_PATH` 指向的 `sendmail` 程序，并验证 PHP-FPM 对该传输方式的实际权限。

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

如果项目包含前端源码并且没有可用的构建产物，再执行：

```bash
npm ci
npm run build
```

不要在生产服务器执行 `composer update`；依赖版本应由 `composer.lock` 固定。每次发布前后可执行：

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan about --only=environment
```

当前路由均使用控制器，生产部署可执行 `route:cache`；发布后建议按上述顺序重建配置、路由和视图缓存。

如果启用 `QUEUE_CONNECTION=database`，需要让队列消费者持续运行；aaPanel 可使用 Supervisor 或常驻进程运行：

```bash
cd /www/wwwroot/yuejing
php artisan queue:work database --sleep=3 --tries=3 --timeout=90
```

如果业务明确不使用异步队列，可将 `QUEUE_CONNECTION` 改为 `sync`；不要同时把队列设为 `database` 却不运行 worker。

### 5. 设置权限

将项目文件所有者设置为 aaPanel 站点实际运行用户。以下命令中的 `www` 只是常见示例，请先在 aaPanel 查看实际用户，避免把目录交给错误用户：

```bash
cd /www/wwwroot/yuejing
chown -R www:www /www/wwwroot/yuejing
find /www/wwwroot/yuejing -type f -exec chmod 644 {} \;
find /www/wwwroot/yuejing -type d -exec chmod 755 {} \;
chmod -R 775 storage bootstrap/cache
chmod 640 .env
```

若 aaPanel 使用其他用户（例如站点专属用户），将上述 `www:www` 替换为该用户。只给 `storage`、`bootstrap/cache` 写权限，不要给整个项目 `777`。

### 6. 配置 HTTPS

在 aaPanel 的站点设置中申请或导入证书，开启 HTTPS，并打开 HTTP 到 HTTPS 重定向。确认：

- `APP_URL` 已改为 `https://` 地址。
- 反向代理或 CDN 正确转发 `X-Forwarded-Proto`，否则应用可能误判请求协议。
- 邮件服务的 TLS 端口和加密方式与服务商要求一致。
- `/up` 和首页均能通过 HTTPS 访问。

### 7. SMTP 配置和验证

生产环境不要使用 `.env` 默认的 `MAIL_MAILER=log`。常见 SMTP 配置如下：

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@example.com
MAIL_PASSWORD="SMTP授权码"
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"

YUEJING_EMAIL_VERIFICATION_REQUIRED=false
YUEJING_ADMIN_NAME="网站管理员"
YUEJING_ADMIN_EMAIL=admin@example.com
YUEJING_ADMIN_PASSWORD="请使用至少12位随机强密码"
```

修改 `.env` 后刷新配置缓存：

```bash
php artisan config:clear
php artisan config:cache
```

通过业务实际发送一封测试邮件；如果供应商要求 SSL，通常使用端口 `465`，并按供应商文档设置对应的 `MAIL_SCHEME`/加密方式。不要把 SMTP 密码写入 Git、脚本参数或工单截图。

### 8. aaPanel 定时任务

在 aaPanel「计划任务」中添加 Shell 脚本任务。建议将任务用户设置为站点运行用户，任务命令使用绝对路径，并将输出写入站点外的日志目录。

健康检查每 5 分钟：

```bash
HEALTHCHECK_URL=https://example.com/up /bin/bash /www/wwwroot/yuejing/scripts/aapanel-healthcheck.sh >> /www/server/cron/yuejing-healthcheck.log 2>&1
```

数据库和文件备份由 aaPanel「计划任务」或「数据库」备份功能完成，建议至少每日一次，并把备份保留在站点目录之外。`aapanel-update.sh` 还会在更新前备份 `.env`、`storage/` 和数据库；脚本需要 `curl`、`tar` 以及 `mysqldump` 或 `mariadb-dump`。更新脚本不要高频自动运行，建议由人工确认后执行：

```bash
DEPLOY_BRANCH=main /bin/bash /www/wwwroot/yuejing/scripts/aapanel-update.sh >> /www/server/cron/yuejing-update.log 2>&1
```

更新脚本默认要求 Git 工作区干净且当前分支为 `main`（可用 `DEPLOY_BRANCH` 覆盖），会在更新前备份 `.env`、`storage` 和数据库，使用 `git pull --ff-only`，执行依赖安装、平台检查、迁移、缓存构建和 `/up` 健康检查。失败时只回滚代码提交，不会自动执行 `migrate:rollback`，以避免破坏数据；请根据备份和迁移记录人工处理数据库回退。只有在已有独立数据库备份并明确接受风险时，才设置 `SKIP_DB_BACKUP=1`。

### 9. 备份、更新和回滚

至少保留以下备份：

- 数据库 SQL 转储。
- `.env` 文件的受限权限副本。
- `storage/` 中的用户上传文件和应用日志（日志可按策略单独轮转）。
- 最近一次可用代码提交或发行包。

手工更新前建议先做一次备份：

```bash
cd /www/wwwroot/yuejing
php artisan down --retry=60
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

若发布失败，先查看 `storage/logs/laravel.log` 和 aaPanel/PHP-FPM/Nginx/Apache 日志。代码回滚示例：

```bash
cd /www/wwwroot/yuejing
git log --oneline -5
git reset --hard <上一个可用提交>
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

代码回滚不等于数据库回滚。不要直接运行 `php artisan migrate:rollback`，除非已确认迁移影响、完成数据库备份并有明确的人工回退方案。

### 10. 健康检查和故障排查

完整的上线前和更新后核对项请参阅 [aaPanel 部署检查清单](docs/aapanel-deployment-checklist.md)。Laravel 13 骨架已注册 `/up` 健康路由。检查站点：

```bash
curl -fsS -o /dev/null -w '%{http_code}\n' https://example.com/up
```

正常应返回 `200`。也可以使用仓库内脚本：

```bash
HEALTHCHECK_URL=https://example.com/up /bin/bash scripts/aapanel-healthcheck.sh
```

常见检查顺序：

1. `php -v`、`php -m` 和 `composer check-platform-reqs --no-dev` 是否使用 PHP 8.5。
2. 站点根目录是否确实为 `public`，Nginx/Apache 是否把请求交给 `public/index.php`。
3. `.env` 的 `APP_KEY`、数据库连接、`APP_DEBUG=false` 和 `APP_URL` 是否正确。
4. `storage`、`bootstrap/cache` 是否可写，`public/storage` 是否存在。
5. `storage/logs/laravel.log`、PHP-FPM、Nginx/Apache 日志是否有权限或扩展错误。
6. 更新配置后是否重新执行 `php artisan config:cache`。

## API 管理

认证、Session/CSRF 约定、管理端点、请求字段、响应码和投稿审核流程请参阅 [API 管理文档](docs/api-management.md)。当前管理 API 是基于 Session Cookie 的有状态接口，不提供 Bearer Token；接入脚本或前端时不要省略 CSRF Token。

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks. [Laracasts](https://laracasts.com) contains tutorials on Laravel, PHP, unit testing, and JavaScript. You can also use [Laravel Learn](https://laravel.com/learn).

## Contributing

Thank you for considering contributing to the Laravel framework. The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

Please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an email to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com).

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
