#!/usr/bin/php
<?php  
        $secret_key = $argv[1] . '{{ console_secret_key }}';
        $ep_api_key = md5( $secret_key );
        echo "define('EP_API_KEY', '$ep_api_key');\n";
?>
