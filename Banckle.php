<?php

/**
 *
 * Copyright 2011 Banckle, Inc.
 *
 */
if (!function_exists('curl_init')) {
	throw new Exception('Banckle needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
	throw new Exception('Banckle needs the JSON PHP extension.');
}

/**
 * Provides access to the Banckle Platform.
 *
 * @author Imran Anwar <imran.anwar@banckle.com>
 */
class Banckle {

	protected $banckleUrl = "https://apps.banckle.com/";

	/**
	 * Performs Banckle Api Request.
	 *
	 * @param string $url Target Banckle API URL.
	 * @param string $method Method to access the API such as GET, POST, PUT and DELETE
	 * @param string $headerType XML or JSON
	 * @param string $src Post data.
	 *
	 *
	 */
	protected function banckleLiveChatRequest($url, $method="GET", $headerType="XML", $src="") {

		$method = strtoupper($method);
		$headerType = strtoupper($headerType);
		$session = curl_init();
		curl_setopt($session, CURLOPT_URL, $url);
		if ($method == "GET") {
			curl_setopt($session, CURLOPT_HTTPGET, 1);
		} else {
			curl_setopt($session, CURLOPT_POST, 1);
			curl_setopt($session, CURLOPT_POSTFIELDS, $src);
			curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method);
		}
		curl_setopt($session, CURLOPT_HEADER, false);
		if ($headerType == "XML") {
			curl_setopt($session, CURLOPT_HTTPHEADER, array('Accept: application/xml', 'Content-Type: application/xml'));
		} else {
			curl_setopt($session, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		}
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		if (preg_match("/^(https)/i", $url))
			curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
		$result = curl_exec($session);
		curl_close($session);

		return $result;
	}

}

//class Banckle

/**
 * Provides Authentication for Banckle Accounts.
 * 
 */
class BanckleAuthentication extends Banckle {

	private $userId = "";
	private $password = "";
	private $sourceSite = "";
	private $platform = "";

	/**
	 *
	 * @param string $userId
	 * @param string $password
	 * @param string $sourceSite
	 * @param string $platform
	 */
	public function __construct($userId, $password, $sourceSite="", $platform="") {
		$this->userId = $userId;
		$this->password = $password;
		$this->sourceSite = $sourceSite;
		$this->platform = $platform;
	}

	/**
	 *
	 *
	 * @return doAuthenticate() - Method will retun the Authentication string in JSON
	 */
	public function doAuthenticate() {
		return $this->banckleLiveChatRequest($this->banckleUrl . 'api/authenticate?userid=' . $this->userId . '&password=' . $this->password . '&sourceSite=' . $this->sourceSite . '&platform=' . $this->platform, "GET", "JSON", "");
	}

	/**
	 *
	 * @return isAdmin() - Method will retun true if user is Admin
	 */
	public function isAdmin() {
		return false;
	}

	/**
	 *
	 * @return getToken() - Method will return the Token after a successfull user authentication.
	 */
	public function getToken() {
		$content = $this->banckleLiveChatRequest($this->banckleUrl . 'api/authenticate?userid=' . $this->userId . '&password=' . $this->password . '&sourceSite=' . $this->sourceSite . '&platform=' . $this->platform, "GET", "JSON", "");
		if ($content !== false) {
			$arr = json_decode($content, true);
			if (array_key_exists('error', $arr)) {
				return $arr['error']['details'];
			} else {
				return $arr['return']['token'];
			}
		} else {
			throw new Exception('Network Error.');
		}
	}

}

/**
 * Provides the access to Banckle Live Chat APIs.
 * 
 */
class BanckleLiveChat extends Banckle {

	protected $dataType = "JSON";
	protected $api = "";
	protected $token = "";
	protected $apiUrl = "";

	/**
	 *
	 * @param string $api API like department, deployments etc..
	 * @param string $dataType JSON or XML
	 * @param string $userId Banckle user Id
	 * @param string $password Banckle password
	 */
	protected function __construct($api="", $dataType="", $userId="", $password="") {
		$bAuth = new BanckleAuthentication($userId, $password);
		$this->token = $bAuth->getToken();
		$this->api = $api;
		$this->dataType = $dataType;
		$this->apiUrl = "";
	}

	protected  function endsWith($haystack, $needle, $case=true) {
		if ($case) {
			return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
		}
		return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
	}

	/**
	 *
	 * @param string $array
	 * @param string $xml
	 * @return string arrayToXML($array,$xml,addApi) - Method will Return the XML represention of an array.
	 */
	protected function arrayToXML($array, $xml = '', $addApi = true) {
		$api = $this->api;
		if($this->endsWith($api, "s")>0){
			$api = substr($api, 0, (strlen($api)-1));
		}
		if ($xml == "")
			$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		if($addApi == true)
			$xml .= "<" . $api . ">";
		foreach ($array as $k => $v) {
			if (is_array($v)) {
				$xml .= '<' . $k . '>';
				$xml = $this->arrayToXML($v, $xml, false);
				$xml .= '</' . $k . '>';
			} else {
				$xml .= '<' . $k . '>' . $v . '</' . $k . '>';
			}
		}
		if($addApi == true)
			$xml .= "</" . $api . ">";
		return $xml;
	}

	/**
	 *
	 * @param string $id Optional, only required when there is a need to add the specific Id for API calls, for example if Department is calling this function then this will be Department ID
	 * @return string url($id) - Method will return the API url.
	 */
	protected function url($id="") {
		$ext = $this->dataType === 'JSON' ? '.js' : '.xml';
		$isId = $id === "" ? "" : "/" . $id;
		return $this->banckleUrl . 'em/api/' . $this->api . $isId . $ext . '?_token=' . $this->token;
	}

	/**
	 *
	 * @return string getAll() - Method will return all the objects for a perticular API.
	 */
	public function getAll() {
		$content = $this->banckleLiveChatRequest($this->url(), "GET", $this->dataType, "");
		if ($content !== false) {
			return $content;
		} else {
			throw new Exception('Network Error.');
		}
	}

	/**
	 *
	 * @param string $id Id of the BLC object, which you want to retrive.
	 * @return <string> Returns the JSON or XML based object info.
	 */
	public function get($id) {
		$content = $this->banckleLiveChatRequest($this->url($id), "GET", $this->dataType, "");
		if ($content !== false) {
			return $content;
		} else {
			throw new Exception('Network Error.');
		}
	}

	/**
	 *
	 * @param array $data Array of the respective object values
	 * @return string JSON or XML representation of the created object.
	 */
	public function create($data) {
		return $this->update("", $data);
	}

	/**
	 *
	 * @param <type> $id
	 * @param <type> $data
	 * @return string JSON or XML representation of the updated object. 
	 */
	public function update($id, $data){
		$input = "";
		$method = "PUT";
		if($id===""){
			$method = "POST";
		}
		if ($this->dataType === "XML") {
			$input = $this->arrayToXML($data);
		} else {
			$input = json_encode($data);
		}
		$content = $this->banckleLiveChatRequest($this->url($id), $method, $this->dataType, $input);
		if ($content !== false) {
			if ($content === "")
				return true;
			else
				return false;
		} else {
			throw new Exception('Network Error.');
		}
	}

	/**
	 *
	 * @param string $id Id of the BLC object to delete such as Department Id, Deployment Id et..
	 * @return bool true if perticular object is deleted from the servers.
	 */
	public function delete($id) {

		$content = $this->banckleLiveChatRequest($this->url($id), "DELETE", $this->dataType, "");
		if ($content !== false) {
			if ($content === "")
				return true;
			else
				return false;
		} else {
			throw new Exception('Network Error.');
		}
	}

}

/**
 * Provides access to Banckle Live Chats' Department API.
 * 
 */
class BLC_Department extends BanckleLiveChat {

	/**
	 *
	 * @param string $dataType	JSON or XML
	 * @param string $userId Banckle User Id.
	 * @param string $password Banckle Password.
	 */
	public function __construct($dataType, $userId, $password) {
		parent::__construct($api = "departments", $dataType, $userId, $password);
	}
}

/**
 * Provides access to Banckle Live Chats' Deployment API.
 *
 */
class BLC_Deployment extends BanckleLiveChat {

	/**
	 * 
	 * @param string $dataType	JSON or XML
	 * @param string $userId Banckle User Id.
	 * @param string $password Banckle Password.
	 */
	public function __construct($dataType, $userId, $password) {
		parent::__construct($api = "deployments", $dataType, $userId, $password);
	}
}
/**
 * Provides access to Banckle Live Chats' surveys API.
 *
 */
class BLC_Survey extends BanckleLiveChat {

	/**
	 *
	 * @param string $dataType	JSON or XML
	 * @param string $userId Banckle User Id.
	 * @param string $password Banckle Password.
	 */
	public function __construct($dataType, $userId, $password) {
		parent::__construct($api = "surveys", $dataType, $userId, $password);
	}
}

//

/**
 * Provides access to Banckle Live Chats' Canned Messages API.
 *
 */
class BLC_CannedMessages extends BanckleLiveChat {

	/**
	 *
	 * @param string $dataType	JSON or XML
	 * @param string $userId Banckle User Id.
	 * @param string $password Banckle Password.
	 */
	public function __construct($dataType, $userId, $password) {
		parent::__construct($api = "canned-messages", $dataType, $userId, $password);
	}
}
/**
 * Provides access to Banckle Live Chats' Filter Rules API.
 *
 */
class BLC_FilterRules extends BanckleLiveChat {

	/**
	 *
	 * @param string $dataType	JSON or XML
	 * @param string $userId Banckle User Id.
	 * @param string $password Banckle Password.
	 */
	public function __construct($dataType, $userId, $password) {
		parent::__construct($api = "filter-rules", $dataType, $userId, $password);
	}
}
?>
