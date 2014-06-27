<?

class SocialFeed{
	
	var $_id;
	var $_api_url;
	var $_next_page_url;
	var $_cache_file;
	var $_cache_dir;
	var $_cache_life = 1800; # default to 30 min
	var $_data;
	var $_rows = array();
	var $_curl_ca_cert_path;
	var $_token;
	var $_feed_type;
	var $_results_per_page = 15;
	var $_fetch_proxy = "http://redirects.finnpartners.com/util-fopen.php?url=";
	
	function SocialFeed($id, $cache_dir){
		global $ServerConfig;
		
		$this->setId($id);
		$this->setCacheDir($cache_dir);
		$this->_rows = array();

		# if server config exists, and if server is not stage or live, use local ca_cert file for curl requests
		if(is_object($ServerConfig) && !in_array($ServerConfig->getConfigName(), array("dev", "stage", "live"))){
			$path = $ServerConfig->get("PROJECT_ROOT")."/".$ServerConfig->get("DOCUMENT_ROOT_DIR")."/cms/include/Common/cacert.pem";
			$this->setCurlCACertPath($path);
		}
	}
	
	function setId($value){
		$this->_rows = array();
		$this->_id = $value;
	}
	
	function getId(){
		return $this->_id;
	}
	
	function setApiUrl($value){
		$this->_api_url = $value;
	}
	
	function getApiUrl(){
		return $this->_api_url;
	}
	
	function getNextUrl(){
		return $this->_next_page_url;
	}
	
	function setNextUrl($value){
		$this->_next_page_url = $value;
	}
	
	function setCacheFile($value){
		$value = preg_replace("/[^a-zA-Z0-9-]/", "-", $value);
		$this->_cache_file = $value;
	}
	
	function getCacheFile(){
		return $this->_cache_file;
	}
	
	function setCacheDir($value){
		$this->_cache_dir = $value;
	}
	
	function getCacheDir(){
		return $this->_cache_dir;
	}
	
	function setCacheLife($value){
		$this->_cache_life = $value;
	}
	
	function getCacheLife(){
		return $this->_cache_life;
	}
	
	function cacheIsFresh(){
		$file = $this->getCacheDir()."/".$this->getCacheFile();
		if(is_file($file)) return  (time() - $this->_cache_life) < filemtime($file);
		else return false;
	}
	
	function setToken($value){
		$this->_token = $value;
	}
	
	function getToken(){
		return $this->_token;
	}
	
	function setFeedType($value){
		$this->_feed_type = $value;
	}
	
	function getFeedType(){
		return $this->_feed_type;
	}

	function setResultsPerPage($value){
		$this->_results_per_page = $value;
	}
	
	function getResultsPerPage(){
		return $this->_results_per_page;
	}
	
	function setData($value){
		$value = $this->unenc_utf16_code_units($value);
		$this->_data = $value;
	}
	
	function getData(){
		return $this->_data;
	}
	
	function setRows($value){
		$this->_rows = $value;
	}
	
	function getRows(){
		return $this->_rows;
	}
	
	function setCurlCACertPath($value){
		$this->_curl_ca_cert_path = $value;
	}
	
	function getCurlCACertPath(){
		return $this->_curl_ca_cert_path;		
	}

	function setFetchProxy($value){
		$this->_fetch_proxy = $value;
	}
	
	function getFetchProxy(){
		return $this->_fetch_proxy;		
	}

	function saveCache($data){
		if($this->getCacheDir()){    
			$f = fopen($this->getCacheDir()."/".$this->getCacheFile(), "w");	
			fwrite($f, $data);
			fclose($f);
		}
	}
	
	function getCache(){
		if(is_file($this->getCacheDir()."/".$this->getCacheFile())){
			return file_get_contents($this->getCacheDir()."/".$this->getCacheFile(), "r"); 
		}else{
			return false;
		}
	}
	
	function loadCache(){        
		if(!$this->cacheIsFresh()){  
			$this->fetch(false, true);
		}else{
			$data = $this->getCache();
			if($data){
				$this->setData($data);
			}
		}
	}
	
