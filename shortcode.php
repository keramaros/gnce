<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('1nce', function () {
    $default_text = __("This is your example text returned from the WordPress backend.", 'gnce-1nce-products');
    $translated_text = $default_text;

    ob_start(); ?>
    <div class="gnce-search-container">
        <h3 class="gnce-search-title"><?php _e('Check SIM Status & Quota', 'gnce-1nce-products'); ?></h3>
        <div class="search-input-wrapper">
            <input type="text" id="api-search-input" autofocus="true"
                   placeholder="<?php echo esc_attr__('Enter ICCID', 'gnce-1nce-products'); ?>"
                   class="input-text">
            <button id="api-search-submit" class="button alt">
                <span class="button-text"><?php _e('Search', 'gnce-1nce-products'); ?></span>
            </button>
        </div>
        <img src="/wp-admin/images/spinner-2x.gif" class="api-spinner" alt="Loading..."/>
        <div id="api-search-results"></div>
    </div>

    <script>
        var current_site_lang = '<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en'; ?>'
        jQuery(document).ready(function ($) {
            $("#api-search-submit").on("click", function (e) {
                e.preventDefault()

                const query = $("#api-search-input").val()
                const $results = $("#api-search-results")

                $.ajax({
                    url: "/wp-admin/admin-ajax.php",
                    type: "POST",
                    data: {
                        action: "gnce_handle_1nce_search",
                        query: query,
                        lang: current_site_lang
                    },
                    beforeSend: function () {
                        $(".api-spinner").show()
                        $results.html("")
                    },
                    success: function (response) {
                        if (response.success) {
                            $results.html(response.data)
                        } else {
                            $results.html('<div class="gnce-error">' + response.data + '</div>')
                            console.error("1nce:", response.data)
                        }
                    },
                    complete: function () {
                        $(".api-spinner").hide()
                    }
                })
            })
        })
    </script>
    <?php
    return ob_get_clean();
});
