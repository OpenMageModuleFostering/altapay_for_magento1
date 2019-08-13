Altapay = {
	deleteToken : function(element) {
		url = '/customer/token/deleteToken';
		token_id = jQuery(element).data('token-id');
		data = {token_id:token_id};
		console.log(data);
		jQuery.ajax({
			type:'POST',
			url:url,
			data:data,
			beforeSend:function(){
				jQuery("#token-custom-name-status-"+token_id).addClass('updating');
				var tr = jQuery(element).closest('tr');
				jQuery('button.token-delete',tr).attr('disabled',true);
				jQuery('input.token-custom-name',tr).attr('disabled',true);				
			}	
		}).done(function(data){
			if(data.status == 'deleted') {
				jQuery(element).closest('tr').remove();
			}
		});
	},
	updatePrimaryToken : function(element) {
		url = '/customer/token/updatePrimaryToken';
		token_id = element.value;
		data = {token_id:token_id};
		console.log(data);
		jQuery.ajax({
			type:'POST',
			url:url,
			data:data,
			beforeSend:function(){
				jQuery("#token-custom-name-status-"+token_id).addClass('updating');
				var tr = jQuery(element).closest('tr');
				jQuery('button.token-delete',tr).attr('disabled',true);
				jQuery('input.token-custom-name',tr).attr('disabled',true);	
				jQuery('#my-tokens-table input.radio[primary-token]').attr('disabled',true);				
			}	
		}).done(function(data){
			element.disabled = false;				
			if(data.status == 'ok' || data.status == 'updated') {
				jQuery("#token-custom-name-status-"+token_id).addClass('updated');	
				setInterval(function() {
					jQuery("#token-custom-name-status-"+token_id).removeClass('updated');
				},2000);
			} else if(data.status == 'error') {
				jQuery("#token-custom-name-status-"+token_id).addClass('error');
				setInterval(function() {
					jQuery("#token-custom-name-status-"+token_id).removeClass('error');
				},2000);
			}
		}).always(function() {
			jQuery("#token-custom-name-status-"+token_id).removeClass('updating');
			var tr = jQuery(element).closest('tr');
			jQuery('button.token-delete',tr).attr('disabled',false);
			jQuery('input.token-custom-name',tr).attr('disabled',false);	
			jQuery('#my-tokens-table input.radio[primary-token]').attr('disabled',false);
		}).error(function() {
			jQuery("#token-custom-name-status-"+token_id).addClass('error');
			setInterval(function() {
				jQuery("#token-custom-name-status-"+token_id).removeClass('error');
			},2000);	
		});
	},
	updateCustomName : function(element) {
		if(jQuery(element).data('token-custom-name') != element.value) {
			element.disabled = true;
			var url = '/customer/token/updateCustomName';
			token_id = jQuery(element).data('token-id');
			var data = {token_id:token_id,custom_name:element.value};
				
			jQuery.ajax({
				type:'POST',
				url:url,
				data:data,
				beforeSend:function(){
					jQuery("#token-custom-name-status-"+token_id).addClass('updating');
				}	
			}).done(function(data){
				element.disabled = false;				
				if(data.status == 'ok' || data.status == 'updated') {
					jQuery("#token-custom-name-status-"+token_id).addClass('updated');	
					setInterval(function() {
						jQuery("#token-custom-name-status-"+token_id).removeClass('updated');
					},2000);
				} else if(data.status == 'error') {
					jQuery("#token-custom-name-status-"+token_id).addClass('error');
					setInterval(function() {
						jQuery("#token-custom-name-status-"+token_id).removeClass('error');
					},2000);
				}

			}).always(function() {
				jQuery("#token-custom-name-status-"+token_id).removeClass('updating');	
			}).error(function() {
				jQuery("#token-custom-name-status-"+token_id).addClass('error');
				setInterval(function() {
					jQuery("#token-custom-name-status-"+token_id).removeClass('error');
				},2000);	
			});
		}
	}
}