	function fetch($url = false, $update_cache = false){
		if(!$url) $url = $this->getApiUrl();
		$data = $this->fetchUrl($url);   
 		if($data){
 			if($update_cache){
 				$this->saveCache($data);
 			}
		}else{
			$data = $this->getCache();
 		}
		if($data) $this->setData($data);	
	}
	
	function buildRows(){
	}
	
	function fetchUrl($url){
		if($this->getFetchProxy()){
			$url = $this->getFetchProxy().urlencode($url);
		}
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);

		// if a local certificate is set, use this one
		if($this->getCurlCACertPath()){    
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false); 
			curl_setopt ($ch, CURLOPT_CAINFO, $this->getCurlCACertPath()); 
		}

		$data = curl_exec($ch);
		curl_close($ch); 

		return $data;
	}
	
	function unenc_utf16_code_units($string) {
	    /* go for possible surrogate pairs first */
	    $string = preg_replace_callback(
	        '/\\\\U(D[89ab][0-9a-f]{2})\\\\U(D[c-f][0-9a-f]{2})/i',
	        create_function('$matches', ' $hi_surr = hexdec($matches[1]);  $lo_surr = hexdec($matches[2]); $scalar = (0x10000 + (($hi_surr & 0x3FF) << 10) | ($lo_surr & 0x3FF)); return "&#x" . dechex($scalar) . ";";  '), 
	        $string);
	    /* now the rest */
	    $string = preg_replace_callback('/\\\\U([0-9a-f]{4})/i',
	        create_function('$matches', ' return "&#x" . dechex(hexdec($matches[1])) . ";"; '), 
	        $string);
	    return $string;
	}	
	
	function processLinks($text, $link_hashtags = true) {
		$text = utf8_decode( $text );
		$text = preg_replace('@(https?://([-\w\.]+)+(d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a target="_blank" href="$1">$1</a>',  $text );
		if($link_hashtags){
			$text = preg_replace("#(^|[\n ])@([^ \"\t\n\r<]*)#ise", "'\\1<a target=\"_blank\" href=\"http://www.twitter.com/\\2\" >@\\2</a>'", $text);
			$text = preg_replace("#(^|[\n ])\#([^ \"\t\n\r<]*)#ise", "'\\1<a target=\"_blank\" href=\"http://hashtags.org/search?query=\\2\" >#\\2</a>'", $text);
		}
		return $text;
	}
	
	function getTimeAgo($datefrom, $dateto=-1){
		// Defaults and assume if 0 is passed in that
		// its an error rather than the epoch
	    
		if($datefrom<=0) { return "A long time ago"; }
		if($dateto==-1) { $dateto = time(); }

		// Calculate the difference in seconds betweeen
		// the two timestamps

		$difference = $dateto - $datefrom;

		// If difference is less than 60 seconds,
		// seconds is a good interval of choice

		if($difference < 60)
		{
		$interval = "s";
		}

		// If difference is between 60 seconds and
		// 60 minutes, minutes is a good interval
		elseif($difference >= 60 && $difference<60*60)
		{
		$interval = "n";
		}

		// If difference is between 1 hour and 24 hours
		// hours is a good interval
		elseif($difference >= 60*60 && $difference<60*60*24)
		{
		$interval = "h";
		}

		// If difference is between 1 day and 7 days
		// days is a good interval
		elseif($difference >= 60*60*24 && $difference<60*60*24*7)
		{
		$interval = "d";
		}

		// If difference is between 1 week and 30 days
		// weeks is a good interval
		elseif($difference >= 60*60*24*7 && $difference <
		60*60*24*30)
		{
		$interval = "ww";
		}

		// If difference is between 30 days and 365 days
		// months is a good interval, again, the same thing
		// applies, if the 29th February happens to exist
		// between your 2 dates, the function will return
		// the 'incorrect' value for a day
		elseif($difference >= 60*60*24*30 && $difference <
		60*60*24*365)
		{
		$interval = "m";
		}

		// If difference is greater than or equal to 365
		// days, return year. This will be incorrect if
		// for example, you call the function on the 28th April
		// 2008 passing in 29th April 2007. It will return
		// 1 year ago when in actual fact (yawn!) not quite
		// a year has gone by
		elseif($difference >= 60*60*24*365)
		{
		$interval = "y";
		}

		// Based on the interval, determine the
		// number of units between the two dates
		// From this point on, you would be hard
		// pushed telling the difference between
		// this function and DateDiff. If the $datediff
		// returned is 1, be sure to return the singular
		// of the unit, e.g. 'day' rather 'days'

		switch($interval)
		{
		case "m":
		$months_difference = floor($difference / 60 / 60 / 24 /
		29);
		while (mktime(date("H", $datefrom), date("i", $datefrom),
		date("s", $datefrom), date("n", $datefrom)+($months_difference),
		date("j", $dateto), date("Y", $datefrom)) < $dateto)
		{
		$months_difference++;
		}
		$datediff = $months_difference;

		// We need this in here because it is possible
		// to have an 'm' interval and a months
		// difference of 12 because we are using 29 days
		// in a month

		if($datediff==12)
		{
		$datediff--;
		}

		$res = ($datediff==1) ? "$datediff month ago" : "$datediff
		months ago";
		break;

		case "y":
		$datediff = floor($difference / 60 / 60 / 24 / 365);
		$res = ($datediff==1) ? "$datediff year ago" : "$datediff
		years ago";
		break;

		case "d":
		$datediff = floor($difference / 60 / 60 / 24);
		$res = ($datediff==1) ? "$datediff day ago" : "$datediff
		days ago";
		break;

		case "ww":
		$datediff = floor($difference / 60 / 60 / 24 / 7);
		$res = ($datediff==1) ? "$datediff week ago" : "$datediff
		weeks ago";
		break;

		case "h":
		$datediff = floor($difference / 60 / 60);
		$res = ($datediff==1) ? "$datediff hour ago" : "$datediff
		hours ago";
		break;

		case "n":
		$datediff = floor($difference / 60);
		$res = ($datediff==1) ? "$datediff minute ago" :
		"$datediff minutes ago";
		break;

		case "s":
		$datediff = $difference;
		$res = ($datediff==1) ? "$datediff second ago" :
		"$datediff seconds ago";
		break;
		}
		return $res;
	}	
	
	
}


