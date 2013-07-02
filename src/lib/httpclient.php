<?php
/**
 Prails Web Framework
 Copyright (C) 2013  Robert Kunze

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/** Class HTTPClient
 *
 * This class provides means to easily open a server-side HTTP(S) connection and query / send data.
 **/
define("CR", "\r\n");
class HTTPClient {
	private $sock = null;
	private $request = Array();
	private $options = Array(
		"timeout" => 30,
		"follow" => true,
	);

	public $responseHeaders = null;
	public $responseText = null;
	public $status = null;
	public $statusText = null;

	function HTTPClient($options = Array()) {
		$this->options = array_merge_recursive_distinct($this->options, $options);
		$this->request = Array();
		$this->responseHeaders = $this->responseText = $this->status = $this->statusText = null;
	}

	/**
	 * open($method, $url[, $user, $password]) -> void
	 * - $method (String) - The HTTP method to use, such as "GET", "POST", "PUT", "DELETE", etc.
	 * - $url (String) - The URL to which to send the request.
	 * - $user (String) - The optional user name to use for authentication purposes.
	 * - $password (String) - The optional password to use for authentication purposes.
	 * 
	 * Initializes a request. 
	 **/
	function open($method, $url, $user = null, $password = null) {
		$this->request = Array();
		$this->responseHeaders = $this->responseText = $this->status = $this->statusText = null;
		$iurl = parse_url($url);

		if (!$iurl)
			throw new Exception("could not read data from url '".$url."'");
		if (!empty($user)) {
			$this->options["user"] = $user;
			$this->options["password"] = $password;
		}

		switch ($iurl["scheme"]) {
			case "https": $scheme = "ssl://"; $port = 443; break;
			case "http":
			default: $scheme = ""; $port = 80; break;
		}

		$this->sock = fsockopen($scheme . $iurl["host"], $port, $errno, $errstr, $this->options['timeout'] or 30);
		if (!$this->sock)  {
			throw new Exception($errstr." (".$errno.")");
		}

		$path = $iurl["path"];
		if (!empty($iurl["query"])) 
			$path .= "?".$iurl["query"];
		
		$this->request["method"] = strtoupper($method);
		$this->request['url'] = $url;

		$this->request["protocol"] = strtoupper($method)." ".$path." HTTP/1.0";
		$this->setRequestHeader("Host", $iurl["host"]);
		$this->setRequestHeader("User-Agent", "Prails Web Framework");
		if (!empty($this->options["user"]) && !empty($this->options["password"])) {
			$this->setRequestHeader("Authorization", "Basic ".base64_encode($this->options["user"].":".$this->options["password"]));
		}
	}

	/**
	 * setRequestHeader($header, $value) -> void
	 * - $header (String) - The name of the header whose value is to be set.
	 * - $value (String) - The value to set as the body of the header.
	 *
	 * Sets the value of an HTTP request header. You must call setRequestHeader() 
	 * after open(), but before send(). 
	 * 
	 * _Please note_: Calling setRequestHeader with that name again will override 
	 * any previously set header with the same name.
	 *
	 * *Example:*
	 * 
	 * {{{
	 * $hc = new HTTPClient();
	 * $hc->open("POST", "http://plain-webservice.example.org/echo");
	 * $hc->setRequestHeader("Content-Type", "text/plain");
	 * $result = $hc->send("Hello World!");
	 * }}}
	 **/
	function setRequestHeader($header, $value) {
		$this->request["headers"][$header] = $value;
	}

	/**
	 * send([$data]) -> String
	 * - $data (String|Array) - the data to be sent (optional)
	 *
	 * Sends the request. This method doesn't return until the response has arrived. Please note, that 
	 * sending data will automatically add a `Content-Length` header to the request. This method should
	 * be called after the request has been initialized through calling the open() method.
	 *
	 * *Example:*
	 *
	 * {{{
	 * $hc = new HTTPClient();
	 * $hc->open("GET", "http://www.google.com/");
	 * $response = $hc->send();
	 * echo $hc->getResponseHeader("Content-Type") . "<br/>" . $response;
	 * }}}
	 **/
	function send($data = null) {
		$this->responseHeaders = null;
		if (!empty($data)) {
			$data = (!is_string($data) ? http_build_query($data) : $data);
		}
		if (!empty($data)) {
			$this->setRequestHeader("Content-Length", strlen($data));
		}

		if (is_array($this->options["headers"])) 
			foreach ($this->options['headers'] as $key => $entry) {
				$this->setRequestHeader($key, $entry);
			}
		else if (is_string($this->options["headers"])) {
			$headers = explode(CR, $this->options["headers"]);
			foreach ($headers as $h) {
				list($name, $value) = explode(":", $h);
				$this->setRequestHeader(trim($name), trim($value));
			}
		}

		$this->setRequestHeader("Connection", "close");
		$content = $this->_buildHeader() . CR . ($data or "");
		fwrite ($this->sock, $content);

		$result = $content = "";
		while (!feof($this->sock)) {
			$result .= @fgets($this->sock, 128);
		}
		fclose($this->sock);
		$response = $this->_parseResponse($result);

		if ($this->options["follow"] && ($this->status === 301 || $this->status === 302)) {
			$this->open($this->request["method"], $this->getResponseHeader("Location"), $this->options["user"], $this->options['password']);
			return $this->send($data);
		}

		return $response;
	}

