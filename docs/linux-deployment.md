# 标准 Linux 部署

本文面向不使用 aaPanel 的 Linux 运维人员，提供一套生产部署流程，目标环境为 **Ubuntu 24.04 LTS + Nginx + PHP-FPM + MySQL + Node.js**。Debian 12/13 或其他 Debian 系发行版可以参考执行，但 PHP、Node.js 软件源和 PHP-FPM socket 需要按实际版本调整。安全基线请配合 [`生产安全部署规范`](production-security.md)；接口和功能行为分别参阅 [`API 接口大全`](api-reference.md) 与 [`项目使用手册`](project-usage-manual.md)。

项目是 Laravel 13，`composer.json` 要求 PHP `^8.3`。当前前端依赖使用 Vite 8，Node.js 需要满足 `^20.19.0` 或 `>=22.12.0`。本文使用 PHP 8.3 和 Node.js 22。

## 1. 部署参数

以下参数必须替换成你的实际值：

```bash
export DOMAIN="online.example.com"
export PROJECT_DIR="/var/www/yuejing"
export DEPLOY_USER="$(id -un)"
export DB_NAME="yuejing"
export DB_USER="yuejing"
```

建议使用独立的生产服务器用户和独立数据库用户。不要把项目根目录配置为网站根目录，Nginx 的网站根目录必须是：

```text
/var/www/yuejing/public
```

本文不自动申请域名。开始部署前，先将域名的 DNS `A`/`AAAA` 记录指向服务器，并确保云防火墙允许 TCP `80` 和 `443`。

## 2. 安装系统依赖

以下命令适用于 Ubuntu 24.04。使用具有 `sudo` 权限的账号执行：

```bash
sudo apt update
sudo apt install -y \
    ca-certificates curl git unzip rsync ufw \
    nginx mysql-server \
    php8.3-cli php8.3-fpm php8.3-common \
    php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-bcmath
```

确认 PHP CLI 和 PHP-FPM 使用同一版本：

```bash
php -v
php --ini
php -m | sort
systemctl is-active php8.3-fpm
ls -l /run/php/php8.3-fpm.sock
```

Laravel 运行时至少需要 `ctype`、`filter`、`hash`、`mbstring`、`openssl`、`session`、`tokenizer`、`dom`、`libxml`、`fileinfo`、`pcre`，MySQL 还需要 `pdo_mysql`。安装完成后，后面的 `composer check-platform-reqs --no-dev` 是最终判断依据。

启动并设置系统服务开机启动：

```bash
sudo systemctl enable --now nginx mysql php8.3-fpm
```

执行 MySQL 基础安全配置：

```bash
sudo mysql_secure_installation
```

生产环境不要开放 MySQL 的公网端口。通常只需要让本机应用通过 `127.0.0.1` 或 Unix socket 连接 MySQL。

## 3. 安装 Composer 2 和 Node.js

安装 Composer：

```bash
sudo apt install -y composer
composer --version
```

