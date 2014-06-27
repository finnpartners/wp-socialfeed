<?php


include_once('SocialFeed_LifeCycle.php');

class SocialFeed_Plugin extends SocialFeed_LifeCycle {

    /**
     * See: http://plugin.michael-simpson.com/?page_id=31
     * @return array of option meta data.
     */
    public function getOptionMetaData() {
        //  http://plugin.michael-simpson.com/?page_id=31
        return array(
            //'_version' => array('Installed Version'), // Leave this one commented-out. Uncomment to test upgrades.
            'TwitterTimeline' => array(__('Twitter timeline<br/><small>use comma as separator</small>', 'my-awesome-plugin')),
            'TwitterSearch' => array(__('Twitter search<br/><small>use comma as separator</small>', 'my-awesome-plugin')),
            'InstagramTimeline' => array(__('Instagram timeline<br/><small>use comma as separator</small>', 'my-awesome-plugin')),
            'InstagramSearch' => array(__('Instagram search<br/><small>use comma as separator</small>', 'my-awesome-plugin')),
            'ExcludedWords' => array(__('Excluded words (comma separated)<br/><small>use comma as separator</small>', 'my-awesome-plugin')),
            'UseLocalCaCert' => array(__('Use local cacert.pem (for local servers)', 'my-awesome-plugin'), 'No', 'Yes')
        );

    }

//    protected function getOptionValueI18nString($optionValue) {
//        $i18nValue = parent::getOptionValueI18nString($optionValue);
//        return $i18nValue;
//    }

    protected function initOptions() {
        $options = $this->getOptionMetaData();
        if (!empty($options)) {
            foreach ($options as $key => $arr) {
                if (is_array($arr) && count($arr > 1)) {
                    $this->addOption($key, $arr[1]);
                }
            }
        }
    }

    public function getPluginDisplayName() {
        return 'SocialFeed';
    }

