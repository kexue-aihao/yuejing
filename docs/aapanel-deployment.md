# aaPanel 最简生产部署

本文是阅境阅读平台的 **aaPanel 独立部署教程**，只需要一台安装了 aaPanel 的服务器，不依赖 Redis 或 Docker。目标读者是需要在 aaPanel 面板上把项目从零部署到生产环境的人员。

项目当前 `composer.json` 要求 PHP `^8.3`、Laravel `^13.8`。阅读器使用了 Vite 8 构建前端，Node.js 需要 `^20.19.0` 或 `>=22.12.0`。

## 1. 服务器要求

- 一台安装了 **aaPanel** 的 Linux 服务器（CentOS 7+/Ubuntu 20.04+/Debian 10+）
- **PHP 8.3+**（建议 8.5）
- **MySQL 5.7+** 或 **MariaDB 10.3+**
- **Nginx** 或 **Apache**
- 域名已配置 DNS 解析

## 2. aaPanel 环境准备

### 2.1 安装软件

在 aaPanel 面板的「软件商城」中安装：

| 软件 | 版本 | 说明 |
|------|------|------|
| Nginx | 任意稳定版 | 或 Apache，二选一 |
| MySQL | 5.7+ | 或 MariaDB |
| PHP | **8.5**（推荐） | 必须 ≥ 8.3 |
| phpMyAdmin | 可选 | 数据库管理工具 |

### 2.2 安装 PHP 扩展

在 aaPanel 的「软件商城 → PHP 8.5 → 设置 → 安装扩展」中确认以下扩展已安装：

**必需：**
- `fileinfo`
- `mbstring`
- `openssl`
- `pdo_mysql`
- `tokenizer`
- `xml` (dom/libxml)

**可选（按需启用）：**
- `redis` — 使用 Redis 缓存/Session/队列时
- `zip` — Composer 处理压缩包
- `curl` — 应用使用 cURL 网络处理器时

### 2.3 安装 Node.js

在 aaPanel 的「软件商城」中搜索并安装 **Node.js 版本管理器**，然后安装 Node.js 22 或 20.19+。

或者在 aaPanel 终端执行：

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs
```

验证：

```bash
node -v   # 应 ≥ v22.12.0 或 ≥ v20.19.0
npm -v    # 应 ≥ 10.x
```

### 2.4 添加 npm 到 PATH

如果终端无法识别 `npm` 命令，临时添加到环境变量：

```bash
export PATH="/www/server/nodejs/v22/bin:$PATH"
hash -r
```

路径按 aaPanel 实际安装的 Node.js 版本调整，常见为 `/www/server/nodejs/v22.12.0/bin` 或 `/www/server/nodejs/v24.18.0/bin`。

## 3. 创建站点和数据库

### 3.1 创建站点

在 aaPanel「网站」中点击「添加站点」：

| 设置项 | 值 |
|--------|-----|
| 域名 | `online.example.com`（你的实际域名） |
| PHP 版本 | PHP-85 |
| 数据库 | 勾选「创建数据库」 |
| 数据库用户名 | 自动生成，记下来 |
| 数据库密码 | 自动生成，记下来 |

### 3.2 设置网站根目录

站点创建后，在「网站设置 → 网站目录」中：

**运行目录** 设置为：

```text
/www/wwwroot/你的域名/public
```

例如：

```text
/www/wwwroot/online.example.com/public
```

> **这一步是必须的。** 如果直接把网站根目录指向项目根目录（不含 `/public`），`.env`、`composer.json` 和 `vendor` 等文件会被公开访问，且 Laravel 路由无法正常工作。

**关闭**「防跨站攻击（open_basedir）」，或在 `open_basedir` 中添加 `:/www/wwwroot/你的域名/storage:/www/wwwroot/你的域名/bootstrap/cache`，否则 Laravel 无法写日志和缓存。

## 4. 上传代码

### 4.1 使用 Git 克隆（推荐）

在 aaPanel 终端执行：

```bash
cd /www/wwwroot
git clone --branch master https://github.com/你的用户名/你的仓库.git 你的域名
cd /www/wwwroot/你的域名
```

### 4.2 或使用 FTP/文件管理器上传

将项目代码（除 `.env`、`vendor/`、`node_modules/` 外）上传到 `/www/wwwroot/你的域名/`。

**不要**上传：
- 本地的 `.env` 文件（包含本地密钥和密码）
- `vendor/` 目录（将在服务器重新安装）
- `node_modules/` 目录（将在服务器重新安装）
- `.git/` 目录（如果使用发布包）

## 5. 配置环境变量

在项目根目录复制环境配置模板：

```bash
cd /www/wwwroot/你的域名
cp .env.example .env
```

编辑 `.env`：

```bash
nano .env
```

至少修改以下配置：

```dotenv
APP_NAME="阅境"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://你的域名.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aaPanel自动创建的数据库名
DB_USERNAME=aaPanel自动创建的数据库用户名
DB_PASSWORD="aaPanel自动创建的数据库密码"

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.你的邮件服务商.com
MAIL_PORT=587
MAIL_USERNAME=你的邮箱账号
MAIL_PASSWORD="你的邮箱密码或授权码"
MAIL_FROM_ADDRESS=你的发件邮箱
MAIL_FROM_NAME="${APP_NAME}"

