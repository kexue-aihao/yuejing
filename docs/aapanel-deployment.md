# aaPanel 生产部署完整教程

本文是阅境阅读平台在 **aaPanel** 上从零到生产可用的完整部署流程。每一步都经过源码验证，标注了容易出错的位置和原因。如果你严格按照本文操作但仍有问题，直接跳到第 14 节「常见错误速查」。

项目是 Laravel 13 + Vite 8 + Tailwind CSS 4，`composer.json` 要求 PHP `^8.3`，前端要求 Node.js `^20.19.0` 或 `>=22.12.0`。

---

## 1. 开始前确认

- 一台已安装 aaPanel 的服务器
- 一个已解析到服务器 IP 的域名
- 云防火墙/安全组已放行 TCP `80`、`443`（以及 aaPanel 面板端口）
- 服务器时间已同步（`timedatectl` 或 `ntp`）

---

## 2. aaPanel 软件安装

在「软件商城」中安装：

| 软件 | 推荐版本 | 说明 |
|------|----------|------|
| Nginx | 1.24+ | 或 Apache，本文以 Nginx 为主 |
| MySQL | 8.0+ | 或 MariaDB 10.6+ |
| PHP | **8.3 或 8.5** | 本文以 8.3 为例，8.5 同样适用 |
| phpMyAdmin | 最新 | 管理数据库（可选） |
| Node.js 版本管理器 | 最新 | 用于安装 Node |

> 安装顺序无影响。装完后，在「已安装」中确认所有服务均为绿色运行状态。

在 Node.js 版本管理器中安装 **Node.js 22**（`v22.12.0` 或以上）。如果你用 Node 20，必须至少是 `v20.19.0`，否则 `npm ci` 会报 engine 不满足。

---

## 3. PHP 扩展检查

在「软件商城 → 已安装 → PHP-83 → 设置 → 安装扩展」中确认以下扩展处于**已安装**状态：

**必须安装（缺少任意一个都会导致 500 或命令失败）：**

| 扩展 | 作用 | 缺了会怎样 |
|------|------|------------|
| `fileinfo` | 文件类型检测 | Composer 安装依赖时报错 |
| `mbstring` | 多字节字符串 | 中文处理异常，Laravel 报错 |
| `openssl` | 加密通信 | APP_KEY 生成失败，Session 加密失败 |
| `pdo_mysql` | MySQL 数据库连接 | `could not find driver`，连不上数据库 |
| `tokenizer` | PHP 源码解析 | Laravel 框架启动失败 |
| `xml` / `dom` | XML/DOM 解析 | Laravel 异常渲染失败 |
| `ctype` | 字符类型检查 | 框架路由/验证器报错 |
| `filter` | 数据过滤 | 请求输入处理异常 |
| `json` | JSON 处理 | PHP 8.3 内置，但仍需确认未禁用 |
| `curl` | HTTP 请求 | 邮件发送（SMTP）、外部 API 调用 |
| `zip` | 压缩处理 | Composer 解包，否则 `composer install` 极慢或失败 |

> `pdo_mysql` 是最容易被遗漏的。它的名称在 aaPanel 中可能显示为 `pdo_mysql` 或 `MySQL` 或 `mysqlnd`，必须确认已安装并启用。

安装完成后在 aaPanel 终端执行：

```bash
php -v
php -m | grep -iE 'pdo|mysql|mbstring|openssl|fileinfo|ctype|tokenizer|xml|curl|zip|filter|json'
```

如果你安装了多个 PHP 版本，确认当前终端的 `php` 命令指向 PHP 8.3（`which php`），不要指向系统自带的 PHP 5.x 或 7.x。aaPanel 的 PHP 通常在 `/www/server/php/83/bin/php`。

---

## 4. 创建站点和数据库

### 4.1 添加站点

在 aaPanel「网站 → 添加站点」：

| 设置项 | 值 |
|--------|-----|
| 域名 | `your-domain.com` |
| 备注 | 阅境 |
| PHP 版本 | PHP-83 |
| 数据库 | **勾选**「创建数据库」，字符集选 `utf8mb4` |

点击提交后，记录弹出的数据库名、用户名、密码。**这组信息只会显示一次，务必立刻保存到安全位置。**

### 4.2 设置网站运行目录

站点创建后，进入「网站 → your-domain.com → 设置 → 网站目录」：

- **运行目录**：点击选择，选中 `/www/wwwroot/your-domain.com/public`，保存。

