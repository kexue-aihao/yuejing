#!/usr/bin/env bash

# aaPanel/Laravel 安全更新脚本。
# 约定：代码由 Git 管理，当前分支必须干净；数据库迁移只前进，不自动回滚。

set -Eeuo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="${DEPLOY_ROOT:-$(cd "$SCRIPT_DIR/.." && pwd)}"
ENV_FILE="$ROOT_DIR/.env"
BACKUP_ROOT="${BACKUP_ROOT:-$(cd "$ROOT_DIR/.." && pwd)/yuejing-backups}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-http://127.0.0.1/up}"
SKIP_DB_BACKUP="${SKIP_DB_BACKUP:-0}"

timestamp="$(date +%Y%m%d-%H%M%S)"
backup_dir="$BACKUP_ROOT/$timestamp"
previous_commit=''
maintenance_started=0
rollback_started=0

log() {
    printf '%s %s\n' "$(date -Is)" "$*"
}

die() {
    log "ERROR: $*" >&2
    exit 1
}

cleanup() {
    local exit_code=$?

    if [ "$maintenance_started" -eq 1 ]; then
        "$PHP_BIN" "$ROOT_DIR/artisan" up >/dev/null 2>&1 || log 'WARNING: 无法自动解除维护模式，请手工执行 php artisan up。'
    fi

    if [ "$exit_code" -ne 0 ] && [ "$rollback_started" -eq 0 ] && [ -n "$previous_commit" ]; then
        log "更新失败，回滚代码到 $previous_commit；不会回滚数据库迁移。"
        rollback_started=1
        if git -C "$ROOT_DIR" reset --hard "$previous_commit" >/dev/null \
            && "$COMPOSER_BIN" --working-dir="$ROOT_DIR" install --no-dev --prefer-dist --optimize-autoloader --no-interaction \
            && "$PHP_BIN" "$ROOT_DIR/artisan" config:cache >/dev/null \
            && "$PHP_BIN" "$ROOT_DIR/artisan" route:cache >/dev/null \
            && "$PHP_BIN" "$ROOT_DIR/artisan" view:cache >/dev/null; then
            log '代码回滚完成。请检查数据库迁移和健康检查结果。'
        else
            log 'ERROR: 代码自动回滚未完成，请使用备份或手工恢复上一个提交。' >&2
        fi
    fi

    exit "$exit_code"
}

trap cleanup EXIT

[ -d "$ROOT_DIR/.git" ] || die "不是 Git 工作区：$ROOT_DIR"
[ -f "$ENV_FILE" ] || die "缺少 $ENV_FILE；请先从 .env.example 创建并填写生产配置。"
command -v "$PHP_BIN" >/dev/null 2>&1 || die "找不到 PHP：$PHP_BIN"
command -v "$COMPOSER_BIN" >/dev/null 2>&1 || die "找不到 Composer：$COMPOSER_BIN"
command -v tar >/dev/null 2>&1 || die '找不到 tar，无法备份 storage。'

git -C "$ROOT_DIR" diff --quiet || die '工作区有未提交的已跟踪修改，已停止更新。'
git -C "$ROOT_DIR" diff --cached --quiet || die '暂存区有未提交修改，已停止更新。'
[ "$(git -C "$ROOT_DIR" branch --show-current)" = "$DEPLOY_BRANCH" ] || \
    die "当前分支不是 $DEPLOY_BRANCH；请切换分支或设置 DEPLOY_BRANCH。"

previous_commit="$(git -C "$ROOT_DIR" rev-parse HEAD)"
mkdir -p "$backup_dir"
chmod 700 "$BACKUP_ROOT" "$backup_dir"

log "备份配置和 storage 到 $backup_dir"
cp -p "$ENV_FILE" "$backup_dir/.env"
printf '%s\n' "$previous_commit" > "$backup_dir/commit.txt"
if [ -d "$ROOT_DIR/storage" ]; then
    tar -czf "$backup_dir/storage.tar.gz" -C "$ROOT_DIR" storage