YUEJING_EMAIL_VERIFICATION_REQUIRED=false
YUEJING_ADMIN_NAME="网站管理员"
YUEJING_ADMIN_EMAIL=admin@你的域名.com
YUEJING_ADMIN_PASSWORD="至少12位的随机强密码"
```

**关键说明：**

- `APP_KEY` 留空，下一步自动生成
- `APP_DEBUG=false` 绝对不要改成 `true`
- `APP_URL` 使用最终 HTTPS 地址
- 没有 Redis 时保持 `SESSION_DRIVER=database`、`CACHE_STORE=database`、`QUEUE_CONNECTION=database`
- 如果不需要异步队列，可将 `QUEUE_CONNECTION` 改为 `sync`
- `MAIL_MAILER` 不要使用 `log`，生产环境必须配置真实 SMTP

## 6. 安装依赖和初始化

### 6.1 安装 PHP 依赖

```bash
cd /www/wwwroot/你的域名
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
```

如果 `composer` 命令不可用：

```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

### 6.2 构建前端资源

```bash
npm ci
npm run build
```

验证前端构建产物：

```bash
test -f public/build/manifest.json && echo "前端构建成功" || echo "前端构建失败！"
```

如果 `npm` 命令不可用，先按 2.4 节添加 PATH：

```bash
export PATH="/www/server/nodejs/v22/bin:$PATH"
hash -r
```

### 6.3 初始化 Laravel

按顺序执行：

```bash
cd /www/wwwroot/你的域名

# 1. 生成应用密钥
php artisan key:generate --force

# 2. 运行数据库迁移
php artisan migrate --force
php artisan migrate:status

# 3. 创建符号链接
php artisan storage:link
```

### 6.4 创建管理员

```bash
php artisan yuejing:admin
```

首次创建管理员时，`.env` 中的 `YUEJING_ADMIN_PASSWORD` 必须已填写且至少 12 位。创建成功后建议从 `.env` 中删除密码：

```bash
sed -i '/^YUEJING_ADMIN_PASSWORD=/d' .env
```

以后如需重置管理员密码：

```bash
# 临时在 .env 写入新的 YUEJING_ADMIN_PASSWORD，然后：
php artisan config:clear
php artisan yuejing:admin --reset-password
php artisan config:cache
```

### 6.5 重建缓存

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 7. 配置 Nginx 伪静态

### 7.1 Nginx 配置

在 aaPanel「网站 → 你的站点 → 设置 → 配置文件」中，确保包含以下内容：

```nginx
server {
    listen 80;
    server_name 你的域名.com www.你的域名.com;

    root /www/wwwroot/你的域名/public;
    index index.php index.html;
    charset utf-8;

    # 伪静态规则：把所有不存在的文件/目录请求转给 index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # 缓静态资源（带哈希的构建产物长期缓存）
    location /build/assets/ {
        access_log off;
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files $uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }

    # PHP 处理（socket 路径按 aaPanel 实际 PHP 版本调整）
    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/tmp/php-cgi-85.sock;
    }

    # 禁止访问隐藏文件（.env、.git 等），保留 ACME 验证
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> **重要：** `/tmp/php-cgi-85.sock` 是 PHP 8.5 的默认 socket。如果你使用 PHP 8.3，应改为 `/tmp/php-cgi-83.sock`。你可以在 aaPanel「软件商城 → 已安装 → PHP → 设置」中查看实际的 socket 路径，或在服务器执行 `ls /tmp/php-cgi-*.sock`。

### 7.2 Apache 配置

如果你使用 Apache，确保项目自带的 `public/.htaccess` 生效。在 aaPanel 站点配置中：

```apache
<VirtualHost *:80>
    ServerName 你的域名.com
    DocumentRoot /www/wwwroot/你的域名/public

    <Directory /www/wwwroot/你的域名/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>