    protected function getMainPluginFileName() {
        return 'socialfeed.php';
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Called by install() to create any database tables if needed.
     * Best Practice:
     * (1) Prefix all table names with $wpdb->prefix
     * (2) make table names lower case only
     * @return void
     */
    protected function installDatabaseTables() {
                global $wpdb;
                $tableName = $this->prefixTableName('data');
                $wpdb->query("
						CREATE TABLE IF NOT EXISTS `$tableName` (
						  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
						  `submission_date` datetime DEFAULT NULL,
						  `type` varchar(50) DEFAULT NULL,
						  `term` varchar(255) DEFAULT NULL,
						  `source` varchar(20) DEFAULT NULL,
						  `source_id` varchar(255) DEFAULT NULL,
						  `name` varchar(255) DEFAULT NULL,
						  `url` varchar(255) DEFAULT NULL,
						  `image_url` varchar(255) DEFAULT NULL,
						  `content` text,
						  `visible` enum('t','f') NOT NULL DEFAULT 't',
						  `full_data` text,
						  PRIMARY KEY (`id`)
						);
                ");
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Drop plugin-created tables on uninstall.
     * @return void
     */
    protected function unInstallDatabaseTables() {
        //        global $wpdb;
        //        $tableName = $this->prefixTableName('mytable');
        //        $wpdb->query("DROP TABLE IF EXISTS `$tableName`");
    }


    /**
     * Perform actions when upgrading from version X to version Y
     * See: http://plugin.michael-simpson.com/?page_id=35
     * @return void
     */
    public function upgrade() {
    }

    public function addActionsAndFilters() {

        // Add options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        add_action('admin_menu', array(&$this, 'addSettingsSubMenuPage'));

        // Example adding a script & style just for the options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        //        if (strpos($_SERVER['REQUEST_URI'], $this->getSettingsSlug()) !== false) {
        //            wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));
        //            wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        }


        // Add Actions & Filters
        // http://plugin.michael-simpson.com/?page_id=37


        // Adding scripts & styles to all pages
        // Examples:
        //        wp_enqueue_script('jquery');
        //        wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));


        // Register short codes
        // http://plugin.michael-simpson.com/?page_id=39


        // Register AJAX hooks
        // http://plugin.michael-simpson.com/?page_id=41
        
 		add_action('wp_ajax_SocialFeedFetch', array(&$this, 'ajaxSocialFeedFetch'));
   		add_action('wp_ajax_nopriv_SocialFeedFetch', array(&$this, 'ajaxSocialFeedFetch')); // optional        

    }
    
    public function ajaxSocialFeedFetch(){
	    // Don't let IE cache this request
	    header("Pragma: no-cache");
	    header("Cache-Control: no-cache, must-revalidate");
	    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
	 
	    header("Content-type: text/plain");
	    
        $tw_timeline   = explode(",", $this->getOption("TwitterTimeline"));
        $tw_search     = explode(",", $this->getOption("TwitterSearch"));
        $inst_timeline = explode(",", $this->getOption("InstagramTimeline"));
        $inst_search   = explode(",", $this->getOption("InstagramSearch"));
        
        $count         = 100;
        
		foreach($tw_search as $id){
			$id = trim($id);
			if($id){
				$tw_feed = new TwitterSocialFeed($id, false);
				if($this->getOption("UseLocalCaCert") == "Yes"){
					$tw_feed->setCurlCACertPath(plugin_dir_path("cacert.pem"));
				}
				$tw_feed->setSearchFeed();
				$tw_feed->setResultsPerPage($count);
				$tw_feed->fetch();
				$rows = $tw_feed->getRows();
				
				foreach($rows as $i => $row){   
					$media = $row["entities"]->media;
					$image_url = false;
					if(is_array($media)){
						foreach($media as $m){
							if($m->type == "photo"){
								$image_url = $m->media_url_https;
								break;
							}
						}
					}
					$this->addEntryInDB(array(
						"type"      => "search",
						"source"    => "twitter", 
						"source_id" => $row["id_str"], 
						"term"      => $id,
						"name"      => $row["author"]["screen_name"],
						"url"       => $row["link"],
						"image_url" => $image_url,
						"content"   => $row["message"],
						"submission_date" => date("Y-m-d H:i", strtotime($row["date"])),
						"full_data" => serialize($row)
					));
				} 
			}
		}
        
		foreach($tw_timeline as $id){
			$id = trim($id);
			if($id){
				$tw_feed = new TwitterSocialFeed($id, false);
				if($this->getOption("UseLocalCaCert") == "Yes"){
					$tw_feed->setCurlCACertPath(plugin_dir_path("cacert.pem"));
				}
				$tw_feed->setTimelineFeed();
				$tw_feed->setResultsPerPage($count);
				$tw_feed->fetch();
				$rows = $tw_feed->getRows();
				
				foreach($rows as $i => $row){   
					$media = $row["entities"]->media;
					$image_url = false;
					if(is_array($media)){
						foreach($media as $m){
							if($m->type == "photo"){
								$image_url = $m->media_url_https;
								break;
							}
						}
					}
					$this->addEntryInDB(array(
						"type"      => "timeline",
						"source"    => "twitter", 
						"source_id" => $row["id_str"], 
						"term"      => $id,
						"name"      => $row["author"]["screen_name"],
						"url"       => $row["link"],
						"image_url" => $image_url,
						"content"   => $row["message"],
						"submission_date" => date("Y-m-d H:i", strtotime($row["date"])),
						"full_data" => serialize($row)
					));
				} 
			}
		}


		foreach($inst_search as $id){
			$id = trim($id);
			if($id){
				$inst_feed = new InstagramSocialFeed(trim($id, "#"), false);
				if($this->getOption("UseLocalCaCert") == "Yes"){
					$inst_feed->setCurlCACertPath(plugin_dir_path("cacert.pem"));
				}
				$inst_feed->setTagSearchFeed();
				$inst_feed->setResultsPerPage($count);
				$inst_feed->fetch();           
				$rows = $inst_feed->getRows();
				foreach($rows as $i => $row){   
					$this->addEntryInDB(array(
						"type"      => "search",
						"source"    => "instagram", 
						"source_id" => $row["id"],   
						"term"      => $id,
						"name"      => $row["author"]["full_name"],
						"url"       => $row["link"],
						"image_url" => $row["images"]->standard_resolution->url,
						"content"   => $row["caption"]->text,
						"submission_date" => date("Y-m-d H:i", $row["created_time"]),
						"full_data" => serialize($row)
					));
				} 
			}
		}
		
		foreach($inst_timeline as $id){
			$id = trim($id);
			if($id){
				$inst_feed = new InstagramSocialFeed($id, false);
				if($this->getOption("UseLocalCaCert") == "Yes"){
					$inst_feed->setCurlCACertPath(plugin_dir_path("cacert.pem"));
				}
				$inst_feed->setTimelineFeed();
				$inst_feed->setResultsPerPage($count);
				$inst_feed->fetch();           
				$rows = $inst_feed->getRows();
				foreach($rows as $i => $row){   
					$this->addEntryInDB(array(
						"type"      => "timeline",
						"source"    => "instagram", 
						"source_id" => $row["id"],   
						"term"      => $id,
						"name"      => $row["author"]["full_name"],
						"url"       => $row["link"],
						"image_url" => $row["images"]->standard_resolution->url,
						"content"   => $row["caption"]->text,
						"submission_date" => date("Y-m-d H:i", $row["created_time"]),
						"full_data" => serialize($row)
					));
				} 
			}
		}
        
        
	    die();    
    }
    
    protected function addEntryInDB($data){
    	global $wpdb;    	
    	$table = $this->prefixTableName('data');
    	$data["visible"] = ($this->hasExcludedWords($data)) ? "f" : "t";
    	
		if(!$wpdb->get_row("
				SELECT * 
				  FROM ". $table ." 
				 WHERE source = '".$data["source"]."' 
				   AND source_id = '".$data["source_id"]."' 
				   AND type='".$data["type"]."'", ARRAY_A)){
			$wpdb->insert($table, $data);
		}
    }
    
    protected function hasExcludedWords($row){
    	$words = $this->getOption("ExcludedWords");
    	$words = explode(",", $words);
    	foreach($row as $key => $value){                     
    		foreach($words as $w){
    			$w = trim($w);
    			if($w && $value){
    				if(strpos($value, $w) !== FALSE){
    					return true;
    				}
    			}
    		}
    	}
    }


}


function socialfeed_get($opts){
	global $wpdb;
	$where = array();
	$rows  = array();
	$limit = "";
	
	$sf    = new SocialFeed_Plugin();
	foreach($opts["feeds"] as $feed){
		$where[] = " (source = '".$feed["source"]."' AND type='".$feed["type"]."' AND term = '".$feed["term"]."') ";
	}
	if($where){
		$where = " WHERE (".join("OR", $where).") ";
		if($opts["limit"]){
			$opts["page"] = intval($opts["page"]);
			if($opts["page"]){
				$limit_from  = ($opts["page"] - 1) * $opts["limit"];
				$limit_from .= ", ";
			}
			$limit = " LIMIT $limit_from ".$opts["limit"];
		}
		$rows = $wpdb->get_results("SELECT * FROM ".$sf->prefixTableName('data')." ".$where." AND visible = 't' ORDER BY submission_date DESC $limit", ARRAY_A);
		//echo $wpdb->last_query;
	}
	
	return $rows;
}