class FacebookSocialFeed extends SocialFeed{
	
	function FacebookSocialFeed($id, $cache_dir){
		$this->setCacheFile("facebook-".$id);
		parent::SocialFeed($id, $cache_dir);
		$this->setToken("AAABzFbZAZABuQBAGPsFF5z5mBZBtIHyEQRNHhuRRt0KPOCzfROck2I2vXDIanpSVtuJVqyW3EY0UoEqKKebKOzZAWWjrcnYZD");
		$this->setApiUrl("https://graph.facebook.com/{$this->getId()}/feed?access_token={$this->getToken()}");
	}
	
	function loadCache(){
		parent::loadCache();
	}

	function fetch($url = false, $update_cache = false){
		parent::fetch($url, $update_cache);
	}
	
	function setData($value){
		parent::setData($value);
		$data = json_decode($this->getData(), true);
		$this->setNextUrl($data["paging"]["next"]);
		$this->buildRows();
	}
	
	function buildRows(){
		$rows = json_decode($this->getData(), true);   

		if($rows['data']){
			foreach ($rows['data'] as $message) {
				$id_parts = explode('_', $message['id']);
				$message['url']      = 'http://www.facebook.com/'.$id_parts[0].'/posts/'.$id_parts[1];
				$message['time_ago'] = $this->getTimeAgo(strtotime($message["created_time"]));
				$message['message']  = $this->processLinks($message["message"], false);
				$this->_rows[] = $message;
			}
		}
		
	}
	
	
	
}


class TwitterSocialFeed extends SocialFeed{
	var $_page;
	var $_follower_count;
	var $_hashatgs;
	var $_results_number;
	var $_CONSUMER_KEY = 'J4L0bceJeud88jxZYHmXGA';
	var $_CONSUMER_SECRET = 'AMVoTwCZwjaQ0kQQfWCtAWVUBFBhPtnB4PAnrlCA28g';
	
