# 阅境

这是基于 Laravel 13 的应用骨架，使用 Composer 管理 PHP 依赖，默认支持 MySQL/MariaDB。Laravel 基础信息与学习资源保留如下。

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

本方案面向一台 aaPanel 服务器，默认不要求 Redis 或 Docker，适用于 PHP 8.5、MySQL/MariaDB，以及 aaPanel 的 Nginx 或 Apache。项目当前 `composer.json` 要求 PHP `^8.3`、Laravel `^13.8`，PHP 8.5 满足该约束。不要把 `.env` 提交到 Git，也不要把站点根目录指向项目根目录。

### 1. aaPanel 准备工作

在 aaPanel 中安装并确认以下组件：

- PHP 8.5，并将站点和 CLI 都切换到 PHP 8.5。
- MySQL 或 MariaDB，创建独立数据库、用户和强密码，字符集使用 `utf8mb4`。
- Nginx 或 Apache，二选一。
- Composer 2。
- PHP 扩展：`bcmath`、`ctype`、`curl`、`dom`、`fileinfo`、`filter`、`mbstring`、`openssl`、`pcre`、`pdo`、`pdo_mysql`、`session`、`tokenizer`、`xml`、`zip`。Laravel 依赖的扩展以 `composer check-platform-reqs` 的结果为准。
- 若要编译前端资源，再安装 Node.js 20 LTS 或更高版本；本最简方案不要求 Node.js，使用仓库中已构建的资源或项目实际构建流程即可。

建议先在 aaPanel 的终端执行：

```bash
php -v
composer --version
php -m
```

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
composer check-platform-reqs
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
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
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
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

数据库和文件备份由 aaPanel「计划任务」或「数据库」备份功能完成，建议至少每日一次，并把备份保留在站点目录之外。更新脚本不要高频自动运行，建议由人工确认后执行：

```bash
DEPLOY_BRANCH=main /bin/bash /www/wwwroot/yuejing/scripts/aapanel-update.sh >> /www/server/cron/yuejing-update.log 2>&1
```

更新脚本默认要求 Git 工作区没有已跟踪的本地修改，会在更新前备份 `.env`、`storage` 和数据库，使用 `git pull --ff-only`，执行依赖安装和迁移。失败时只回滚代码提交，不会自动执行 `migrate:rollback`，以避免破坏数据；请根据备份和迁移记录人工处理数据库回退。

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

Laravel 13 骨架已注册 `/up` 健康路由。检查站点：

```bash
curl -fsS -o /dev/null -w '%{http_code}\n' https://example.com/up
```

正常应返回 `200`。也可以使用仓库内脚本：

```bash
HEALTHCHECK_URL=https://example.com/up /bin/bash scripts/aapanel-healthcheck.sh
```

常见检查顺序：

1. `php -v`、`php -m` 和 `composer check-platform-reqs` 是否使用 PHP 8.5。
2. 站点根目录是否确实为 `public`，Nginx/Apache 是否把请求交给 `public/index.php`。
3. `.env` 的 `APP_KEY`、数据库连接、`APP_DEBUG=false` 和 `APP_URL` 是否正确。
4. `storage`、`bootstrap/cache` 是否可写，`public/storage` 是否存在。
5. `storage/logs/laravel.log`、PHP-FPM、Nginx/Apache 日志是否有权限或扩展错误。
6. 更新配置后是否重新执行 `php artisan config:cache`。

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
