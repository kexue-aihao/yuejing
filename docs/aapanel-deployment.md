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

### 3.1 解除 PHP 禁用函数

aaPanel 默认禁用了 `putenv`、`proc_open`、`exec` 这三个函数，**必须解除**，否则 Laravel 部分核心流程会失败：

| 函数 | 用途 | 禁用后会怎样 |
|------|------|--------------|
| `putenv` | 读取 `.env` 环境变量 | Laravel 无法加载配置，直接 500 |
| `proc_open` | Composer 内部脚本执行、队列 worker 进程管理 | `composer install` 报错中断，队列无法运行 |
| `exec` | `php artisan storage:link` 创建符号链接、`key:generate` 随机密钥生成 | `storage:link` 报错，但可手动 `ln -s` 绕过 |

**如何解除**：在 aaPanel「软件商城 → 已安装 → PHP-83 → 设置 → 禁用函数」中，把 `putenv,proc_open,exec` 从列表里删掉，然后保存、重启 PHP-FPM。

> 如果你必须保留 `exec` 的禁用（安全基线要求），可以接受手工创建 `ln -sf ../storage/app/public public/storage` 替代 `php artisan storage:link`。但 `putenv` 和 `proc_open` 无论如何不能禁用，否则 Laravel 启动和依赖安装都会失败。

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

# 只有使用自有固定反向代理时才填写，写精确 IP/CIDR；直连或 Cloudflare 经 Nginx real_ip 处理时保持注释
# TRUSTED_PROXIES=10.0.0.10,192.0.2.0/24

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

> **这是部署中最容易漏掉的一步，也是"配置了 .env 但登录不了"的根源。**
>
> 在 `.env` 里写 `YUEJING_ADMIN_*` 只是告诉了 Laravel「管理员应该是什么」，但管理员账号**不会自动写入数据库**。必须执行 `php artisan yuejing:admin` 这条命令，它才会读取 `.env` 的配置并把管理员写入 `users` 表。只配 `.env` 不执行这条命令，数据库里根本没有这个用户，登录永远不识别。
>
> **另一个常见踩坑**：如果你先执行了 `php artisan config:cache`，当时 `.env` 里 `YUEJING_ADMIN_PASSWORD` 还是空的，缓存就会把空密码冻结。之后即使补填了 `.env`，`yuejing:admin` 也读不到。修复方法是先清缓存再执行：
> ```bash
> php artisan config:clear
> php artisan yuejing:admin
> php artisan config:cache
> ```
>
> 验证管理员是否创建成功：
> ```bash
> php artisan tinker --execute="echo \App\Models\User::where('role','admin')->get()->pluck('email')->implode(', ') ?: '没有管理员账号';"
> ```

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

> **`view:cache` 报 `Unable to locate a class or view for component [xxx]`**：
> 这说明某个 `<x-xxx />` Blade 组件文件放错了目录。所有组件必须放在 `resources/views/components/` 下，不能放在 `resources/views/partials/`。如果报这个错，找到对应文件移动过去即可：
> ```bash
> mv resources/views/partials/theme-toggle.blade.php resources/views/components/
> mv resources/views/partials/book-cover.blade.php resources/views/components/
> ```
> 移完后执行 `php artisan view:clear` 再 `php artisan view:cache`。

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

