<?php
/*Vista Panel Vast API Library Version : 2 by Firaz Hosting(Owned By Firaz Server)
Owner of this project: @firazserver-cmd

Content: application/x-www-form-urlencoded 


This API Library is maintained & created by Firaz Hosting(@firazserver-cmd)
Class Name: VistaPanelVastAPI


More Information Of This : vistapanelvastapi.firazhosting.it.eu.org

Documentation: vistapanelvastapi.firazhosting.it.eu.org

Email: firazhosting@gmail.com 
[Firaz Hosting Owned By Firaz Server]
*/
error_reporting(E_ERROR | E_PARSE);
if (!function_exists('str_contains')) {
	function str_contains($haystack, $needle) {
		return '' === $needle || strpos($haystack, $needle) !== false;
	}
}

if (!function_exists('str_ends_with')) {
	function str_ends_with(string $haystack, string $needle) {
		$needle_len = strlen($needle);
		return $needle_len === 0 || 0 === substr_compare($haystack, $needle, - $needle_len);
	}
}

class VistaPanelVastApi {
	private $cpanelUrl = "https://cpanel.byethost.com";
	private $loggedIn = false;
	private $vistapanelSession = "";
	private $vistapanelSessionName = "PHPSESSID";
	private $accountUsername = "";
	private $cookie = "";

	private function getLineWithString($content, $str) {
		$lines = explode("\n", $content);
		foreach ($lines as $line) {
			if (str_contains($line, $str)) {
				return $line;
			}
		}
		return -1;
	}

	private function simpleCurl(
		$url = "",
		$post = false,
		$postfields = [],
		$header = false,
		$httpheader = [],
		$followlocation = false
	) {
		$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
if ($post) {
	curl_setopt($ch, CURLOPT_POST, 1);

	// Send as application/x-www-form-urlencoded instead of multipart/form-data
	if (is_array($postfields)) {
		$postfields = http_build_query($postfields, '', '&', PHP_QUERY_RFC1738);
	}
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);

	// Add the Content-Type header only if the caller hasn't set one
	$hasContentType = false;
	foreach ($httpheader as $h) {
		if (stripos($h, 'Content-Type:') === 0) {
			$hasContentType = true;
			break;
		}
	}
	if (!$hasContentType) {
		$httpheader[] = "Content-Type: application/x-www-form-urlencoded";
	}
}
		if ($header) {
			curl_setopt($ch, CURLOPT_HEADER, true);
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt(
			$ch,
			CURLOPT_USERAGENT,
			"Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13"
		);
		if ($followlocation) {
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		}
		$result = curl_exec($ch);
		$resultUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		curl_close($ch);


		if (str_contains($resultUrl, $this->cpanelUrl . "/panel/indexpl.php?option=error")) {
			
			$dom = new DOMDocument();
			libxml_use_internal_errors(true);
			$dom->loadHTML($result);
			$xpath = new DOMXPath($dom);

		
			$alertMessageNodes = $xpath->query('//div[contains(@class, "alert-message")]');
			if ($alertMessageNodes->length > 0) {
				$errorMessage = trim($alertMessageNodes[0]->textContent);
				throw new Exception($errorMessage);
			}
		}

		return $result;
	}

	private function checkCpanelUrl() {
		if (empty($this->cpanelUrl)) {
			throw new Exception("Please set cpanelUrl first.");
		}
		if (substr($this->cpanelUrl, -1) == "/") {
			$this->cpanelUrl = substr_replace($this->cpanelUrl, "", -1);
		}
		return true;
	}

	private function IsUserLogin() {
		$this->checkCpanelUrl();
		if (!$this->loggedIn) {
			throw new Exception("Not logged in.");
		}
		return true;
	}

	private function checkForEmptyParams($params) {
		foreach ($params as $index => $parameter) {
			if (empty($parameter)) {
				throw new Exception($index . " is required.");
			}
		}
	}

	private function getToken() {
		$this->IsUserLogin();
		$homepage = $this->simpleCurl($this->cpanelUrl . "/panel/indexpl.php", false, [], false, [$this->cookie]);
		$json = $this->getLineWithString($homepage, "/panel\/indexpl.php?option=domains&ttt=");
		$json = substr_replace($json, "", -1);
		$json = json_decode($json, true);
		$url = $json["url"];
		return (int) filter_var($url, FILTER_SANITIZE_NUMBER_INT);
	}

