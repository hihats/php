<?php
/**
 * Google Analytics class
 * @author Hisahiro Tsukamoto
 */

include_once '/GoogleAnalytics.conf.php';

class GoogleAnalytics {
	private $_email;
	private $_passwd;
	private $_authCode;
	private $_profileId;
	public $_apikey;
	
	private $_endDate;
	private $_startDate;

	public $profiles;
	public $dimensions;
	public $metrics;
	public $sort;
	public $filters;
	public $params;
	public $start_date;
	public $end_date;
	public $dateHourStr;

	public function __construct($email, $password, $country = 'JP')
	{
		if (!function_exists('curl_init')) throw new Exception('The curl extension for PHP is required.');
		global $GOOGLE_ANALYTICS_PROFILES;
		global $GOOGLE_ANALYTICS_API_KEY;
		// set the email and password
		$this->_email = $email;
		$this->_passwd = $passwd;
		
		if (isset($this->_email) && isset($this->_passwd)) {
			// autenticate the user
			if (!$this->_authenticate()) {
				//print $this->_authCode;
				throw new Exception('Failed to authenticate, please check your email and password.');
			}
		}
		//default the start and end date
		$this->_endDate = date('Y-m-d', strtotime('-1 day'));
		$this->_startDate = date('Y-m-d', strtotime('-1 month'));
		$this->profiles = $GOOGLE_ANALYTICS_PROFILES[$country];
		$this->setApiKey($GOOGLE_ANALYTICS_API_KEY[$country]);
		$this->setLatestDate();
		$this->deimension = null;
		$this->metrics = null;
		$this->sort = null;
		$this->filters = null;
		$this->params = array();
		$this->dateHourStr = null;
	}

	/**
    * Sets Profile ID
	*
    * @param string $id (format: 'ga:1234')
    */
	public function setProfile($id) {
		//look for a match for the pattern ga:XXXXXXXX, of up to 10 digits 
		if (!preg_match('/^ga:\d{1,10}/',$id)) {
			throw new Exception('Invalid GA Profile ID set. The format should ga:XXXXXX, where XXXXXX is your profile number');
		}
		$this->_profileId = $id; 
		return TRUE;
	}
	
	/**
    * Sets the date range
    * 
    * @param string $startDate (YYYY-MM-DD)
    * @param string $endDate   (YYYY-MM-DD)
    */
	public function setDateRange($startDate, $endDate) {
		//validate the dates
		if (!preg_match('/\d{4}-\d{2}-\d{2}/', $startDate)) {
			throw new Exception('Format for start date is wrong, expecting YYYY-MM-DD format');
		}
		if (!preg_match('/\d{4}-\d{2}-\d{2}/', $endDate)) {
			throw new Exception('Format for end date is wrong, expecting YYYY-MM-DD format');
		}
		if (strtotime($startDate)>strtotime($endDate)) {
			throw new Exception('Invalid Date Range. Start Date is greated than End Date');
		}
		$this->_startDate = $startDate;
		$this->_endDate = $endDate;
		return TRUE;
	}
	
	/**
    * Retrieve the report according to the properties set in $properties
	*
    * @param array $properties
	* @return array
    */
	public function getReport($properties = array()) {
		if (!count($properties)) {
			die ('getReport requires valid parameter to be passed');
		return FALSE;
		}
		
		//arrange the properties in key-value pairing
		foreach($properties as $key => $value){
            $params[] = $key.'='.$value;
        }
		//compose the apiURL string
        $apiUrl = 'https://www.google.com/analytics/feeds/data?key='.$this->_apikey.'&ids='.$this->_profileId.'&start-date='.$this->_startDate.'&end-date='.$this->_endDate.'&'.implode('&', $params);
		
		//call the API
		$xml = $this->_callAPI($apiUrl);
		
		//get the results
		if ($xml) {
			if(!isset($dims)) $dims = null;
			$dom = new DOMDocument();
			$dom->loadXML($xml);
			$entries = $dom->getElementsByTagName('entry');
		
			foreach ($entries as $entry){
				$dimensions = $entry->getElementsByTagName('dimension');
				foreach ($dimensions as $dimension) {
					$dims .= $dimension->getAttribute('value').'~~';
				}

				$metrics = $entry->getElementsByTagName('metric');
				foreach ($metrics as $metric) {
					$name = $metric->getAttribute('name');
					$mets[$name] = $metric->getAttribute('value');
				}
				
				$dims = trim($dims,'~~');
				$results[$dims] = $mets;
				
				$dims='';
				$mets='';
			}
		} else {
			throw new Exception('getReport() failed to get a valid XML from Google Analytics API service###'.$apiUrl, GAERRORCODE);
		}
		if (isset($results)) {
			return $results;
		} else {
			return false;
		}
	}
	