	function TwitterSocialFeed($id, $cache_dir){
		global $ServerConfig;
		
		$this->setCacheFile("twitter-".$id);
		parent::SocialFeed($id, $cache_dir);
		$this->setPage(1);
		
		if(is_object($ServerConfig) && $ServerConfig->exists("TWITTER_CONSUMER_KEY") && $ServerConfig->exists("TWITTER_CONSUMER_SECRET")){
			$this->setConsumerKey($ServerConfig->get("TWITTER_CONSUMER_KEY"));
			$this->setConsumerSecret($ServerConfig->get("TWITTER_CONSUMER_SECRET"));
		}
		
	}
	
	function setConsumerKey($value){
		$this->_CONSUMER_KEY = $value;
	}
	
	function getConsumerKey(){
		return $this->_CONSUMER_KEY;
	}
	
	function setConsumerSecret($value){
		$this->_CONSUMER_SECRET = $value;
	}
	
	function getConsumerSecret(){
		return $this->_CONSUMER_SECRET;
	}
	
	function setPage($value){
		$this->_page = $value;
	}
	
	function getPage(){
		return $this->_page;
	}
	
	function setFollowerCount($value){
		$this->_follower_count = $value;
	}
	
	function getFollowerCount(){
		return $this->_follower_count;
	}
	
	function setSearchFeed(){
		$this->setFeedType("search");
	}
	
	function setTimelineFeed(){
		$this->setFeedType("timeline");
	}
	
	function setFollowerFeed(){
		$this->setFeedType("follower");
	}
	
	function setRetweetFeed(){
		$this->setFeedType("retweet");
	}
	
	function setHashTags($hashtags){
		if(!is_array($hashtags)){
			$hashtags = explode(",", $hashtags);
		}
		$this->_hashtags = $hashtags;
	}         
	
	function setResultsNumber($value){
		$this->_results_number = $value;
	}  
	
	function isSearch(){
		return $this->_feed_type == "search";
	}
	
	function isTimeline(){
		return $this->_feed_type == "timeline";
	}
	
	function isFollower(){
		return $this->_feed_type == "follower";
	}
	
	function isRetweet(){
		return $this->_feed_type == "retweet";
	}
	
	function setFeedType($value){
		parent::setFeedType($value);
		
		if($this->isSearch()){
			$this->setApiUrl("https://api.twitter.com/1.1/search/tweets.json");
		}elseif($this->isTimeline()){
			$this->setApiUrl("https://api.twitter.com/1.1/statuses/user_timeline.json");
		}elseif($this->isFollower()){
			$this->setApiUrl("https://api.twitter.com/1.1/users/lookup.json");
		}elseif($this->isRetweet()){
			$this->setApiUrl("https://api.twitter.com/1.1/statuses/retweets/{{ID}}.json");
		}
	}
	
	function loadCache(){
		parent::loadCache();
	}

	function fetch($url = false, $update_cache = false){       
		if($this->isSearch()){
			$formed_url ='?q='.urlencode($this->getId()); 
			$formed_url .= '&include_entities=true&count='.$this->getResultsPerPage();    
			$get = "GET /1.1/search/tweets.json".$formed_url." HTTP/1.1";			
		}elseif($this->isTimeline()){
			$formed_url ='?screen_name='.urlencode($this->getId());
			$formed_url .= '&include_entities=true&count='.$this->getResultsPerPage().'&page='.$this->getPage(); 
			$get = "GET /1.1/statuses/user_timeline.json".$formed_url." HTTP/1.1";			
		}elseif($this->isFollower()){
			$formed_url ='?screen_name='.urlencode($this->getId());
			$formed_url .= '&include_entities=true&count='.$this->getResultsPerPage().''; 
			$get = "GET /1.1/users/lookup.json".$formed_url." HTTP/1.1";
		}elseif($this->isRetweet()){
			$get = "GET /1.1/statuses/retweets/".$this->getId().".json HTTP/1.1";
			$this->setApiUrl(str_replace('{{ID}}', $this->getId(), $this->getApiUrl()));
		}
		
		$headers = array( 
			$get, 
			"Host: api.twitter.com", 
			"Authorization: Bearer ".$this->get_bearer_token()."",
		);
		$ch = curl_init();  
		curl_setopt($ch, CURLOPT_URL,$this->getApiUrl().$formed_url);  
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
		// if a local certificate is set, use this one
		if($this->getCurlCACertPath()){
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false); 
			curl_setopt ($ch, CURLOPT_CAINFO, $this->getCurlCACertPath()); 
		}