	private function getTableElements($url = "", $id = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("url"));
		$html = $this->simpleCurl($url, false, [], false, [$this->cookie,]);
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);
		libxml_clear_errors();
		if (empty($id)) {
			$header = $dom->getElementsByTagName("th");
			$detail = $dom->getElementsByTagName("td");
		} else {
			$header = $dom->getElementById($id)->getElementsByTagName("th");
			$detail = $dom->getElementById($id)->getElementsByTagName("td");
		}
		foreach ($header as $nodeHeader) {
			$aDataTableHeaderHTML[] = trim($nodeHeader->textContent);
		}
		$i = 0;
		$j = 0;
		foreach ($detail as $sNodeDetail) {
			$aDataTableDetailHTML[$j][] = trim($sNodeDetail->textContent);
			$i = $i + 1;
			$j = $i % count($aDataTableHeaderHTML) == 0 ? $j + 1 : $j;
		}
		for ($i = 0; $i < count($aDataTableDetailHTML); $i++) {
			for ($j = 0; $j < count($aDataTableHeaderHTML); $j++) {
				$aTempData[$i][$aDataTableHeaderHTML[$j]] =
					$aDataTableDetailHTML[$i][$j];
			}
		}
		return $aTempData;
	}

	private function tableToArray($html) {
		$doc = new DOMDocument();
		$doc->loadHTML($html);
		$table = $doc->getElementById("stats");
		$rows = $table->getElementsByTagName("tr");

		$data = [];

		foreach ($rows as $row) {
			$cols = $row->getElementsByTagName("td");
			if ($cols->length === 2) {
				$key = trim($cols->item(0)->nodeValue);
				$value = trim($cols->item(1)->nodeValue);
				$data[$key] = $value;
			}
		}

		return $data;
	}

	public function setCpanelUrl($url = "") {
		$this->checkForEmptyParams(compact("url"));
		$this->cpanelUrl = $url;
		return true;
	}
	public function FirstTimeLogin($username = "", $password = "", $theme = "PaperLantern", $action = "") {
		$this->checkCpanelUrl();
		$this->checkForEmptyParams(compact("username", "password", "action"));

		$action = strtolower(trim($action));

		if ($action !== "approve" && $action !== "disapprove") {
			throw new Exception("Invalid action. Use approve or disapprove.");
		}

		$login = $this->simpleCurl(
					 $this->cpanelUrl . "/login.php",
					 true,
					 [
						 "uname" => $username,
						 "passwd" => $password,
						 "theme" => $theme,
						 "seeesurf" => "567811917014474432",
					 ],
					 true,
					 [],
					 true
				 );

		preg_match_all("/^Set-Cookie:\s*([^;]*)/mi", $login, $matches);

		$cookies = [];

		foreach ($matches[1] as $item) {
			parse_str($item, $cookie);
			$cookies = array_merge($cookies, $cookie);
		}

		if ($this->loggedIn === true) {
			throw new Exception("You are already logged in.");
		}

		if (empty($cookies[$this->vistapanelSessionName])) {
			throw new Exception("Unable to login.");
		}

		if (str_contains($login, "panel/index_pl_sus.php")) {
			throw new Exception("Your account is suspended.");
		}

		if (!str_contains($login, "document.location.href = 'panel/indexpl.php")) {
			throw new Exception("Invalid login credentials.");
		}

		$this->loggedIn = true;
		$this->accountUsername = $username;
		$this->vistapanelSession = $cookies[$this->vistapanelSessionName];

		$this->cookie =
			"Cookie: " .
			$this->vistapanelSessionName .
			"=" .
			$this->vistapanelSession;

		$notice = $this->simpleCurl(
					  $this->cpanelUrl . "/panel/indexpl.php",
					  false,
					  [],
					  false,
					  [$this->cookie]
				  );

		if ($action === "approve") {
			$this->simpleCurl(
				$this->cpanelUrl . "/panel/approve.php",
				true,
				["submit" => true],
				false,
				[$this->cookie]
			);
		}
		elseif($action === "disapprove") {
			$this->simpleCurl(
				$this->cpanelUrl . "/panel/disapprove.php",
				true,
				["submit" => false],
				false,
				[$this->cookie]
			);
		}

		$this->Logout();

		return true;
	}

	public function AccountDetails() {
		$this->IsUserLogin();

		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php",
					false,
					[],
					false,
					[$this->cookie],
					true
				);

		if (empty($html)) {
			throw new Exception("Unable to retrieve dashboard page.");
		}

		libxml_use_internal_errors(true);

		$dom = new DOMDocument();

		if (!@$dom->loadHTML($html)) {
			libxml_clear_errors();
			throw new Exception("Unable to parse dashboard page.");
		}

		libxml_clear_errors();

		$xpath = new DOMXPath($dom);

		$details = [];

	
		$heading = $xpath->query(
					   '//div[contains(@class,"panel-heading") and contains(normalize-space(.),"Account Details")]'
				   );

		if ($heading->length === 0) {
			throw new Exception("Account Details section was not found.");
		}

		$panel = $heading->item(0)->parentNode;

		$tables = $xpath->query(
					  './/table[contains(@class,"table")]',
					  $panel
				  );

		if ($tables->length === 0) {
			throw new Exception("Account Details table was not found.");
		}

	
		$rows = $xpath->query(
					'.//tr',
					$tables->item(0)
				);

		foreach ($rows as $row) {
			$cells = $xpath->query('./td', $row);

			if ($cells->length < 2) {
				continue;
			}

			$key = trim(
					   preg_replace('/\s+/', ' ', $cells->item(0)->textContent)
				   );

			$value = trim(
						 preg_replace('/\s+/', ' ', $cells->item(1)->textContent)
					 );

			if ($key === '' || $value === '') {
				continue;
			}

			$key = rtrim($key, ':');

			$details[$key] = $value;
		}

	
		$homeDirectory = $xpath->query(
							 './/td[contains(normalize-space(.),"Home Directory:")]',
							 $panel
						 );

		if ($homeDirectory->length > 0) {
			$text = $homeDirectory->item(0)->textContent;

			if (preg_match(
						'/Home Directory:\s*(\/[^\s]+)/',
						preg_replace('/\s+/', ' ', $text),
						$match
					)) {
				$details["Home Directory"] = $match[1];
			}
		}

		if (empty($details)) {
			throw new Exception("Unable to extract account details.");
		}

		return $details;
	}

	public function ListDomainForParkedDomain() {
		$this->IsUserLogin();

		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=parked",
					false,
					[],
					false,
					[$this->cookie],
					true
				);

		if (empty($html)) {
			throw new Exception("Unable to retrieve parked domains page.");
		}

		libxml_use_internal_errors(true);

		$dom = new DOMDocument();

		if (!@$dom->loadHTML($html)) {
			libxml_clear_errors();
			throw new Exception("Unable to parse parked domains page.");
		}

		libxml_clear_errors();

		$xpath = new DOMXPath($dom);

		$select = $xpath->query('//select[@name="domain_selector"]');

		if ($select->length === 0) {
			throw new Exception(
				"domain_selector was not found on the VistaPanel parked domains page."
			);
		}

		$domains = [];

		$options = $select->item(0)->getElementsByTagName("option");

		foreach ($options as $option) {
			$domain = trim(
						  preg_replace('/\s+/', ' ', $option->textContent)
					  );

			if ($domain === "") {
				continue;
			}

			$domains[] = $domain;
		}

		return $domains;
	}
	public function ListDomainForSubdomain() {
		$this->IsUserLogin();

		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=subdomains&ttt=" . $this->getToken(),
					false,
					[],
					false,
					[$this->cookie],
					true
				);

		if (empty($html)) {
			throw new Exception("Unable to retrieve subdomains page.");
		}

		libxml_use_internal_errors(true);

		$dom = new DOMDocument();

		if (!@$dom->loadHTML($html)) {
			libxml_clear_errors();
			throw new Exception("Unable to parse subdomains page.");
		}

		libxml_clear_errors();

		$xpath = new DOMXPath($dom);

		$select = $xpath->query('//select[@name="domain_selector"]');

		if ($select->length === 0) {
			throw new Exception(
				"domain_selector was not found on the VistaPanel subdomains page."
			);
		}

		$domains = [];

		$options = $select->item(0)->getElementsByTagName("option");

		foreach ($options as $option) {
			$domain = trim(
						  preg_replace('/\s+/', ' ', $option->textContent)
					  );

			if ($domain === "") {
				continue;
			}

			$domains[] = $domain;
		}

		return $domains;
	}

	public function ApproveNotification() {
		$this->IsUserLogin();

		$result = $this->simpleCurl(
					  $this->cpanelUrl . "/panel/approve.php",
					  true,
					  [],
					  true,
					  [$this->cookie],
					  true
				  );

		if (str_contains($result, "Please click 'I Approve' below")) {
			throw new Exception("Notification approval was not accepted.");
		}

		return true;
	}

	public function DisapproveNotification() {
		$this->IsUserLogin();

		$result = $this->simpleCurl(
					  $this->cpanelUrl . "/panel/disapprove.php",
					  true,
					  [],
					  true,
					  [$this->cookie],
					  true
				  );

		if (str_contains($result, "Please click 'I Approve' below")) {
			throw new Exception("Notification disapproval was not accepted.");
		}

		return true;
	}

	public function login($username = "", $password = "", $theme = "PaperLantern") {
		$this->checkCpanelUrl();
		$this->checkForEmptyParams(compact("username", "password"));
		$login = $this->simpleCurl(
					 $this->cpanelUrl . "/login.php",
					 true,
					 [
						 "uname" => $username,
						 "passwd" => $password,
						 "theme" => $theme,
						 "seeesurf" => "567811917014474432",
					 ],
					 true,
					 [],
					 true
				 );
		preg_match_all("/^Set-Cookie:\s*([^;]*)/mi", $login, $matches);
		$cookies = [];
		foreach ($matches[1] as $item) {
			parse_str($item, $cookie);
			$cookies = array_merge($cookies, $cookie);
		}
		if ($this->loggedIn === true) {
			throw new Exception("You are already logged in.");
		}
		if (empty($cookies[$this->vistapanelSessionName])) {
			throw new Exception("Unable to login.");
		}
		if (str_contains($login, "panel/index_pl_sus.php")) {
			throw new Exception("Your account is suspended.");
		}
		if (!str_contains($login, "document.location.href = 'panel/indexpl.php")) {
			throw new Exception("Invalid login credentials.");
		}
		$this->loggedIn = true;
		$this->accountUsername = $username;
		$this->vistapanelSession = $cookies[$this->vistapanelSessionName];
		$this->cookie = "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession;
		$notice = $this->simpleCurl($this->cpanelUrl . "/panel/indexpl.php", false, [], false, [$this->cookie]);
		if (str_contains($notice, "Please click 'I Approve' below to allow us.")) {
			throw new Exception("Please approve or disapprove notifications first.");
		}
		return true;
	}

	public function setSession($session = "", $username = "") {
		$this->checkForEmptyParams(compact("session", "username"));

		$this->vistapanelSession = $session;
		$this->accountUsername = $username;
		$this->cookie = "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession;

		if (!$this->loggedIn) {
			$this->loggedIn = true;
		}

		return true;
	}

	public function createDatabase($dbname = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("dbname"));
		$this->simpleCurl(
			$this->cpanelUrl . "/panel/indexpl.php?option=mysql&cmd=create",
			true,
			["db" => $dbname],
			false,
			[$this->cookie]
		);
		return true;
	}

	public function ListDatabases() {
		$databases = [];
		$aDataTableDetailHTML = $this->getTableElements($this->cpanelUrl . "/panel/indexpl.php?option=pma");
		foreach ($aDataTableDetailHTML as $database) {
			$databases[] = str_replace($this->accountUsername . "_", "", array_shift($database));
		}
		return $databases;
	}

	public function deleteDatabase($database = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("database"));
		if (!in_array($database, $this->ListDatabases())) {
			throw new Exception("The database " . $database . " doesn't exist.");
		}
		$this->simpleCurl(
			$this->cpanelUrl . "/panel/indexpl.php?option=mysql&cmd=remove",
			true,
			[
				"toremove" => $this->accountUsername . "_" . $database,
				"Submit2" => "Remove Database",
			],
			false,
			[$this->cookie]
		);
		return true;
	}

	public function getPhpmyadminLink($database = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("database"));

		if (!in_array($database, $this->ListDatabases(), true)) {
			throw new Exception("The database " . $database . " doesn't exist.");
		}

		$fullDatabase = $this->accountUsername . "_" . $database;

		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=pma",
					false,
					[],
					false,
					[$this->cookie]
				);

		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);
		libxml_clear_errors();

		$links = $dom->getElementsByTagName("a");

		foreach ($links as $link) {
			$href = $link->getAttribute("href");

			if (str_contains($href, "&db=" . $fullDatabase)) {
				return $href;
			}
		}

		throw new Exception("phpMyAdmin link for database " . $fullDatabase . " was not found.");
	}

	public function listDomains($option = "all") {
		$this->IsUserLogin();
		switch ($option) {
		case "sub":
			$option = "subdomains";
			$id = "subdomaintbl";
			break;
		case "parked":
			$option = "parked";
			$id = "parkeddomaintbl";
			break;
		case "addon":
			$option = "domains";
			$id = "subdomaintbl";
			break;
		default:
			$option = "ssl";
			$id = "sql_db_tbl";
			break;
		}
		$domains = [];
		$aDataTableDetailHTML = $this->getTableElements(
									$this->cpanelUrl . "/panel/indexpl.php?option={$option}&ttt=" . $this->getToken(), $id
								);
		foreach ($aDataTableDetailHTML as $domain) {
			$domains[] = array_shift($domain);
		}
		return $domains;
	}

	public function createRedirect($domainname = "", $target = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname", "target"));
		$this->simpleCurl(
			$this->cpanelUrl . "/panel/indexpl.php?option=redirect_add",
			true,
			[
				"domain_name" => $domainname,
				"redirect_url" => $target,
			],
			false,
			[$this->cookie],
			true
		);
		return true;
	}

	public function deleteRedirect($domainname = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname"));
		$this->simpleCurl(
			$this->cpanelUrl . "/panel/indexpl.php?option=redirect_rem&domain=" . $domainname . "&redirect_url=http://",
			true,
			[],
			false,
			[$this->cookie]
		);
		return true;
	}

	public function showRedirect($domainname = "") {
		/* Returns the URL that has been set for an redirect. */
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname"));

		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=redirect_configure",
					true,
					["domain_name" => $domainname],
					false,
					[$this->cookie]
				);


		$xpath = '//*[@id="content"]/div/div[1]/table/tbody/tr[2]/td[1]/b[2]';
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);

		$domxpath = new DOMXPath($dom);

		$values = $domxpath->query($xpath);
		return $values->item(0)->nodeValue;
	}

	public function getPrivateKey($domainname = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname"));
		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=sslconfigure&domain_name=" . $domainname,
					false,
					[],
					false,
					[$this->cookie]
				);
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);

		$xpath = new DOMXPath($dom);

		$privatekeys = $xpath->query("//textarea[@name='key']");
		return $privatekeys->item(0)->nodeValue;
	}

	public function getCertificate($domainname = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname"));
		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=sslconfigure&domain_name=" . $domainname,
					false,
					[],
					false,
					[$this->cookie]
				);
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);

		$xpath = new DOMXPath($dom);

		$certificates = $xpath->query("//textarea[@name='cert']");
		return $certificates->item(0)->nodeValue;
	}

	public function uploadPrivateKey($domainname = "", $key = "", $csr = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname", "key"));

		$postData = [
						"domain_name" => $domainname,
						"key" => $key
					];

		if (trim($csr) !== "") {
			$postData["csr"] = $csr;
		}

		$this->simpleCurl(
			$this->cpanelUrl . "/panel/modules-new/sslconfigure/uploadkey.php",
			true,
			$postData,
			false,
			[$this->cookie]
		);

		return true;
	}
	public function uploadCertificate($domainname = "", $cert = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname", "cert"));
		$this->simpleCurl(
			$this->cpanelUrl . "/panel/modules-new/sslconfigure/uploadcert.php",
			true,
			[
				"domain_name" => $domainname,
				"cert" => $cert,
			],
			false,
			[$this->cookie]
		);
		return true;
	}

	public function deleteCertificate($domainname = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname"));
		$this->simpleCurl(
			$this->cpanelUrl .
			"/panel/modules-new/sslconfigure/deletecert.php" .
			"?domain_name=" .
			$domainname .
			"&username=" .
			$this->accountUsername,
			false,
			[],
			false,
			[$this->cookie]
		);
		return true;
	}

	public function FetchSoftaculousLink() {
		$this->IsUserLogin();
		$getlink = $this->simpleCurl(
					   $this->cpanelUrl . "/panel/indexpl.php?option=installer&ttt=" . $this->getToken(),
					   false,
					   [],
					   true,
					   [$this->cookie],
					   false
				   );
		if (preg_match("~Location: (.*)~i", $getlink, $match)) {
			$location = trim($match[1]);
		}
		return $location;
	}

	public function showErrorPage($domainname = "", $option = "400") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname"));
		$xpath = '//input[@name="' . $option . '"]';
		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=errorpages_configure",
					true,
					["domain_name" => $domainname],
					false,
					[$this->cookie]
				);
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);

		$domxpath = new DOMXPath($dom);

		$values = $domxpath->query($xpath);
		return $values->item(0)->getAttribute("value");
	}



	public function UpdateErrorPages($domainname = "", $v400 = "", $v401 = "", $v403 = "", $v404 = "", $v503 = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname"));

		$url = $this->cpanelUrl . "/panel/indexpl.php?option=errorpages_change";

		$postData = [
						"domain_name" => $domainname,
						"400" => $v400,
						"401" => $v401,
						"403" => $v403,
						"404" => $v404,
						"503" => $v503
					];

		return $this->simpleCurl(
				   $url,
				   true,
				   $postData,
				   false,
				   [$this->cookie],
				   true
			   );
	}
	public function ShowPHPConfig($domainname = "", $option = "display_errors") {
		/* Returns the URL that has been set for an error page.
		 * Available options: "display_errors", "mbstring_http_input", "date_timezone".
		 * Returns displayerror if no option is given.
		 *
		 * Returning Values:
		 * display_errors: true - Enabled / false - Disabled (Boolean)
		 * mbstring_http_input: Value (String)
		 * date_timezone: Timezone (String)
		 */
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname"));
		if ($option !== "date_timezone") {
			$xpath = '//input[@name="' . $option . '"]';
		} else {
			$xpath = "//select[@name='date_timezone']/option[@selected]";
		}
		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=phpchangeconfig_configure",
					true,
					["domain_name" => $domainname],
					false,
					[$this->cookie]
				);
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);
		$domxpath = new DOMXPath($dom);
		$values = $domxpath->query($xpath);

		switch ($option) {
		case "mbstring_http_input":
			return $values->item(0)->getAttribute("value");
		case "date_timezone":
			return $values->item(0)->nodeValue;
		default:
			$checked = $domxpath->query("//input[@name='display_errors' and @checked]");
			if ($checked->length > 0) {
				return $checked->item(0)->getAttribute("value");
			}
			return null;
		}
	}

	public function SetPHPConfig($domainname = "", $displayerrors = "", $mbstringinput = "", $timezone = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname"));

		$displayerrors = strtolower((string)$displayerrors);

		if ($displayerrors === "1" || $displayerrors === "on") {
			$displayerrors = "On";
		} else {
			$displayerrors = "Off";
		}

		$response = $this->simpleCurl(
						$this->cpanelUrl . "/panel/indexpl.php?option=phpchangeconfig_change",
						true,
						[
							"domain_name" => $domainname,
							"display_errors" => $displayerrors,
							"mbstring_http_input" => $mbstringinput,
							"date_timezone" => $timezone,
							"B1" => "Alter PHP directives",
						],
						false,
						[$this->cookie]
					);

		return $response;
	}
	public function GetAccountStatistics($option = "") {
	
		if (!empty($option) && !str_ends_with($option, ":")) {
			$option = $option . ":";
		}

		$stats = $this->tableToArray(
					 $this->simpleCurl($this->cpanelUrl . "/panel/indexpl.php", true, null, false, [$this->cookie])
				 );

		$stats["MySQL Databases:"] = substr($stats["MySQL Databases:"], 0, -1);
		$stats["Parked Domains:"] = substr($stats["Parked Domains:"], 0, -1);
		$stats["Bandwidth used:"] = preg_replace('/MB\\n.{1,50}/i', 'MB', $stats["Bandwidth used:"]);

		$stats = preg_replace('/\\\n.{1,20}",/i', '",', json_encode($stats));
		$stats = json_decode($stats, true);

		if (empty($option)) {
			return $stats;
		} else {
			return $stats[$option];
		}
	}

	public function FetchCNAMERecords() {
		$this->IsUserLogin();
		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=cnamerecords&ttt=" . $this->getToken(),
					false,
					null,
					false,
					[$this->cookie]
				);

		$dom = new DOMDocument();
		$dom->loadHTML($html);

		$rows = $dom->getElementsByTagName('tr');

		$array = [];
		for ($i = 2; $i < $rows->length; $i++) {
			$row = $rows->item($i);
			$cols = $row->getElementsByTagName('td');

			$cname = $cols->item(0)->nodeValue;
			if (!isset($cname)) {
				continue;
			}
			$destination = $cols->item(1)->nodeValue;

			$array[] = [
						   'Record' => $cname,
						   'Destination' => $destination,
					   ];
		}

		return $array;
	}

	public function createCNAMErecord($source, $domain, $dest) {

		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("source", "domain", "dest"));
		$this->simpleCurl(
			$this->cpanelUrl . "/panel/modules-new/cnamerecords/add.php",
			true,
			[
				"source" => $source,
				"d_name" => $domain,
				"destination" => $dest,
			],
			false,
			[$this->cookie],
			true
		);
		return true;

	}

	private function FetchCNAMEDeletionLink($source) {
		$this->IsUserLogin();
		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=cnamerecords&ttt=" . $this->getToken(),
					false,
					[],
					false,
					[$this->cookie]
				);
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);
		libxml_clear_errors();

		$dom = new DOMDocument();
		$dom->loadHTML($html);
		$anchorTags = $dom->getElementsByTagName('a');

		foreach ($anchorTags as $anchorTag) {
			if (str_contains($anchorTag->getAttribute('href'), '?site=' . $source)) {
				return $anchorTag->getAttribute('href');
			}
		}
	}

	public function deleteCNAMErecord($source) {
		/* $source: The record source */
		$this->IsUserLogin();
		$link = $this->FetchCNAMEDeletionLink($source);
		$this->simpleCurl(
			$this->cpanelUrl . '/panel/' . $link,
			false,
			[],
			false,
			[$this->cookie]
		);

		return true;
	}

	public function FetchMXRecords() {
		
		$this->IsUserLogin();
		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=mxrecords&ttt=" . $this->getToken(),
					false,
					null,
					false,
					[$this->cookie]
				);

		$dom = new DOMDocument();
		$dom->loadHTML($html);

		$rows = $dom->getElementsByTagName('tr');

		$array = [];
		for ($i = 2; $i < $rows->length; $i++) {
			$row = $rows->item($i);
			$cols = $row->getElementsByTagName('td');

			$domain = $cols->item(0)->nodeValue;
			if (!isset($domain)) {
				continue;
			}
			$mx = $cols->item(1)->nodeValue;
			$priority = $cols->item(2)->nodeValue;
			$array[] = [
						   'Domain' => $domain,
						   'MX' => $mx,
						   'Priority' => $priority,
					   ];
		}

		return $array;
	}

	public function createMXrecord($domain, $server, $priority) {
	

		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domain", "server"));
		if (in_array(["Domain" => $domain, "MX" => $server . ".", "Priority" => $priority], $this->FetchMXRecords())) {
			throw new Exception("Duplicate MX Record detected, please delete the old one first.");
		}
		$this->simpleCurl(
			$this->cpanelUrl . "/panel/modules-new/mxrecords/add.php",
			true,
			[
				"d_name" => $domain,
				"Data" => $server,
				"Preference" => $priority,
			],
			false,
			[$this->cookie],
			true
		);
		return true;

	}

	private function FetchMXDeletionLink($domain, $srv, $priority) {
		$this->IsUserLogin();
		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=mxrecords&ttt=" . $this->getToken(),
					false,
					[],
					false,
					[$this->cookie]
				);
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);
		libxml_clear_errors();

		$dom = new DOMDocument();
		$dom->loadHTML($html);
		$anchorTags = $dom->getElementsByTagName('a');

		foreach ($anchorTags as $anchorTag) {
			if (
				str_contains($anchorTag->getAttribute('href'), '?site=' . $domain)
				&&
				str_contains($anchorTag->getAttribute('href'), '&data=' . $srv)
				&&
				str_contains($anchorTag->getAttribute('href'), '&aux=' . $priority)
			) {
				return $anchorTag->getAttribute('href');
			}

		}
	}

	public function DeleteMXRecord($domain, $srv, $priority) {
		
		$this->IsUserLogin();
		$link = $this->FetchMXDeletionLink($domain, $srv, $priority);
		$this->simpleCurl($this->cpanelUrl . '/panel/' . $link, false, [], false, [$this->cookie]);
		return true;
	}

	public function FetchSPFRecords() {
		$this->IsUserLogin();
		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=spfrecords&ttt=" . $this->getToken(),
					false,
					null,
					false,
					[$this->cookie]
				);

		$dom = new DOMDocument();
		$dom->loadHTML($html);

		$rows = $dom->getElementsByTagName('tr');

		$array = [];
		for ($i = 2; $i < $rows->length; $i++) {
			$row = $rows->item($i);
			$cols = $row->getElementsByTagName('td');

			$domain = $cols->item(0)->nodeValue;
			if (!isset($domain)) {
				continue;
			}
			$data = $cols->item(1)->nodeValue;
			$array[] = [
						   'Domain' => $domain,
						   'Data' => $data,
					   ];
		}

		return $array;
	}

	public function CreateSPFRecord($domain, $data) {

		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domain", "data"));
		if (in_array(["Domain" => $domain, "Data" => $data], $this->FetchSPFRecords(), true)) {
			throw new Exception("Duplicate SPF Record detected, please delete the old one first.");
		}
		$this->simpleCurl(
			$this->cpanelUrl . "/panel/modules-new/spfrecords/add.php",
			true,
			[
				"d_name" => $domain,
				"Data" => $data,
			],
			false,
			[$this->cookie],
			true
		);
		return true;
	}

	private function FetchSPFDeletionLink($domain, $data) {
		$this->IsUserLogin();
		$html = $this->simpleCurl(
					$this->cpanelUrl . "/panel/indexpl.php?option=spfrecords&ttt=" . $this->getToken(),
					false,
					[],
					false,
					[$this->cookie]
				);
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);
		libxml_clear_errors();

		$dom = new DOMDocument();
		$dom->loadHTML($html);
		$anchorTags = $dom->getElementsByTagName('a');

		foreach ($anchorTags as $anchorTag) {
			if (
				str_contains($anchorTag->getAttribute('href'), '?site=' . $domain)
				&&
				str_contains($anchorTag->getAttribute('href'), '&data=' . $data)
			) {
				return $anchorTag->getAttribute('href');
			}
		}
	}

	public function DeleteSPFRecord($domain, $data) {
		$this->IsUserLogin();
		$link = $this->FetchSPFDeletionLink($domain, $data);
		$this->simpleCurl($this->cpanelUrl . '/panel/' . $link, false, [], false, [$this->cookie]);
		return true;
	}

	public function ChangeEmail($newEmail, $confirmEmail) {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("newEmail", "confirmEmail"));

		if ($newEmail !== $confirmEmail) {
			throw new Exception("New email and confirmation email do not match.");
		}

		$token = $this->getToken();

		$url = $this->cpanelUrl . "/panel/indexpl.php?option=changeemail&ttt=" . urlencode($token);

		$postData = http_build_query([
										 "ttt" => $token,
										 "newemail" => $newEmail,
										 "confemail" => $confirmEmail,
										 "B1" => " Change   "
									 ]);

		$response = $this->simpleCurl(
						$url,
						true,
						$postData,
						false,
						[
							$this->cookie,
							"Content-Type: application/x-www-form-urlencoded"
						],
						true
					);

		return $response;
	}



	public function AddPasswordProtectionToFolder($domainName, $folderName, $username, $password) {
		$this->IsUserLogin();

		$postData = [
						"htaccess_username" => $username,
						"htaccess_password" => $password,
						"install_path" => $folderName,
						"domain_name" => $domainName,
						"Submit" => "Protect it!",
					];

		$response = $this->simpleCurl(
						$this->cpanelUrl . "/panel/indexpl.php?option=protectedfolders_add_user",
						true,
						$postData,
						false,
						[$this->cookie],
						true
					);

		if ($response === false || $response === null || $response === "") {
			return [
					   "success" => false,
					   "error" => "No response was received from VistaPanel."
				   ];
		}

		if (stripos($response, "error") !== false ||
											 stripos($response, "failed") !== false ||
													 stripos($response, "invalid") !== false) {
			return [
					   "success" => false,
					   "error" => $response
				   ];
		}

		return [
				   "success" => true,
				   "response" => $response
			   ];
	}


	public function createDNSRecord($recordType, $domain, $data = "", $destination = "", $priority = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("recordType", "domain"));

		switch (strtoupper($recordType)) {

		case "MX":
			$this->checkForEmptyParams(compact("data", "priority"));

			$endpoint = $this->cpanelUrl . "/panel/modules-new/mxrecords/add.php";

			$postData = http_build_query([
											 "d_name" => $domain,
											 "Data" => $data,
											 "Preference" => $priority
										 ]);
			break;

		case "SPF":
			$this->checkForEmptyParams(compact("data"));

			$endpoint = $this->cpanelUrl . "/panel/modules-new/spfrecords/add.php";

			$postData = http_build_query([
											 "d_name" => $domain,
											 "Data" => $data
										 ]);
			break;

		case "CNAME":
			$this->checkForEmptyParams(compact("data", "destination"));

			$endpoint = $this->cpanelUrl . "/panel/modules-new/cnamerecords/add.php";

			$postData = http_build_query([
											 "source" => $data,
											 "d_name" => $domain,
											 "destination" => $destination
										 ]);
			break;

		default:
			throw new Exception(
				"Unsupported record type. Use MX, SPF or CNAME."
			);
		}

		return $this->simpleCurl(
				   $endpoint,
				   true,
				   $postData,
				   false,
				   [
					   $this->cookie,
					   "Content-Type: application/x-www-form-urlencoded"
				   ],
				   true
			   );
	}
	public function AddDomain($domainname = "", $option = "", $domain_selected = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domainname"));

		$token = $this->getToken();

		switch ($option) {
		case "parked":
			$this->checkForEmptyParams(compact("domain_selected"));

			$endpoint = $this->cpanelUrl . "/panel/indexpl.php?option=parked_add";

			$postData = [
							"DomainName" => $domainname,
							"domain_selector" => $domain_selected,
							"B1" => "Add Parked Domain"
						];
			break;

		case "subdomain":
			$endpoint = $this->cpanelUrl . "/panel/indexpl.php?option=subdomains_add";

			$postData = [
							"DomainName" => trim($domainname),
							"domain_selector" => trim($domain_selected),
							"B1" => "Create"
						];
			break;
		case "addon":
			$endpoint = $this->cpanelUrl . "/panel/indexpl.php?option=domains_add";

			$postData = [
							"DomainName" => $domainname,
							"B1" => "Add Domain"
						];
			break;

		default:
			throw new Exception(
				"Invalid domain option. Use parked, subdomains or domains."
			);
		}

		$result = $this->simpleCurl(
					  $endpoint,
					  true,
					  $postData,
					  true,
					  [$this->cookie],
					  true
				  );

		$dom = new DOMDocument();
		libxml_use_internal_errors(true);

		if (@$dom->loadHTML($result)) {
			$xpath = new DOMXPath($dom);

			$errorNodes = $xpath->query(
							  '//*[contains(@class,"alert") or contains(@class,"error") or contains(@class,"alert-message")]'
						  );

			if ($errorNodes->length > 0) {
				$errors = [];

				foreach ($errorNodes as $node) {
					$text = trim(preg_replace('/\s+/', ' ', $node->textContent));

					if ($text !== "" && !in_array($text, $errors, true)) {
						$errors[] = $text;
					}
				}

				if (!empty($errors)) {
					throw new Exception(implode("\n", $errors));
				}
			}
		}

		return [
				   "success" => true,
				   "option" => $option,
				   "domain" => $domainname,
				   "domain_selected" => $domain_selected,
				   "response" => $result
			   ];
	}
	public function DeleteDomain($domain = "", $type = "") {
		$this->IsUserLogin();
		$this->checkForEmptyParams(compact("domain", "type"));

		switch ($type) {
		case "subdomain":
			$option = "subdomains_rem";
			break;

		case "parked":
			$option = "parked_rem";
			break;

		case "addon":
			$option = "domains_rem";
			break;

		default:
			throw new Exception(
				"Invalid domain type. Use subdomains, parked or domains."
			);
		}

		$url = $this->cpanelUrl .
			   "/panel/indexpl.php?option=" .
			   $option .
			   "&site=" .
			   urlencode($domain);

		$result = $this->simpleCurl(
					  $url,
					  false,
					  [],
					  true,
					  [$this->cookie],
					  true
				  );

		return true;
	}
