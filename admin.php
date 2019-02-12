<?php
/**
 *   @author Myron Turner <turnermm02@shaw.ca>
 *   @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
*/
class admin_plugin_xtern extends DokuWiki_Admin_Plugin {
	private $dnld = false;
	private $check = false;
	private  $wikiRoot;
	private  $dir = NULL;
	private   $accumulator = null;
	private $broken = array();
    private  $debug_handle = null;
    function __construct() {
		$this->wikiRoot = realpath (DOKU_INC. 'data/pages');
		$this->accumulator = metaFN('xtern:accumulator','.ser');		
        $this->debug_handle=fopen(DOKU_INC.'xtern.txt', 'wb');
        
	}

    function handle() {
 
      if (!isset($_REQUEST['cmd'])) return;   // first time - nothing to do
 
      $this->output = 'invalid';
	
      if (!checkSecurityToken()) return;
      if (!is_array($_REQUEST['cmd'])) return; 
   
      switch (key($_REQUEST['cmd'])) {
        case 'check_links' :
		    $this->output = 'check_links';
			$this->check = true;
            if(!empty($_REQUEST['dir'])) {
                $this->dir = $_REQUEST['dir'];
            }
			break;
        case 'download' : 
	  	    $this->output = 'download'; 
			$this->dnld = true;
      }      
	
	  //msg(__DIR__);
    }
 
    /**
     * output appropriate html
     */
    function html() {
		
	  $max_time =  $this->getConf('max_time');
	  $ini_max = ini_get('max_execution_time');
	  $max_time = $max_time >  $ini_max ?  $max_time : $ini_max;
		  	
	  $this->buttons($max_time);

	  if($this->check) {
	      $this->check_links($max_time);
	  }
	  else if ($this->dnld) {
		  $this->downloadPem();
	  }
	
    }
	
	     function check_links($max_time) {
		   set_time_limit($max_time);
		  $this->disable_ob();
		   $this->buttons($max_time,$this->dir);  
			if(isset($this->dir)){
                $dir = trim($this->dir,':');
                $dir = str_replace(':', '/', $dir);
                $dir = $this->wikiRoot . '/' . $dir;
            }	
            else $dir = $this->wikiRoot;
             ptln('<div id="xtern_chklnk"><hr>');
			echo "Checking: $dir<br />";
		    usleep(300000);	
			$site = $this->scanDirectories($dir);
			echo "Checking links\n<br />";
			 usleep(300000);
			foreach($site AS $entry=>$data) {
                $this->write_debug("\n** New File" . $data['path']);
				  $handle = fopen($data['path'], "r");             
				 if ($handle) {	
					$this->parse_dwfile($handle,$data['id'],$data['path']);
					fclose($handle);
				 }
			}
           ptln("<br /><b>DONE</b>");
           ptln('</div>' . NL);
		   io_saveFile($this->accumulator,serialize($this->broken)) ;	
           fclose($this->debug_handle);
	}
       
     function buttons($max_time = "",$ns="") {        
          echo $this->locale_xhtml('header');	 
          //$ns = isset($this->dir) ? $this->dir : "";
          ptln('<div id="xtern_adminform">' .NL); 
          ptln('<form action="'.wl($ID).'" method="post">'); 
          // output hidden values to ensure dokuwiki will return back to this plugin	 
          ptln('  <input type="hidden" name="do"   value="admin" />');
          ptln('  <input type="hidden" name="page" value="'.$this->getPluginName().'" />');
          formSecurityToken(); 
          ptln('  <input type="submit" name="cmd[download]" class  = "xtern_font" value="'.$this->getLang('btn_download').'" />');
          ptln('&nbsp;  <input type="submit" name="cmd[check_links]" class  = "xtern_font" value="'.$this->getLang('btn_check_links').'" />');
          ptln('  <label><span class="xtern_font">' .$this->getLang('ns').'</span> ');
          ptln(' <input type="textbox" name="dir"  value="' . $ns . '" /></label>&nbsp;');                
          ptln('</form>');
          if($max_time) {
			    ptln('<br />' . $this->getLang('max_time') . ":  $max_time");
		  }			  
          ptln('</div>');    
     }
     /**
	  *   @ $id  wiki page
	  *   @	 $url  broken link address
	 */
     function local_url($id,$url) {
          $id = trim($id,':');
		  $url = rawurlencode($url);		 
          $id = str_replace(array('"', "'"),array(""),$id);             
              return " <a href='". DOKU_URL ."doku.php?id=$id&xtern_url=$url&do=edit' target = 'xtern_xtern' class='wikilink1'>$id</a>";
           }
	function add_broken($id,$url) {
         $id = trim($id,':');
		if(!isset($this->broken[$id])) {
			$this->broken[$id] = array();
		}
		$this->broken[$id][] = $url;
	}		
    