	/**
	 * getResponseHeaders() -> Array
	 * 
	 * Returns all the response headers as an associative array, or `null` if no response has been received. 
	 **/
	function getResponseHeaders() {
		return $this->responseHeaders;
	}

	/**
	 * getResponseHeader($name) -> String
	 * - $name (String) - Name of the response header to retrieve
	 * 
	 * Returns the string containing the text of the specified header, or `null` if either the response 
	 * has not yet been received or the header doesn't exist in the response.
	 **/
	function getResponseHeader($name) {
		return $this->responseHeaders[$name];
	}

	/**
	 * doGet($url[, $user, $password[, $timeout[, $headers[, &$response]) -> String
 	 * doGet($url[, $options]) -> String
 	 * - $url (String) - URL to fetch data from
 	 * - $user (String) - the username to authenticate with
 	 * - $password (String) - the password to authenticate with
 	 * - $headers (String|Array) - additional header information to be sent. Array as key-value pairs.
 	 * - $timeout (Number) - connection timeout in seconds. Defaults to `30` seconds.
 	 * - $response (Array) - if provided, this array will receive the complete response data (including headers). The array will contain a `headers` key and a `body` key. The first one containing a key-value pair of all headers the server responded with, the latter one a string containing the response data.
 	 * - $options (Array) - contains an array with key-value pairs defining the above parameters. This is just for convenience.
 	 *
 	 * A static short-cut function for triggering a GET request. The function returns the data fetched from the (other) server.
	 **/
	static function doGet($url, $user=null, $pass=null, $timeout = null, $headers = null, &$response = null) {
	    if (is_array($user)) {
	        $options = $user;
	        $user = $options["user"];
	        $pass = $options["password"];
	        $timeout = $options["timeout"];
	        $headers = $options["headers"];
	        $response = &$options["response"];
	    } else {
	        $options = Array("user" => $user, "password" => $pass, "timeout" => $timeout, "headers" => $headers);
	    }

	    $hc = new HTTPClient($options);
	    $hc->open("GET", $url);
	    $response["body"] = $hc->send();
	    $response["headers"] = $hc->getResponseHeaders();
		$response["status"] = $hc->status;
		$response["statusText"] = $hc->statusText;

	    return $response["body"];
	}

	/**
	 * doPost($url[, $postData[, $user, $pass[, $timeout[, $headers[, &$response]]]]]) -> String
	 * doPost($url[, $postData[, $options]]) -> String
	 * - $url (String) - URL to POST to
	 * - $postData (Array|String) - data to be posted. Array as key-value pairs.
	 * - $user (String) - user name to connect with (for basic HTTP authentication). If `null`, no authorization header will be sent (default).
	 * - $pass (String) - password to connect with (for basic HTTP authentication). If `null`, then no authorization header will be sent (default).
	 * - $timeout (Number) - connection timeout in seconds. Defaults to `30` seconds.
	 * - $headers (Array|String) - additional headers to be sent as part of the request. Array as key-value pairs.
	 * - $response (Array) - if provided, this array will receive the complete response data (including headers). The array will contain a `headers` key and a `body` key. The first one containing a key-value pair of all headers the server responded with, the latter one a string containing the response data.
	 * - $options (Array) - contains an array with key-value pairs defining the above parameters. This is just for convenience.
	 *
	 * A static short-cut function for triggering a POST request. It returns the server's reply body as a `String`.
	 *
	 * *Example:*
	 * 
	 * {{{
	 * // execute a POST request to http://svc.example.org/find with the POST data &query=my+search+term
	 * $result = HTTPClient::doPost("http://svc.example.org/find", Array("query" => "my search term"));
	 * echo $result;
	 * }}}
	 **/
	static function doPost($url, $data, $user=null, $pass=null, $timeout = null, $headers = null, &$response = null) {
		if (is_array($user)) {
			$options = $user;
			$user = $options["user"];
			$pass = $options["password"];
			$timeout = $options["timeout"];
			$headers = $options["headers"];
			$response = &$options["response"];
		}
		if ($timeout == null) $timeout = 30;
		if ($response == null) $response = Array();
		if ($headers == null) $headers = Array();

		if (empty($headers["Content-Type"])) {
			$headers["Content-Type"] = "application/x-www-form-urlencoded";
		}

		$hc = new HTTPClient($options);
		$hc->open("POST", $url);
		$response["body"] = $hc->send($data);
		$response["header"] = $hc->getResponseHeaders();
		$response["status"] = $hc->status;
		$response["statusText"] = $hc->statusText;

		return $response["body"];		
	}

