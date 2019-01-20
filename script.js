 jQuery( document ).ready(function() { 	
	  jQuery("#dokuwiki__content a" ).each (function( index ) { 
	  var target = jQuery( this ).attr('target');
	  if(!target && target != 'extern') return;
      
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
		 
		request.done(function( data ,status) {		     
			if(data =="200" || data == '301' || data == '301') {		              
                  prev.removeClass( "xtern__xtern" ).addClass( "xtern_xtrn" );   
			}
			else {
                if(data == "NOCURL") return;           
                prev.removeClass( "xtern__xtern" ).addClass( "xtern_broken" );   
			}
		});
  });	  
 });