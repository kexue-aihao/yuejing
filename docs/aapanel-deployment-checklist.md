# aaPanel 部署检查清单

这份清单用于阅境上线前、更新后和故障排查。命令中的 `/www/wwwroot/yuejing`、域名、PHP-FPM 服务名和运行用户请替换为实际值。

## 1. PHP 版本和扩展

- [ ] 站点 PHP-FPM 和 CLI 使用同一 PHP 8.x 版本，且满足项目的 PHP `^8.3` 约束。
- [ ] CLI 检查版本、配置文件和扩展：

```bash
cd /www/wwwroot/yuejing
php -v
php --ini
php -m
composer check-platform-reqs --no-dev
```

- [ ] 生产运行时已启用 Laravel 及其运行时依赖要求的扩展：`ctype`、`filter`、`hash`、`mbstring`、`openssl`、`session`、`tokenizer`、`dom`、`libxml`、`fileinfo` 和 `pcre`。`json` 在 PHP 8.3 中属于内置能力；最终以当前环境的 `composer check-platform-reqs --no-dev` 为准。
- [ ] MySQL/MariaDB 站点已启用 `pdo` 和 `pdo_mysql`；如果使用其他数据库，已启用对应的 PDO 驱动。
- [ ] 部署脚本所需的系统命令 `curl`、`tar` 已安装；数据库备份另需 `mysqldump` 或 `mariadb-dump`。系统 `curl` 与 PHP `ext-curl` 是两项不同依赖。
- [ ] 如果应用或 Composer 包选择 cURL 网络处理器，再启用 PHP `ext-curl`；不要因部署脚本使用 `curl` 命令而误判为 PHP 扩展要求。
- [ ] 如果使用 Redis 的缓存、Session 或队列，已启用 `redis`（phpredis）；如果使用 Memcached，已启用 `memcached`。本项目默认 database 驱动，不要求这两个扩展。
- [ ] `xml`、`xmlwriter`、`phar`、`zip`、`pcov`/`xdebug`、`intl`、`gd`、`gmp`、`pcntl`、`posix` 仅按 PHPUnit、开发工具、调试或实际功能需要启用；不把 Composer 的 `suggest` 项误当作生产硬要求。

### CLI 与 PHP-FPM 对照

- [ ] aaPanel 站点设置中的 PHP 版本与 `php -v` 对应；不要只检查 CLI。
- [ ] 站点 PHP-FPM 使用的 `php.ini` 与 CLI 配置没有关键差异，至少核对 `extension_loaded()`、`disable_functions`、时区、上传限制和内存限制。
- [ ] 如需临时核对 Web PHP，可用受保护的诊断页检查 `PHP_VERSION`、`Loaded Configuration File` 和扩展；核对后立即删除，不要公开 `phpinfo()`。

## 2. PHP 函数和安全限制

- [ ] 应用源码未直接调用 `system()`、`shell_exec()`、`passthru()`、`proc_open()`、`popen()`、`pcntl_exec()` 或 PHP 原生 `mail()`，这些函数可按服务器安全基线保持禁用。
- [ ] `random_bytes()`、`hash_hmac()`、`hash_equals()` 和 JSON/过滤/编码函数用于二步验证、配置和认证流程；它们不是应随意加入 `disable_functions` 的部署项。
- [ ] 分别检查 CLI 的禁用函数配置：

```bash
php -r 'echo "CLI disable_functions: ", (ini_get("disable_functions") ?: "(none)"), PHP_EOL;'
```

- [ ] Laravel 的 `storage:link` 可能调用 `exec()` 创建符号链接；若 PHP-FPM 禁用 `exec()`，应使用上面的 `ln -s` 方式手工创建并验证 `public/storage`，而不是把 `exec()` 当作应用请求的必需函数。
- [ ] 禁用 `exec()` 时，手工创建公共存储软链接并确认权限：

```bash
cd /www/wwwroot/yuejing
ln -s ../storage/app/public public/storage
chown -h www:www public/storage
```

## 3. 站点和 PHP-FPM

- [ ] 网站根目录为 `/www/wwwroot/yuejing/public`，不是项目根目录。
- [ ] aaPanel「防跨站攻击（open_basedir）」已关闭，或添加了 `storage/` 和 `bootstrap/cache/` 路径例外。
- [ ] 如果从 Git 或源码包部署，已安装 Node.js `^20.19.0` 或 `>=22.12.0` 并执行 `npm ci` 和 `npm run build`。
- [ ] `public/build/manifest.json` 存在；只有使用已包含构建产物的正式发布包时，才可以跳过前端构建。
- [ ] Nginx 配置包含 `try_files $uri $uri/ /index.php?$query_string;`，并将 PHP 请求转发到正确的 PHP-FPM socket。
- [ ] Apache 的 `DocumentRoot` 指向 `public`，且 `public/.htaccess` 生效；需要 `AllowOverride All` 时已配置。
- [ ] Nginx 配置检查通过并已重载；Apache 配置检查通过并已重载。
- [ ] PHP-FPM 进程运行正常，站点配置的 socket 存在且 Web 服务器用户有权限访问。
- [ ] `/up` 和首页均返回 HTTP 200：

