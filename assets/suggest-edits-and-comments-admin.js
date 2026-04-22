jQuery(document).ready(function($) {
    $('.my-color-field').wpColorPicker();

    $('#suggest-edits-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        $('#suggest-edits-save-spinner').addClass('is-active');
        $('#suggest-edits-save-message').text('').css('color', '');

        var excludedRoles = [];
        $('input[name="excluded_roles[]"]:checked').each(function() {
            excludedRoles.push($(this).val());
        });

        var allowedPostTypes = [];
        $('input[name="allowed_post_types[]"]:checked').each(function() {
            allowedPostTypes.push($(this).val());
        });

        var data = {
            action: 'seaco_save_settings',
            nonce: seaco_admin_vars.nonce,
            excluded_roles: excludedRoles,
            allowed_post_types: allowedPostTypes,
            target_selectors: $('#target_selectors').val(),
            recaptcha_enable: $('#recaptcha_enable').is(':checked') ? 1 : 0,
            recaptcha_site_key: $('#recaptcha_site_key').val(),
            recaptcha_secret_key: $('#recaptcha_secret_key').val(),
            max_text_length: $('#max_text_length').val(),
            daily_limit: $('#daily_limit').val(),
            quote_border_color: $('#quote_border_color').val(),
            quote_text_color: $('#quote_text_color').val(),
            tooltip_btn_text: $('#tooltip_btn_text').val(),
            tooltip_btn_color: $('#tooltip_btn_color').val(),
            tooltip_btn_hover_color: $('#tooltip_btn_hover_color').val(),
            submit_btn_text: $('#submit_btn_text').val(),
            submit_btn_color: $('#submit_btn_color').val(),
            submit_btn_hover_color: $('#submit_btn_hover_color').val(),
            uninstall_delete_comments: $('#uninstall_delete_comments').is(':checked') ? 1 : 0,
            uninstall_delete_settings: $('#uninstall_delete_settings').is(':checked') ? 1 : 0
        };

        $.post(seaco_admin_vars.ajax_url, data, function(response) { // Lấy url từ biến localize
            $('#suggest-edits-save-spinner').removeClass('is-active');
            if(response.success) {
                $('#suggest-edits-save-message').css('color', '#00a32a').text(response.data);
            } else {
                $('#suggest-edits-save-message').css('color', '#d63638').text('Error saving settings.');
            }
        });
    });
});