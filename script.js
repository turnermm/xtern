 jQuery( document ).ready(function() { 	
      jQuery("input.xtern_info_but").click(function() {		
             jQuery("div#xtern_info" ).toggle();
			var current_val =  jQuery("input.xtern_info_but").attr('value');
			if(current_val ==  LANG.plugins.xtern.info_show)  {
				   jQuery("input.xtern_info_but").attr('value',LANG.plugins.xtern.info_close);
			}
			else {
				 jQuery("input.xtern_info_but").attr('value',LANG.plugins.xtern.info_show);
			}
        });  
		
      var in_admin = 0;
      if(window.location.search.match(/do=admin/)) {
		  in_admin =1;
	  }  
      
      var selector = "#dokuwiki__content a";  //default
     if(JSINFO && JSINFO['xtern_selector'])  {      
        selector = JSINFO['xtern_selector'] ;     
     }

	  jQuery(selector).each (function( index ) { 
	 if(in_admin) return;
     if(JSINFO && JSINFO['xtern_disable']) return;
     if(JSINFO && JSINFO['xtern_skip']) return;
     var _class = jQuery(this).attr('class');
     if(typeof _class == 'undefined') return;    
     if(!_class.match(/extern/)) return;
     var lnk = jQuery( this );
      var prev = jQuery( this ).prev();    
	   var _url  = jQuery( this ).attr('href');
	  _url = encodeURI(_url);
	  
		var request = jQuery.ajax({
             url: _url,
             url: DOKU_BASE + 'lib/exe/ajax.php',
             data: { 
                call: 'extern_url',
                url:  _url
             },                    
		    dataType: "html"
		});
		 
		jQuery.when(request).done(function( data,status) {
			if(data =="200" || data == '301' || data == '302') {		                      
              lnk.removeClass(_class).addClass( "xtern_xtrn" );             
			}
			else if( (data.match(/^4\d\d/))) {
                var title;  
                switch (data) {
                     case "400":
                         title = '400: Bad Request';
                         break;
                    case "401":
                         title = '401: Unauthorized'; 
                         break;             
                    case "402":
                         title = '402: Payment Required';
                         break;
                    case "403":
                         title = '403: Forbidden';
                         break;
                    case "404":
                         title = '404:  Not Found';
                         break;
                    case "405":
                         title = '405: Method Not Allowed';
                         break;
                    case "406":
                         title = '406: Not Acceptable';
                         break;
                    case "407":
                         title = '407: Proxy Authentication Required (RFC 7235)';
                         break;
                    case "408":
                        title = '408: Request Timeout';
                         break;
                    case "495":
                         title = '495: SSL Certificate Error';
                         break;                     
                    default:
                         title=LANG.plugins.xtern.restricted;
                         break;
                }
			    lnk.attr('title', title);
				lnk.removeClass(_class).addClass( "xtern_noaccess" );
			}	
			else {          
                if(data == "NOCURL") return;
                if(data == "PERCENT") {
                    lnk.attr('title', 'Unable to process url');
                    lnk.removeClass(_class).addClass( "xtern_noaccess" )
                    return;
                }                           
                 lnk.removeClass(_class).addClass( "xtern_broken" );   
			}
		});
  });	  
 });