> 这是 Laravel 部署最关键的设置。如果运行目录是项目根目录而不是 `public`，你的 `.env`、`composer.json`、`vendor` 目录和 `storage/logs` 都会暴露在公网，并且所有 Laravel 路由都会 404。

### 4.3 关闭防跨站或添加例外路径

在同一个「网站目录」设置页，找到「防跨站攻击（open_basedir）」，**关掉它**。

如果安全策略不允许关闭，在里面追加：

```text
:/www/wwwroot/your-domain.com/storage:/www/wwwroot/your-domain.com/bootstrap/cache
```

不关或不追加路径会导致 Laravel 写日志时抛出 `file_put_contents(): open_basedir restriction in effect` 错误，网站 500。

### 4.4 确认 MySQL 用户的主机匹配

这是 aaPanel 部署最常见的一个坑。在 aaPanel 终端执行：

```bash
mysql -u root -p -e "SELECT user, host FROM mysql.user WHERE user = '你的数据库用户名';"
```

如果你的 `.env` 使用 `DB_HOST=127.0.0.1`（后面会配置），但 MySQL 用户只有 `user@localhost`，你的应用会连不上数据库，报 `SQLSTATE[HY000] [1045] Access denied for user`。

**正确的是让用户同时有 `localhost` 和 `127.0.0.1`：**

```sql
CREATE USER '用户名'@'127.0.0.1' IDENTIFIED BY '密码';
GRANT ALL PRIVILEGES ON 数据库名.* TO '用户名'@'127.0.0.1';
FLUSH PRIVILEGES;
```

或者直接在 `.env` 里把 `DB_HOST` 改成 `localhost`（让 MySQL 走 Unix socket 而不是 TCP）。

---

## 5. 部署代码

### 5.1 克隆仓库

```bash
cd /www/wwwroot/your-domain.com
rm -rf ./* .[!.]*   # 清空 aaPanel 生成的默认文件
git clone --branch master https://github.com/kexue-aihao/yuejing.git .
```

> 注意最后的 `.` —— 把代码克隆到当前目录而不是子目录。克隆完成后 `ls` 应该能看到 `artisan`、`composer.json`、`public/`、`resources/` 等。

### 5.2 确认关键文件存在

```bash
test -f artisan && echo "✓ artisan 存在"
test -f composer.json && echo "✓ composer.json 存在"
test -f package.json && echo "✓ package.json 存在"
test -f .env.example && echo "✓ .env.example 存在"
test -f public/index.php && echo "✓ 入口文件存在"
```

---

## 6. 配置 `.env`

```bash
cp .env.example .env
chmod 640 .env
```

编辑 `.env`，逐项检查以下内容。**不要跳过任何一行。** 这就是你的站点配置文件，一行配错整站不可用。

### 6.1 应用基本配置

```dotenv
APP_NAME="阅境"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=zh_CN
```

- `APP_DEBUG=false` 绝对不能改 `true`，否则报错时会泄露数据库密码和服务器路径。
- `APP_URL` 必须和浏览器最终访问的地址一样（含 `https://`），否则登录后 cookie 不生效，会反复跳回登录页。
- `APP_KEY` 留空不填，后面自动生成。