	/**
    * Retrieve the list of Website Profiles according to your GA account
	*
    * @param none
	* @return array
    */
	public function getWebsiteProfiles() {
	
		// make the call to the API
		$response = $this->_callAPI('https://www.google.com/analytics/feeds/accounts/default');
		
		//parse the response from the API using DOMDocument.
		if ($response) {
			$dom = new DOMDocument();
			$dom->loadXML($response);
			$entries = $dom->getElementsByTagName('entry');
			foreach($entries as $entry){
				$tmp['title'] = $entry->getElementsByTagName('title')->item(0)->nodeValue;
				$tmp['id'] = $entry->getElementsByTagName('id')->item(0)->nodeValue;
				foreach($entry->getElementsByTagName('property') as $property){
					if (strcmp($property->getAttribute('name'), 'ga:accountId') == 0){
						$tmp["accountId"] = $property->getAttribute('value');
					}    
					if (strcmp($property->getAttribute('name'), 'ga:accountName') == 0){
					   $tmp["accountName"] = $property->getAttribute('value');
					}
					if (strcmp($property->getAttribute('name'), 'ga:profileId') == 0){
						$tmp["profileId"] = $property->getAttribute('value');
					}
					if (strcmp($property->getAttribute('name'), 'ga:webPropertyId') == 0){
						$tmp["webProfileId"] = $property->getAttribute('value');
					}
				}
				$profiles[] = $tmp;
			}
		} else {
			throw new Exception('getWebsiteProfiles() failed to get a valid XML from Google Analytics API service', GAERRORCODE);
		}
		return $profiles;
	}
	
	/**
    * Make the API call to the $url with the $_authCode specified
	*
    * @param url
	* @return result from _postTo
    */
	private function _callAPI($url) {
		return $this->_postTo($url,array(),array("Authorization: GoogleLogin auth=".$this->_authCode));
	}
		
	/**
    * Authenticate the email and password with Google, and set the $_authCode return by Google
	*
    * @param none
	* @return none
    */
	private function _authenticate() {	
		$postdata = array(
            'accountType' => 'GOOGLE',
            'Email' => $this->_email,
            'Passwd' => $this->_passwd,
            'service' => 'analytics',
            'source' => 'askaboutphp-v01'
        );
		
		$response = $this->_postTo("https://www.google.com/accounts/ClientLogin", $postdata);
		//process the response;
		if ($response) {
			preg_match('/Auth=(.*)/', $response, $matches);
			if(isset($matches[1])) {
				$this->_authCode = $matches[1];
				return TRUE;
			}
		}
		return FALSE;
	}
		
