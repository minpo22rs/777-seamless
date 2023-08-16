<?php

namespace App\Classes;

use Exception;

class TMNOne
{

	private $tmnone_endpoint = 'https://api.tmn.one/api.php';
	private $wallet_endpoint = 'https://tmn-mobile-gateway.public-a-cloud1p.ascendmoney.io/tmn-mobile-gateway/';
	private $wallet_user_agent = 'okhttp/4.4.0';
	private $tmnone_keyid = 0;
	private $wallet_msisdn, $wallet_login_token, $wallet_tmn_id, $wallet_device_id, $wallet_access_token, $proxy_ip = '', $proxy_username = '', $proxy_password = '';

	public function __construct()
	{
	}

	public function setData($tmnone_keyid, $wallet_msisdn, $wallet_login_token, $wallet_tmn_id) {
		$this->tmnone_keyid = $tmnone_keyid;
		$this->wallet_msisdn = $wallet_msisdn;
		$this->wallet_login_token = $wallet_login_token;
		$this->wallet_tmn_id = $wallet_tmn_id;
		$this->wallet_device_id = substr(md5($wallet_msisdn . $wallet_tmn_id), 0, 16);
	}

	public function setProxy($proxy_ip, $proxy_username, $proxy_password) {
		$this->proxy_ip = $proxy_ip;
		$this->proxy_username = $proxy_username;
		$this->proxy_password = $proxy_password;
	}

	public function setDataWithAccessToken($tmnone_keyid, $wallet_access_token, $wallet_login_token, $wallet_device_id) {
		$this->tmnone_keyid = $tmnone_keyid;
		$this->wallet_access_token = $wallet_access_token;
		$this->wallet_login_token = $wallet_login_token;
		$this->wallet_device_id = $wallet_device_id;
	}

	public function getCachedAccessToken()
	{
		$request_body = json_encode(array('scope'=>'text_storage_obj', 'cmd'=>'get'));
		$encrypted_access_token = $this->tmnone_connect($request_body)['data'];
		if(!empty($encrypted_access_token))
		{
			$aes_key = hex2bin(substr(hash('sha512', $this->wallet_tmn_id) ,0 ,64));
			$aes_iv = hex2bin(substr($encrypted_access_token, 0, 32));
			$access_token = openssl_decrypt(base64_decode(substr($encrypted_access_token, 32)), 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv);
			if(!empty($access_token))
			{
				 $this->wallet_access_token = $access_token;
			}
		}
	}

