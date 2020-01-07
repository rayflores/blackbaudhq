jQuery(document).ready(function ($) {
    
    $('.go').on('click', function(){
        $('.LoaderBalls1').css('display','flex');
        $(this).hide();
        $('.enter_creds').hide();
        var dbid = $( '.bbhq_dbid' ).val();
        var apikey = $( '.bbhq_apikey' ).val();
        var data = {
            'action' : 'login_bbhq',
            'dbid' : dbid,
            'apikey' : apikey,
        };
        $.ajax({
            url: ajaxurl,
            type: "post",
            data: data,
            success:function(data) {
                // This outputs the result of the ajax request
                if ( data.results == 'success') {
                    $('.LoaderBalls1').css('display', 'none');
                    $('.loggedin').show();
                    $('.next_step_1').show();
                }
            },
            error: function(errorThrown){
                console.log(errorThrown);
            }
        });
    });
    
    $('.getusers').on( 'click', function() {
        $('.LoaderBalls2').css('display','flex');
        $(this).hide();
        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'get_bbhq_users',
            },
            success:function(response) {
                console.log(response)
                // This outputs the result of the ajax request
                if ( response.success ) {
                    $('.LoaderBalls2').css('display', 'none');
                    $('.next_step_2').show();
                }
            },
            error: function(errorThrown){
                console.log(errorThrown);
            }
        });
    });
});