fi

dotenv_value() {
    "$PHP_BIN" -r '
        require $argv[1] . "/vendor/autoload.php";
        $dotenv = Dotenv\Dotenv::createImmutable($argv[1]);
        $dotenv->safeLoad();
        $key = $argv[2];
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? "";
        if (is_string($value)) { echo $value; }
    ' "$ROOT_DIR" "$1"
}

db_connection="$(dotenv_value DB_CONNECTION)"
if [ "$SKIP_DB_BACKUP" = '1' ]; then
    log 'WARNING: SKIP_DB_BACKUP=1，已跳过数据库备份。'
elif [ "$db_connection" = 'sqlite' ]; then
    sqlite_path="$(dotenv_value DB_DATABASE)"
    [ -f "$ROOT_DIR/$sqlite_path" ] && cp -p "$ROOT_DIR/$sqlite_path" "$backup_dir/database.sqlite" || \
        log 'WARNING: 未找到 SQLite 文件，跳过 SQLite 备份。'
elif [ "$db_connection" = 'mysql' ] || [ "$db_connection" = 'mariadb' ] || [ -z "$db_connection" ]; then
    dump_bin=''
    if command -v mysqldump >/dev/null 2>&1; then
        dump_bin="$(command -v mysqldump)"
    elif command -v mariadb-dump >/dev/null 2>&1; then
        dump_bin="$(command -v mariadb-dump)"
    else
        die '找不到 mysqldump 或 mariadb-dump；如确认已有独立数据库备份，可设置 SKIP_DB_BACKUP=1。'
    fi
    db_host="$(dotenv_value DB_HOST)"; db_port="$(dotenv_value DB_PORT)"
    db_name="$(dotenv_value DB_DATABASE)"; db_user="$(dotenv_value DB_USERNAME)"
    db_password="$(dotenv_value DB_PASSWORD)"
    [ -n "$db_name" ] || die 'DB_DATABASE 为空，停止更新。'
    log '创建 MySQL/MariaDB 一致性备份'
    MYSQL_PWD="$db_password" "$dump_bin" --single-transaction --quick --routines --triggers \
        --host="${db_host:-127.0.0.1}" --port="${db_port:-3306}" \
        --user="$db_user" --databases "$db_name" > "$backup_dir/database.sql"
else
    die "不支持自动备份的 DB_CONNECTION=$db_connection；请设置 SKIP_DB_BACKUP=1 并确认已有备份。"
fi

if [ -f "$ROOT_DIR/storage/framework/down" ]; then
    log '检测到已有维护模式，更新期间不会改变其状态。'
else
    "$PHP_BIN" "$ROOT_DIR/artisan" down --retry=60 >/dev/null
    maintenance_started=1
fi

log "拉取 $DEPLOY_BRANCH 的快进更新"
git -C "$ROOT_DIR" pull --ff-only origin "$DEPLOY_BRANCH"

log '安装生产依赖'
"$COMPOSER_BIN" --working-dir="$ROOT_DIR" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
"$COMPOSER_BIN" --working-dir="$ROOT_DIR" check-platform-reqs

log '执行前进迁移和 Laravel 缓存构建'
"$PHP_BIN" "$ROOT_DIR/artisan" migrate --force
"$PHP_BIN" "$ROOT_DIR/artisan" storage:link
"$PHP_BIN" "$ROOT_DIR/artisan" config:cache
"$PHP_BIN" "$ROOT_DIR/artisan" route:cache
"$PHP_BIN" "$ROOT_DIR/artisan" view:cache

log "执行健康检查：$HEALTHCHECK_URL"
HEALTHCHECK_URL="$HEALTHCHECK_URL" "$SCRIPT_DIR/aapanel-healthcheck.sh"

log "更新完成，备份目录：$backup_dir"