		ob_start();  
		$output = curl_exec ($ch); 
		$data = ob_get_contents(); 
		ob_end_clean(); 
		curl_close($ch);
    	
		if($data){
			
			$this->setData($data);    
			              			
			if($update_cache){
				$this->saveCache($data);
			}
		}
	}
	
	function get_bearer_token(){
		// Step 1
		// step 1.1 - url encode the consumer_key and consumer_secret in accordance with RFC 1738
		$encoded_consumer_key = urlencode($this->getConsumerKey());
		$encoded_consumer_secret = urlencode($this->getConsumerSecret());
		// step 1.2 - concatinate encoded consumer, a colon character and the encoded consumer secret
		$bearer_token = $encoded_consumer_key.':'.$encoded_consumer_secret;
		// step 1.3 - base64-encode bearer token
		$base64_encoded_bearer_token = base64_encode($bearer_token);
		// step 2
		$url = "https://api.twitter.com/oauth2/token"; // url to send data to for authentication
		$headers = array( 
			"POST /oauth2/token HTTP/1.1", 
			"Host: api.twitter.com", 
			"Authorization: Basic ".$base64_encoded_bearer_token."",
			"Content-Type: application/x-www-form-urlencoded;charset=UTF-8", 
			"Content-Length: 29"
		); 

		$ch = curl_init();  // setup a curl
		curl_setopt($ch, CURLOPT_URL,$url);  // set url to send to
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // set custom headers
		curl_setopt($ch, CURLOPT_POST, 1); // send as post
		curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials"); // post body/fields to be sent
		if($this->getCurlCACertPath()){
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false); 
			curl_setopt ($ch, CURLOPT_CAINFO, $this->getCurlCACertPath()); 
		}
		$header = curl_setopt($ch, CURLOPT_HEADER, 1); // send custom headers
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		ob_start();  // start ouput buffering
		curl_exec ($ch); // execute the curl
		$retrievedhtml = ob_get_contents(); // grab the retreived html
		ob_end_clean(); //End buffering and clean output 
		curl_close($ch); // close the curl
		$output = explode("\n", $retrievedhtml);
		$bearer_token = '';
		foreach($output as $line)
		{
			if($pos === false)
			{
				// there was no bearer token
			}else{
				$bearer_token = $line;
			}
		}
		$bearer_token = json_decode($bearer_token);
		return $bearer_token->{'access_token'};
	}
	
	
	function setData($value){
		parent::setData($value);
		//$data = json_decode($this->getData(), true);
		$this->buildRows();
	}
	
	function getNextUrl(){
		return 	$this->setNextUrl($this->getApiUrl()."&page=".$this->getPage());
	}
	
	function getApiUrl(){
		return $this->_api_url;//."&page=".$this->getPage();
	}

	function buildRows($finish = false){
		
		if ($finish) return;
			
		$data = json_decode($this->getData());
	    		
		if($this->isRetweet()){
			foreach ($data as $user) {
				//p($user);
			}
			return;
		}
		
		if($this->isFollower()){
			foreach ($data as $user) {
				$this->_rows[$user->screen_name] = $user->followers_count;
			}
			return;
		}
		
		if($this->isSearch()){
			$rows = $data->statuses;
		}elseif($this->isTimeline()){
			$rows = $data;
		}
		
		foreach ($rows as $key => $status) {
			
			$tweet = get_object_vars($status);
			$tweet["link"] = "http://twitter.com/u/status/".$status->id_str;
			$tweet["date"] = $status->created_at;
			$tweet["text"] = $status->text;
//				$tweet["href"] = $tweet["link"]["@attributes"]["href"];
			$tweet["author"] = get_object_vars($status->user);
			$tweet["message"] = utf8_encode($this->processLinks($tweet["text"]));
			$tweet["time-ago"] = $this->getTimeAgo(strtotime($tweet["date"]), time());
			if ($this->_hashtags){ 
				if ($this->message_hasHashtags($tweet["message"], $this->_hashtags)) $this->_rows[] = $tweet;
			}else{
				 $this->_rows[] = $tweet;
			}	
			
		}
		
		if ($this->_results_number && sizeof($this->_rows) < $this->_results_number){
			$this->_page = $this->_page + 1;				
			$this->fetch();
			$this->buildRows(true);
		}		
		
	}
	
	function message_hasHashtags($message, $hashtags){
		
		if (!sizeof($hashtags)) return false;
		
		$has_hashtags = false;
		foreach ($hashtags as $hashtag){
			if (is_integer(strpos($message, $hashtag))){
				$has_hashtags = true; continue;
			}
		}	
		
		return $has_hashtags;
	}	
	
}