Composer 版本应为 2.x。如果系统 Composer 版本过旧，应按照 [Composer 官方安装说明](https://getcomposer.org/download/) 更新，不要使用 Composer 1。

Ubuntu 24.04 自带的 Node.js 版本可能低于 Vite 8 要求，因此使用 NodeSource 安装 Node.js 22：

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node --version
npm --version
```

`node --version` 应满足 `v22.12.0` 或更高版本。若使用 Node.js 20，必须至少是 `v20.19.0`。

## 4. 创建 MySQL 数据库

先进入 MySQL 管理终端：

```bash
sudo mysql
```

在 MySQL 提示符中执行下面的 SQL。将密码替换成长度足够的随机密码，不要使用示例值：

```sql
CREATE DATABASE yuejing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'yuejing'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_A_LONG_RANDOM_PASSWORD';
GRANT ALL PRIVILEGES ON yuejing.* TO 'yuejing'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

如果数据库或用户已经存在，不要重复执行 `CREATE`；应先检查现有配置，再使用 `ALTER USER` 或 `GRANT` 修正权限。

## 5. 获取代码

推荐使用 Git 克隆。生产环境部署固定分支或具体提交，不要直接跟随不受控的开发分支。下面使用变量表示实际线上分支；`master` 只能作为示例，不能直接假定为线上分支：

```bash
sudo mkdir -p /var/www
sudo chown "$DEPLOY_USER":www-data /var/www
export DEPLOY_BRANCH="<实际线上分支>"
sudo git clone --branch "$DEPLOY_BRANCH" https://你的代码仓库地址.git "$PROJECT_DIR"
cd "$PROJECT_DIR"
```

如果代码已经上传到服务器，则确认当前目录就是项目根目录，并检查以下文件存在：

```bash
test -f artisan
test -f composer.json
test -f package.json
```

不要在 Web 根目录暴露项目根目录。项目根目录中的 `.env`、`vendor`、`composer.json` 和 `storage` 不应被 Nginx 直接访问。

## 6. 配置环境变量

创建生产环境配置：

```bash
cd "$PROJECT_DIR"
cp .env.example .env
chmod 640 .env
```

编辑 `.env`：

```bash
sudoedit "$PROJECT_DIR/.env"
```

至少设置以下内容：

```dotenv
APP_NAME="阅境"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://online.example.com
APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=zh_CN

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yuejing
DB_USERNAME=yuejing
DB_PASSWORD="数据库用户密码"

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@example.com
MAIL_PASSWORD="SMTP授权码或密码"
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"

YUEJING_EMAIL_VERIFICATION_REQUIRED=false
YUEJING_ADMIN_NAME="网站管理员"
YUEJING_ADMIN_EMAIL=admin@example.com
YUEJING_ADMIN_PASSWORD="至少12位随机强密码"
```

说明：

- `APP_KEY` 不需要手工填写，后面的 `php artisan key:generate --force` 会生成。
- 生产环境必须保持 `APP_DEBUG=false`，不要通过浏览器显示堆栈和环境信息。
- `APP_URL` 使用最终 HTTPS 地址；`SESSION_SECURE_COOKIE=true` 要求用户通过 HTTPS 访问。
- 当前项目默认使用数据库保存 Session、Cache 和 Queue，因此必须先执行数据库迁移。
- 密码重置、邮箱验证和后台 SMTP 测试依赖真实 SMTP。生产环境不要使用 `MAIL_MAILER=log`。
- 如果明确不需要异步队列，可以把 `QUEUE_CONNECTION` 改为 `sync`，这样不需要运行队列 worker，但任务会在请求中同步执行。

## 7. 安装 PHP 依赖和构建前端

必须在切换 Nginx 流量前完成下面步骤。`public/build` 被 Git 忽略，源码克隆后不能假设前端构建产物存在：

```bash
cd "$PROJECT_DIR"
test -f composer.lock && test -f package-lock.json || { echo "缺少 Composer/npm 锁文件，停止生产部署" >&2; exit 1; }
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
npm ci
npm run build

test -f vendor/autoload.php
test -f public/build/manifest.json
```

如果 `composer check-platform-reqs` 报 `could not find driver`，检查 PHP-FPM/CLI 是否安装并启用了 `php8.3-mysql` 和 `pdo_mysql`。如果 `npm run build` 报 Node engine 错误，检查 Node.js 是否至少为 20.19.0 或 22.12.0。

## 8. 初始化 Laravel

```bash
cd "$PROJECT_DIR"
php artisan key:generate --force
php artisan migrate --force
php artisan migrate:status
```

创建初始管理员：

```bash
php artisan yuejing:admin
```

首次创建管理员时，`.env` 中的 `YUEJING_ADMIN_PASSWORD` 必须存在且至少 12 位。管理员创建成功后，建议从 `.env` 删除该变量，再重建配置缓存：

```bash
sudo sed -i '/^YUEJING_ADMIN_PASSWORD=/d' "$PROJECT_DIR/.env"
```

以后如需重置管理员密码，临时写入新的 `YUEJING_ADMIN_PASSWORD`，执行：

```bash
php artisan config:clear
php artisan yuejing:admin --reset-password
php artisan config:cache
```

创建存储链接。先检查目标路径，避免覆盖已有的真实目录或错误链接：

```bash
cd "$PROJECT_DIR"
if [ -L public/storage ]; then
    test "$(readlink public/storage)" = "../storage/app/public"
elif [ -e public/storage ]; then
    echo "public/storage 已存在且不是正确的符号链接" >&2
    exit 1
else
    ln -s ../storage/app/public public/storage
fi
```

重建生产缓存：

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan about --only=environment
```

不要在生产环境执行 `php artisan db:seed`，因为当前测试 seeder 会创建测试账号。生产管理员应使用 `php artisan yuejing:admin` 创建。

## 9. 设置文件权限

标准 Ubuntu/Debian 的 PHP-FPM 和 Nginx 用户通常是 `www-data`。部署用户负责 Git、Composer 和前端构建，运行用户负责读取代码和写入运行时目录：

```bash
sudo chown -R "$DEPLOY_USER":www-data "$PROJECT_DIR"
sudo find "$PROJECT_DIR" -type d -exec chmod 755 {} \;
sudo find "$PROJECT_DIR" -type f -exec chmod 644 {} \;
sudo chmod 640 "$PROJECT_DIR/.env"
sudo chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
sudo chown -R www-data:www-data "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
```

验证 PHP-FPM 用户能够写日志和缓存：

```bash
sudo -u www-data test -w "$PROJECT_DIR/storage"
sudo -u www-data test -w "$PROJECT_DIR/bootstrap/cache"
sudo -u www-data test -r "$PROJECT_DIR/.env"
```

如果项目放在其他目录，确保 `www-data` 对 `/var`、`/var/www` 和项目路径的父目录具有执行权限。不要使用 `chmod -R 777`。

## 10. 配置 Nginx

创建站点配置：

```bash
sudoedit /etc/nginx/sites-available/yuejing
```

先使用 HTTP 配置，让 Certbot 可以完成 ACME 验证：

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name online.example.com;

    root /var/www/yuejing/public;
    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
    }

    location ^~ /build/assets/ {
        access_log off;
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files $uri =404;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

将示例中的 `online.example.com` 和 `/var/www/yuejing` 替换成实际值。标准 Ubuntu/Debian 使用的 PHP-FPM socket 通常是 `/run/php/php8.3-fpm.sock`，不要直接复制 aaPanel 示例中的 `/tmp/php-cgi-85.sock`。

启用站点并检查配置：

```bash
sudo ln -s /etc/nginx/sites-available/yuejing /etc/nginx/sites-enabled/yuejing
sudo nginx -t
sudo systemctl reload nginx
```

如果 `default` 站点与本项目的域名或监听端口冲突，先查看其内容并确认已有配置已备份，再按系统维护流程停用它；不要把删除默认站点作为无条件的部署步骤：

```bash
sudo nginx -T 2>&1 | grep -nE 'sites-enabled/default|server_name|listen '
```

确认不再需要该站点后，可使用发行版提供的停用方式（例如将 `default` 的链接移出 `sites-enabled`），然后再次执行 `sudo nginx -t`。不要在未核对目标的情况下直接删除配置文件。

验证 PHP-FPM socket 与服务状态：

```bash
systemctl is-active php8.3-fpm
stat /run/php/php8.3-fpm.sock
systemctl is-active nginx
```

## 11. 配置 HTTPS

确认 DNS 已生效后安装 Certbot：

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx --redirect -d online.example.com
```

按照提示填写邮箱并同意条款。Certbot 会申请证书、更新 Nginx 配置并配置 HTTP 到 HTTPS 重定向。检查自动续期：

```bash
sudo certbot renew --dry-run
```

如果使用 Cloudflare 或其他 CDN：

- DNS 必须指向这台服务器或正确的源站地址。
- CDN 到源站应使用 HTTPS。
- 反向代理必须转发 `X-Forwarded-Proto: https`。
- 源站防火墙仍需允许 CDN 回源访问 `443`。

## 12. 防火墙

确认 SSH 端口后再启用 UFW，避免把自己锁在服务器外：

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status verbose
```

不要开放 `3306` 到公网，除非你有明确的远程数据库需求并配置了来源 IP 限制。

## 13. 队列和定时发布

项目默认 `QUEUE_CONNECTION=database`。如果保留该配置，应创建常驻 worker：

```bash
sudoedit /etc/systemd/system/yuejing-queue.service
```

```ini
[Unit]
Description=Yuejing Laravel queue worker
After=network.target mysql.service php8.3-fpm.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/yuejing
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --timeout=90
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

启用 worker：

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now yuejing-queue
sudo systemctl status yuejing-queue --no-pager
```

项目还提供 `yuejing:publish-drafts` 命令，用于发布到时间的草稿。使用 systemd timer 每分钟执行一次：

```bash
sudoedit /etc/systemd/system/yuejing-publish.service
```

```ini
[Unit]
Description=Publish scheduled Yuejing novels
After=mysql.service

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/var/www/yuejing
ExecStart=/usr/bin/php artisan yuejing:publish-drafts
```

```bash
sudoedit /etc/systemd/system/yuejing-publish.timer
```

```ini
[Unit]
Description=Run Yuejing scheduled publishing every minute

[Timer]
OnBootSec=2min
OnUnitActiveSec=1min
Persistent=true

[Install]
WantedBy=timers.target
```

启用定时器：

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now yuejing-publish.timer
sudo systemctl list-timers yuejing-publish.timer --no-pager
```

如果已将 `QUEUE_CONNECTION` 改为 `sync`，不需要启用 `yuejing-queue.service`。

## 14. 部署验证

依次执行：

```bash
cd "$PROJECT_DIR"
php -v
composer check-platform-reqs --no-dev
node --version
npm --version
test -f vendor/autoload.php
test -f public/build/manifest.json
php artisan about --only=environment
php artisan migrate:status
php artisan route:list >/dev/null
sudo nginx -t
systemctl is-active nginx php8.3-fpm mysql
```

从服务器或其他主机访问：

```bash
curl -fsS -o /dev/null -w '%{http_code}\n' "https://$DOMAIN/up"
curl -fsS -o /dev/null -w '%{http_code}\n' "https://$DOMAIN/"
curl -fsS -o /dev/null -w '%{http_code}\n' "https://$DOMAIN/login"
```

`/up` 只证明 Laravel、PHP-FPM 和路由能够响应，不会主动验证数据库、SMTP、Session、Cache、Queue、文件权限或 Vite 静态资源。还应实际检查：

- 登录和退出流程。
- 数据库读写、书库和章节页面。
- 管理员登录、分类或小说管理。
- 密码重置或邮箱验证邮件。
- 页面源代码引用的 `/build/assets/*` 资源返回 `200`。
- `storage/logs/laravel.log` 没有新的异常。

## 15. 常见 500/502 排查

应用 500：

```bash
cd "$PROJECT_DIR"
tail -n 100 storage/logs/laravel.log
php artisan optimize:clear
php artisan about
php artisan migrate:status
```

重点检查：

- `.env` 是否存在，`APP_KEY` 是否为空。
- `APP_DEBUG=false` 时不要只看浏览器错误页，要看 Laravel 日志。
- `vendor/autoload.php` 和 `public/build/manifest.json` 是否存在。
- `storage`、`bootstrap/cache` 是否可由 `www-data` 写入。
- `pdo_mysql` 是否启用，数据库连接和迁移是否完成。
- `APP_URL`、SMTP、Session 的 HTTPS 配置是否一致。

Nginx 502 或 PHP 请求失败：

```bash
systemctl status php8.3-fpm --no-pager
journalctl -u php8.3-fpm -n 100 --no-pager
journalctl -u nginx -n 100 --no-pager
ls -l /run/php/php8.3-fpm.sock
sudo nginx -t
```

Nginx 404 而 Laravel 路由没有响应：

- 检查 `root` 是否指向 `/var/www/yuejing/public`。
- 检查 `try_files $uri $uri/ /index.php?$query_string;` 是否存在。
- 不要把 Apache 的 `public/.htaccess` 规则复制到 Nginx。
- 检查 `public/index.php` 和 `vendor/autoload.php` 是否存在。

Laravel 日志位置：

```text
/var/www/yuejing/storage/logs/laravel.log
/var/log/nginx/access.log
/var/log/nginx/error.log
journalctl -u php8.3-fpm
journalctl -u yuejing-queue
```

## 16. 更新发布

更新前先备份数据库、`.env`、`storage/` 和当前可用提交。一个基本的人工发布流程如下；先设置实际线上分支：

```bash
cd "$PROJECT_DIR"
export DEPLOY_BRANCH="<实际线上分支>"
php artisan down --retry=60

git fetch origin "$DEPLOY_BRANCH"
git checkout "$DEPLOY_BRANCH"
git pull --ff-only origin "$DEPLOY_BRANCH"

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

发布失败时先保留 Laravel、PHP-FPM 和 Nginx 日志，再根据备份和迁移记录处理回滚。代码回滚不等于数据库回滚，不要未经评估直接执行 `php artisan migrate:rollback`。

## 17. 备份建议

至少每日备份一次数据库，并将备份保存到 Web 根目录之外：

```bash
sudo mkdir -p /var/backups/yuejing
sudo mysqldump --single-transaction --routines --triggers \
    -u yuejing -p yuejing | gzip > /var/backups/yuejing/db-$(date +%F).sql.gz
```

同时保留：

- 受限权限的 `.env` 副本。
- `storage/` 中的应用数据和日志。
- 最近一次可用代码提交或发行包。

备份文件包含敏感数据，必须限制权限并设置清理策略，不要放进 `/var/www/yuejing/public`。