```bash
curl -fsS -o /dev/null -w '%{http_code}\n' https://example.com/up
```

## 4. 环境变量、数据库和存储

- [ ] `.env` 存在且未提交到 Git，`APP_KEY` 已生成，生产环境 `APP_DEBUG=false`，`APP_URL` 使用最终 HTTPS 地址。
- [ ] `APP_LOCALE=zh_CN` 和 `APP_FALLBACK_LOCALE=zh_CN` 已配置。
- [ ] `VITE_APP_NAME="${APP_NAME}"` 已配置。
- [ ] `DB_CONNECTION=mysql` 时，数据库、用户、密码、端口和 `utf8mb4` 配置正确。
- [ ] **MySQL 用户 host 匹配**：如果 `DB_HOST=127.0.0.1`，确认 MySQL 用户有 `'user'@'127.0.0.1'` 权限（不只是 `'user'@'localhost'`）。或把 `DB_HOST` 改为 `localhost`，走 Unix socket。
- [ ] `SESSION_SECURE_COOKIE=true` 时网站已配置 HTTPS；暂未配置 SSL 时临时改为 `false`（上线前必须改回）。
- [ ] `SESSION_ENCRYPT=true` 依赖 `APP_KEY`；`APP_KEY` 生成后不要再变更，否则所有用户 Session 立即失效。
- [ ] 已执行迁移并确认应用可以读写数据库：

```bash
php artisan migrate --force
php artisan about --only=environment
```

- [ ] `storage` 和 `bootstrap/cache` 对站点运行用户可写，其他项目目录不授予不必要的写权限。
- [ ] `public/storage` 已链接到 `storage/app/public`。
- [ ] 日志、上传文件和数据库备份位于可恢复的位置，备份不放在 Web 根目录内。

## 5. 邮件、缓存和队列

- [ ] 生产环境没有使用 `MAIL_MAILER=log`；SMTP 主机、端口、TLS/SSL、账号、授权码和发件人已配置。
- [ ] 已通过“站点设置”的 SMTP 测试或实际业务流程验证密码重置、邮箱验证邮件能够送达。
- [ ] 项目优先使用 `smtp`。如果使用 `sendmail`，已确认 `MAIL_SENDMAIL_PATH` 指向的程序存在，并验证 PHP-FPM 权限和邮件投递。
- [ ] 没有 Redis 时，保持 `SESSION_DRIVER=database`、`CACHE_STORE=database`、`QUEUE_CONNECTION=database`，并确认对应表已迁移。
- [ ] 使用 `QUEUE_CONNECTION=database` 时，已在 aaPanel 计划任务中运行 worker（注意用 aaPanel PHP 的绝对路径，如 `/www/server/php/83/bin/php`）：

```bash
/www/server/php/83/bin/php /www/wwwroot/yuejing/artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-jobs=500 --rest=1
```

- [ ] 使用 `sync` 队列时，已确认业务可接受请求内同步执行；不要配置 database 队列却不运行 worker。

## 6. 发布、缓存和回滚

- [ ] 发布前已备份数据库、`.env`、`storage/` 和当前可用代码版本。
- [ ] 生产安装依赖使用锁文件，不执行 `composer update`：

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
```

- [ ] 已按顺序重建缓存：

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- [ ] 更新失败时先保留日志和备份，再回滚代码；代码回滚不等于数据库回滚，不要未经评估直接执行 `migrate:rollback`。
- [ ] 更新完成后重新访问 `/up`、首页、登录、数据库读写、邮件测试和后台权限页面。

## 7. 常见日志位置

- [ ] Laravel：`storage/logs/laravel.log`
- [ ] PHP-FPM：aaPanel 对应 PHP 版本的错误日志
- [ ] Nginx/Apache：站点访问和错误日志
- [ ] 队列 worker：Supervisor 或 aaPanel 计划任务输出

出现扩展错误时，先对照 CLI 与 PHP-FPM 的版本、`php.ini` 和 `php -m`，再运行 `composer check-platform-reqs --no-dev`；不要只依据 aaPanel 面板中显示的扩展列表判断。