    		function parse_dwfile($handle="",$id, $path) { 
           $in_code = false;
           $in_file = false;
           $lineno = 0;
		   while (!feof($handle)) {
               $lineno++;
				$buffer = fgets($handle);
                $this->write_debug($buffer);
                if($in_code) {
                    if(preg_match("#<\/code>#",$buffer)) {
                        $in_code = false;                        
                    }
                    else continue;
                }
                if($in_file) {
                    if(preg_match("#\<\/file>#",$buffer)) {
                        $in_file = false;
                    }
                    else continue;
                }                
                if(preg_match("#^\s*\<code.*?>#",$buffer)) {                 
                    $in_code=true;
                    continue;
                }
                  if(preg_match("#^\s*\<file.*?>#",$buffer)) {
                    $in_file=true;
                    continue;
                }
                 if(preg_match("#\<nowiki>#",$buffer)) {                                       
                       if(preg_match('#\<nowiki>.*?https?:\/\/.*?\<\/nowiki\>#', $buffer)) {
                          continue;
                       }
                }  
                 if(preg_match("#https?://\S+#",$buffer,$matches)) {                     
                 $this->do_check($matches[0],$lineno,$id);
                 }
              }
           }   
           function do_check($url, $lineno,$id = "") {
                //  if(preg_match("#https?://\S+#",$buffer,$matches)) {                        
                    $this->write_debug("matches-0: " .$url);
					list($url,$rest) = explode('|',$url);
                    if(strpos($url, '{{') !== false || strpos($url, '}}') !== false) {
                        if(preg_match("#\{\{https?://(.*?)\}\}#", $url,$submatches)) {
                            $url = $submatches[1];
                            $url = "submatches: $url";
                        }
                        else return "";
                    }                 
                    $url = trim($url,']');
					$status =   $this->link_check($url);
                    $this->write_debug("$status -- url: " .$url);
					if($status !="200" && $status !="300"  && $status != "301"  && $status != "0") {       
                       $link =$this->local_url($id,$url);  
                       $len = strlen($url);
                        if(strlen($url) > 1024)  {
                            $status = "414";                       
                        }  
                                               
						   $this->add_broken($id,$url);
                           $trunc = substr($url,0,512);  
                           if(strlen($trunc) > strlen($url)) {
                               $url .= '.  .  .';
                           }
					    	echo $status .":  $link:\n<br />";
						   usleep(300000);
						   echo '&nbsp;&nbsp;&nbsp;&nbsp;line' . " $lineno:&nbsp;$url" . "\n<br />";
						   usleep(300000);
					}
			//	} 		   
		}	
		function __parse_dwfile($handle="",$id, $path) { 
           $in_code = false;
           $in_file = false;
           $lineno = 0;
		   while (!feof($handle)) {
               $lineno++;
				$buffer = fgets($handle);
                $this->write_debug($buffer);
                if($in_code) {
                    if(preg_match("#<\/code>#",$buffer)) {
                        $in_code = false;                        
                    }
                    else continue;
                }
                if($in_file) {
                    if(preg_match("#\<\/file>#",$buffer)) {
                        $in_file = false;
                    }
                    else continue;
                }                
                if(preg_match("#^\s*\<code.*?>#",$buffer)) {                 
                    $in_code=true;
                    continue;
                }
                  if(preg_match("#^\s*\<file.*?>#",$buffer)) {
                    $in_file=true;
                    continue;
                }
                 if(preg_match("#\<nowiki>#",$buffer)) {                                       
                       if(preg_match('#\<nowiki>.*?https?:\/\/.*?\<\/nowiki\>#', $buffer)) {
                          continue;
                       }
                }                
			//	if(preg_match("#(\[\[)*(https?://.*?[^\]\[]+)(\]\])*#",$buffer,$matches)) {
                	//if(preg_match("#(\[\[)*(https?://\S+)(\]\])*\s*#",$buffer,$matches)) {
                  if(preg_match("#https?://\S+#",$buffer,$matches)) {                        
                    $this->write_debug("matches-0: " .$matches[0]);
					list($url,$rest) = explode('|',$matches[0]);
                    if(strpos($url, '{{') !== false || strpos($url, '}}') !== false) {
                        if(preg_match("#\{\{https?://(.*?)\}\}#", $url,$submatches)) {
                            $url = $submatches[1];
                            $url = "submatches: $url";
                        }
                        else return "";
                    }                 
                    $url = trim($url,']');
					$status =   $this->link_check($url);
                    $this->write_debug("$status -- url: " .$url);
					if($status !="200" && $status !="300"  && $status != "301"  && $status != "0") {       
                       $link =$this->local_url($id,$url);  
                       $len = strlen($url);
                        if(strlen($url) > 1024)  {
                            $status = "414";                       
                        }  
                                               
						   $this->add_broken($id,$url);
                           $trunc = substr($url,0,512);  
                           if(strlen($trunc) > strlen($url)) {
                               $url .= '.  .  .';
                           }
					    	echo $status .":  $link:\n<br />";
						   usleep(300000);
						   echo '&nbsp;&nbsp;&nbsp;&nbsp;line' . " $lineno:&nbsp;$url" . "\n<br />";
						   usleep(300000);
					}
				}
		   }
		}	
			