	public function loginWithPin6($wallet_pin)
	{
		$this->getCachedAccessToken();
		if(!empty($this->wallet_access_token))
		{
			return $this->wallet_access_token;
		}
		$wallet_pin = hash('sha256', $this->wallet_tmn_id . $wallet_pin);
		$signature = $this->calculate_sign256($this->wallet_login_token . '|' . $wallet_pin);
		$postdata = array();
		$postdata['pin'] = $wallet_pin;
		$postdata['app_version'] = '5.25.1';
		$postdata = json_encode($postdata);
		$wallet_response_body = $this->wallet_connect('mobile-auth-service/v1/pin/login', array('Content-Type: application/json', 'Authorization: ' . $this->wallet_login_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='), $postdata);
		if(!empty($wallet_response_body['data']['access_token']))
		{
			$this->wallet_access_token = $wallet_response_body['data']['access_token'];
			$aes_key = hex2bin(substr(hash('sha512', $this->wallet_tmn_id) ,0 ,64));
			$aes_iv = openssl_random_pseudo_bytes(16);
			$encrypted_access_token = bin2hex($aes_iv) . base64_encode(openssl_encrypt($this->wallet_access_token, 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv));
			$request_body = json_encode(array('scope'=>'text_storage_obj', 'cmd'=>'set', 'data'=>$encrypted_access_token));
			$this->tmnone_connect($request_body);
		}
		return $this->wallet_access_token;
	}

	public function getBalance()
	{
		$uri = 'user-profile-composite/v1/users/';
		$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri);
		$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token), '');
		return isset($wallet_response_body['data']['current_balance']) ? $wallet_response_body['data']['current_balance'] : '';
	}

	public function fetchTransactionHistory($start_date, $end_date, $limit=10, $page=1)
	{
		$uri = 'history-composite/v1/users/transactions/history/?start_date=' . $start_date . '&end_date=' . $end_date . '&limit=' . $limit . '&page=' . $page . '&type=&action=';
		$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri);
		$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='), '');
		return isset($wallet_response_body['data']['activities']) ? $wallet_response_body['data']['activities'] : array();
	}

	public function fetchTransactionInfo($report_id)
	{
		$cache_filename = sys_get_temp_dir() . '/tmn-' . $report_id;
		$aes_key = hex2bin(substr(hash('sha512', $this->wallet_tmn_id) ,0 ,64));
		if(file_exists($cache_filename))
		{
			$wallet_response_body = file_get_contents($cache_filename);
			$aes_iv = hex2bin(substr($wallet_response_body, 0, 32));
			$wallet_response_body = openssl_decrypt(substr($wallet_response_body, 32), 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv);
			$wallet_response_body = json_decode($wallet_response_body, true);
			$wallet_response_body['cached'] = true;
			return $wallet_response_body;
		}
		$uri = 'history-composite/v1/users/transactions/history/detail/' . $report_id . '?version=1';
		$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri);
		$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='), '');
		if(!empty($wallet_response_body['data']))
		{
			$aes_iv = openssl_random_pseudo_bytes(16);
			$encrypted_wallet_response_body = bin2hex($aes_iv) . openssl_encrypt(json_encode($wallet_response_body['data']), 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv);
			file_put_contents($cache_filename, $encrypted_wallet_response_body);
		}
		return isset($wallet_response_body['data']) ? $wallet_response_body['data'] : array();
	}

	public function generateVoucher($amount,$detail='')
	{
		try
		{
			$uri = 'transfer-composite/v1/vouchers/';
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' .  $this->wallet_access_token . '|R|' . $amount . '|1|' . $detail);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='),
				'{"amount":"' . $amount . '","detail":"' . $detail . '","tmn_id":"' . $this->wallet_tmn_id . '","mobile":"' . $this->wallet_msisdn . '","voucher_type":"R","member":"1"}');
			if(substr($wallet_response_body['code'],-4) != '-200')
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
			//{"code":"TRC-200","data":{"tmn_id":"tmn.xxxxxx","amount":1.00,"link":"0000000000f3453f62bd07185708325c38N","mobile":"0987654321","weight":0.4,"link_voucher":"https://gift.truemoney.com/campaign/?v=0000000000f3453f62bd07185708325c38N/#/voucher_detail/","utiba_id":50020690000000,"type":"R","update_date":1683893000100,"expire_date":1684153000100,"link_redeem":"https://gift.truemoney.com/campaign/?v=0000000000f3453f62bd07185708325c38N","member":1,"voucher_id":299291608745000000,"detail":"TEXT","create_date":1683893000100,"status":"active"}}
			return $wallet_response_body['data'];
		}
		catch (Exception $e)
		{
			return array('error'=>$e->getMessage());
		}
	}

	public function getRecipientName($payee_wallet_id)
	{
		try
		{
			$amount = '1.00';
			$uri = 'transfer-composite/v2/p2p-transfer/draft-transactions';
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' .  $this->wallet_access_token . '|' . $amount . '|' . $payee_wallet_id);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='),
				'{"receiverId":"' . $payee_wallet_id . '","amount":"' . $amount . '"}');
			if(substr($wallet_response_body['code'],-4) != '-200')
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
			return $wallet_response_body['data']['recipient_name'];
		}
		catch (Exception $e)
		{
			return array('error'=>$e->getMessage());
		}
	}

	public function transferP2P($payee_wallet_id,$amount,$personal_msg='')
	{
		try
		{
			$amount = number_format($amount, 2, '.', '');
			$uri = 'transfer-composite/v2/p2p-transfer/draft-transactions';
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' .  $this->wallet_access_token . '|' . $amount . '|' . $payee_wallet_id);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='),
				'{"receiverId":"' . $payee_wallet_id . '","amount":"' . $amount . '"}');
			if(substr($wallet_response_body['code'],-4) != '-200')
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
			$draft_transaction_id = $wallet_response_body['data']['draft_transaction_id'];
			$reference_key = $wallet_response_body['data']['reference_key'];

			$uri = 'transfer-composite/v1/p2p-transfer/draft-transactions/' . $draft_transaction_id;
			$signature = $this->calculate_sign256($reference_key);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='),
				'{"personal_message":"' . $personal_msg . '","signature":"' . $signature . '"}', 'PUT');
			if(substr($wallet_response_body['code'],-4) != '-200')
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}

			$uri = 'transfer-composite/v1/p2p-transfer/transactions/' . $draft_transaction_id . '/';
			$signature = $this->calculate_sign256($reference_key);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='),
				'{"reference_key":"' . $reference_key . '","signature":"' . $signature . '"}');
			if(substr($wallet_response_body['code'],-4) != '-200')
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
		}
		catch (Exception $e)
		{
			return array('error'=>$e->getMessage());
		}
		return isset($wallet_response_body['data']) ? $wallet_response_body['data'] : array();
	}

	/*
	$bank_code : SCB,BBL,BAY,KBANK,KTB
	*/
	public function transferBankAC($bank_code,$bank_ac,$amount,$wallet_pin)
	{
		try
		{
			$amount = number_format($amount, 2, '.', '');
			$signature = $this->calculate_sign256($amount . '|' . $bank_code . '|' . $bank_ac);
			$wallet_response_body = $this->wallet_connect('fund-composite/v1/withdrawal/draft-transaction', array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='),
				'{"bank_name":"' . $bank_code . '","bank_account":"' . $bank_ac . '","amount":"' . $amount . '"}');
			if(substr($wallet_response_body['code'],-4) != '-200')
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
			$draft_transaction_id = $wallet_response_body['data']['draft_transaction_id'];

			$uri = 'fund-composite/v3/withdrawal/transaction';
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' .  $this->wallet_access_token . '|' . $draft_transaction_id);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='),
				'{"draft_transaction_id":"' . $draft_transaction_id . '"}');
			if(substr($wallet_response_body['code'],-4) != '-428') //{"code":"MAS-428","data":{"csid":"a9d8989b-xxxx-xxxx-xxxx-b4a36a0bfa7d","method":"pin"}}
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
			$csid = $wallet_response_body['data']['csid'];

			$wallet_pin = hash('sha256', $this->wallet_tmn_id . $wallet_pin);
			$signature = $this->calculate_sign256($this->wallet_access_token . '|' . $csid . '|' . $wallet_pin . '|manual_input');
			$wallet_response_body = $this->wallet_connect('mobile-auth-service/v1/authentications/pin', array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'CSID: ' . $csid),
				'{"pin":"' . $wallet_pin . '","method":"manual_input"}');
			if(substr($wallet_response_body['code'],-4) != '-200') //{"code":"FNC-200","data":{"withdraw_status":"VERIFIED"}}
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
		}
		catch (Exception $e)
		{
			return array('error'=>$e->getMessage() . ' (line:' . $e->getLine() . ')');
		}
		return isset($wallet_response_body['data']) ? $wallet_response_body['data'] : array();
	}

	public function getWithdrawalStatus($draft_transaction_id)
	{
		$wallet_response_body = array();
		try
		{
			$uri = 'fund-composite/v1/withdrawal/transaction/' . $draft_transaction_id . '/status';
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng='),
				'');
		}
		catch (Exception $e)
		{
			return array('error'=>$e->getMessage());
		}
		return $wallet_response_body;
	}

	private function tmnone_connect($request_body)
	{
		$headers = [];
		$aes_key = hex2bin(substr(hash('sha512', $this->wallet_login_token) ,0 ,64));
		$aes_iv = openssl_random_pseudo_bytes(16);
		$request_body = bin2hex($aes_iv) . base64_encode(openssl_encrypt($request_body, 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv));
		$request_body = json_encode(array('encrypted'=>$request_body));
		$curl = curl_init($this->tmnone_endpoint);
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('X-KeyID: ' . $this->tmnone_keyid, 'Content-Type: application/json'));
		curl_setopt($curl, CURLOPT_USERAGENT, 'okhttp/4.4.0/202305202300/' . $this->tmnone_keyid);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body);
		curl_setopt($curl, CURLOPT_HEADERFUNCTION,
			function($curl, $header) use (&$headers)
			{
				$len = strlen($header);
				$header = explode(':', $header, 2);
				if (count($header) < 2) // ignore invalid headers
				{
					return $len;
				}

				$headers[strtolower(trim($header[0]))] = trim($header[1]);

				return $len;
			}
		);
		$response_body = curl_exec($curl);
		curl_close($curl);
		if(!empty($headers['x-wallet-user-agent']))
		{
			$this->wallet_user_agent = $headers['x-wallet-user-agent'];
		}
		$response_body = json_decode($response_body,true);
		if(isset($response_body['encrypted']))
		{
			$response_body = openssl_decrypt(base64_decode($response_body['encrypted']), 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv);
			$response_body = json_decode($response_body,true);
		}
		return $response_body;
	}

	private function wallet_connect($uri, $headers, $request_body='', $custom_method=null)
	{
		$ssl_ciphers = array('ECDHE-RSA-AES256-GCM-SHA384','ECDHE-RSA-AES128-GCM-SHA256','ECDHE-RSA-CHACHA20-POLY1305-SHA256','ecdhe_rsa_aes_256_gcm_sha_384','ecdhe_rsa_aes_128_gcm_sha_256','ecdhe_rsa_chacha20_poly1305_sha_256');
		foreach($ssl_ciphers as $ssl_cipher)
		{
			$wallet_connect = $this->wallet_connect_curl($uri, $headers, $request_body, $custom_method, $ssl_cipher);
			if(is_array($wallet_connect) || strpos($wallet_connect,'Unknown cipher') === false)
			{
				break;
			}
		}
		return $wallet_connect;
	}

	private function wallet_connect_curl($uri, $headers, $request_body, $custom_method, $ssl_cipher)
	{
		$curl = curl_init($this->wallet_endpoint . $uri);
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_VERBOSE, false);
		curl_setopt($curl, CURLOPT_USERAGENT, $this->wallet_user_agent);
		if(stripos(PHP_OS, 'WIN') === 0)
		{
			curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_3);
		}
		else
		{
			curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, $ssl_cipher);
		}
		if(!empty($this->proxy_ip))
		{
			curl_setopt($curl, CURLOPT_PROXY, $this->proxy_ip);
			if(!empty($this->proxy_username))
			{
				curl_setopt($curl, CURLOPT_PROXYUSERPWD, $this->proxy_username . ':' . $this->proxy_password);
			}
		}
		if(!empty($request_body))
		{
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body);
		}
		if(!empty($custom_method))
		{
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $custom_method);
		}
		$response_body = curl_exec($curl);
		if($response_body === false)
		{
			return curl_error($curl);
		}
		curl_close($curl);
		$response_body = json_decode($response_body,true);
		if(empty($response_body))
		{
			return '';
		}
		if(isset($response_body['code']) && $response_body['code'] == 'MAS-401')
		{
			$request_body = json_encode(array('scope'=>'text_storage_obj', 'cmd'=>'set', 'data'=>''));
			$this->tmnone_connect($request_body);
		}
		return $response_body;
	}

	public function calculate_sign256($data)
	{
		$request_body = json_encode(array('cmd'=>'calculate_sign256', 'data'=>array('login_token'=>$this->wallet_login_token, 'device_id'=>$this->wallet_device_id, 'data'=>$data)));
		return isset($this->tmnone_connect($request_body)['signature']) ? $this->tmnone_connect($request_body)['signature'] : '';
	}

}

?>