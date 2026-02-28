#!/bin/bash

PROJECT_DIR="/www/wwwroot/fishing.kaiyuantong.cn/fishingAdmin"
cd "$PROJECT_DIR" || exit 1

BRANCH=$(git rev-parse --abbrev-ref HEAD)
echo "项目目录: $PROJECT_DIR"
echo "当前分支: $BRANCH"

if ! git fetch origin; then
    echo "错误: git fetch 失败"
    exit 1
fi

REMOTE=$(git rev-parse "origin/$BRANCH" 2>/dev/null)
if [ -z "$REMOTE" ]; then
    echo "警告: 远程分支 origin/$BRANCH 不存在"
    exit 0
fi

LOCAL=$(git rev-parse HEAD)
if [ "$LOCAL" != "$REMOTE" ]; then
    # 若有本地修改（如 .env），先 stash 再 pull，成功后再恢复
    if ! git diff --quiet 2>/dev/null || ! git diff --cached --quiet 2>/dev/null; then
        echo "暂存本地修改（如 .env）后拉取..."
        git stash push -m "sync: keep local"
        STASHED=1
    fi
    echo "执行 git pull origin $BRANCH"
    if ! git pull origin "$BRANCH"; then
        [ -n "$STASHED" ] && git stash pop 2>/dev/null || true
        exit 1
    fi
    if [ -n "$STASHED" ]; then
        git stash pop 2>/dev/null || true
        echo "已恢复本地修改"
    fi
    # 设置上游分支，之后可直接 git pull
    git branch --set-upstream-to=origin/"$BRANCH" "$BRANCH" 2>/dev/null || true
    if [ -f "composer.json" ]; then
        echo "执行 composer install"
        # 屏蔽 PHP 8 下旧版 Composer 的 Deprecated 提示（不影响执行）
        php -d error_reporting=24575 /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction
    fi
    echo "更新完成"
else
    echo "无更新"
fi
