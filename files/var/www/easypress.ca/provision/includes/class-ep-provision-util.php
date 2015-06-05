<?php
/**
 * class-ep-provision-util.php
 *
 * easyPress provisioning utility class
 *
 */


class EP_Provision_Util {


    /**
     * Validate and sanitize POST parameters
     *
     */
    public function validate_post_parameters() {
        global $params;

        if ( isset( $_POST['email'] ) && !empty( $_POST['email'] ) ) {
            if ( $this->is_valid_email_address( $_POST['email'] ) ) {
                $params['email'] = $_POST['email'];
            } else {
                $params['status']['errors']++;
                $params['status']['messages'][] = 'Email address is invalid';
            }
        } else {
            $params['status']['errors']++;
            $params['status']['messages'][] = 'Email address is missing';
        }

        if ( isset( $_POST['location'] ) && !empty( $_POST['location'] ) ) {
            if ( $_POST['location'] == 'jester' ||
                 $_POST['location'] == 'us' ||
                 $_POST['location'] == 'equalit-test' ) {

                $params['location'] = $_POST['location'];
            }
        }

        if ( isset( $_POST['domain'] ) && !empty( $_POST['domain'] ) ) {
            $domain = $this->sanitize_domain( $_POST['domain'] );
            if ( $this->is_valid_domain( $domain ) ) {
                if ( $params['location'] == 'uk' ) {
                    $params['domain_cname'] = '.wp.pendeopress.com';
                }
                $params['domain'] = $domain;
                $params['domain_cname'] = $domain . $params['domain_cname'];
            } else {
                $params['status']['errors']++;
                $params['status']['messages'][] = 'Domain name is invalid: ' . $domain;
            }
        } else {
            $params['status']['errors']++;
            $params['status']['messages'][] = 'Domain name is missing';
        }

        if ( isset( $_POST['first_name'] ) && !empty( $_POST['first_name'] ) ) {
            $params['real_first_name'] =  htmlspecialchars($_POST['first_name'] );
            $params['first_name'] = $this->sanitize_name( $_POST['first_name'] ); 
        } else {
            $params['status']['errors']++;
            $params['status']['messages'][] = 'First name is missing';
        }

        if ( isset( $_POST['last_name'] ) && !empty( $_POST['last_name'] ) ) {
            $params['real_last_name'] = htmlspecialchars( $_POST['last_name'] );
            $params['last_name'] = $this->sanitize_name( $_POST['last_name'] ); 
        } else {
            $params['status']['errors']++;
            $params['status']['messages'][] = 'Last name is missing';
        }

        if ( isset( $_POST['multisite'] ) && ( $_POST['multisite'] == "subdomain" || $_POST['multisite'] == "subdirectory" ) ) {
            $params['multisite'] = $_POST['multisite']; 
        } else {
            $params['multisite'] = false;
        }

        if ( isset( $_POST['db_charset'] ) ) {
            $params['db_charset'] = $_POST['db_charset']; 
        }

        if ( isset( $_POST['db_collate'] ) ) {
            $params['db_collate'] = $_POST['db_collate']; 
        }

        if ( isset( $_POST['easydns_user'] ) && !empty( $_POST['easydns_user'] ) ) {
            $params['easydns_user'] = $_POST['easydns_user']; 
        }

        if ( isset( $_POST['bcc_email'] ) && !empty( $_POST['bcc_email'] ) && $this->is_valid_email_address( $_POST['bcc_email']) ) {
                $params['bcc_email'] = $_POST['bcc_email'];
        }

        if ( isset( $_POST['alert_email'] ) && !empty( $_POST['alert_email'] ) && $this->is_valid_email_address( $_POST['alert_email']) ) {
            $params['alert_email'] = $_POST['alert_email'];
        }

        if ( isset( $_POST['is_dev'] ) && !empty( $_POST['is_dev'] ) ) {
            $params['is_dev'] = $_POST['is_dev'];
        }

        if ( isset( $_POST['add_cname'] ) && !empty( $_POST['add_cname'] ) ) {
            $params['add_cname'] = $_POST['add_cname'];
        }
    }

