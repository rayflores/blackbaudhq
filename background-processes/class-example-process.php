<?php

class WP_Example_Process extends WP_Background_Process {

	use WP_Example_Logger;

	/**
	 * @var string
	 */
	protected $action = 'example_process';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $user_array ) {
		
		$this->really_long_running_task();
		
		$new_user = wp_insert_user(
			array(
				'user_login' => $user_array[2],
				'user_pass' => NULL,
				'first_name' => $user_array[0],
				'last_name' => $user_array[1],
				'user_email' => $user_array[2],
				'role' => 'members'
			)
		);
		// On success.
		if ( ! is_wp_error( $new_user ) ) {
			$this->log( 'User ID: ' . $new_user );
		}

		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		// Show notice to user or perform some other arbitrary task...
	}

}