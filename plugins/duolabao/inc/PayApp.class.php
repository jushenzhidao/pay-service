<?php

class PayApp
{
    const GATEWAY = 'https://openapi.duolabao.com';
    private $accessKey;
	private $secretKey;

    function __construct($accessKey, $secretKey)
	{
		$this->accessKey = $accessKey;
		$this->secretKey = $secretKey;
	}

    public function submit($path, $param = null){
        $json = $param ? json_encode($param) : '';
        $time = time();
        $token = $this->get_token($path, $json, $time);
        $headers = [
            'Content-Type: application/json',
            'accessKey: ' . $this->accessKey,
            'timestamp: ' . $time,
            'token: ' . $token
        ];
		$path = implode('/', array_map('urlencode', explode('/', $path)));
        $response = $this->curl($path, $headers, $json);
        $result = json_decode($response, true);
        if($result['result'] == 'success'){
            return $result['data'];
        }elseif(isset($result['error'])){
            throw new Exception('['.$result['error']['errorCode'].']'.$result['error']['errorMsg']);
        }else{
            throw new Exception('接口请求失败');
        }
    }

    public function verifyNotify()
	{
		$timestamp = $_SERVER['HTTP_TIMESTAMP'];
		$signString = 'secretKey='.$this->secretKey.'&timestamp='.$timestamp;
		$token = strtoupper(sha1($signString));
		if ($token !== $_SERVER['HTTP_TOKEN']) {
			return false;
		}
		return true;
	}

    private function get_token($path, $body, $time){
        $sign_data = [
			'secretKey' => $this->secretKey,
			'timestamp' => $time,
			'path'      => $path,
		];
		if($body) {
			$sign_data['body'] = $body;
		}
		$o = '';
		foreach ($sign_data as $k => $v) {
			 $o .= "{$k}={$v}&";
		}
		$o = substr($o , 0 , -1);
		$token = strtoupper(sha1($o));
        return $token;
    }

    private function curl($path, $headers, $post = null)
	{
		$url = self::GATEWAY . $path;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$httpheader[] = "Accept: */*";
		$httpheader[] = "Accept-Encoding: gzip,deflate";
		$httpheader[] = "Accept-Language: zh-CN,zh;q=0.9";
		$httpheader[] = "Connection: keep-alive";
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		if ($post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($httpheader, $headers));
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
}