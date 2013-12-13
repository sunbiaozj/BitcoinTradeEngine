<?php
/*
 * Data structure
 *
 * ADD ORDER
 * 0 1 2 3 4 5 6 7|0 1 2 3 4 5 6 7|0 1 2 3 4 5 6 7|0 1 2 3 4 5 6 7
 * T|   MARKET          MARKET    |          ACCOUNT NUMBER          ... + 0
 * ...                            |                QTY               ... + 32
 * ...                                                               ... + 64
 * ...                            |               PRICE              ... + 96
 * ...                                                               ... + 128
 * ...                            |D|* * * * * * * * * * * * * * *       + 160
 */
class Order {
	public $type = 0;
	public $market = 0;
	public $account_num;
	public $qty = 1;
	public $price = 1;
	public $direction = 0;
	public function toString() {
		$o = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
		$o[0] = chr(
			(($this->type << 7) & 0x80) |
			($this->market & ~0x80)
		);

		$o[1] = chr($o[1] | (($this->account_num >> 8) & 0xFF));
		$o[2] = chr($o[2] | (($this->account_num) & 0xFF));

		$o[3] = chr($o[3] | (($this->qty >> 56) & 0xFF));
		$o[4] = chr($o[4] | (($this->qty >> 48) & 0xFF));
		$o[5] = chr($o[5] | (($this->qty >> 40) & 0xFF));
		$o[6] = chr($o[6] | (($this->qty >> 32) & 0xFF));
		$o[7] = chr($o[7] | (($this->qty >> 24) & 0xFF));
		$o[8] = chr($o[8] | (($this->qty >> 16) & 0xFF));
		$o[9] = chr($o[9] | (($this->qty >> 8) & 0xFF));
		$o[10] = chr($o[10] | (($this->qty) & 0xFF));

		$o[11] = chr($o[11] | (($this->price >> 56) & 0xFF));
		$o[12] = chr($o[12] | (($this->price >> 48) & 0xFF));
		$o[13] = chr($o[13] | (($this->price >> 40) & 0xFF));
		$o[14] = chr($o[14] | (($this->price >> 32) & 0xFF));
		$o[15] = chr($o[15] | (($this->price >> 24) & 0xFF));
		$o[16] = chr($o[16] | (($this->price >> 16) & 0xFF));
		$o[17] = chr($o[17] | (($this->price >> 8) & 0xFF));
		$o[18] = chr($o[18] | (($this->price) & 0xFF));

		$o[19] = chr($o[19] | (($this->direction << 7) & 0x80));
		return $o;
	}
	public function send(&$duration = null){
		$sock = stream_socket_client('unix:///tmp/BitcoinTradeEngine.sock', $errno, $errstr);
		if(!$sock){
			echo "no socket\n";
			exit;
		}
		$start = microtime(true);
		fwrite($sock, $this->toString());
		//echo "Wrote ", microtime(true) - $start, "\n";
		$data = fread($sock, 8);
		//echo "Read ", microtime(true) - $start, "\n";
		if(!strlen($data)){
			echo "Error with order\n";
			return null;
		}
		$data = unpack('N*', $data);
		fclose($sock);
		$duration = microtime(true) - $start;
		return (((int) current($data)) << 32) | ((int) next($data)) ;
	}
}
function run_random_order() {
	$order = new Order;
	$order->account_num = mt_rand(0, mt_getrandmax());
	$order->qty = mt_rand(0, mt_getrandmax());
	$order->price = mt_rand(0, mt_getrandmax());
	$order->direction = mt_rand(0, 1); // Sell
	return $order->send($time);
}
$num_processes = 25;
$threads = array();
$start = microtime(true);
$last_check = 0;
$count = 0;
while(true) {
	while(count($threads) < $num_processes) {
		$pid = pcntl_fork();
		$threads[$pid] = null;
		if($pid) {
			// Parent
			continue;
		} else {
			run_random_order();
			//echo "$oid\n";
			exit;
		}
	}
	$pid = pcntl_wait($status);
	unset($threads[$pid]);
	//echo "process ended ".count($threads)." left\n";
	$count++;
	if($last_check + 1 < ($cur_time = microtime(true))){
		echo "Processing at about: ". ($count / ($cur_time - $start)) . " per second   Total: $count       \n";
		$last_check = $cur_time;
	}
}