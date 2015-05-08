var loaded = false;

// ajax to 
jQuery(document).ready(function($) {

	// binds the change-handler:
	$('#prcnvs_submit_url').change(function(){
		// sets the 'disabled' property to false (if the 'this' is checked, true if not):
		$('.prcnvs_assign_radio').prop('disabled', !this.checked);
	// triggers the change event (so the disabled property is set on page-load:
	}).change();
	

	//when this ID'ed element is clicked
			if(loaded === false) {
				$('#prcnvs_wait').show(); //show loading image
			
				data = {
					//set up post variables from PHP
					action: 'prcnvs_get_assignments'
				};
		
				//point to WP's Ajax URL via ajaxurl
				$.post(ajaxurl, data, function(response) {
					$('#prcnvs_assign_list').html(response);
					$('#prcnvs_wait').hide();
					$('#prcnvs_submit_url').prop('disabled', false);
				});
			
				loaded = true; // set loaded to true so we don't run again
				}
			return false;
});