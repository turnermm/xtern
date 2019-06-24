<?php
/**
 *   @author Myron Turner <turnermm02@shaw.ca>
 *   @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
*/
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
class action_plugin_xtern extends DokuWiki_Action_Plugin {
   	private   $accumulator = null;
    private $current;
	function __construct() {
		$this->accumulator = metaFN('xtern:accumulator','.ser');			
	}
    public function register(Doku_Event_Handler $controller) {
       $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call_unknown');    
       $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'curl_check'); 
       $controller->register_hook('IO_WIKIPAGE_READ', 'AFTER', $this, 'handle_wiki_read'); 	
       $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'handle_wiki_content'); 	
       $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'BEFORE', $this, 'handle_page_save'); 	
       
    }

    /**
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    
   public function curl_check(Doku_Event $event, $param) {
        global $USERINFO,$JSINFO,$ID;     
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
		if($this->getConf('noicons')) {
            $JSINFO['xtern_disable'] = '1';
		}
     	if($this->getConf('alt_div')) {
            $JSINFO['xtern_selector'] = "#" .$this->getConf('alt_div') . " a";
		}
        else if($this->getConf('alt_class')) {
            $JSINFO['xtern_selector'] = '.' . $this->getConf('alt_class') . " a";
		}
        $skip = $this->getConf('skip_pages'); 
         if($skip) {
            $skip = preg_replace("/\s+/","",$skip) ;
            $skip = str_replace(',','|',$skip) ;
            $regex = "#^($skip)$#";
            if(preg_match($regex,$ID)) {
                 $JSINFO['xtern_skip'] = 1;
            }
         }
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
        global $INPUT, $ACT;
		if($event->data[3]) {  //by-pass revision		
			return;
		}
        $act = act_clean($ACT);
        if($act == 'admin' || $act =- 'index') return;
        if(!file_exists($this->accumulator)) return;
        $id = $INPUT->str('id');
        $ar = unserialize(file_get_contents($this->accumulator));
        foreach($ar[$id] as $url) {            
           $this->update_wiki_page($event->result, $url) ;
        }
		
		$srch = array('[[__ BROKEN-LINK:','LINK-BROKEN __ LINK-BROKEN __') ;
		$repl = array( '[[','LINK-BROKEN __');
		$event->result = str_replace($srch,$repl,	$event->result);
		$event->result = preg_replace("#(\s*)\{\{__ BROKEN\-LINK\:#", "$1__BROKEN-LINK:{{",$event->result);
		
    }
    
    function update_wiki_page(&$result, $url)
    {
        msg(($url) , 2);
        $this->current = $url;

        $result = preg_replace_callback("|(?<!LINK:)(\[\[)?(" . preg_quote($url) . "(\|)*([^\]]+)*(\]\])?)[\s]*|ms", function ($matches)
        {
            $test = preg_split("/[\s]+/", $matches[2]); 
            foreach ($test as $piece)
            {
                if (strpos($piece, 'http') !== false)
                {
                    if (strpos($piece, $this->current) !== false && strpos($matches[0], '-LINK:' . $piece) === false)
                    {
                        if ($matches[1] == '[[')
                        {
                            $link = preg_quote($this->current);
                            $matches[0] = preg_replace("#\[\[($link.*?)\]\]#ms", "__ BROKEN-LINK:[[$1]] LINK-BROKEN __", $matches[0]);
                        }
                        else
                        {
                            $matches[0] = str_replace($piece, "__ BROKEN-LINK:" . $piece . " LINK-BROKEN __", $matches[0]);
                        }
                    }
                }
            }

            return $matches[0];
        }
        , $result);
    }

  function handle_wiki_content(Doku_Event $event, $param) {  
     global $ACT;
     
	 if($ACT == 'preview') {
         return;
     }
     else if($this->getConf('conceal')) {
     $event->data = preg_replace('#\<em\s+class=(\"|\')u(\1)\>\s*BROKEN\-LINK\:(.*?)LINK\-BROKEN\s*</em>#',"$3",$event->data);
     }
   }
  function handle_page_save(Doku_Event $event, $param) {  
        global $INPUT;
		$url = $INPUT->str('xtern_url');        
		if(!isset($url) || empty($url)) return;
        if($event->data['contentChanged']) return;
        if(strpos($event->data['newContent'], 'BROKEN-LINK:') !== false) {
            $event->data['contentChanged'] = true;
        }
    }
 }
