<?php

class fuiou2_plugin
{
	static public $info = [
		'name'        => 'fuiou2', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '富友支付(合作方)', //支付插件显示名称
		'author'      => '富友', //支付插件作者
		'link'        => 'https://www.fuiou.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '机构号',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appkey' => [
				'name' => '富友公钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appurl' => [
				'name' => '订单号前缀',
				'type' => 'input',
				'note' => '',
			],
			'entrykey' => [
				'name' => '代理进件密钥',
				'type' => 'input',
				'note' => '不使用进件或投诉接口可不填写',
			],
			'appswitch' => [
				'name' => '环境选择',
				'type' => 'select',
				'options' => [0=>'生产环境',1=>'测试环境'],
			],
		],
		'select' => null,
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'JS支付',
		],
		'note' => '', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			if(checkalipay() && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='wxpay'){
			if(checkmobile() && !checkwechat() && $channel['appwxa']>0){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $device, $mdevice;

		if($order['typename']=='alipay'){
			if($mdevice=='alipay' && in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/alipayjs/'.TRADE_NO.'/?d=1'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($device=='mobile' && $mdevice!='wechat' && $channel['appwxa']>0){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//通用下单
	static private function addOrder($pay_type){
		global $siteurl, $channel, $order, $ordername, $clientip, $conf;

		require(PAY_ROOT."inc/PayService.class.php");

		$param = [
			'order_type' => $pay_type,
			'order_amt' => strval($order['realmoney']*100),
			'mchnt_order_no' => $channel['appurl'].TRADE_NO,
			'txn_begin_ts' => date('YmdHis'),
			'goods_des' => $ordername,
			'term_ip' => $clientip,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'goods_detail' => '',
			'addn_inf' => '',
			'curr_type' => 'CNY',
			'goods_tag' => '',
		];

		$client = new PayService($channel['appid'],$channel['appmchid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $param) {
			$result = $client->submit('/preCreate', $param);
			return $result['qr_code'];
		});
	}

	//JSAPI支付
	static private function jspay($trade_type, $sub_appid, $sub_openid){
		global $siteurl, $channel, $order, $ordername, $clientip, $conf;

		require(PAY_ROOT."inc/PayService.class.php");

		$param = [
			'trade_type' => $trade_type,
			'order_amt' => strval($order['realmoney']*100),
			'mchnt_order_no' => $channel['appurl'].TRADE_NO,
			'txn_begin_ts' => date('YmdHis'),
			'goods_des' => $ordername,
			'term_ip' => $clientip,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'limit_pay' => '',
			'goods_detail' => '',
			'addn_inf' => '',
			'curr_type' => 'CNY',
			'goods_tag' => '',
			'product_id' => '',
			'openid' => '',
			'sub_openid' => $sub_openid,
			'sub_appid' => $sub_appid,
		];

		$client = new PayService($channel['appid'],$channel['appmchid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $param) {
			$result = $client->submit('/wxPreCreate', $param);
			return $result;
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('2',$channel['apptype']) && !in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}else{
			try{
				$code_url = self::addOrder('ALIPAY');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
		}

		if(checkalipay() || $mdevice=='alipay'){
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}
	}

	static public function alipayjs(){
		[$user_type, $user_id] = alipay_oauth();

		$blocks = checkBlockUser($user_id, TRADE_NO);
		if($blocks) return $blocks;

		if($user_type == 'openid'){
			return ['type'=>'error','msg'=>'支付宝快捷登录获取uid失败，需将用户标识切换到uid模式'];
		}

		try{
			$result = self::jspay('FWC', '', $user_id);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$result['reserved_transaction_id'], 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $siteurl, $device, $mdevice;
		try{
			$code_url = self::addOrder('WECHAT');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if(checkwechat() || $mdevice == 'wechat'){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile() || $device == 'mobile') {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl, $channel;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');
		
		//①、获取用户openid
		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');
		try{
			$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
			$openid = $tools->AppGetOpenid($code);
		}catch(Exception $e){
			exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');
		
		//②、统一下单
		try{
			$result = self::jspay('LETPAY', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"微信支付下单失败！'.$ex->getMessage().'"}');
		}

		$payinfo = ['appId'=>$result['sdk_appid'], 'timeStamp'=>$result['sdk_timestamp'], 'nonceStr'=>$result['sdk_noncestr'], 'package'=>$result['sdk_package'], 'signType'=>$result['sdk_signtype'], 'paySign'=>$result['sdk_paysign']];

		exit(json_encode(['code'=>0, 'data'=>$payinfo]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl, $channel, $order;

		if ($channel['appwxa']>0) {
            $wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
            try {
                $code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
            } catch (Exception $e) {
                return ['type'=>'error','msg'=>$e->getMessage()];
            }
            return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
        }else{
			return self::wxpay();
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::addOrder('UNIONPAY');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'银联云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$xml = urldecode($_POST['req']);
		if(!$xml) return ['type'=>'html','data'=>'no data'];
		//file_put_contents('logs.txt', $xml);

		$arr = json_decode(json_encode(simplexml_load_string($xml)), true);
		if(!$arr) return ['type'=>'html','data'=>'xml err'];

		require(PAY_ROOT."inc/PayService.class.php");
		
		$client = new PayService($channel['appid'],$channel['appmchid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);
		$verify_result = $client->verifySign($arr);

        if ($verify_result) {
			if($arr['result_code'] == '000000'){
				$out_trade_no = substr($arr['mchnt_order_no'], strlen($channel['appurl']));
				$trade_no = $arr['transaction_id'];
				$money = $arr['order_amt'];
				$buyer = $arr['user_id'];
				if($out_trade_no == TRADE_NO){
					processNotify($order, $trade_no, $buyer);
				}
				return ['type'=>'html','data'=>'1'];
			}
			return ['type'=>'html','data'=>'0'];
        }else{
			return ['type'=>'html','data'=>'0'];
		}

	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		require(PAY_ROOT."inc/PayService.class.php");

		if($order['type'] == 1) $pay_type = 'ALIPAY';
		else if($order['type'] == 2) $pay_type = 'WECHAT';
		else if($order['type'] == 4) $pay_type = 'UNIONPAY';

		$param = [
			'mchnt_order_no' => $channel['appurl'].$order['trade_no'],
			'refund_order_no' => $order['refund_no'],
			'order_type' => $pay_type,
			'total_amt' => strval($order['realmoney']*100),
			'refund_amt' => strval($order['refundmoney']*100),
			'operator_id' => '',
		];

		$client = new PayService($channel['appid'],$channel['appmchid'],$channel['appkey'],$channel['appsecret'],$channel['appswitch']==1);

		try{
			$result = $client->submit('/commonRefund', $param);

			return ['code'=>0, 'trade_no'=>$result['mchnt_order_no'], 'refund_fee'=>$result['reserved_refund_amt']];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//投诉回调
	static public function complainnotify(){
		global $channel, $order;

		$json = $_POST['req'];
		if(!$json) return ['type'=>'html','data'=>'no req'];

		$data = json_decode($json,true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		require(PAY_ROOT."inc/EntryService.class.php");
		
		$client = new EntryService($channel['appid'],$channel['entrykey']);
		$verify_result = $client->verifySign($data);

        if ($verify_result) {
			$thirdid = $data['complaint_id'];
			$action_type = $data['action_type'];
			if(substr($channel['appmchid'],0,1)=='['){
				$channel['appmchid'] = $data['fy_mchnt_cd'];
			}
			$model = \lib\Complain\CommUtil::getModel($channel);
			$model->refreshNewInfo($thirdid, $action_type);
			return ['type'=>'html','data'=>'1'];
        }else{
			return ['type'=>'html','data'=>'0'];
		}
	}

	//平台入网审核回调
	static public function applynotify(){
		global $channel, $order;

		$json = $_POST['req'];
		if(!$json) return ['type'=>'html','data'=>'no req'];

		$data = json_decode($json,true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		require(PAY_ROOT."inc/EntryService.class.php");
		
		$client = new EntryService($channel['appid'],$channel['entrykey']);
		$verify_result = $client->verifySign($data);

        if ($verify_result) {
			$model = \lib\Applyments\CommUtil::getModel2($channel);
			if($model) $model->notify($data);
			return ['type'=>'html','data'=>'1'];
        }else{
			return ['type'=>'html','data'=>'0'];
		}
	}
}