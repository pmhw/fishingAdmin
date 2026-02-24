        #!/bin/bash

        # 项目目录：若代码在站点根目录则用 /www/wwwroot/fishing.kaiyuantong.cn
        # 若在子目录则用 /www/wwwroot/fishing.kaiyuantong.cn/fishingAdmin
        PROJECT_DIR="/www/wwwroot/fishing.kaiyuantong.cn/fishingAdmin"
        cd "$PROJECT_DIR" || exit

        # 获取远程更新信息
        git fetch origin

        # 检查是否有更新
        LOCAL=$(git rev-parse HEAD)
        REMOTE=$(git rev-parse origin/$(git rev-parse --abbrev-ref HEAD))

        if [ "$LOCAL" != "$REMOTE" ]; then
            echo "有更新，执行 git pull"
            git pull
            # 拉取后安装/更新 Composer 依赖（生成 vendor 目录）
            if [ -f "composer.json" ]; then
                echo "执行 composer install"
                composer install --no-dev --optimize-autoloader
            fi
        else
            echo "无更新"
        fi