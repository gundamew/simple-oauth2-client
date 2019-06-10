<?php

class Simple_Oauth2_Client {

	private $plugin_name;

	private $option_name;

	private $version;

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->option_name = str_replace( '-', '_', $plugin_name );
		$this->version = $version;

	}

	public function set_locale() {

		load_plugin_textdomain(
			$this->plugin_name,
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

	}

	public function plugin_add_options_page() {

		add_options_page(
			__( 'Simple Oauth2 Client', $this->plugin_name ),
			__( 'Simple Oauth2 Client', $this->plugin_name ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_options_page' )
		);

	}

	public function display_options_page() {

		  include_once plugin_dir_path( __FILE__ ) . 'templates/options-admin.php';

	}

	public function register_simple_oauth2_client_settings() {

		add_settings_section(
			$this->option_name . '_general',
			__( 'General', $this->plugin_name ),
			array( $this, $this->option_name . '_general_callback' ),
			$this->plugin_name
		);

		add_settings_field(
			$this->option_name . '_server_url',
			__( 'Server URL', $this->plugin_name ),
			array( $this, $this->option_name . '_server_url_callback' ),
			$this->plugin_name,
			$this->option_name . '_general',
			array( 'label_for' => $this->option_name . '_server_url' )
		);

		add_settings_field(
			$this->option_name . '_client_id',
			__( 'Client ID', $this->plugin_name ),
			array( $this, $this->option_name . '_client_id_callback' ),
			$this->plugin_name,
			$this->option_name . '_general',
			array( 'label_for' => $this->option_name . '_client_id' )
		);

		add_settings_field(
			$this->option_name . '_client_secret',
			__( 'Client Secret', $this->plugin_name ),
			array( $this, $this->option_name . '_client_secret_callback' ),
			$this->plugin_name,
			$this->option_name . '_general',
			array( 'label_for' => $this->option_name . '_client_secret' )
		);

	}

	public function simple_oauth2_client_general_callback() {

		include_once plugin_dir_path( __FILE__ ) . 'templates/general-section-admin.php';

	}

	public function simple_oauth2_client_server_url_callback() {

		$field_name = $this->option_name . '_server_url';
		$option_value = get_option( $this->option_name )[ $field_name ];

		include_once plugin_dir_path( __FILE__ ) . 'templates/server-url-field-admin.php';

	}

	public function simple_oauth2_client_client_id_callback() {

		$field_name = $this->option_name . '_client_id';
		$option_value = get_option( $this->option_name )[ $field_name ];

		include_once plugin_dir_path( __FILE__ ) . 'templates/client-id-field-admin.php';

	}

	public function simple_oauth2_client_client_secret_callback() {

		$field_name = $this->option_name . '_client_secret';
		$option_value = get_option( $this->option_name )[ $field_name ];

		include_once plugin_dir_path( __FILE__ ) . 'templates/client-secret-field-admin.php';

	}

	public function simple_oauth2_client_actions() {

		$filtered = array_filter( $_POST, function( $value, $key ) {
			return strpos( $key, $this->option_name ) === 0;
		}, ARRAY_FILTER_USE_BOTH );

		update_option( $this->option_name, $filtered );

		wp_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
		exit();

	}

	public function activate() {

		$this->create_table();

	}

	public function deactivate() {

		//delete_option( $this->option_name );

	}

	public function authenticate_with_sso( $user, $email, $password ) {

		if ( $user instanceof WP_User ) {
			return $user;
		}

		if ( empty( $email ) || empty( $password ) ) {
			if ( is_wp_error( $user ) ) {
				return $user;
			}

			$error = new WP_Error();

			if ( empty( $email ) ) {
				$error->add( 'empty_username', __( '<strong>ERROR</strong>: The email field is empty.' ) );
			}

			if ( empty( $password ) ) {
				$error->add( 'empty_password', __( '<strong>ERROR</strong>: The password field is empty.' ) );
			}

			return $error;
		}

		$data = $this->get_oauth_response_body( $email, $password );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! email_exists( $email ) ) {
			wp_create_user( $email, $password, $email );
		}

		$wp_user = get_user_by( 'email', $email );

		$this->update_token( $wp_user->ID, $data );

		return $wp_user;

	}

	protected function get_oauth_response_body( $email, $password ) {

		$options = get_option( $this->option_name );

		$response = wp_remote_post( $options[ $this->option_name . '_server_url' ], array(
			'method' => 'POST',
			'headers' => array(
				'Accept' => 'application/json',
			),
			'body' => array(
				'grant_type' => 'password',
				'client_id' => $options[ $this->option_name . '_client_id' ],
				'client_secret' => $options[ $this->option_name . '_client_secret' ],
				'username' => $email,
				'password' => $password,
				'scope' => '',
			),
			'sslverify' => false,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode($response['http_response']->get_data());

		if ( property_exists( $data, 'error' ) ) {
			return new WP_Error( 'oauth2_authentication_failed', __( $data->error . ': ' . $data->message, $this->plugin_name ) );
		}

		return $data;

	}

	protected function update_token( $user_id, $token_meta ) {

		global $wpdb;

		$table_name = $wpdb->prefix . 'oauth_access_tokens';

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$wpdb->replace( $table_name, array(
			'wp_user_id' => $user_id,
			'access_token' => $token_meta->access_token,
			'created_at' => $now->format( 'Y-m-d H:i:s' ),
			'expires_at' => $now->modify( "+{$token_meta->expires_in} seconds" )
				->format( 'Y-m-d H:i:s' ),
		) );

	}

	protected function create_table() {

		global $wpdb;

		$table_name = $wpdb->prefix . 'oauth_access_tokens';

		$sql = <<<CREATE_TABLE
			CREATE TABLE IF NOT EXISTS {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				wp_user_id bigint(20) unsigned NOT NULL,
				access_token text NOT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				expires_at datetime DEFAULT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY {$table_name}_wp_user_unique (wp_user_id)
			);
CREATE_TABLE;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

	}

}
