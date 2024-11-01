var file = '';
function partner_show_form(link) {
    jQuery(document).ready(function($) {
        $('#partner-requestForm').slideToggle("slow");
        file = link;
    });
}

jQuery(document).ready(function($) {

    $('#submit').click(function() {

        $('#waiting').show(500);
        $('#partner-requestForm').hide(0);
        $('#message').hide(0);

        $.ajax({
            type : 'POST',
            url : file,
            dataType : 'json',
            data: {
                image_link : $('#image_link').val(),
                url	  : $('#url').val(),
                description	  : $('#description').val(),
                name  : $('#name').val()
            },
            success : function(data){
                $('#waiting').hide(500);
                $('#message').removeClass().addClass((data.error === true) ? 'error' : 'success')
                .text(data.msg).show(500);
                if (data.error === true)
                    $('#partner-requestForm').show(500);
            },
            error : function(XMLHttpRequest, textStatus, errorThrown) {
                $('#waiting').hide(500);
                $('#message').removeClass().addClass('error')
                .text(XMLHttpRequest).show(500);
                $('#partner-requestForm').show(500);
            }
        });

        return false;
    });
});