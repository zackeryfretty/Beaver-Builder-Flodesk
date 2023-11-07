<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for the Flodesk API.
 *
 */
final class FLBuilderServiceFlodesk extends FLBuilderService {

	/**
	 * The ID for this service.
	 *
	 * @var string $id
	 */
	public $id = 'flodesk';

	/**
	 * The API URI for this service.
	 *
	 * @param string $api
	 */
	private static $api_base = 'https://api.flodesk.com/v1';

	/**
	 * Test the API connection.
	 *
	 * @since 1.5.4
	 * @param array $fields {
	 *      @type string $api_key A valid API key.
	 * }
	 * @return array{
	 *      @type bool|string $error The error message or false if no error.
	 *      @type array $data An array of data used to make the connection.
	 * }
	 */
	public function connect( $fields = array() ) {
		$response = array(
			'error' => false,
			'data'  => array(),
		);

		// Make sure we have an API key.
		if ( ! isset( $fields['api_key'] ) || empty( $fields['api_key'] ) ) {
			// If not, remind user.
			$response['error'] = __( 'Error: You must provide an API key.', 'fl-builder' );
		
		} else {
			// hit the segment endpoint to test validity.
			$api_response = wp_remote_get( self::$api_base . '/segments' , array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . $fields['api_key'] . '',
				),
				'user-agent' => 'BB-Flodesk (zackeryfretty.com)'
			) );

			// get the response code.
			$api_responce_code = wp_remote_retrieve_response_code( $api_response );
			
			// if there's no wp error and flodesk gives a 200, save working key.
			if(( ! is_wp_error($api_response)) && (200 === $api_responce_code )) {
				$response['data'] = array(
					'api_key' => $fields['api_key'],
				);
			} else {
			// otherwise tell the user it's a bad key.
				$response['error'] = 'Error: Please check your API key.';
			}
		}

		return $response;
	}

	/**
	 * Renders the markup for the connection settings.
	 *
	 * @since 1.5.4
	 * @return string The connection settings markup.
	 */
	public function render_connect_settings() {
		ob_start();

		FLBuilder::render_settings_field( 'api_key', array(
			'row_class' => 'fl-builder-service-connect-row',
			'class'     => 'fl-builder-service-connect-input',
			'type'      => 'text',
			'label'     => __( 'API Key', 'fl-builder' ),
			'help'      => __( 'Your API key can be found in your Flodesk account.', 'fl-builder' ),
			'preview'   => array(
				'type' => 'none',
			),
		));

		return ob_get_clean();
	}

	/**
	 * Render the markup for service specific fields.
	 *
	 * @since 1.5.4
	 * @param string $account The name of the saved account.
	 * @param object $settings Saved module settings.
	 * @return array {
	 *      @type bool|string $error The error message or false if no error.
	 *      @type string $html The field markup.
	 * }
	 */
	public function render_fields( $account, $settings ) {
		$response     = array(
			'error' => false,
			'html'  => '',
		);

		// get saved account api key
		$account_data = $this->get_account_data( $account );

		// grab all the segments
		$api_response = wp_remote_get( self::$api_base . '/segments' , array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . $account_data['api_key'] . '',
			),
			'user-agent' => 'BB-Flodesk (zackeryfretty.com)'
		) );

		// get just the body
		$api_responce_body = json_decode(wp_remote_retrieve_body( $api_response ));

		// now just the segments
		$segments = $api_responce_body->data;

		// render list
		if ( ! $segments ) {
			$response['error'] = __( 'Error: Please check your API key.', 'fl-builder' );
		} else {
			$response['html'] = $this->render_list_field( $segments, $settings );
		}

		return $response;
	}

	/**
	 * Render markup for the list field.
	 *
	 * @since 1.5.4
	 * @param array $segments Segment data from the API.
	 * @param object $settings Saved module settings.
	 * @return string The markup for the list field.
	 * @access private
	 */
	private function render_list_field( $segments, $settings ) {
		ob_start();

		$options = array(
			'' => __( 'Choose...', 'fl-builder' ),
		);

		// if segments exist in the account, proceed.
		if ( isset( $segments ) ) {
			// loop through each and grab the id & name to build the <select>.
			foreach ( $segments as $segment ) {
				$options[ $segment->id ] = esc_attr( $segment->name );
			}
		}

		FLBuilder::render_settings_field( 'list_id', array(
			'row_class' => 'fl-builder-service-field-row',
			'class'     => 'fl-builder-service-list-select',
			'type'      => 'select',
			'label'     => _x( 'Segment', 'An email list from a third party provider.', 'fl-builder' ),
			'options'   => $options,
			'preview'   => array(
				'type' => 'none',
			),
		), $settings);

		return ob_get_clean();
	}

	/**
	 * Subscribe an email address to Flodesk.
	 *
	 * @since 1.5.4
	 * @param object $settings A module settings object.
	 * @param string $email The email to subscribe.
	 * @param string $name Optional. The full name of the person subscribing.
	 * @return array {
	 *      @type bool|string $error The error message or false if no error.
	 * }
	 */
	public function subscribe( $settings, $email, $name = '' ) {
		$response     = array(
			'error' => false,
		);
		// get account api key
		$account_data = $this->get_account_data( $settings->service_account );

		// build field data
		$data = array(
			'email' => $email,
		);

		if ( ! empty( $name ) ) {
			$data['fname'] = $name;
		}

		if ( ! $account_data ) {
			// if that errors out, let user know.
			$response['error'] = __( 'Error: You were not subscribed.', 'fl-builder' );
		
		} else {

			// add/update the user in flodesk
			$api_response = wp_remote_post( self::$api_base . '/subscribers' , array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . $account_data['api_key'] . '',
				),
				'user-agent' => 'BB-Flodesk (zackeryfretty.com)',
				'body' => json_encode([
					'first_name' => $data['fname'] ?? '',
					'email' => $data['email']
				])
				)
			);

			// get response code & body
			$api_responce_code = wp_remote_retrieve_response_code( $api_response );
			$api_responce_body = json_decode(wp_remote_retrieve_body( $api_response ));
			
			if(( ! is_wp_error($api_response)) && (200 === $api_responce_code )) {
				$api_response_2 = wp_remote_post( self::$api_base . '/subscribers' . '/' . $data['email'] . '/segments' , array(
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Basic ' . $account_data['api_key'] . '',
					),
					'user-agent' => 'BB-Flodesk (zackeryfretty.com)',
					'body' => json_encode([
						'segment_ids' => [$settings->list_id],
					])
					)
				);
			} else {
				$response['error'] = 'Error: '. $api_responce_body->message .'';
			}

		}

		return $response;
	}
}
