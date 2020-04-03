<?php
class WP_Example_Process_Single extends WP_Background_Process {

  use WP_Example_Logger;

  /**
   * @var string
   */
  protected $action = 'example_process_single';

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
  protected function task( $member_array ) {
    /**
     * $member_array = array(
    $member->ID,  // 0
    $enabled, // 1  true/false
    );
     */
    $this->really_long_running_task();
    $message = 'User ID: ' . $member_array[0] . ' is Enabled';
    $this->really_long_running_task();
    if ( $member_array[1] !== true ) {
      wp_delete_user( $member_array[0] );
      $message = 'User ID: ' . $member_array[0] . ' has been deleted.';
    }

    $this->log( $message );
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