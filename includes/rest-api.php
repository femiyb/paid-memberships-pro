<?php

if ( class_exists( 'WP_REST_Controller' ) ) {
	class PMPro_REST_API_Routes extends WP_REST_Controller {
		
		public function pmpro_rest_api_register_routes() {
			$namespace = 'wp/v2';
			register_rest_route( $namespace, '/users/(?P<id>\d+)'.'/pmpro_membership_level' , 
			array(
				array(
					'methods'         => WP_REST_Server::READABLE,
					'callback'        => array( $this, 'pmpro_rest_api_get_user_level' ),
					'permission_callback' => array( $this, 'pmpro_rest_api_get_permissions_check' ),
			),));
			
			register_rest_route( $namespace, '/posts/(?P<post_id>\d+)'.'/user_id/(?P<user_id>\d+)/pmpro_has_membership_access' , 
			array(
				array(
					'methods'         => WP_REST_Server::READABLE,
					'callback'        => array( $this, 'pmpro_rest_api_get_has_membership_access' ),
					'permission_callback' => array( $this, 'pmpro_rest_api_get_permissions_check' ),
			),));

			register_rest_route( 'pmpro/v1', '/membership_level/(?P<id>\d+)' , 
			array(
				array(
					'methods'         => WP_REST_Server::READABLE,
					'callback'        => array( $this, 'pmpro_rest_api_get_membership_level' ),
					'permission_callback' => array( $this, 'pmpro_rest_api_get_permissions_check' ),
			),
				array(
					'methods' 		=> 'DELETE',
					'callback'        => array( $this, 'pmpro_rest_api_delete_membership_level' ),
					'permission_callback' => array( $this, 'pmpro_rest_api_get_permissions_check' ),
				)
		));

			register_rest_route( 'pmpro/v1', '/membership_level' , 
			array(
				array(
					'methods'         => 'GET,POST,PUT,PATCH',
					'callback'        => array( $this, 'pmpro_rest_api_set_membership_level' ),
					'args' => array(
						'id' => array(),
						'name' => array(),
						'description' => array(),
						'confirmation' => array(),
						'initial_payment' => array(),
						'billing_amount' => array(),
						'cycle_number' => array(),
						'billing_limit' => array(),
						'trial_amount' => array(),
						'trial_limit' => array(),
						'allow_signups' => array(),
						'expiration_number' => array(),
						'expiration_period' => array(),
						'categories' => array()
					),
					'permission_callback' => array( $this, 'pmpro_rest_api_get_permissions_check' ),
			),
			array(
				'methods' 		=> 'DELETE',
				'callback'        => array( $this, 'pmpro_rest_api_delete_membership_level' ),
				'permission_callback' => array( $this, 'pmpro_rest_api_get_permissions_check' ),
				'args' => array(
					'id' => array(),
				)
			)
		));

		}
		
		//Ex:http://example.com/wp-json/wp/v2/users/2/pmpro_membership_level
		function pmpro_rest_api_get_user_level($request) {
			$params = $request->get_params();
			
			$user_id = $params['id'];
			
			$level = pmpro_getMembershipLevelForUser($user_id);
			if ( ! empty( $level ) ) {
				$level = (array)$level;
			}
			return new WP_REST_Response($level, 200 );
		}
		
		//Ex: http://example.com/wp-json/wp/v2/posts/58/user_id/2/pmpro_has_membership_access
		function pmpro_rest_api_get_has_membership_access($request) {
			$params = $request->get_params();
			$post_id = $params['post_id'];
			$user_id = $params['user_id'];
			
			$has_access = pmpro_has_membership_access($post_id, $user_id);
			return $has_access;
		}
		
		function pmpro_rest_api_get_permissions_check($request)	{

			// default permissions to 'read' (subscriber)
			$permissions = current_user_can('read');
			$method = $request->get_method();
			if ( $method != 'GET' ) {
				$permissions = current_user_can('edit_users');
			}

			$permissions = apply_filters( 'pmpro_rest_api_permissions', $permissions, $request );

			return $permissions;
		}

		/**
		 * Endpoint to get membership level data
		 * Example: http://example.com/wp-json/wp/v2/pmpro/membership_level/1
		 **/
		function pmpro_rest_api_get_membership_level( $request ) {

			if ( ! class_exists( 'PMPro_Membership_Level' ) ) {
				return false;
			}
			
			$params = $request->get_params();
			$id = intval( $params['id'] );
			return new PMPro_Membership_Level( $id );
		}

		/**
		 * Method to create/update a level.
		 * Example: http://example.com/wp-json/wp/v2/pmpro/membership_level/ (pass query parameters)
		 */
		function pmpro_rest_api_set_membership_level( $request ) {

			if ( ! class_exists( 'PMPro_Membership_Level' ) ) {
				return false;
			}

			$params = $request->get_params();
			$method = $request->get_method();

			// Get all parameters.
			$id = intval( $params['id'] );
			$name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
			$description = isset( $params['description'] ) ? sanitize_text_field( $params['description'] ) : '';
			$confirmation = isset( $params['confirmation'] ) ? sanitize_text_field( $params['confirmation'] ) : '';
			$initial_payment = isset( $params['initial_payment'] ) ? floatval( $params['initial_payment'] ) : '';
			$billing_amount = isset( $params['billing_amount'] ) ? floatval( $params['billing_amount'] ) : '';
			$cycle_number = isset( $params['cycle_number'] ) ? intval( $params['cycle_number'] ) : '';
			$cycle_period = isset( $params['cycle_period'] ) ? sanitize_text_field( $params['cycle_period'] ) : '';
			$billing_limit = isset( $params['billing_limit'] ) ? sanitize_text_field( $params['billing_limit'] ) : '';
			$trial_amount = isset( $params['trial_amount'] ) ? floatval( $params['trial_amount'] ) : '';
			$trial_limit = isset( $params['trial_limit'] ) ? floatval( $params['trial_limit'] ) : '';
			$allow_signups = isset( $params['allow_signups'] ) ? intval( $params['allow_signups'] ) : '';
			$expiration_number = isset( $params['expiration_number'] ) ? intval( $params['expiration_number'] ) : '';
			$expiration_period = isset( $params['expiration_period'] ) ? intval( $params['expiration_period'] ) : '';

			// Just return level object if method is GET, otherwise assume POST,PUT or PATCH.
			if ( $method === 'GET' ) {
				return new PMPro_Membership_Level( $id );
			} else {
				$level = new PMPro_Membership_Level();

				// Pass through an ID only for PUT/PATCH methods. POST treats it as a brand new level.
				if ( ! empty( $id ) && ( $method === 'PUT' || $method === 'PATCH' ) ) {
					$level->id = $id;
				} elseif ( empty( $id ) && ( $method === 'PUT' || $method === 'PATCH' ) ) {
					return false; // Error trying to update /// Improve this.
				}

				$level->name = $name;
				$level->description = $description;
				$level->confirmation = $confirmation;
				$level->initial_payment = $initial_payment;
				$level->billing_amount = $billing_amount;
				$level->cycle_number = $cycle_number;
				$level->cycle_period = $cycle_period;
				$level->billing_limit = $billing_limit;
				$level->trial_amount = $trial_amount;
				$level->trial_limit = $trial_limit;
				$level->allow_signups = $allow_signups;
				$level->expiration_number = $expiration_number;
				$level->expiration_period = $expiration_period;

				/// Handle categories
				// $level->categories = array();

				$level->save();

				return $level;
			}

		}

		/**
		 * Delete membership level and remove users from level. (And cancel their subscription.)
		 * @since 2.3
		 */
		function pmpro_rest_api_delete_membership_level( $request ) {

			if ( ! class_exists( 'PMPro_Membership_Level' ) ) {
				return false;
			}

			$params = $request->get_params();
			$id = intval( $params['id'] );
			
			$level = new PMPro_Membership_Level( $id );
			return $level->delete();
		}



	} // End of class


	function pmpro_rest_api_register_custom_routes() {
		$pmpro_rest_api_routes = new PMPro_REST_API_Routes;
		$pmpro_rest_api_routes->pmpro_rest_api_register_routes();
	}

	add_action( 'rest_api_init', 'pmpro_rest_api_register_custom_routes', 5 );
}