</VirtualHost>
```

Apache 还需要确认：
- `mod_rewrite` 已启用
- `AllowOverride All` 生效
- `DocumentRoot` 指向 `public`

### 7.3 保存并重载

在 aaPanel 中点击「保存」并重载 Nginx/Apache。

## 8. 设置权限

在 aaPanel 终端执行：

```bash
cd /www/wwwroot/你的域名

# aaPanel 常见的运行用户是 www
chown -R www:www /www/wwwroot/你的域名
find /www/wwwroot/你的域名 -type d -exec chmod 755 {} \;
find /www/wwwroot/你的域名 -type f -exec chmod 644 {} \;
chmod 640 .env
chmod -R 775 storage bootstrap/cache
chown -R www:www storage bootstrap/cache
```

如果 aaPanel 使用其他用户（在「网站设置」中可以看到「运行用户」），把 `www:www` 替换为实际用户。**只给 `storage` 和 `bootstrap/cache` 写权限，不要给整个项目 777。**

## 9. 配置 HTTPS

在 aaPanel「网站 → 你的站点 → SSL」中：

1. 选择「Let's Encrypt」或「其他证书」
2. 勾选「强制 HTTPS」
3. 点击「申请」或导入已有证书

确认 `.env` 中的 `APP_URL` 使用 `https://`：

```bash
grep APP_URL .env
```

如果使用 Cloudflare 或其他 CDN，确保 CDN 到源站使用 HTTPS，且反向代理转发 `X-Forwarded-Proto: https`。

## 10. 健康检查

从服务器本身执行：

```bash
curl -fsS -o /dev/null -w '%{http_code}\n' https://你的域名.com/up
curl -fsS -o /dev/null -w '%{http_code}\n' https://你的域名.com/
```

正常应返回 `200`。

从浏览器访问：
- `https://你的域名.com/` — 首页
- `https://你的域名.com/novels` — 书库
- `https://你的域名.com/login` — 登录
- `https://你的域名.com/admin` — 管理后台（用管理员账号登录）

> `/up` 只证明 Laravel 和 PHP-FPM 能响应，不等于数据库、邮件、Session 和静态资源都正常。必须实际登录和浏览验证。

## 11. 队列和定时任务

### 11.1 队列 Worker

如果 `QUEUE_CONNECTION=database`，需要让队列持续消费。在 aaPanel「计划任务」中添加：

| 设置项 | 值 |
|--------|-----|
| 任务类型 | Shell 脚本 |
| 任务名称 | 阅境队列 |
| 执行周期 | N 分钟（选「持续运行」或 1 分钟） |
| 脚本内容 | 见下方 |

```bash
cd /www/wwwroot/你的域名 && /usr/bin/php artisan queue:work database --sleep=3 --tries=3 --timeout=90
```

注意必须使用绝对路径 `/usr/bin/php`。可在终端用 `which php` 确认。

> 如果已将 `QUEUE_CONNECTION` 改为 `sync`，不需要配置队列 worker。

### 11.2 定时发布

项目提供 `yuejing:publish-drafts` 命令用于定时发布到时间的草稿。在 aaPanel「计划任务」中添加：

| 设置项 | 值 |
|--------|-----|
| 任务类型 | Shell 脚本 |
| 任务名称 | 阅境定时发布 |
| 执行周期 | 1 分钟 |
| 脚本内容 | 见下方 |

```bash
cd /www/wwwroot/你的域名 && /usr/bin/php artisan yuejing:publish-drafts >> /www/server/cron/yuejing-publish.log 2>&1
```

### 11.3 健康检查

