<?php

    class Silent_Plugin_Installer_Skin extends WP_Upgrader_Skin {
        var $api;
        var $type;
        var $errors = [];

        function __construct($args = array()) {
            $defaults = array( 'type' => 'web', 'url' => '', 'plugin' => '', 'nonce' => '', 'title' => '' );
            $args = wp_parse_args($args, $defaults);

            $this->type = $args['type'];
            $this->api = isset($args['api']) ? $args['api'] : array();

            parent::__construct($args);
        }

        // let's not print anything, yeah?
        function header() {}
        function footer() {}
        function feedback($string) {}
        function before() {}
        function after() {}

        function error($errors) {
            error_log(print_r($errors, true));
            if ( is_string($errors) ) {
                $this->errors[] = $errors;
            } elseif ( is_wp_error($errors) && $errors->get_error_code() ) {
                foreach ( $errors->get_error_messages() as $message ) {
                    $this->errors[] = $message;
                }
            }
        }
    }

?>