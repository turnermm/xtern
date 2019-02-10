<?php
/**
 *   @author Myron Turner <turnermm02@shaw.ca>
 *   @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
*/
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
class action_plugin_xtern extends DokuWiki_Action_Plugin {
   	private   $accumulator = null;
    public function register(Doku_Event_Handler $controller) {
       $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call_unknown');    
       $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'curl_check'); 
       $controller->register_hook('IO_WIKIPAGE_READ', 'AFTER', $this, 'handle_wiki_read'); 	
    }

    /**
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    
   public function curl_check(Doku_Event $event, $param) {
        global $USERINFO;     
        $admin = false;
           if(isset($USERINFO)) {
              $groups = $USERINFO['grps'];       
              if(in_array('admin', $groups)) $admin = true;
           }
         if($admin && !function_exists("curl_init"))  {
             msg($this->getLang('nocurl'),2);
             return;  
         }  
      	$this->accumulator = metaFN('xtern:accumulator','.ser');		
   }
   
    public function handle_ajax_call_unknown(Doku_Event $event, $param) {
      if ($event->data !== 'extern_url') {
        return;
      }
    
      global $lang,$INPUT;
      $event->stopPropagation();
      $event->preventDefault();
       $url = $INPUT->str('url');    
       if(!function_exists("curl_init")) {
           echo "NOCURL";
           return;
       }

       $ch = curl_init($url);
	   if($this->getConf('ca_required')) {
            curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/ca/cacert.pem");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
	   }
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5); 
		curl_setopt($ch,CURLOPT_TIMEOUT,10);
		$output = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		echo "$httpcode";
        return 1;
    }

    function handle_wiki_read(Doku_Event $event, $param) {
        global $INPUT;
		if($event->data[3]) {  //by-pass revision		
			return;
		}
		$url = $INPUT->str('xtern_url');        
		if(!isset($url) || empty($url)) return;
       	 
        $id = $INPUT->str('id');
        $ar = unserialize(file_get_contents($this->accumulator));
        foreach($ar[$id] as $url) {            
           $this->update_wiki_page($event->result, $url) ;
        }
    }
    function update_wiki_page(&$result, $url) {
		msg( ($url), 2);
	    $result = preg_replace_callback(
                      "|(?<!LINK:)\s*(\[\[)?(". preg_quote($url). "(\|)*([^\]]+)*(\]\])?)|ms",
                     function($matches){
                       $test = preg_split("/[\s]+/",$matches[2]);                      
                        if(count($test) > 2) {                          
                             return $matches[0];
                        }       				  
                         return "\n__ BROKEN-LINK:" .  $matches[0] .  " LINK-BROKEN __\n";
                  }, 
                  $result
                );
    }

 }