| 设置项 | 值 |
|--------|-----|
| 任务类型 | Shell 脚本 |
| 任务名称 | 阅境健康检查 |
| 执行周期 | 5 分钟 |
| 脚本内容 | 见下方 |

```bash
curl -fsS -o /dev/null -w '%{http_code}' https://你的域名.com/up | grep -q 200 || echo "健康检查失败 $(date)" >> /www/server/cron/yuejing-health.log
```

## 12. 常见问题排查

### 12.1 访问出现 500 错误

查看 Laravel 日志：

```bash
tail -n 100 /www/wwwroot/你的域名/storage/logs/laravel.log
```

常见原因：

| 现象 | 检查 |
|------|------|
| `The Vite manifest does not exist` | `npm run build` 未执行，或 `public/build/manifest.json` 不存在 |
| `could not find driver` | `pdo_mysql` 扩展未安装 |
| `Access denied for user` | 数据库用户名/密码错误 |
| `No application encryption key` | `APP_KEY` 为空，执行 `php artisan key:generate --force` |
| `The stream or file could not be opened` | `storage/` 或 `bootstrap/cache/` 不可写 |
| `Class not found` | `composer install` 未执行，检查 `vendor/autoload.php` |

### 12.2 访问出现 404 错误

**Nginx：**
1. 确认「网站目录 → 运行目录」已设置为 `/www/wwwroot/你的域名/public`
2. 确认 Nginx 配置中有 `try_files $uri $uri/ /index.php?$query_string;`

**Apache：**
1. 确认 `mod_rewrite` 已启用
2. 确认 `AllowOverride All` 或 `public/.htaccess` 已生效
3. 确认 `DocumentRoot` 指向 `public`

### 12.3 访问出现 502 错误

1. PHP-FPM 未运行：`systemctl status php-fpm-85`（aaPanel 的服务名可能不同）
2. PHP-FPM socket 路径错误：确认 Nginx 的 `fastcgi_pass` 路径与实际一致
3. PHP-FPM socket 权限不足：`ls -la /tmp/php-cgi-85.sock`

### 12.4 登录后跳转回登录页

1. 确认 `.env` 的 `SESSION_DRIVER=database`，并已执行 `php artisan migrate --force`
2. 确认 `.env` 的 `APP_URL` 与浏览器地址栏一致（含 `https://`）
3. `SESSION_SECURE_COOKIE=true` 要求使用 HTTPS，若暂未配置 HTTPS，临时改为 `false`

### 12.5 密码重置 / 邮箱验证邮件未收到

1. 确认 `.env` 的 `MAIL_MAILER` 不是 `log`
2. 确认 SMTP 主机、端口、用户名、密码正确
3. 通过后台「站点设置」的 SMTP 测试功能验证
4. 确认服务器的 587 或 465 端口未被云防火墙拦截

## 13. 更新维护

### 13.1 更新流程

```bash
cd /www/wwwroot/你的域名

# 拉取最新代码
git pull --ff-only origin master

# 安装依赖
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build

# 数据库迁移
php artisan migrate --force

# 重建缓存
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 13.2 更新前备份

```bash
# 备份数据库
mysqldump -u 数据库用户 -p 数据库名 | gzip > /www/backup/yuejing-$(date +%F).sql.gz

# 备份 .env
cp .env /www/backup/yuejing-env-$(date +%F)
```

### 13.3 回滚

如果更新后出现问题，先查看日志：

```bash
tail -n 100 /www/wwwroot/你的域名/storage/logs/laravel.log
```

代码回滚：

```bash
git log --oneline -5
git reset --hard <上一个可用提交>
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> 代码回滚不等于数据库回滚。除非确认了迁移影响并有独立的数据库备份，否则不要执行 `php artisan migrate:rollback`。

## 14. 安全建议

- 不要把 `.env` 提交到 Git
- 定期轮换数据库密码和管理员密码
- 生产环境保持 `APP_DEBUG=false`
- 删除测试 seeder 创建的测试账号（如 `test@example.com`）
- 定期执行 `php artisan yuejing:admin` 确保管理员信息同步
- SSH 使用密钥认证
- aaPanel 面板端口和登录地址不要使用默认值
- MySQL 的 `3306` 端口不要开放到公网