class YouTubeSocialFeed extends SocialFeed{
	
	function YouTubeSocialFeed($id, $cache_dir){
		$this->setCacheFile("youtube-".$id);
		parent::SocialFeed($id, $cache_dir);
		$this->setApiUrl("http://gdata.youtube.com/feeds/base/users/{$this->getId()}/uploads?client=ytapi-youtube-rss-redirect&v=2&orderby=updated&alt=json");
	}
	
	function loadCache(){
		parent::loadCache();
	}

	function fetch($url = false, $update_cache = false){
		parent::fetch($url, $update_cache);
	}
	
	function setData($value){
		parent::setData($value);
		$data = json_decode($this->getData(), true);
		$this->buildRows();
	}
	
	function buildRows(){  
		global $ServerConfig;
		
		$rows = json_decode($this->getData(), true);
		
		if($rows['feed']['entry']){
			foreach ($rows['feed']['entry'] as $row) {
				$row["title"]     = $row["title"]["\$t"];
				$row["published"] = $row["published"]["\$t"];
				$row['time_ago']  = $this->getTimeAgo(strtotime($row["published"]));
				$row["url"]       = $row["link"][0]["href"];
				$row["id"]        = $this->getVideoID($row["url"]);
				$row["content"]   = $row["content"]["\$t"];
				$details          = $this->fetchUrl("http://gdata.youtube.com/feeds/api/videos/".$row["id"]."?v=2&alt=json");
				if(is_object($ServerConfig) && !in_array($ServerConfig->getConfigName(), array("dev", "stage", "live"))){
					ob_start();
					?>
						{"version":"1.0","encoding":"UTF-8","entry":{"xmlns":"http://www.w3.org/2005/Atom","xmlns$media":"http://search.yahoo.com/mrss/","xmlns$gd":"http://schemas.google.com/g/2005","xmlns$yt":"http://gdata.youtube.com/schemas/2007","gd$etag":"W/\"DEcDQn47eCp7I2A9WhFRGUQ.\"","id":{"$t":"tag:youtube.com,2008:video:RFznCy9qWdM"},"published":{"$t":"2013-07-02T14:32:37.000Z"},"updated":{"$t":"2013-07-05T03:47:53.000Z"},"category":[{"scheme":"http://schemas.google.com/g/2005#kind","term":"http://gdata.youtube.com/schemas/2007#video"},{"scheme":"http://gdata.youtube.com/schemas/2007/categories.cat","term":"People","label":"People & Blogs"}],"title":{"$t":"Body & Soul and the Blues Community Challenge Learn about label reading at Meijer"},"content":{"type":"application/x-shockwave-flash","src":"http://www.youtube.com/v/RFznCy9qWdM?version=3&f=videos&app=youtube_gdata"},"link":[{"rel":"alternate","type":"text/html","href":"http://www.youtube.com/watch?v=RFznCy9qWdM&feature=youtube_gdata"},{"rel":"http://gdata.youtube.com/schemas/2007#video.responses","type":"application/atom+xml","href":"http://gdata.youtube.com/feeds/api/videos/RFznCy9qWdM/responses?v=2"},{"rel":"http://gdata.youtube.com/schemas/2007#video.related","type":"application/atom+xml","href":"http://gdata.youtube.com/feeds/api/videos/RFznCy9qWdM/related?v=2"},{"rel":"http://gdata.youtube.com/schemas/2007#mobile","type":"text/html","href":"http://m.youtube.com/details?v=RFznCy9qWdM"},{"rel":"http://gdata.youtube.com/schemas/2007#uploader","type":"application/atom+xml","href":"http://gdata.youtube.com/feeds/api/users/_7_KwTfInY6ozCaLqBpNwA?v=2"},{"rel":"self","type":"application/atom+xml","href":"http://gdata.youtube.com/feeds/api/videos/RFznCy9qWdM?v=2"}],"author":[{"name":{"$t":"AHealthierMichigan"},"uri":{"$t":"http://gdata.youtube.com/feeds/api/users/AHealthierMichigan"},"yt$userId":{"$t":"_7_KwTfInY6ozCaLqBpNwA"}}],"yt$accessControl":[{"action":"comment","permission":"moderated"},{"action":"commentVote","permission":"denied"},{"action":"videoRespond","permission":"moderated"},{"action":"rate","permission":"allowed"},{"action":"embed","permission":"allowed"},{"action":"list","permission":"allowed"},{"action":"autoPlay","permission":"allowed"},{"action":"syndicate","permission":"allowed"}],"gd$comments":{"gd$feedLink":{"rel":"http://gdata.youtube.com/schemas/2007#comments","href":"http://gdata.youtube.com/feeds/api/videos/RFznCy9qWdM/comments?v=2","countHint":0}},"yt$hd":{},"media$group":{"media$category":[{"$t":"People","label":"People & Blogs","scheme":"http://gdata.youtube.com/schemas/2007/categories.cat"}],"media$content":[{"url":"http://www.youtube.com/v/RFznCy9qWdM?version=3&f=videos&app=youtube_gdata","type":"application/x-shockwave-flash","medium":"video","isDefault":"true","expression":"full","duration":117,"yt$format":5},{"url":"rtsp://r1---sn-4g57lnee.c.youtube.com/CiILENy73wIaGQnTWWovC-dcRBMYDSANFEgGUgZ2aWRlb3MM/0/0/0/video.3gp","type":"video/3gpp","medium":"video","expression":"full","duration":117,"yt$format":1},{"url":"rtsp://r1---sn-4g57lnee.c.youtube.com/CiILENy73wIaGQnTWWovC-dcRBMYESARFEgGUgZ2aWRlb3MM/0/0/0/video.3gp","type":"video/3gpp","medium":"video","expression":"full","duration":117,"yt$format":6}],"media$credit":[{"$t":"ahealthiermichigan","role":"uploader","scheme":"urn:youtube","yt$display":"AHealthierMichigan"}],"media$description":{"$t":"Blue Cross Blue Shield of Michigan is currently sponsoring its fourth annual Blues' Community Challenge, in partnership with the American Cancer Society's Body & Soul program with 15 participating churches from the Grand Rapids, Mich. area.\n\nThis is part two of a six part series. In this segment, Grace speaks with Tenisa and Darnell about the values of label reading.","type":"plain"},"media$keywords":{},"media$license":{"$t":"youtube","type":"text/html","href":"http://www.youtube.com/t/terms"},"media$player":{"url":"http://www.youtube.com/watch?v=RFznCy9qWdM&feature=youtube_gdata_player"},"media$thumbnail":[{"url":"http://i1.ytimg.com/vi/RFznCy9qWdM/default.jpg","height":90,"width":120,"time":"00:00:58.500","yt$name":"default"},{"url":"http://i1.ytimg.com/vi/RFznCy9qWdM/mqdefault.jpg","height":180,"width":320,"yt$name":"mqdefault"},{"url":"http://i1.ytimg.com/vi/RFznCy9qWdM/hqdefault.jpg","height":360,"width":480,"yt$name":"hqdefault"},{"url":"http://i1.ytimg.com/vi/RFznCy9qWdM/sddefault.jpg","height":480,"width":640,"yt$name":"sddefault"},{"url":"http://i1.ytimg.com/vi/RFznCy9qWdM/1.jpg","height":90,"width":120,"time":"00:00:29.250","yt$name":"start"},{"url":"http://i1.ytimg.com/vi/RFznCy9qWdM/2.jpg","height":90,"width":120,"time":"00:00:58.500","yt$name":"middle"},{"url":"http://i1.ytimg.com/vi/RFznCy9qWdM/3.jpg","height":90,"width":120,"time":"00:01:27.750","yt$name":"end"}],"media$title":{"$t":"Body & Soul and the Blues Community Challenge Learn about label reading at Meijer","type":"plain"},"yt$aspectRatio":{"$t":"widescreen"},"yt$duration":{"seconds":"117"},"yt$uploaded":{"$t":"2013-07-02T14:32:37.000Z"},"yt$uploaderId":{"$t":"UC_7_KwTfInY6ozCaLqBpNwA"},"yt$videoid":{"$t":"RFznCy9qWdM"}},"gd$rating":{"average":5.0,"max":5,"min":1,"numRaters":1,"rel":"http://schemas.google.com/g/2005#overall"},"yt$statistics":{"favoriteCount":"0","viewCount":"36"},"yt$rating":{"numDislikes":"0","numLikes":"1"}}}				
					<?
					$details = ob_get_clean();
				}
				if($details){
					$row["details"] = json_decode($details, true);
					$row["description"] = $row["details"]["entry"]["media\$group"]["media\$description"]["\$t"];
					$row["views"] = $row["details"]["entry"]["yt\$statistics"]["viewCount"];
					$row["thumbnails"] = $row["details"]["entry"]["media\$group"]["media\$thumbnail"];
				}  
				$this->_rows[] = $row;
			}
		}
		
		//print_r($this->getRows());     

	}
	
