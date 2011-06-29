(function ($, SN) {
    var PokeConfirmation = function(form) {
        var submit_i = form.find('.submit');

        var submit = submit_i.clone();

        form.show();

        submit
            .addClass('submit_dialogbox')
            .removeClass('submit');
        form.append(submit);

        submit_i.hide();

        form
            .addClass('dialogbox')
            .append('<button class="close">&#215;</button>')

        form.find('button.close').click(function(){
            $(this).remove();

            form.removeClass('dialogbox')

            form.find('.submit_dialogbox').remove();
            form.find('.submit').show();
            form.hide()
            return false;
        });
    };

    $('.entity_poke .poke_button').live('click', function(e) {
        e.preventDefault();

        PokeConfirmation($(this).parent('.entity_poke')
            .eq(0).find('.form_poke'));
        return false;
    });
}) (jQuery, SN);