5. **确认 `try_files` 没有被 aaPanel 默认值覆盖。** 这是最常见的 404 原因——aaPanel 默认生成的 `location /` 是：
   ```nginx
   location / {
       try_files $uri $uri/ =404;
   }
   ```
   这个配置会直接返回 404，不会把请求交给 `index.php`。**必须自己改成**：
   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```
   如果你粘贴了上面的完整配置却还是 404，检查 aaPanel 是否在更外层还有另一个 `location /` 覆盖了你的。

6. 保存后重载 Nginx：在 aaPanel 中点击「保存」即可自动重载。

### 8.1 真实访问 IP（Cloudflare/反向代理）

网站右下角的「当前访问 IP」和投稿审计中的「投稿来源 IP」都来自 Laravel 的 `$request->ip()`。**不要用 JavaScript、第三方 IP 查询接口或客户端提交的 `X-Forwarded-For` 获取 IP。**

如果用户直接访问源站，`$request->ip()` 就是客户端地址。如果链路是 Cloudflare → aaPanel Nginx → PHP，必须先让 Nginx 验证 Cloudflare 来源并把 `CF-Connecting-IP` 写入真实客户端地址；否则显示的会是 Cloudflare 节点 IP。

在 Nginx `server` 块中加入下面配置。Cloudflare 官方 IP 网段会更新，部署时请以 [Cloudflare 官方 IP 列表](https://www.cloudflare.com/ips/) 为准，下面只放格式示例，**不要把示例网段当成完整列表**：

```nginx
# 只信任 Cloudflare 官方网段，下面网段请替换/补全为官方列表
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
set_real_ip_from 103.31.4.0/22;
set_real_ip_from 141.101.64.0/18;
set_real_ip_from 108.162.192.0/18;
set_real_ip_from 190.93.240.0/20;
set_real_ip_from 188.114.96.0/20;
set_real_ip_from 197.234.240.0/22;
set_real_ip_from 162.158.0.0/15;
set_real_ip_from 104.16.0.0/13;
set_real_ip_from 172.64.0.0/13;
set_real_ip_from 131.0.72.0/22;
# IPv6 官方网段也必须配置（从 Cloudflare 官方列表复制）
# set_real_ip_from xxxx::/xx;

real_ip_header CF-Connecting-IP;
real_ip_recursive on;
```

同时，源站防火墙只允许 Cloudflare 官方 IP 访问 `80/443`，否则公网客户端可以绕过 Cloudflare 直接伪造 `CF-Connecting-IP`。确认生效：

```bash
curl -sk https://your-domain.com/ | grep -o '当前访问 IP[^<]*'
```

如果使用自有固定反代而不是 Cloudflare，在 `.env` 中填写反代的**精确 IP/CIDR**：

```dotenv
TRUSTED_PROXIES=10.0.0.10,192.0.2.0/24
```

不要写 `*`、`0.0.0.0/0` 或 `::/0`。项目会在 Laravel 中只对这些受信代理解析标准 `X-Forwarded-*` 头。

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

### 11.1 如果页面样式异常（纯文字无 CSS）

这种情况 95% 是 **Cloudflare 缓存了旧版 HTML**。你的服务器上 `npm run build` 已经生成了新的 CSS/JS 文件（带新哈希名），但 Cloudflare 边缘节点缓存中的 HTML 仍然引用旧文件名。旧文件在 `public/build/assets/` 里已经不存在，返回 404。

**验证是否 Cloudflare 缓存导致的：**

```bash
# 看 manifest.json 里的实际文件名
curl -sk "https://your-domain.com/build/manifest.json" | grep '"file"'

# 看首页 HTML 里引用的文件名
curl -sk "https://your-domain.com/" | grep -o 'build/assets/[^"]*'

# 对比是两个不同的文件名（hash 不同），就确认是 Cloudflare 缓存问题
```

**修复：**

1. 在服务器上重建视图缓存：
   ```bash
   cd /www/wwwroot/your-domain.com
   php artisan view:clear
   php artisan view:cache
   ```

2. 登录 Cloudflare 控制台 → 你的域名 → 缓存 → 配置 → **清除所有内容**（Purge Everything）。

3. 用带随机参数的地址跳过浏览器缓存验证：`https://your-domain.com/?v=1`

4. 以后每次 `npm run build` 后如果用了 Cloudflare，都建议清一次缓存。或者在 Cloudflare 的缓存规则中设置 **Bypass Cache** 对首页 `/` 生效。

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

### 13.1 从 GitHub 更新已部署站点（推荐）

如果站点最初是用 Git 克隆的，后续更新**不要重新 `git clone`，也不要在网站目录里解压覆盖**。使用 aaPanel 终端进入原项目目录，按下面顺序更新。本文仓库的线上分支是 `master`，不是 `main`。

> 更新前先确认当前站点可以访问，并确认你有数据库、`.env`、`storage/` 和当前提交的备份。更新命令会修改线上代码、依赖、缓存和数据库，不能在未备份的情况下直接执行。

#### 第一步：定义路径和 PHP/Node 路径

将路径替换为实际站点路径。PHP 版本必须和 aaPanel 网站设置中的版本一致：

