<?php
declare (strict_types = 1);

namespace app\command;

use app\service\SessionExpireService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 自动结束到期的开钓单（方案A）
 * 用法：php think session:expire
 */
class SessionExpire extends Command
{
    protected function configure()
    {
        $this->setName('session:expire')
            ->setDescription('Auto finish expired fishing sessions');
    }

    protected function execute(Input $input, Output $output)
    {
        $count = SessionExpireService::run(200);
        $output->writeln('expired_finished=' . $count);
        return 0;
    }
}