### 6.2 数据库配置

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aaPanel创建的数据库名
DB_USERNAME=aaPanel创建的数据库用户名
DB_PASSWORD="aaPanel创建的数据库密码"
```

> `DB_HOST=127.0.0.1` 和 `localhost` 的区别：MySQL 中 `localhost` 走 Unix socket，`127.0.0.1` 走 TCP。如果你的 MySQL 用户是 `user@localhost` 但 `DB_HOST=127.0.0.1`，连接会被拒绝。**最简单的做法是把 `DB_HOST` 也改成 `localhost`，或按 4.4 节同时授权两个 host。**

### 6.3 Session / Cache / Queue

```dotenv
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database
```

关键点：

- `SESSION_ENCRYPT=true` 依赖 `APP_KEY`。如果以后再执行 `php artisan key:generate`，所有旧 Session 会立即失效，用户需要重新登录。
- `SESSION_SECURE_COOKIE=true` 要求网站必须使用 HTTPS。如果还没有配置 SSL 证书就先部署测试，把它临时改成 `false`，但上线前必须改回 `true`。
- 没有 Redis 时全部用 `database` 驱动即可。如果不需要队列，可以把 `QUEUE_CONNECTION` 改为 `sync`（任务在请求中同步执行，不需要 worker 进程）。

### 6.4 邮件配置

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-email@your-domain.com
MAIL_PASSWORD="邮箱的授权码或密码"
MAIL_FROM_ADDRESS=your-email@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

- 生产环境**不要**用 `MAIL_MAILER=log`。虽然不影响浏览，但密码重置、邮箱验证和管理员 SMTP 测试都会失败。
- 如果你用的是 QQ 邮箱 / 163 邮箱 / 阿里企业邮等，SMTP 密码一般是「授权码」而不是登录密码，需要去邮箱设置里单独生成。
- 云服务器厂商（阿里云、腾讯云、华为云）默认封锁了 25 端口，587（TLS）或 465（SSL）通常可用。确认云防火墙放行。

### 6.5 管理员配置

```dotenv
YUEJING_EMAIL_VERIFICATION_REQUIRED=false
YUEJING_ADMIN_NAME="网站管理员"
YUEJING_ADMIN_EMAIL=admin@your-domain.com
YUEJING_ADMIN_PASSWORD="至少12位随机强密码"
```

- `YUEJING_ADMIN_PASSWORD` 必须自己设置一个至少 12 位的密码，**不要用 `password` 或 `123456`**。
- 管理员创建成功后（第 7.5 节），建议从 `.env` 中删除这行密码，避免密码以明文长时间存在服务器上。

### 6.6 前端构建变量

```dotenv
VITE_APP_NAME="${APP_NAME}"
```

这个变量会被注入到前端 JS 中。如果不写，Vite 构建不会报错但应用名可能显示异常。

---

## 7. 安装依赖和初始化

下面步骤**必须按顺序执行**。每一步都有验证，不要在验证失败时跳到下一步。

### 7.1 确认 PHP 路径

```bash
which php
```

aaPanel 中通常是 `/usr/bin/php`（软链）或 `/www/server/php/83/bin/php`。确认版本：

```bash
php -v   # 必须显示 PHP 8.3.x
```

如果显示了 PHP 5.x 或 7.x，说明系统默认 PHP 版本不对。此时用完整路径：

```bash
export PHP_BIN="/www/server/php/83/bin/php"
alias php="$PHP_BIN"
```

### 7.2 安装 Composer 依赖

```bash
cd /www/wwwroot/your-domain.com
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

验证：

```bash
test -f vendor/autoload.php && echo "✓ Composer 安装完成"
composer check-platform-reqs --no-dev
```

如果 `composer check-platform-reqs --no-dev` 报 `ext-xxx is missing`，去 aaPanel 的 PHP 扩展设置中安装该扩展，然后重试。

### 7.3 构建前端资源

```bash
npm ci
npm run build
```

验证：

```bash
test -f public/build/manifest.json && echo "✓ 前端构建成功" || echo "✗ 前端构建失败！检查 npm/node 版本"
```

> 常见失败原因：Node.js 版本太低。执行 `node -v`，必须 ≥ `v22.12.0` 或 ≥ `v20.19.0`。如果版本不够，在 aaPanel 的 Node.js 版本管理器中切换或安装更高版本。
>
> 如果 `npm` 命令找不到，先添加到 PATH：
> ```bash
> export PATH="/www/server/nodejs/v22.12.0/bin:$PATH"
> # 路径按实际安装版本调整，确认方法：ls /www/server/nodejs/
> ```

