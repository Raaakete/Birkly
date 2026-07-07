<?php
require_once __DIR__ . '/bootstrap.php';
test_reset_failures();
echo "Commerce quote tests\n\n";
$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/includes/CommerceQuoteStore.php';
$qs = new CommerceQuoteStore(sys_get_temp_dir().'/bq-'.getmypid());
$q = $qs->create(['customer_email'=>'b2b@test.com','lines'=>[['line_id'=>'p:1','title'=>'Item','quantity'=>1,'line_total_cents'=>2500,'effective_amount_cents'=>2500]],'total_cents'=>2500,'currency'=>'eur']);
test_assert('Q-01 created', !empty($q['token']));
$a = $qs->accept($q['token']);
test_assert('Q-02 accepted', ($a['quote']['status']??'')==='pending_payment');
exit(test_finish('commerce_quote_test'));