    /**
     * Authenticate the request
     *
     */
    public function authenticate( $username, $password, $source ){
        global $params;
        if ( $username == 'equalit-test' ) {
            if ( hash('sha256', $password ) == 'ef4bc1f71c4eed44075d41af78a8e6df1c8fee30fd2077884b8d34063b711e44' ) {
                $params['location'] = 'equalit-test';
                $params['org_name'] = 'Equalit.ie Hosting';
                $params['org_email'] = 'support@equalit.ie';
                $params['org_twitter'] = '@equalitie';
            } else {
                $this->process_errors( 'Incorrect password.', true );
            }
        } else if ( $username == 'vmg') {
            if ( hash( 'sha256', $password ) != 'a21ebacd945e297ffbe849a95cfd7c3a7d9ac000ab45326f00c2821af0abd4e3' ) {
                $this->process_errors( 'Incorrect password.', true );
            }
        } else {
            $this->process_errors( 'Incorrect username or password.', true );
        }
    }
    /**
     * Process errors
     *
     * @param string $message is the error message to write to the log and/or screen.
     * @param boolean $exit_now will determine whether or not to terminate the process.
     * 
     */
    public function process_errors( $message, $exit_now = false ) {
        global $params;
        $params['status']['errors']++;
        $params['status']['messages'][] = $message;
        if ( $exit_now ) {
            header( 'Content-Type: application/json; charset=utf-8' );
            echo json_encode( $params['status'] );
            file_put_contents( EP_PROV_DIR . 'ep-provision.log',
                "\n" . date( DATE_RFC850 ) .
                ' (request ' . $params['request_id'] . ' failed): ' .
                serialize( $params ) . "\n", FILE_APPEND | LOCK_EX );
            exit;
        }
    }

