 jQuery( document ).ready(function() { 	
	  jQuery("#dokuwiki__content a" ).each (function( index ) { 
	  var target = jQuery( this ).attr('target');
	  if(!target && target != 'extern') return;
	  var lnk =  jQuery( this );
      var current = this; 
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
		 
		request.done(function( data ,status) {		
            jQuery(current).siblings().remove();
			if(data =="200") {			          
		    	lnk.prepend( '<span class="xtern_xtrn"></span> ' );
			}
			else {
				lnk.prepend( '<span class="xtern_broken"></span> ');
			}
		});
  });	  
 });