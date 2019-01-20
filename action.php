<?php

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
class action_plugin_xtern extends DokuWiki_Action_Plugin {
 
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call_unknown');
    #    $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'prepend_span'); 
    }

    /**
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
     public function  __construct() {
         if(!function_exists("curl_init"))  {
             msg($this->getLang('nocurl'),2);
             return;  
         }  

    }
    public function handle_ajax_call_unknown(Doku_Event &$event, $param) {
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
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5); 
		curl_setopt($ch,CURLOPT_TIMEOUT,10);
		$output = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		echo "$httpcode";
       return 1;
    }
     function prepend_span(Doku_Event &$event, $param) {
		 return;
         if(!function_exists("curl_init")) return;
		  $event->data = preg_replace_callback(    
		  "/\<a href=(.*?)class=\"urlextern\"/i",
        function ($matches) {			
            return '<span class="xtern__xtern"></span> ' . $matches[0];
	    },
        $event->data
       );
     }
}