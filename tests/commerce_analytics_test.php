<?php
require_once __DIR__ . '/bootstrap.php';
test_reset_failures();
echo "Commerce analytics tests\n\n";
$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/includes/CommerceOrderStore.php';
require_once $root . '/plugins/commerce/includes/CommerceAnalyticsService.php';
$dir = sys_get_temp_dir().'/ba-'.getmypid(); @mkdir($dir.'/orders',0755,true);
$orders = new CommerceOrderStore($dir.'/orders');
$orders->create(['status'=>'paid','total_cents'=>5000,'currency'=>'eur','lines'=>[['title'=>'A','quantity'=>1,'line_total_cents'=>5000]],'affiliate_id'=>'p1']);
$s = (new CommerceAnalyticsService($orders))->getSummary();
test_assert('A-01 revenue', ($s['revenue_cents']??0)===5000);
test_assert('A-02 csv', strpos((new CommerceAnalyticsService($orders))->exportOrdersCsv(),'order_id')!==false);
exit(test_finish('commerce_analytics_test'));