	/**
	 * doPut($url[, $putData[, $user, $pass[, $timeout[, $headers[, &$response]]]]]) -> String
	 * doPut($url[, $putData[, $options]]) -> String
	 * - $url (String) - URL to PUT to
	 * - $putData (Array|String) - data to be put. Array as key-value pairs.
	 * - $user (String) - user name to connect with (for basic HTTP authentication). If `null`, no authorization header will be sent (default).
	 * - $pass (String) - password to connect with (for basic HTTP authentication). If `null`, then no authorization header will be sent (default).
	 * - $timeout (Number) - connection timeout in seconds. Defaults to `30` seconds.
	 * - $headers (Array|String) - additional headers to be sent as part of the request. Array as key-value pairs.
	 * - $response (Array) - if provided, this array will receive the complete response data (including headers). The array will contain a `headers` key and a `body` key. The first one containing a key-value pair of all headers the server responded with, the latter one a string containing the response data.
	 * - $options (Array) - contains an array with key-value pairs defining the above parameters. This is just for convenience.
	 *
	 * A static short-cut function for triggering a PUT request. This method returns the server's reply as a `String`.
	 **/
	static function doPut($url, $data, $user=null, $pass=null, $timeout = null, $headers = null, &$response = null) {
		if (is_array($user)) {
			$options = $user;
			$user = $options["user"];
			$pass = $options["password"];
			$timeout = $options["timeout"];
			$headers = $options["headers"];
			$response = &$options["response"];
		}
		if ($timeout == null) $timeout = 30;
		if ($response == null) $response = Array();
		if ($headers == null) $headers = Array();

		if (empty($headers["Content-Type"])) {
			$headers["Content-Type"] = "application/octet-stream";
		}

		$hc = new HTTPClient($options);
		$hc->open("POST", $url);
		$response["body"] = $hc->send($data);
		$response["header"] = $hc->getResponseHeaders();

		return $response["body"];		
	}

	////////////////////////////////
	// private methods
	////////////////////////////////
	private function _buildHeader() {
		if (empty($this->request["protocol"])) {
			throw new Exception("No request opened. Please use HTTPClient::open() first.");
		}
		$content = $this->request['protocol'] . CR;
		foreach ($this->request["headers"] as $name => $value) {
			$content .= $name . ": " . $value . CR;
		}
		return $content;
	}	

	private function _parseResponse($result) {
		list($header, $content) = split(CR.CR, $result);
		$response = split(CR.CR, $result);
		$header = $response[0];
		$content = $response[1];

		if (!(strpos($header, "Transfer-Encoding: chunked") === false)) {
			$aux = split(CR, $content);
		        for ($i = 0; $i < count($aux); $i++) {
				if ($i % 2 == 0) {
					$aux[$i] = "";
				}
			}
			$content = implode("", $aux);
		}
		
		$content = chop($content);
		$this->responseText = $content;

		$hdata = explode("\n", $header);
		$this->responseHeaders = Array();
		foreach ($hdata as $line => $h) {
			if (strpos($h, ":") === false && $line == 0) {
				list($protocol, $status, $statusText) = explode(" ", $h);
				$this->status = intval($status);
				$this->statusText = $statusText;
			} else {
				$kvs = explode(":", $h);
				$this->responseHeaders[$kvs[0]] = trim(implode(":", array_slice($kvs, 1)));
			}
		}
		return $content;
	}
}
