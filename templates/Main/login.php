<div class="box">
    <div class="content">
        <div class="form" id="lform">
            <h2>Panel</h2>
            <div class="box-user" data-type="user" data-ic="fa-user">
                <p class="text"></p>
                <input class="user" type="text" name="user" placeholder="User" value="<?= $input ?>">
            </div>
            <div class="box-passwd" data-type="passwd" data-ic="fa-lock">
                <p class="text"></p>
                <input class="psswd" type="password" name="passwd" placeholder="Password" value="<?= $input_p ?>">
                <!-- <a onclick="recover()">¿Olvidaste tu contraseña?</a> -->
            </div>
            <p class="error"></p>
        </div>
    </div>

    <button class="g-recaptcha" 
        data-sitekey="6LcCZ5saAAAAAMlLS2_khYtm9081aL_LPcxO7cBm" 
        data-callback='enter' 
        data-action='submit'>Log In</button>
</div>

<script type="text/javascript">

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name=csrf_token]').attr('content') } });

    $('input').keypress(function (e) {
        if (e.keyCode === 13) {
            if ($(e.target).hasClass('user')) $('.psswd').focus();
            if ($(e.target).hasClass('psswd')) enter();
        }
    })

    function loading(type){
        $('input.user').prop('disabled', type);
        $('button').prop('disabled', type);
        $('button').html(type == true? 'Loading...' : 'Enter');
    }

    function message_error(success, message){
        if(!message) return;
        if(success == true) return;

        var msg = '';
        if(typeof message == 'object' && message.length == 1){
            msg = message[0];
        }else if(typeof message == 'object'  && message.length > 0){
            msg = '<ul><li>'+message.join('</li><li>')+'</li></ul>';
        }else{
            msg = message;
        }

        $('.box').addClass('box-error');
        $('p.error').html(msg);
    }

    function enter(token){
        
        loading(true);

        $('.box').removeClass('box-error');

        var params = {
	        	user: $('input.user').val(),
	        	passwd: $('input.psswd').val(),
                token: token
	        };

		$.ajax({
			url: './u/?action=login',
		    data: params, method: 'POST',
		    success: function( response ) {
		    	
	            if(response.success == true){
                    window.location = './';
                    return;
	            }else{
	                loading(false);
	            }
	            message_error(response.success, response.messages);
	        },
		    error: function( response ) {
		    	console.log('error',response);
	            loading(false);
	            message_error(false, 'Conection error.');
	        }
		});
    }
</script>