	function getVideoID($url){
	    
		$parts = explode("?", $url);
		$parts = explode("&", $parts[1]);

		foreach($parts as $p){
			list($p, $v) = explode("=", $p);
			if($p == "v"){
				$youtube_id = $v;
			}
		}
	    
	    if (!$youtube_id){
    		preg_match('/^(.*)\/v\/(.*)[\?(.*)]+/', $url, $matches);
	    	$youtube_id = $matches[2];
	    }
	    
	    return $youtube_id;
	}
	
}

class InstagramSocialFeed extends SocialFeed{
	
	function InstagramSocialFeed($id, $cache_dir){
		global $ServerConfig;
		
		$this->setCacheFile("instagram-".$id);
		parent::SocialFeed($id, $cache_dir);
		// To generate a new Instagram token use this form: http://www.secured-app.com/instagram/get-token/
		$this->setToken("264512628.4e483f5.128ac71396e040c9ba4174f9e8f47dd5");
		
	}
	
	function setTagSearchFeed(){
		$this->setFeedType("tag-search");
	}
	
	function setTimelineFeed(){
		$this->setFeedType("timeline");
	}
	
	function isTagSearch(){
		return $this->_feed_type == "tag-search";
	}
	
	function isTimeline(){
		return $this->_feed_type == "timeline";
	}
	
