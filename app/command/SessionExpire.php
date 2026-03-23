<?php
declare (strict_types = 1);

namespace app\command;

use app\service\OrderTimeoutService;
use app\service\SessionExpireService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 定时：到期开钓单结束 + 待支付订单超时（释放座位占用、订单标记 timeout）
 * 用法：php think session:expire
 */
class SessionExpire extends Command
{
    protected function configure()
    {
        $this->setName('session:expire')
            ->setDescription('Expire fishing sessions + timeout stale pending orders');
    }

    protected function execute(Input $input, Output $output)
    {
        $sessions = SessionExpireService::run(200);
        $orders = OrderTimeoutService::run(200);
        $output->writeln('sessions_finished=' . $sessions);
        $output->writeln('orders_timeout=' . $orders);
        return 0;
    }
}