    /**
     * Validate an email address.
     *
     * @param string $email is the email address.
     * @return boolean
     *
     */
    public function is_valid_email_address( $email ) {
        if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check for an existing domain.
     *
     * @param string $domain is the domain name of the site being installed.
     * @return boolean letting the caller know if the domain exists.
     *
     */
    public function domain_exists( $domain ) {
        $dir = '/etc/nginx/sites-available/';
        $exists = false;
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if ( $domain == $file )
                        $exists = true;
                }
            }
            closedir($dh);
        }
        return $exists;
    }

    /**
     * Validate a domain name.
     *
     * @param string $domain is the domain name of the site to install.
     * @return boolean
     *
     */
    public function is_valid_domain( $domain ) {
        $ctr = 0;
        $pieces = explode( '.', $domain );
        if ( ( $num_pieces = sizeof( $pieces ) ) < 2)
            return false;
        foreach( $pieces as $piece ) {
            $ctr++;
            if ( !preg_match( '/^[a-z\d][a-z\d-]{0,62}$/i', $piece ) || preg_match( '/-$/', $piece ) )
                return false;
        }
        return true;
    }

    /**
     * Sanitize the domain name.
     *
     * @param string $domain is the domain name of the site to install.
     * @return string $domain sanitized
     *
     */
    public function sanitize_domain( $domain ) {
        $domain = strtolower( $domain );
        $domain = preg_replace( '/^ /', '', $domain );
        $domain = preg_replace( '/ $/', '', $domain );
        $domain = preg_replace( '#^https?://#', '', $domain );
        return $domain;
    }


    /**
     * Specify the name of the database using the domain name.
     * 
     * Return everything but the top level domain and trim everything after
     * 60 chars and add a random string to the end. Replace all dots with
     * underscores.
     *
     * @param string $domain is the domain name of the site to install.
     *
     */
    public function give_the_db_a_name( $domain ) {
        global $params;
        $ctr = 0;
        $subdomain = '';
        $pieces = explode( '.', $domain );
        $num_pieces = sizeof( $pieces );
        foreach( $pieces as $piece ) {
            $ctr++;
            $subdomain .= $piece . '_';
            if ( $ctr == $num_pieces ) {
                $subdomain = substr( $subdomain, 0, 58 );
                $params['db_name'] = $subdomain . $this->random( 3 );
            }   
        }
    }

    /**
     * Strip non-ascii characters from first and last name
     *
     * @param string $name is part of the name.
     * @return string $s_name is the sanitized name.
     *
     */
    public function sanitize_name( $name ) {
        $char_swap = array( 'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A',
            'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I',
            'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ß'=>'ss',
            'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i',
            'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o',
            'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'Ğ'=>'G', 'İ'=>'I', 'Ş'=>'S', 'ğ'=>'g',
            'ı'=>'i', 'ş'=>'s', 'ü'=>'u', 'ă'=>'a', 'Ă'=>'A', 'ș'=>'s', 'Ș'=>'S', 'ț'=>'t', 'Ț'=>'T', 'ł'=>'l' );
        $s_name = strtr( $name, $char_swap );
        $s_name = preg_replace( '/[^a-zA-Z0-9]/', '', $s_name );
        return $s_name;
    }

    /**
     * Return a random string
     *
     * @param int $len is the length of the random string to generate.
     * @param bool $alphanum specifies if the random string should be alphanumeric
     * @return string containing the random string.
     */
    public function random( $len = 23, $alphanum = true ) {
        if ( $alphanum === true )
            $chars = '/[0-9A-Za-z]/';
        else
            $chars = '/[\x20-\x7E]/';
        // exclude single quotes, double quotes and backslash from random strings
        $excluded_chars = '/[\x22\x27\x5C]/';
        $rs = "";
        $f=fopen( $_SERVER['EP_DEV_RAND'], 'r' );
        if ( $f == FALSE ) {
            $f=fopen( '/dev/urandom', 'r' );
            if ( $f == FALSE )
                $this->process_errors( 'Could not open '  . $_SERVER['EP_DEV_RAND'], true );
        }
        for( $x = 0; $x < $len; $x++ ) {
            $c = fgetc($f);
            if ( preg_match($excluded_chars, $c ) )
                $x--;
            elseif ( preg_match( $chars, $c ) )
                $rs .= $c;
            else
                $x--;
        }
        fclose( $f );
        return $rs;
    }


    /**
     * Generate the keys and salts
     *
     * @return string $keysalts
     */
    public function generate_keysalts() {
        $keysalts =
            "define('AUTH_KEY',         '" . $this->random( 64, false ) . "');\n" .
            "define('SECURE_AUTH_KEY',  '" . $this->random( 64, false ) . "');\n" .
            "define('LOGGED_IN_KEY',    '" . $this->random( 64, false ) . "');\n" .
            "define('NONCE_KEY',        '" . $this->random( 64, false ) . "');\n" .
            "define('AUTH_SALT',        '" . $this->random( 64, false ) . "');\n" .
            "define('SECURE_AUTH_SALT', '" . $this->random( 64, false ) . "');\n" .
            "define('LOGGED_IN_SALT',   '" . $this->random( 64, false ) . "');\n" .
            "define('NONCE_SALT',       '" . $this->random( 64, false ) . "');";
        return $keysalts;
    }

    /**
     * Generate the passwords
     *
     */
    public function generate_credentials() {
        global $params;

        $this->give_the_db_a_name( $params['domain'] ); // Set the DB name and store it in $params['db_name'].
        $params['db_pass'] = $this->random();
        $params['db_user'] = substr( $params['last_name'], 0, 12 ) . '_' . $this->random( 3 );
        $params['db_prefix'] = $this->random( 8 ) . '_';
        $params['sftp_pass'] = $this->random();
        $params['wpadmin_pass'] = $this->random();

        // Default units for password expiry is in days but if we're testing make it minutes.
        $units = 'days';
        if ( $params['location'] == 'dev' )
            $units = 'minutes';
        $args  = '&time=45&units=' . $units . '&views=10&url_only=yes'; // Password pusher POST arguments.
        // fetch the password pusher URLs
        $response = $this->http_req( array( 'post_fields' => 'cred=' . $params['db_pass'] . $args ) );
        $params['pwp']['db'] = $response['body'];
        $response = $this->http_req( array( 'post_fields' =>'cred=' . $params['wpadmin_pass'] . $args ) );
        $params['pwp']['wp'] = $response['body'];
        $response = $this->http_req( array( 'post_fields' => 'cred=' . $params['sftp_pass'] . $args ) );
        $params['pwp']['sftp'] = $response['body'];
    }

    /**
     * Add a CNAME to the wp.easypress.ca zone via the easyDNS API.
     *
     */
    public function add_cname( $domain ) {
        global $params;
        $req = array ();

        $req['timeout'] = 60;
        $req['method'] = 'PUT';
        $req['host'] = $_SERVER['DNS_API_HOST'];
        $req['url'] = $_SERVER['DNS_API_URL'];
        $req['username'] = $_SERVER['DNS_API_USERNAME'];
        $req['password'] = $_SERVER['DNS_API_PASSWORD'];
        $data = array( 'host' => $domain, 'rdata' => $params['nodes'][$params['location']]['cname'] );
        $json_data = json_encode( $data );
        $req['post_fields'] = $json_data;
        $req['header'] = array( 'Content-Type: application/json; charset=utf-8', 'Content-Length: ' . strlen( $json_data ) );
        $response = $this->http_req( $req );
        error_log( var_export( $response, true ) );
    }


    /**
     * Create the tmp directory to hold the configuration files.
     *
     */
    public function create_config_dir() {
        if ( !mkdir( EP_TMP_DIR, 0700, true ) )
            $this->process_errors( 'Could not create config directory: ' . EP_TMP_DIR, true );
    }

    /**
     * Move the tmp directory into the pending state, ready to be processed by monit_ep_provision.sh.
     *
     */
    public function move_config_dir() {
        global $params;
        // retry if the directory already exists, loop, sleep, retry, 5 times then fail
        if ( !rename( EP_TMP_DIR, EP_PEND_DIR . $params['domain']  ) )
            $this->process_errors( 'Could not move config directory ' . EP_TMP_DIR . ' to ' . EP_PEND_DIR, true );
        if ( !rmdir( EP_TMP ) )
            $this->process_errors( 'Could not delete config directory: ' . EP_TMP, false );
    }

    /**
     * Create wp_config.php.
     *
     */
    public function create_wp_config() {
        global $params, $mustache;
        $ep_secret_key = $params['domain'] . $_SERVER['API_KEY_SECRET'];
        $params['ep_api_key'] = md5( $ep_secret_key );
        $keysalts = $this->generate_keysalts();
        $params['db_prefix'] = $this->random( 8 ) . "_";

        $tpl = $mustache->loadTemplate('wp-config.php');
        $out = $tpl->render( array(
            'dbhost'        => $params['db_host'],
            'dbname'        => $params['db_name'],
            'dbuser'        => $params['db_user'],
            'dbpass'        => $params['db_pass'],
            'dbprefix'      => $params['db_prefix'],
            'dbcharset'     => $params['db_charset'],
            'dbcollate'     => $params['db_collate'],
            'ep_api_key'    => $params['ep_api_key'],
            'keysalts'      => $keysalts,
        ) );

        $file = EP_TMP_DIR . '/wp-config.php';
        if ( !file_put_contents( $file, $out ) )
            $this->process_errors( "Could not create $file", true );
    }

    /**
     * Create the nginx virtual host config file.
     *
     */
    public function create_nginx_config() {
        global $params, $mustache;
        $multisite_config = '';
        $multisite_subdir_config = '';
        $lua_config_root = '';
        $lua_config_php = '';

        if ( $params['multisite'] !== false ) {
            $domain = $params['domain'];
            $domain_cname = $params['domain_cname'];
            $multisite_config = <<<EOT
    server_name *.$domain;
    server_name *.$domain_cname;

    server_name_in_redirect off;
EOT;
            if ( $params['multisite'] == 'subdirectory' ) {
                $multisite_subdir_config = <<<EOT
    include /etc/nginx/common_multisite_3.5.conf;
EOT;
            }
        }

        if ( $params['location'] != 'ca1') {
            $lua_config_root = <<<EOLUA
        access_by_lua_file /etc/nginx/lua_block_post_noreferrer.lua;
EOLUA;
        }

        if ( $params['location'] != 'ca1') {
            $lua_config_php = <<<EOLUA
        access_by_lua_file /etc/nginx/lua_block_wplogin_noreferrer.lua;
EOLUA;
        }

        $tpl = $mustache->loadTemplate('nginx-config');
        $out = $tpl->render( array(
            'domain'                    => $params['domain'],
            'domain_cname'              => $params['domain_cname'],
            'multisite_config'          => $multisite_config,
            'multisite_subdir_config'   => $multisite_subdir_config,
            'lua_config_root'           => $lua_config_root,
            'lua_config_php'            => $lua_config_php
        ) );
        $file = EP_TMP_DIR . '/' . $params['domain'] . '_ngx.conf';
        if ( !file_put_contents( $file, $out ) )
            $this->process_errors( "Could not create $file", true );
    }

    /**
     * Create ansible playbook
     *
     */
    public function create_ansible_playbook() {
        global $params, $mustache;
        $multisite_config = '';
        $switch_to_dev = '';
        $console = '';
        $domain = $params['domain'];
        $domain_cname = $params['domain_cname'];
        $from_email = 'support@boreal321.com (Boreal321 Hosting Support)';
        $subject = "Your WordPress site for $domain is ready!";

        if ( $params['multisite'] !== false ) {
            if ( $params['multisite'] == 'subdomain' ) {
                $multisite_config = <<<EOT
  - name: Convert WordPress installation to multisite subdomains
    command: chdir=/var/www/$domain/wordpress /usr/local/sbin/wp --allow-root core multisite-convert --subdomains

EOT;
            }
            if ( $params['multisite'] == 'subdirectory' ) {
                $multisite_config = <<<EOT
  - name: Convert WordPress installation to multisite subdirectories
    command: chdir=/var/www/$domain/wordpress /usr/local/sbin/wp --allow-root core multisite-convert

EOT;
            }

            // install multisite domain mapping plugin
            $multisite_config .= <<<EOT
  - name: Install domain mapping plugin
    command: chdir=/var/www/$domain/wordpress /usr/local/sbin/wp --allow-root plugin install wordpress-mu-domain-mapping --activate-network

  - stat: path=/var/www/$domain/wordpress/wp-content/sunrise.php
    register: sunrise_file

  - name: move sunrise file into wp-content directory
    command: /bin/mv /var/www/$domain/wordpress/wp-content/plugins/wordpress-mu-domain-mapping/sunrise.php /var/www/$domain/wordpress/wp-content
    when: sunrise_file.stat.exists == False

  - name: add surise constant to wp-config.php
    lineinfile: dest=/var/www/$domain/wordpress/wp-config.php state=present insertafter=EP_CACHE_OPTIONS line="define('SUNRISE', 'on');"

EOT;
        } // end of multisite config

        if ( $params['is_dev'] == 1 ) {
            $switch_to_dev = <<<EOD
  - name: update home DB option to be a dev site
    command: chdir=/var/www/$domain/wordpress /usr/local/sbin/wp --allow-root option set home http://$domain_cname

  - name: update siteurl DB option to be a dev site
    command: chdir=/var/www/$domain/wordpress /usr/local/sbin/wp --allow-root option set siteurl http://$domain_cname

EOD;
        }

        $console = <<<EOE
  - name: Install easyPress Console as must-use plugins for $domain
    command: /usr/local/sbin/ep_install_console.sh $domain creates=/var/www/$domain/wordpress/wp-content/mu-plugins/easypress.php
    ignore_errors: yes

EOE;

        // eQualit.ie settings
        if ( $params['location'] == 'equalit.ie' ) {
            $params['bcc_email'] = 'support@equalit.ie, vmg@boreal321.com';
            $from_email = 'support@equalit.ie (eQualit.ie Support)';
            $subject = "Your WordPress site for $domain is ready!";
        }

        // Get the name of the server that ansible will use to install the site.
        $server = $params['nodes'][$params['location']]['hostname'];
        $tpl = $mustache->loadTemplate('ep-provision.yml');
        $out = $tpl->render( array(
	        'prov_dir'          => EP_PROV_DIR,
            'server'            => $server,
            'domain'            => $params['domain'],
            'email'             => $params['email'],
            'first_name'        => $params['real_first_name'],
            'last_name'         => $params['real_last_name'],
            'wpadmin_user'      => $params['first_name'],
            'wpadmin_pass'      => $params['wpadmin_pass'],
            'wpadmin_email'     => $params['email'],
            'multisite_config'  => $multisite_config,
            'db_host'           => $params['db_host'],
            'db_name'           => $params['db_name'],
            'db_user'           => $params['db_user'],
            'db_pass'           => $params['db_pass'],
            'db_charset'        => $params['db_charset'],
            'db_collate'        => $params['db_collate'],
            'web_user'          => $params['web_user'],
            'web_group'         => $params['web_group'],
            'sftp_pass'         => $params['sftp_pass'],
            'sftp_user'         => $params['db_user'],
            'bcc_email'         => $params['bcc_email'],
            'alert_email'       => $params['alert_email'],
            'from_email'        => $from_email,
            'subject'           => $subject,
            'switch_to_dev'     => $switch_to_dev,
            'console'           => $console,
        ) );
        $file = EP_TMP_DIR . '/ep-provision.yml';
        if ( !file_put_contents( $file, $out ) )
            $this->process_errors( "Could not create $file", true );
    }

    /**
     * Create the welcome email message we send to the customer.
     *
     */
    public function create_welcome_email() {
        global $params, $mustache;
        $domain = $params['domain'];
        $welcome_email_template = 'ep-welcome-email.txt.mustache';

        // If the site is for development then send the development domain.
        if ( $params['is_dev'] == 1 ) {
            $domain = $params['domain_cname'];
        }

        // Use another welcome email template for eQualit.ie customers.
        if ( $params['location'] == 'eQualit.ie' ) {
            $welcome_email_template = 'eq-welcome-email.txt.mustache';
        }

        // Get the IP address of the server.
        $server_ip = $params['nodes'][$params['location']]['ip'];

        // Create the email from the template
        $tpl = $mustache->loadTemplate( $welcome_email_template );
        $out = $tpl->render( array(
            'domain'        => $domain,
            'email'         => $params['email'],
            'first_name'    => $params['real_first_name'],
            'last_name'     => $params['real_last_name'],
            'wp_admin'      => $params['first_name'],
            'db_name'       => $params['db_name'],
            'db_user'       => $params['db_user'],
            'sftp_server'   => $params['domain_cname'],
            'sftp_user'     => $params['db_user'],
            'pwp_wp'        => $params['pwp']['wp'],
            'pwp_db'        => $params['pwp']['db'],
            'pwp_sftp'      => $params['pwp']['sftp'],
            'server_ip'     => $server_ip,
            'org_name'      => $params['org_name'],
            'org_email'     => $params['org_email'],
            'org_twitter'   => $params['org_twitter']
        ) );

        // Save the parsed template to a file to be read by ansible process.
        $file = EP_TMP_DIR . '/ep-welcome-email.txt';
        if ( !file_put_contents( $file, $out ) )
            $this->process_errors( "Could not create $file", true );
    }

    /**
     * HTTP requests
     *
     * @param array $request should minimally contain the POST arguments.
     * @return array containing HTTP response code, the body of the response, elapsed time.
     */
    public function http_req( $request = array() ) {
        global $params;
        $host = 'provision.boreal321.com';
        if ( $params['location'] == 'equalit.ie' ) {
            $host = 'eqpress.equalit.ie';
        }
        $url = 'https://' . $host . '/pwpush/pwpusher_public/pw.php';
        $response = array();
        $defaults = array(
            'url'       => $url,
            'host'      => $host,
            'method'    => 'POST',
            'timeout'   => 30,
            'verbose'   => 0,
        );
        $request = array_merge( $defaults, $request );
        try {
            $mych = new CurlRequest;
            $mych->init( $request );
            $response = $mych->exec();
            if ( $response['curl_error'] ) throw new Exception( $response['curl_error'] );
            //if ( $response['http_code'] != '200' ) throw new Exception( "HTTP Code = " . $response['http_code'] . '\nBody: ' . $response['body'] );
            if ( NULL === $response['body'] ) throw new Exception( "Body of response is empty" );
        }
        catch ( Exception $e ) {
            $this->process_errors( "http_req error: " . $e->getMessage() . "\n" . print_r( $response, true ) );
        }
        //return array( 'code' => $response['http_code'], 'body' => $response['body'], 'etime' => $response['etime'] );
        //return $response['body'];
        return $response;
    }
}