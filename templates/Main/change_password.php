<div class="box">
    <h1>Plataforma Forestal Alfa</h1>
    <div class="content">
        <!-- <div id="ic" class="fa fa-user"></div> -->
        <div class="form">
            <h2>Establecer Nueva Contraseña</h2>
            <div class="box-user" data-type="user" data-ic="fa-user">
                <p class="text"></p>
                <input class="passwd1" type="password" name="passwd1" placeholder="Nueva Contraseñas" value="<?= $input ?>">
            </div>
            <div class="box-passwd" data-type="passwd" data-ic="fa-lock">
                <p class="text"></p>
                <input class="passwd2" type="password" name="passwd2" placeholder="Confirmar Contraseña" value="<?= $input ?>">
                <!-- <a onclick="recover()">¿Olvidaste tu contraseña?</a> -->
            </div>
            <p class="error"></p>
        </div>
    </div>
	<button type="button" class="btn btn-default btn-login" onclick="cambiar()">Cambiar</button>
</div>

<script type="text/javascript">
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name=csrf_token]').attr('content') } });

    function loading(type){
        $('input.user').prop('disabled', type);
        $('button').prop('disabled', type);
        $('button').html(type == true? 'Cargando...' : 'Entrar');
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

    function cambiar(){
        loading(true);

        $('.box').removeClass('box-error');

        var params = {
	        	action: 'upasswd',
	        	passwd1: $('input.passwd1').val(),
	        	passwd2: $('input.passwd2').val()
	        };

		$.ajax({
			url: './usuarios/',
		    data: params, method: 'POST',
		    success: function( response ) {
	            if(response.success == true){
                    window.location = './';
                    return;
	            }else{
	                loading(false);
	            }

	            message_error(response.success, response.message);
	        },
		    error: function( response ) {
		    	console.log('error',response);
	            loading(false);
	            message_error(false, 'Error en la Conexión.');
	        }
		});
    }
</script>