```bash
export APP_DIR="/www/wwwroot/your-domain.com"
export PHP_BIN="/www/server/php/83/bin/php"
export NODE_BIN="/www/server/nodejs/v22.12.0/bin"
export COMPOSER_BIN="composer"
export BRANCH="master"

cd "$APP_DIR"
```

检查路径，不要跳过：

```bash
test -f artisan || { echo "错误：APP_DIR 不是 Laravel 项目根目录" >&2; exit 1; }
test -d .git || { echo "错误：该目录不是 Git 工作区，不能执行 git pull" >&2; exit 1; }
test -x "$PHP_BIN" || { echo "错误：PHP_BIN 不存在，请按 aaPanel 实际版本修改" >&2; exit 1; }
test -x "$NODE_BIN/node" || { echo "错误：NODE_BIN 不存在，请按 aaPanel 实际 Node 版本修改" >&2; exit 1; }
"$PHP_BIN" -v
"$NODE_BIN/node" -v
"$NODE_BIN/npm" -v
git branch --show-current
git status --short
```

当前分支必须是 `master`，工作区必须没有输出。若 `git status --short` 有输出，先保存或处理这些修改，不要用 `git reset --hard` 强行覆盖，因为那会删除服务器上的本地改动：

```bash
test "$(git branch --show-current)" = "$BRANCH" || { echo "错误：当前分支不是 $BRANCH" >&2; exit 1; }
test -z "$(git status --porcelain)" || { echo "错误：Git 工作区不干净，请先处理修改" >&2; exit 1; }
```

#### 第二步：备份数据库、环境和运行数据

备份目录必须放在 `public` 目录之外：

```bash
export BACKUP_DIR="/www/backup/yuejing/$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

cp -p .env "$BACKUP_DIR/.env"
tar -czf "$BACKUP_DIR/storage.tar.gz" storage

# 从 .env 读取数据库配置并执行备份；不要把密码写入命令行历史
set -a
. ./.env
set +a
MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --quick --routines --triggers \
    --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" \
    --user="$DB_USERNAME" --databases "$DB_DATABASE" > "$BACKUP_DIR/database.sql"
chmod 600 "$BACKUP_DIR/.env" "$BACKUP_DIR/database.sql"
printf '%s
' "$(git rev-parse HEAD)" > "$BACKUP_DIR/previous-commit.txt"
```

如果服务器没有 `mysqldump`，先在 aaPanel 安装对应 MySQL/MariaDB 客户端，或使用 aaPanel「数据库 → 备份」完成备份。没有可恢复的数据库备份时不要继续。

#### 第三步：进入维护模式并拉取 GitHub 代码

```bash
"$PHP_BIN" artisan down --retry=60 --secret="临时维护口令"

# 只允许快进更新，避免服务器自动产生合并提交
git fetch origin "$BRANCH"
git pull --ff-only origin "$BRANCH"
```

如果 `git pull` 提示本地有修改、分支不一致或需要合并，停止操作，先保留日志并人工处理。不要执行：

```bash
git reset --hard origin/master
```

这会永久删除服务器上的未提交文件。更新失败时，使用备份的提交号和人工确认后的回滚流程。

#### 第四步：安装后端依赖和构建前端

```bash
"$COMPOSER_BIN" --working-dir="$APP_DIR" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
"$COMPOSER_BIN" --working-dir="$APP_DIR" check-platform-reqs --no-dev

export PATH="$NODE_BIN:$PATH"
npm ci
npm run build

test -f vendor/autoload.php || { echo "错误：vendor/autoload.php 不存在" >&2; exit 1; }
test -f public/build/manifest.json || { echo "错误：Vite manifest 不存在" >&2; exit 1; }
```

这一步不能省略：

- `public/build` 被 `.gitignore` 忽略，GitHub 更新不会携带构建产物。
- 本次 UI 使用的 Blade 组件必须位于 `resources/views/components/`，确认组件文件随 Git 拉取：

```bash
for file in \
    resources/views/components/theme-toggle.blade.php \
    resources/views/components/visitor-ip.blade.php \
    resources/views/components/book-cover.blade.php; do
    test -f "$file" || { echo "错误：缺少 Blade 组件 $file" >&2; exit 1; }
done
```

#### 第五步：迁移数据库、重建缓存和恢复服务

```bash
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan storage:link
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

# 让常驻队列 worker 在处理完当前任务后加载新代码
"$PHP_BIN" artisan queue:restart || true
"$PHP_BIN" artisan up
```

