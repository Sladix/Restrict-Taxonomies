jQuery(document).ready(function($) {
	
	$( '.select-all' ).click( function( e ) {
		e.preventDefault();
		
		items = $( this ).closest( 'div' ).find('.tabs-panel-active input[type="checkbox"]:visible');
		
		if ( items.length === items.filter( ':checked' ).length )
			items.removeAttr( 'checked' );
		else
			items.prop( 'checked', true );
	});
	
});