		  function link_check($url) {  
			 $url = trim($url, ' "\'' );
			$url=html_entity_decode($url);
			$ch = curl_init($url);
			// curl --remote-name --time-cond cacert.pem https://curl.haxx.se/ca/cacert.pem
	        if($this->getConf('ca_required')) {			
                curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/ca/cacert.pem");
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
	        }     
			curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 5); 
			curl_setopt($ch,CURLOPT_TIMEOUT,15);
			$output = curl_exec($ch);
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_errno = curl_errno($ch);
			 if($curl_errno && $curl_errno !=3) {
				return "Curl Erro: " .curl_errno($ch) .  "--" . curl_error($ch);			   
		   }
			curl_close($ch);
			return trim("$httpcode");   
		 } 
		 
     /*https://stackoverflow.com/questions/1281140/run-process-with-realtime-output-in-php/5956708#5956708 */    
	function disable_ob() {
        // Turn off output buffering
        ini_set('output_buffering', 'off');
        // Turn off PHP output compression
     //   ini_set('zlib.output_compression', false);
        // Implicitly flush the buffer(s)
        ini_set('implicit_flush', true);
        ob_implicit_flush(true);
        // Clear, and turn off output buffering
        while (ob_get_level() > 0) {
            // Get the curent level
            $level = ob_get_level();
            // End the buffering
            ob_end_clean();
            // If the current level has not changed, abort
            if (ob_get_level() == $level) break;
        }
        // Disable apache output buffering/compression
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
            apache_setenv('dont-vary', '1');
        }
	}
		/* http://php.net/manual/en/function.scandir.php#80057 */
	function scanDirectories($rootDir, $allData=array()) {
		// set filenames invisible if you want
		$invisibleFileNames = array(".", "..", ".htaccess", ".htpasswd");
		// run through content of root directory
		$dirContent = scandir($rootDir);
		foreach($dirContent as $key => $content) {
			// filter all files not accessible
			$path = $rootDir.'/'.$content;
		 //   echo "$content\n<br />";	
           if(!is_dir($path)) { 		 
		       $ext =  pathinfo ( $path,PATHINFO_EXTENSION);
			   if($ext !='txt') continue;
		   }   
			if(!in_array($content, $invisibleFileNames) ) {		
				// if content is file & readable, add to array
				if(is_file($path) && is_readable($path)) {
					// save file name with path
					$ns = preg_replace('#' . preg_quote($this->wikiRoot) . '#', "", $path);  
					$ns = str_replace(array('/','\\\\','.txt'), array(':',':'), $ns);
					$allData[] = array('path'=>$path,'file'=>$content, 'id'=>$ns);
				// if content is a directory and readable, add path and name
				}elseif(is_dir($path) && is_readable($path)) {
					// recursive callback to open new directory
					$allData = $this->scanDirectories($path, $allData);
				}
			}
		}
		return $allData;
	}
	
	function downloadPem() {
    @set_time_limit(60);         
    $SavePath = DOKU_INC .  'lib/plugins/xtern/ca/cacert.pem';
    $url = "https://curl.haxx.se/ca/cacert.pem";
     io_makeFileDir($SavePath);
    $http = new DokuHTTPClient();
    $http->max_bodysize = 32777216;
    $http->timeout = 120; 
    $http->keep_alive = false; 

    $data = $http->get($url);
    if(!$data) { 
        $this->say('download failed',  $url);
        return;
      }  

    $fp = @fopen($SavePath,'wb');	 
     if($fp === false) { 
           $this->say('write_fail',  $SavePath);
           return;
      }
      if(!fwrite($fp,$data)) {
         $this->say('write_fail',  $SavePath); 
         return;
      }
      fclose($fp); 
     $this->say('file_saved',   $SavePath);
 
}

  function say(){
        $args = func_get_args();	
        echo vsprintf("%s:  %s\n",$args);
        ob_flush();
    }

    function write_debug($data) {   
      if(!$this->debug_handle) return;  
      fwrite($this->debug_handle, "$data\n");
 //     fclose($handle);

}


}