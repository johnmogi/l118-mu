<?php
/**
 * Core helper functions for MU Plugins
 */

defined('ABSPATH') || exit;

if (!function_exists('project_mu_log')) {
    /**
     * Simple logging function for debugging
     * 
     * @param mixed $message The message to log
     * @return void
     */
    function project_mu_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }
}
