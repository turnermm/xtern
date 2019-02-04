 jQuery( document ).ready(function() { 	
      var in_admin = 0;
      if(window.location.search.match(/do=admin/)) {
		  in_admin =1;
	  }  
	  jQuery("#dokuwiki__content a" ).each (function( index ) { 
	 if(in_admin) return;
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
		 
		jQuery.when(request).done(function( data ,status) {		         
			if(data =="200" || data == '301' || data == '302') {		                      
              lnk.removeClass(_class).addClass( "xtern_xtrn" );             
			}
			else if( data.match(/^40\d/)  &&  data !='404') {	       
			    lnk.attr('title', LANG.plugins.xtern.restricted);
				lnk.removeClass(_class).addClass( "xtern_noaccess" );
			}	
			else {              
                if(data == "NOCURL") return;           
                 lnk.removeClass(_class).addClass( "xtern_broken" );   
			}
		});
  });	  
 });