	function setFeedType($value){
		parent::setFeedType($value);
		
		if(!$this->getApiUrl()){
			if($this->isTagSearch()){
				$this->setApiUrl("https://api.instagram.com/v1/tags/".$this->getId()."/media/recent/?count=".$this->getResultsPerPage()."&access_token=".$this->getToken());
			}elseif($this->isTimeline()){
				if(!intval($this->getId())){
					$user_id = $this->getUserIdFromUsername($this->getId());
					if($user_id){
						$this->setId($user_id);
						$this->setCacheFile("instagram-".$user_id);
					}else{
						trigger_error("Instagram User not found", E_USER_ERROR);
					}
				}
				$this->setApiUrl("https://api.instagram.com/v1/users/".$this->getId()."/media/recent/?access_token=".$this->getToken());
			}
		}
	}
	
	function getUserIdFromUsername($username){
		$data = $this->fetchUrl("https://api.instagram.com/v1/users/search/?q=".urlencode($username)."&access_token=".$this->getToken());
		$data = json_decode($data);
		
		return $data->data[0]->id;
	}

	function setData($value){
		parent::setData($value);
		$this->buildRows();
	}

	function buildRows(){
		
		$data = json_decode($this->getData());

		$rows = $data->data;
		
		foreach ($rows as $key => $row) {
			$photo = get_object_vars($row);
			$photo["date"] = $row->created_time;
			$photo["text"] = $row->caption->text;
			$photo["author"] = get_object_vars($row->user);
			$photo["time-ago"] = $this->getTimeAgo(strtotime($photo["date"]), time());
			$this->_rows[] = $photo;
		}

		// & is html encoded in pagination urls and needs to be replaced in order to make the next url work
		$this->setNextUrl(str_replace("&#x26;", "&", $data->pagination->next_url));
	}

	
}
?>
