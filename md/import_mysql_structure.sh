#!/bin/bash
# ============================================
# 仅导入 MySQL 表结构（不导入数据，不影响已有数据）
# 从 .env 读取数据库配置，导入指定目录下【所有】.sql 文件中的 CREATE TABLE 部分
# 说明：会跳过 import_all.sql（该文件含 SOURCE 指令，仅适合在 mysql 客户端内执行）
# 若有外键依赖，请保证表名/文件名顺序（如先父表后子表），或按需调整 .sql 文件名排序
# ============================================

set -e

# 脚本所在目录（fishingAdmin 项目根）
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# 默认 .env 路径
ENV_FILE="${ENV_FILE:-$SCRIPT_DIR/.env}"
# 默认 SQL 目录（可环境变量 MYSQL_DIR 覆盖，例如: MYSQL_DIR=/path/to/miniprogram-2/mysql）
MYSQL_DIR="${MYSQL_DIR:-$SCRIPT_DIR/mysql}"

# 解析 .env 中的数据库配置（兼容 KEY = value 与 KEY=value）
get_env() {
    local key="$1"
    grep -E "^[[:space:]]*${key}[[:space:]]*=" "$ENV_FILE" 2>/dev/null | sed -E 's/^[^=]+=[[:space:]]*//;s/[[:space:]]*$//' | head -1
}

if [ ! -f "$ENV_FILE" ]; then
    echo "错误: 未找到 .env 文件: $ENV_FILE"
    echo "可设置环境变量: ENV_FILE=/path/to/.env"
    exit 1
fi

DB_HOST="$(get_env DB_HOST)"
DB_PORT="$(get_env DB_PORT)"
DB_NAME="$(get_env DB_NAME)"
DB_USER="$(get_env DB_USER)"
DB_PASS="$(get_env DB_PASS)"
DB_CHARSET="$(get_env DB_CHARSET)"

[ -z "$DB_HOST" ] && DB_HOST="127.0.0.1"
[ -z "$DB_PORT" ] && DB_PORT="3306"
[ -z "$DB_CHARSET" ] && DB_CHARSET="utf8mb4"

for v in DB_NAME DB_USER DB_PASS; do
    if [ -z "${!v}" ]; then
        echo "错误: .env 中未配置 $v"
        exit 1
    fi
done

if [ ! -d "$MYSQL_DIR" ]; then
    echo "错误: SQL 目录不存在: $MYSQL_DIR"
    echo "可设置环境变量: MYSQL_DIR=/path/to/mysql"
    exit 1
fi

# 只导入表结构：去掉 INSERT 语句，保留 CREATE TABLE / SET / 注释等
# 避免影响已有数据
import_structure_only() {
    local sql_file="$1"
    awk '
        /^INSERT INTO/ { skip=1; next }
        skip { if (/;[\s]*$/) skip=0; next }
        { print }
    ' "$sql_file"
}

export MYSQL_PWD="$DB_PASS"
echo "数据库: $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME"
echo "SQL 目录: $MYSQL_DIR"
echo "仅导入表结构（跳过 INSERT，不影响已有数据）"
echo "----------------------------------------"

# 导入该目录下所有 .sql（排除 import_all.sql，因其含 SOURCE 指令需在 mysql 内执行）
for path in "$MYSQL_DIR"/*.sql; do
    [ -f "$path" ] || continue
    f="$(basename "$path")"
    if [ "$f" = "import_all.sql" ]; then
        echo "跳过: $f（请直接使用各单表 .sql）"
        continue
    fi
    echo "导入结构: $f"
    import_structure_only "$path" | mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" --default-character-set="$DB_CHARSET" "$DB_NAME" || {
        echo "导入失败: $f"
        export MYSQL_PWD=""
        exit 1
    }
done

export MYSQL_PWD=""
echo "----------------------------------------"
echo "表结构导入完成。"