### 7.4 生成 APP_KEY 并执行数据库迁移

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan migrate:status
```

`migrate:status` 应显示所有迁移均为 `Ran`。如果某个迁移显示 `Pending`，先检查数据库连接是否正确：

```bash
php artisan db:show
```

如果连接失败，回头检查 4.4 节和 6.2 节。

### 7.5 创建管理员

```bash
php artisan yuejing:admin
```

输出：

```text
Administrator admin@your-domain.com created.
```

如果输出 `YUEJING_ADMIN_PASSWORD is required`，说明 `.env` 中 `YUEJING_ADMIN_PASSWORD` 为空或不足 12 位。回去补填再执行。

创建成功后删除 `.env` 中的明文密码：

```bash
sed -i '/^YUEJING_ADMIN_PASSWORD=/d' .env
```

### 7.6 创建存储链接

```bash
php artisan storage:link
```

如果报错 `symlink(): Permission denied` 或 PHP 禁用了 `exec()`，改为手动创建：

```bash
ln -sf ../storage/app/public public/storage
```

验证：

```bash
test -L public/storage && echo "✓ storage 链接正常"
```

### 7.7 构建生产缓存

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

这三个命令**必须在第 8 节之前执行**。为什么重要：

- `config:cache` 把 `.env` 和所有 `config/*.php` 合并为单个缓存文件，加速请求并确保 `.env` 变更生效。
- `route:cache` 预编译所有路由，加速路由匹配。当前路由全部使用控制器语法，不会因闭包导致缓存失败。
- `view:cache` 预编译所有 Blade 模板。

以后每次修改 `.env` 或配置文件后，都必须重新运行这三条。

> 注意：配置缓存后 `.env` 不再被 PHP 读取，所有 `env()` 调用都从缓存取。所以修改 `.env` 后**必须先清缓存再重建**：
> ```bash
> php artisan config:clear && php artisan config:cache
> ```

---

## 8. 配置 Nginx

在「网站 → your-domain.com → 设置 → 配置文件」中，**不要用 aaPanel 默认生成的配置**。全部替换为以下内容，然后按实际路径修改标注的几处：

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com www.your-domain.com;

    # ↓ 改为你的实际 public 路径
    root /www/wwwroot/your-domain.com/public;
    index index.php;
    charset utf-8;

    # ↓ 伪静态：所有不存在的文件/目录请求交给 index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ↓ 前端构建产物长期缓存（文件名带哈希，永不过期）
    location /build/assets/ {
        access_log off;
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files $uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }

    # ↓ PHP 处理 — 这是最关键的配置
    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

        # ★ socket 路径必须修改 ★
        # 执行 ls /tmp/php-cgi-*.sock 确认实际文件名
        # PHP 8.3 → /tmp/php-cgi-83.sock
        # PHP 8.5 → /tmp/php-cgi-85.sock
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
    }

    # ↓ 禁止访问隐藏文件（.env、.git 等），但保留 LetsEncrypt 证书验证
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**修改后必须做的事：**

1. 把 `your-domain.com` 和 `www.your-domain.com` 换成你的实际域名
2. 把 `/www/wwwroot/your-domain.com/public` 换成你的实际路径
3. **确认 socket 路径正确**：
   ```bash
   ls /tmp/php-cgi-*.sock
   ```
   把输出中的 socket 文件名写入 `fastcgi_pass`。PHP 8.3 是 `/tmp/php-cgi-83.sock`，PHP 8.5 是 `/tmp/php-cgi-85.sock`。

4. **绝对不要**在这份配置之外再嵌套另一个 `server {}` 块。aaPanel 有时会在「配置文件」中把用户内容插入到一个已有的 server 块内——如果发现有两层 `server {}`，把外层删掉。

5. 保存后重载 Nginx：在 aaPanel 中点击「保存」即可自动重载。

---

## 9. 设置权限

aaPanel 的 PHP-FPM 和 Nginx 通常以 `www` 用户运行。执行以下命令（把 `www` 换成你实际的运行用户，可在 aaPanel「网站设置」中查看）：

```bash
cd /www/wwwroot/your-domain.com

# 所有文件归 www 用户
chown -R www:www .

# 目录 755，文件 644
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# .env 限制更严，防止被其他用户读取
chmod 640 .env

# storage 和 bootstrap/cache 需要写入权限
chmod -R 775 storage bootstrap/cache
chown -R www:www storage bootstrap/cache
```

验证 PHP-FPM 用户可以写入（用你的实际运行用户替换 `www`）：

```bash
sudo -u www test -w storage && echo "✓ storage 可写" || echo "✗ storage 不可写！"
sudo -u www test -w bootstrap/cache && echo "✓ bootstrap/cache 可写" || echo "✗ bootstrap/cache 不可写！"
sudo -u www test -r .env && echo "✓ .env 可读" || echo "✗ .env 不可读！"
```

---

## 10. 配置 HTTPS

在「网站 → your-domain.com → SSL」中：

1. 选择「Let's Encrypt」
2. 勾选「强制 HTTPS」
3. 点击「申请」

申请成功后验证：

```bash
curl -I https://your-domain.com/up
```

应返回 `HTTP/2 200`。

> 如果 Let's Encrypt 申请失败，通常原因：
> - 域名 DNS 还没解析到服务器 IP（`dig your-domain.com` 检查）
> - 80 端口被云防火墙拦截
> - `.well-known/acme-challenge/` 目录被 Nginx 的隐藏文件规则拦截（上面配置已用 `?!well-known` 排除了这个目录）

HTTPS 配置完成后，确认 `.env` 的 `APP_URL` 以 `https://` 开头，且 `SESSION_SECURE_COOKIE=true`：

```bash
grep -E 'APP_URL|SESSION_SECURE_COOKIE' .env
```

如果前面测试时把 `SESSION_SECURE_COOKIE` 改成了 `false`，现在改回 `true`，然后：

```bash
php artisan config:clear && php artisan config:cache
```

---

## 11. 首次访问验证

```bash
curl -fsS -o /dev/null -w '%{http_code}\n' https://your-domain.com/up
curl -fsS -o /dev/null -w '%{http_code}\n' https://your-domain.com/
curl -fsS -o /dev/null -w '%{http_code}\n' https://your-domain.com/novels
curl -fsS -o /dev/null -w '%{http_code}\n' https://your-domain.com/login
```

四条都应返回 `200`。如果 `/up` 返回 `200` 但其他页面 `500`：

```bash
tail -100 storage/logs/laravel.log
```

然后用第 14 节的速查表定位。

然后用浏览器访问：

1. `https://your-domain.com` — 首页应该显示正常
2. `https://your-domain.com/login` — 用管理员邮箱和密码登录
3. `https://your-domain.com/admin` — 进入管理后台
4. 浏览书库、打开一本书、查看章节

---

## 12. 队列 Worker 和定时任务

### 12.1 队列 Worker（如果 QUEUE_CONNECTION=database）

在 aaPanel「计划任务」中添加：

| 设置项 | 值 |
|--------|-----|
| 任务名称 | 阅境队列 Worker |
| 执行周期 | N 分钟（选「持续运行」） |
| 脚本内容 | 见下 |

```bash
/www/server/php/83/bin/php /www/wwwroot/your-domain.com/artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-jobs=500 --rest=1
```

> **关键**：必须用 aaPanel 安装的 PHP 的绝对路径（`/www/server/php/83/bin/php`），不要用 `/usr/bin/php`（可能指向系统自带的旧 PHP）。`--max-jobs=500 --rest=1` 让 worker 处理 500 个任务后自动重启，防止内存泄漏。

如果不确定 PHP 路径：

```bash
which php           # 看当前终端用的是哪个
ls /www/server/php/ # 看 aaPanel 安装了哪些版本
```

### 12.2 定时发布（yuejing:publish-drafts）

项目提供了一个命令，用于把到达 `published_at` 时间的草稿自动发布。在 aaPanel「计划任务」中添加：

| 设置项 | 值 |
|--------|-----|
| 任务名称 | 阅境定时发布 |
| 执行周期 | 1 分钟 |
| 脚本内容 | 见下 |

```bash
/www/server/php/83/bin/php /www/wwwroot/your-domain.com/artisan yuejing:publish-drafts >> /www/server/cron/yuejing-publish.log 2>&1
```

### 12.3 健康检查（可选）

| 设置项 | 值 |
|--------|-----|
| 任务名称 | 阅境健康检查 |
| 执行周期 | 5 分钟 |
| 脚本内容 | 见下 |

```bash
curl -fsS -o /dev/null -w '%{http_code}' https://your-domain.com/up | grep -q 200 || echo "$(date) 健康检查失败" >> /www/server/cron/yuejing-health.log
```

---

## 13. 更新和维护

### 13.1 正常更新流程

```bash
cd /www/wwwroot/your-domain.com

# 拉取代码
git pull --ff-only origin master

# 安装依赖
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci && npm run build

# 数据库迁移
php artisan migrate --force

# 重建所有缓存
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 13.2 更新前备份

```bash
mkdir -p /www/backup/yuejing

# 数据库备份
mysqldump -u用户名 -p密码 数据库名 --single-transaction --routines --triggers \
  | gzip > /www/backup/yuejing/db-$(date +%F-%H%M).sql.gz

# .env 备份
cp .env /www/backup/yuejing/env-$(date +%F-%H%M)
```

### 13.3 带维护模式的更新（推荐）

如果更新涉及数据库结构变更或多人访问的站点：

```bash
php artisan down --retry=60 --secret="临时访问令牌"

# 执行更新步骤...
# git pull, composer install, npm ci && npm run build, php artisan migrate, 重建缓存

php artisan up
```

`--secret` 参数允许你用 `https://your-domain.com/临时访问令牌` 访问站点进行验证，其他用户看到的是维护页面。

### 13.4 回滚

```bash
git log --oneline -5
git reset --hard <上一个正常工作的提交哈希>
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> 代码回滚不等于数据库回滚。除非你有独立的数据库备份并清楚迁移内容，否则**不要**执行 `php artisan migrate:rollback`。

---

## 14. 常见错误速查

### 14.1 全站 500

```bash
tail -100 storage/logs/laravel.log
```

| 日志中的关键错误 | 原因 | 解决 |
|-----------------|------|------|
| `could not find driver` | 没装 `pdo_mysql` | 第 3 节 |
| `Access denied for user ...@127.0.0.1` | MySQL 用户没有 `127.0.0.1` 权限 | 第 4.4 节 |
| `Access denied for user ...@localhost` | 数据库密码错误 | 第 6.2 节 |
| `No application encryption key` | APP_KEY 为空 | 第 7.4 节 |
| `Base table or view not found: sessions` | 没执行数据库迁移 | 第 7.4 节 |
| `The Vite manifest does not exist` | 没构建前端 `npm run build` | 第 7.3 节 |
| `file_put_contents(...): failed to open stream` | storage 不可写 | 第 9 节 |
| `require(vendor/autoload.php): failed to open stream` | 没执行 `composer install` | 第 7.2 节 |
| `Class "..." not found` | `composer install` 不完整或被 kill | 第 7.2 节，重试 |
| `open_basedir restriction in effect` | 没关闭防跨站 | 第 4.3 节 |

### 14.2 首页 200 但其他页面 404

Nginx 伪静态没配好。检查配置文件的 `location /` 块：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

没有了这行，所有非首页地址都无法交给 Laravel 处理。

### 14.3 全站 502 Bad Gateway

PHP-FPM 没在运行或 socket 路径配错。

```bash
ls /tmp/php-cgi-*.sock          # 查看实际的 socket 文件
```

Nginx 配置中的 `fastcgi_pass unix:/tmp/php-cgi-83.sock;` 必须和上面命令输出的文件名一致。如果不一致，在 aaPanel「软件商城 → 已安装 → PHP-83 → 设置」中查看实际使用的版本和 socket。

### 14.4 登录后就跳回登录页

三个原因，按顺序排查：

1. **`SESSION_SECURE_COOKIE=true` 但访问的是 HTTP** — 检查浏览器地址栏是否是 `https://`。如果暂时没有 SSL，临时改 `.env` 为 `SESSION_SECURE_COOKIE=false`，然后 `php artisan config:clear && php artisan config:cache`。

2. **`APP_URL` 与浏览器地址不一致** — `.env` 里的 `APP_URL` 必须和浏览器地址栏的域名完全相同（包括 `https://` 前缀）。

3. **`sessions` 表不存在** — 确认已执行 `php artisan migrate --force`。

### 14.5 邮件发送失败

- 云服务器默认封锁 25 端口，换用 587（TLS）或 465（SSL）
- QQ/163 等邮箱用「授权码」而非登录密码
- 确认 `MAIL_SCHEME` 与端口匹配：587 用 `tls`，465 用 `ssl`
- 在管理后台的「站点设置」中有 SMTP 测试功能，可以验证配置

### 14.6 npm 命令找不到

```bash
# 查看 aaPanel 安装的 Node.js 版本
ls /www/server/nodejs/

# 按实际版本添加到 PATH
export PATH="/www/server/nodejs/v22.12.0/bin:$PATH"
node -v
npm -v
```

---

## 15. 安全建议

- [ ] `.env` 权限为 640，不提交到 Git
- [ ] `APP_DEBUG=false` 在生产环境永不为 `true`
- [ ] aaPanel 面板端口不使用默认 8888
- [ ] MySQL 3306 端口不开放公网
- [ ] 使用强密码（数据库、管理员、aaPanel 面板）
- [ ] 管理员创建后从 `.env` 中删除 `YUEJING_ADMIN_PASSWORD`
- [ ] 定期执行数据库备份
- [ ] 不要在 `public/` 目录下存放备份文件或敏感信息
- [ ] 删除 `test@example.com` 等测试账号（如果有的话不要执行 `db:seed`）

---

## 16. 部署后检查清单

- [ ] `curl https://your-domain.com/up` 返回 200
- [ ] 浏览器访问首页、书库、登录页均正常
- [ ] 管理员可以登录并进入后台
- [ ] 新建分类、新建小说、新建章节均成功
- [ ] 邮件测试（后台站点设置 → SMTP 测试）
- [ ] `storage/logs/laravel.log` 没有 ERROR 级别日志
- [ ] HTTPS 证书有效且自动续期正常
- [ ] 计划任务的队列和定时发布正常运行
- [ ] 数据库备份脚本可执行
