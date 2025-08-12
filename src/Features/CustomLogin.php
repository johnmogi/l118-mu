<?php
/**
 * Custom Login Feature
 * 
 * Example of a feature class that handles custom login functionality.
 */

namespace Project\MU\Features;

defined('ABSPATH') || exit;

class CustomLogin
{
    /**
     * Initialize the feature
     */
    public function init()
    {
        // Add custom login styles
        add_action('login_head', [$this, 'add_login_styles']);
        
        // Modify login header URL
        add_filter('login_headerurl', [$this, 'custom_login_logo_url']);
        
        // Modify login header text
        add_filter('login_headertext', [$this, 'custom_login_logo_url_title']);
    }

    /**
     * Add custom styles to login page
     */
    public function add_login_styles()
    {
        ?>
        <style type="text/css">
            body.login {
                background: #f1f1f1;
            }
            #login h1 a, .login h1 a {
                background-image: url(<?php echo esc_url(wp_get_attachment_image_src(get_theme_mod('custom_logo'), 'full')[0] ?? ''); ?>);
                height: 65px;
                width: 320px;
                background-size: contain;
                background-repeat: no-repeat;
                padding-bottom: 30px;
            }
        </style>
        <?php
    }

    /**
     * Change the login logo URL
     */
    public function custom_login_logo_url()
    {
        return home_url();
    }

    /**
     * Change the login logo title
     */
    public function custom_login_logo_url_title()
    {
        return get_bloginfo('name');
    }
}