public function FileManager(): string
{
    try {
        $this->IsUserLogin();

        $html = $this->simpleCurl(
            $this->cpanelUrl . "/panel/indexpl.php",
            false,
            [],
            false,
            [$this->cookie],
            true
        );

        if (empty($html)) {
            throw new Exception(
                "Unable to retrieve the cPanel dashboard."
            );
        }

        if (!preg_match(
            '~"url"\s*:\s*"(https?://filemanager\.ai/new3/index\.php\?[^"]+)"~i',
            $html,
            $matches
        )) {
            throw new Exception(
                "File Manager URL was not found."
            );
        }

        $url = str_replace('\\/', '/', $matches[1]);

        $url = html_entity_decode(
            $url,
            ENT_QUOTES | ENT_HTML5,
            "UTF-8"
        );

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception(
                "The File Manager URL is invalid."
            );
        }

        return $url;

    } catch (Throwable $e) {
        throw new Exception($e->getMessage(), 0, $e);
    }
}
	public function Logout() {
		$this->IsUserLogin();
		$this->simpleCurl($this->cpanelUrl . "/panel/indexpl.php?option=signout", false, [], false, [$this->cookie]);
		$this->loggedIn = false;
		$this->vistapanelSession = "";
		$this->accountUsername = "";
		$this->cookie = "";
		return true;
	}
}