如果 `storage:link` 因 `exec` 被禁用失败，确认目标后手动创建：

```bash
test -e public/storage && test ! -L public/storage && { echo "错误：public/storage 已存在但不是软链接" >&2; exit 1; }
ln -sfn ../storage/app/public public/storage
```

如果中途失败，先查看日志；确认问题处理完并完成缓存后再执行 `php artisan up`。不要让站点长期停留在维护模式。

#### 第六步：验证更新结果

```bash
"$PHP_BIN" artisan about --only=environment
"$PHP_BIN" artisan migrate:status
curl -fsS -o /dev/null -w '%{http_code}
' https://your-domain.com/up
curl -fsS -o /dev/null -w '%{http_code}
' https://your-domain.com/
curl -fsS -o /dev/null -w '%{http_code}
' https://your-domain.com/login
```

浏览器还要实际验证：登录、后台、投稿审核、书库、阅读器、右下角当前 IP。确认首页 HTML 引用的 CSS/JS 与服务器上的 manifest 一致：

```bash
curl -sk https://your-domain.com/build/manifest.json | grep '"file"'
curl -sk https://your-domain.com/ | grep -o 'build/assets/[^" ]*'
```

如果使用 Cloudflare，更新并构建后进入 Cloudflare「缓存 → 配置 → 清除所有内容（Purge Everything）」，再使用 `https://your-domain.com/?v=$(date +%s)` 强制验证。否则可能出现 HTML 仍引用旧哈希资源、页面无样式的问题。

### 13.2 通过 aaPanel 计划任务使用更新脚本

仓库中的 `scripts/aapanel-update.sh` 会备份 `.env`、`storage/` 和数据库，并在失败时尝试回滚代码。使用前必须显式指定当前分支 `master`、正确的 PHP/Composer 路径和 HTTPS 健康检查地址：

```bash
cd /www/wwwroot/your-domain.com
DEPLOY_ROOT=/www/wwwroot/your-domain.com \
DEPLOY_BRANCH=master \
PHP_BIN=/www/server/php/83/bin/php \
COMPOSER_BIN=/usr/local/bin/composer \
HEALTHCHECK_URL=https://your-domain.com/up \
/bin/bash scripts/aapanel-update.sh \
>> /www/server/cron/yuejing-update.log 2>&1
```

> 当前脚本适合人工确认后执行，不建议直接设置为高频自动任务。它只负责 Composer，不会替你执行 `npm ci`/`npm run build`；只要 GitHub 更新了 CSS、JS 或 Blade，优先使用 13.1 的完整流程，或在脚本后手工构建前端并重建视图缓存。

### 13.3 更新前备份

```bash
mkdir -p /www/backup/yuejing
mysqldump -u用户名 -p 数据库名 --single-transaction --routines --triggers \
  | gzip > /www/backup/yuejing/db-$(date +%F-%H%M).sql.gz
cp .env /www/backup/yuejing/env-$(date +%F-%H%M)
```

### 13.4 带维护模式的更新（推荐）

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
| `proc_open() has been disabled` | `proc_open` 被禁用 | 第 3.1 节 |
| `putenv() has been disabled` | `putenv` 被禁用 | 第 3.1 节 |
| `exec() has been disabled` (storage:link 报错) | `exec` 被禁用 | 第 3.1 节，或手工 `ln -s` |

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

### 14.6 配置了管理员但登录不识别

在 `.env` 里配置了 `YUEJING_ADMIN_EMAIL` 和 `YUEJING_ADMIN_PASSWORD`，部署完去登录却提示邮箱或密码错误。

**原因：** `.env` 只是告诉 Laravel「管理员应该是什么」，但不会自动写入数据库。你漏掉了 `php artisan yuejing:admin` 这条命令，所以数据库 `users` 表里根本没有这个用户。

**解决：**

```bash
# 如果之前跑过 config:cache，先清掉
php artisan config:clear
# 执行这条创建管理员（用 .env 里的密码写入数据库）
php artisan yuejing:admin
# 重建缓存
php artisan config:cache
```

验证：

```bash
php artisan tinker --execute="echo \App\Models\User::where('role','admin')->pluck('email')->implode(', ') ?: '没有管理员';"
```

### 14.7 npm 命令找不到

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
