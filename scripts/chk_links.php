<?php
if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../');
require_once (DOKU_INC . 'inc/utf8.php');
require_once (DOKU_INC . 'inc/pageutils.php');
require_once (DOKU_INC . 'inc/io.php');
require_once (DOKU_INC . 'conf/dokuwiki.php');
global  $wikiRoot;
$dir =realpath (DOKU_INC. 'data/pages');
$wikiRoot = $dir;
echo "$dir\n"; 
$site = scanDirectories($dir);
//print_r($site);

foreach($site AS $entry=>$data) {
	  $handle = fopen($data['path'], "r");             
     if ($handle) {	
        parse_dwfile($handle,$data['id'],$data['path']);
		fclose($handle);
     }
}

function parse_dwfile($handle="",$id, $path) { 
   while (!feof($handle)) {
        $buffer = fgets($handle);
	    if(preg_match("#\[\[(https?://.*?)\]\]#",$buffer,$matches)) {
		//	echo $matches[0] ."\n";
			list($url,$rest) = explode('|',$matches[1]);
			$status =   link_check($url);
			if($status !="200" && $status !="300"  && $status != "301") {
				echo $status .":  $id:\n\t";
				echo  $url . "\n";
			}
        }
   }
}

	 function link_check($url) {  
         $url = trim($url, ' "\'' );
        $url=html_entity_decode($url);
        $ch = curl_init($url);
        // curl --remote-name --time-cond cacert.pem https://curl.haxx.se/ca/cacert.pem
        //curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/certs/cacert.pem");
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);        
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5); 
		curl_setopt($ch,CURLOPT_TIMEOUT,10);
		$output = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if(curl_errno($ch)){
			return "500:  " . curl_error($ch);
           // msg( 'Request Error:' . curl_error($ch));
       }
 		curl_close($ch);
		return trim("$httpcode");   
	 } 
/* http://php.net/manual/en/function.scandir.php#80057 */
function scanDirectories($rootDir, $allData=array()) {
global  $wikiRoot;
    // set filenames invisible if you want
    $invisibleFileNames = array(".", "..", ".htaccess", ".htpasswd");
    // run through content of root directory
    $dirContent = scandir($rootDir);
    foreach($dirContent as $key => $content) {
        // filter all files not accessible
        $path = $rootDir.'/'.$content;
     //   echo "$content\n";
        if(!in_array($content, $invisibleFileNames)) {
            // if content is file & readable, add to array
            if(is_file($path) && is_readable($path)) {
                // save file name with path
                $ns = preg_replace('#' . preg_quote($wikiRoot) . '#', "", $path);  
				$ns = str_replace(array('/','\\\\','.txt'), array(':',':'), $ns);
                $allData[] = array('path'=>$path,'file'=>$content, 'id'=>$ns);
            // if content is a directory and readable, add path and name
            }elseif(is_dir($path) && is_readable($path)) {
                // recursive callback to open new directory
                $allData = scanDirectories($path, $allData);
            }
        }
    }
    return $allData;
}
?>