	/**
    * Performs the curl calls to the $url specified. 
	*
    * @param string $url
	* @param array $data - specify the data to be 'POST'ed to $url
	* @param array $header - specify any header information
	* @return $response from submission to $url
    */
	private function _postTo($url, $data=array(), $header=array()) {
		
		//check that the url is provided
		if (!isset($url)) {
			return FALSE;
		}
		
		//send the data by curl
		$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		if (count($data)>0) {
			//POST METHOD
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		} else {
			$header[] = "application/x-www-form-urlencoded";
//			$header[] = array("application/x-www-form-urlencoded");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		}
		
		$response = curl_exec($ch);
        $info = curl_getinfo($ch);
		
        curl_close($ch);

        switch($info['http_code']){
        	case 200:
        		return $response;
        		break;
        	default:
        		throw new APIException($response, GAERRORCODE, null, $info);
        		break;
        }
		return FALSE;		
	}

	/**
	* Set API KEY
	* @param string $key
	*/
	public function setApiKey($key) {
		$this->_apikey = $key; 
	}
	/**
	* Set Params
	* @param array $arr
	*/
	public function setParams($arr) {
		if(!is_array($arr)) throw new Exception('Parameter setting is not valid');
		foreach($arr as $key => $val){
			$this->params[$key] = $val; 
		}
	}
	/**
	* Set Dimensions
	* @param array $arr
	*/
	public function setDimensions($arr, $reset=true) {
		if(!is_array($arr)) throw new Exception('Dimensions setting is not valid');
		if($reset) $this->dimensions = null;
		foreach($arr as $val){
			if($this->dimensions) $this->dimensions .= ","; 
			$this->dimensions .= "ga:$val"; 
		}
		$this->setParams(array("dimensions" => urlencode($this->dimensions)));
	}
	/**
	* Set Metrics
	* @param array $arr
	*/
	public function setMetrics($arr) {
		if(!is_array($arr)) throw new Exception('Metrics setting is not valid');
		foreach($arr as $val){
			if($this->metrics) $this->metrics .= ","; 
			$this->metrics .= "ga:$val"; 
		}
		$this->setParams(array("metrics" => urlencode($this->metrics)));
	}
	/**
	* Set Sort
	* @param array $arr ($key => $val)
	* @  ->$key sorting metrics
	* @  ->in case of descendant, set $val false
	*/
	public function setSort($arr) {
		if(!is_array($arr)) throw new Exception('Sort setting is not valid');
		foreach($arr as $met => $asc){
			if($this->sort) $this->sort .= ","; 
			if(!$asc) $this->sort .= "-"; 
			$this->sort .= "ga:$met"; 
		}
		$this->setParams(array("sort" => $this->sort));
	}
	/**
	* Set Filters
	* @param array $arr
	* @  ->$dim dimention name
	* @  ->$fil filterling condition
	* @param string $sep : separator OR->, AND->;
	*/
	public function setFilter($arr, $sep, $reset) {
		if(!is_array($arr)) throw new Exception('Filters setting is not valid');
		if($reset) $this->filters = null;
		foreach($arr as $dim => $fil){
			if($this->filters) $this->filters .= $sep; 
			$this->filters .= "ga:$fil";
		}
		$this->setParams(array("filters" => urlencode($this->filters)));
	}

	public function setDateRange($target_date=null)
	{
		if($target_date){
			$this->end_date = date("Y-m-d", strtotime($target_date));
			$this->start_date = date("Y-m-d", strtotime("-1 day", strtotime($target_date)));
		}
 		parent::setDateRange($this->start_date,$this->end_date);
	}
	public function setLatestDate()
	{
		$this->start_date = date("Y-m-d", strtotime('-1 day'));
		$this->end_date = date("Y-m-d");
 		$this->setDateRange();
	}
	/**
	* For filtering hourtime when use dateHour on dimensions 
	* @param int $start_hour
	*/
	public function dateHourStringForFilterignRegex($start_hour=FALSE)
	{
		if($start_hour === FALSE) $start_hour = date('H');
		$hour_str1 = $hour_str2 = '';
		for($i=0;$i<24;$i++){
			$hour = $start_hour + $i;
			if($hour<24){
			      $hour_str1 .= $hour. '|';
			}else{
				$hour = $hour-24;
		        $hour_str2 .= sprintf("%02d", $hour). '|';	
			}
		}
		$s_ts = strtotime($this->start_date);
		$e_ts = strtotime($this->end_date);
	   	$dateHourStr = "^(" . date("Ymd", $s_ts) . "(".substr($hour_str1, 0, -1).")";
		$s_ts += 3600*24;
	   	while($s_ts < $e_ts){
		   	$dateHourStr .= "|". date('Ymd', $s_ts) ."[0-2][0-9]";
			$s_ts += 3600*24;
		}
	   	$dateHourStr .= "|".date("Ymd", $e_ts). "(". substr($hour_str2, 0 ,-1) ."))";
		$this->dateHourStr = $dateHourStr;
	}

	
	public function getAllDevicesRecordByCid($cid){
		$result = array();
		foreach($this->profiles as $key => $val){
			$this->setProfile("ga:$val");
			if ($key == 'PC') {
				$this->setDimensions(array('customVarValue4', 'dateHour'), true);
				$this->setFilter(array("pageviews>0", "dateHour=~{$this->dateHourStr}", "customVarValue4=={$cid}"), ";", true);
			} else {
				$this->setDimensions(array('pagePath', 'dateHour'), true);
				$this->setFilter(array("pageviews>0", "dateHour=~{$this->dateHourStr}", "pagePath=~^(\/cid\/$cid|\/db\/.*[\?&]cid=$cid).*"), ";", true);
			}
			$result[$key] = $this->getReport($this->params);
		}
		return $result;
	}

	public function sumAllDevicesPVbyCid($report, $cid=null) {
		foreach ($report as $device => $data) {
			if (is_array($data)) {
				foreach ($data as $cid_time => $val) {
					if ($device == 'PC'){
						if(!$cid){
							preg_match_all('/\d+/', $cid_time, $matches);
							$cid = $matches[0][0];
						}
					} else {
						if(!$cid){
							preg_match('/cid[\/\=](\d+)/', $cid_time, $matches);
							$cid = $matches[1];
						}
					}
					if (isset($pv[$cid])) {
						$pv[$cid] = (int)$val["ga:pageviews"] + $pv[$cid];
					} else {
						$pv[$cid] = (int)$val["ga:pageviews"];
					}
				}
			}else{
				if (!isset($pv[$cid])) {
					$pv[$cid] = 0;
				}
			}
		}
		if (isset($pv)){
			return $pv;
		}else{
			return 0;
		}
	}
		
}
