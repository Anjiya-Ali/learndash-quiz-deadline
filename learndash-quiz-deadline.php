<?php

/**
 * Plugin Name: LearnDash Quiz Deadline
 * Description: Set deadline for quiz and assignment. Option to send reminder before expiry
 * Version: 1.0.0
 * Author: Anjiya
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learndash-quiz-deadline
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LD_Quiz_Deadline {
	/**
	 * @var LD_Quiz_Deadline
	 */
	private static $instance = null;

	private function __construct() {
		if ( ! defined( 'LEARNDASH_VERSION' ) ) {
			return;
		}

		if ( ! function_exists( 'learndash_notifications' ) ) {
			return;
		}

		if ( ! class_exists( 'BuddyPress' ) ) {
			return;
		}

		$this->includes();
		$this->hooks();
	}

	public static function loader() {
		if ( is_null( self::$instance ) && ! ( self::$instance instanceof LD_Quiz_Deadline ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	private function includes() {

	}

	private function hooks() {
		add_filter( 'learndash_notifications_triggers', [ $this, 'learndash_notifications_triggers' ] );
		add_filter( 'learndash_notification_settings', [ $this, 'learndash_notification_settings' ] );
		add_filter( 'learndash_notifications_shortcodes_instructions', [
			$this,
			'learndash_notifications_shortcodes_instructions'
		] );

		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ], 10, 3 );
		add_action( 'save_post', [ $this, 'learndash_notifications_save_meta_box' ], 10, 3 );

		add_action( 'init', [ $this, 'learndash_notifications_cron_daily_register' ] );
		add_action( 'learndash_notifications_cron_daily', [ $this, 'learndash_notifications_cron_daily_event' ] );
	}

	public function admin_enqueue_scripts() {
		global $post;

		if ( ! in_array( $post->post_type, [ 'sfwd-quiz' ] ) ) {
			return;
		}

		wp_enqueue_style( 'jquery-ui-datepicker', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
	}

	public function add_assignment_expiry_date( $setting_option_fields, $settings_metabox_key ) {
		$setting_option_fields['assignment_expiry_time'] = [
			'name'           => 'assignment_expiry_time',
			'label'          => esc_html__( 'Date of Expiry', 'learndash-quiz-deadline' ),
			'type'           => 'date',
			'help_text'      => esc_html__( 'Specify the expiry date of assignment.', 'learndash-quiz-deadline' ),
			'default'        => '',
			'parent_setting' => 'lesson_assignment_upload',
		];

		return $setting_option_fields;
	}

	/**
	 * Add course grid settings meta box
	 */
	public function add_meta_box() {
		add_meta_box( 'learndash-quiz-deadline-meta-box', __( 'Quiz Deadline', 'ld-customization-netzwerkn' ), [
			$this,
			'output_meta_box'
		], [ 'sfwd-quiz' ], 'advanced', 'low', [] );
	}

	/**
	 * Output course grid settings meta box
	 *
	 * @param array $args List or args passed on callback function
	 */
	public function output_meta_box( $args ) {
		$post_id = get_the_ID();

		$label       = __( 'Deadline date', 'learndash-quiz-deadline' );
		$description = __( 'Specify the deadline of the quiz.', 'learndash-quiz-deadline' );

		$expiry_date = get_post_meta( $post_id, '_learndash_quiz_deadline_expiry_date', true );
		?>

		<?php wp_nonce_field( 'learndash_quiz_deadline_save', 'learndash_quiz_deadline_save_nonce' ); ?>
        <div class="sfwd sfwd_options">
            <div class="sfwd_input" id="learndash_quiz_deadline_expiry_date_field">
			<span class="sfwd_option_label" style="text-align:right;vertical-align:top;">
				<a class="sfwd_help_text_link" style="cursor:pointer;" title="Click for Help!"
                   onclick="toggleVisibility('learndash_quiz_deadline_expiry_date');"><img
                            src="<?php echo LEARNDASH_LMS_PLUGIN_URL . 'assets/images/question.png' ?>">
				<label class="sfwd_label textinput"><?php echo $label; ?></label></a>
			</span>
                <span class="sfwd_option_input">
				<div class="sfwd_option_div">
					<input name="learndash_quiz_deadline_expiry_date"
                           id="learndash_quiz_deadline_expiry_date_input"
                           type="text"
                           value="<?php echo esc_attr( $expiry_date ); ?>">
				</div>
				<div class="sfwd_help_text_div" style="display:none"
                     id="learndash_quiz_deadline_expiry_date">
					<label class="sfwd_help_text"><?php echo $description; ?>
					</label>
				</div>
			</span>
                <p style="clear:left"></p>
            </div>
        </div>
        <script>
            (function ($) {
                $(document).ready(function () {
                    $('#learndash_quiz_deadline_expiry_date_input').datepicker();
                });
            })(jQuery);
        </script>
		<?php
	}

	/**
	 * Save expiry date metafield
	 *
	 * @param int $post_id Post ID
	 * @param object $post WP post object
	 * @param bool $update True if post is an update
	 */
	public function save_meta_box( $post_id, $post, $update ) {
		if ( ! in_array( $post->post_type, [ 'sfwd-quiz' ] ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['learndash_quiz_deadline_save_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['learndash_quiz_deadline_save_nonce'], 'learndash_quiz_deadline_save' ) ) {
			wp_die( __( 'Cheatin\' huh?' ) );
		}

		update_post_meta( $post_id, '_learndash_quiz_deadline_expiry_date', sanitize_text_field( trim( $_POST['learndash_quiz_deadline_expiry_date'] ) ) );
	}

	/**
	 * Add new trigger
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public function learndash_notifications_triggers( $triggers ) {
		$triggers['deadline_quiz'] = __( '"X" days before quiz deadline', 'learndash-quiz-deadline' );

		return $triggers;
	}

	/**
	 * Register trigger keys to populate module dropdowns
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	public function learndash_notification_settings( $settings ) {
		$settings['course_id']['parent'][] = 'deadline_quiz';
		$settings['lesson_id']['parent'][] = 'deadline_quiz';
		$settings['topic_id']['parent'][]  = 'deadline_quiz';
		$settings['quiz_id']['parent'][]   = 'deadline_quiz';
		$settings['delay']['hide_on'][]    = 'deadline_quiz';

		$settings['quiz_expires_days'] = [
			'type'       => 'text',
			'title'      => __( 'Before how many days?', 'learndash-notifications' ),
			'help_text'  => __( 'Setting associated with the email trigger setting above.', 'learndash-notifications' ),
			'label'      => __( 'day(s)', 'learndash-notifications' ),
			'hide'       => 1,
			'hide_delay' => 1,
			'size'       => 2,
			'parent'     => [ 'deadline_quiz' ]
		];

		return $settings;
	}

	public function learndash_notifications_shortcodes_instructions( $instructions ) {
		$instructions['deadline_quiz'] = $instructions['pass_quiz'];

		return $instructions;
	}

	/**
	 * Save notifications meta box value
	 *
	 * @param int $notification_id ID of post created/updated
	 * @param WP_Post $notification
	 * @param bool $update
	 */
	public function learndash_notifications_save_meta_box( $notification_id, $notification, $update ) {
		if ( ! isset( $_POST['learndash_notifications_nonce'] ) ) {
			return;
		}

		if ( $notification->post_type != 'ld-notification' || ! check_admin_referer( 'learndash_notifications_meta_box', 'learndash_notifications_nonce' ) ) {
			return;
		}

		if ( 'deadline_quiz' === $_POST['_ld_notifications_trigger'] ) {
			$course_id = (int) $_POST['_ld_notifications_course_id'];
			$lesson_id = (int) $_POST['_ld_notifications_lesson_id'];
			$topic_id  = (int) $_POST['_ld_notifications_topic_id'];
			update_post_meta( $notification_id, '_ld_notifications_course_id', $course_id );
			update_post_meta( $notification_id, '_ld_notifications_lesson_id', $lesson_id );
			update_post_meta( $notification_id, '_ld_notifications_topic_id', $topic_id );

			$quiz_expires_days = $_POST['_ld_notifications_quiz_expires_days'] ?? '';
			update_post_meta( $notification_id, '_ld_notifications_quiz_expires_days', $quiz_expires_days );
		}
	}

	public function learndash_notifications_cron_daily_register() {
		if ( ! wp_next_scheduled( 'learndash_notifications_cron_daily' ) ) {
			wp_schedule_event( time(), 'daily', 'learndash_notifications_cron_daily' );
		}
	}

	/**
	 * @see learndash_notifications_cron_hourly()
	 */
	public function learndash_notifications_cron_daily_event() {
		$this->learndash_notifications_quiz_expires();

		learndash_notifications_update_cron_status();
	}

	public function learndash_notifications_quiz_expires() {
		$notifications = learndash_notifications_get_notifications( 'deadline_quiz' );

		if ( empty( $notifications ) ) {
			return;
		}

		$args = [
			'post_type'      => 'sfwd-quiz',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'meta_key'       => '_learndash_quiz_deadline_expiry_date',
			'meta_value'     => '',
			'meta_compare'   => '!=',
		];

		$quizzes_qyery = new WP_Query;
		$quizzes       = $quizzes_qyery->query( $args );

		foreach ( $quizzes as $quiz ) {
			$course_ID = get_post_meta( $quiz->ID, 'course_id', true );
			$topic_ID  = get_post_meta( $quiz->ID, 'lesson_id', true );
			$lesson_ID = learndash_get_lesson_id( $topic_ID, $course_ID );

			$c_access_list = learndash_get_course_users_access_from_meta( $course_ID );

			// If course has no access list, continue
			if ( empty( $c_access_list ) ) {
				continue;
			}

			// Foreach users who have access
			foreach ( $c_access_list as $u_id ) {
				if ( learndash_course_completed( $u_id, $course_ID ) ) {
					continue;
				}

				if ( learndash_is_lesson_complete( $u_id, $lesson_ID, $course_ID ) ) {
					continue;
				}

				if ( ! empty( $topic_ID ) && learndash_is_topic_complete( $u_id, $topic_ID ) ) {
					continue;
				}

				if ( learndash_is_quiz_complete( $u_id, $quiz->ID, $course_ID ) ) {
					continue;
				}

				$expiry_date  = get_post_meta( $quiz->ID, '_learndash_quiz_deadline_expiry_date', true );
				$expiry_date  = date_create( $expiry_date );
				$current_date = date_create();

				// Foreach notifications
				foreach ( $notifications as $n ) {
					$n_days = get_post_meta( $n->ID, '_ld_notifications_quiz_expires_days', true );

					if ( empty( $n_days ) ) {
						continue;
					}

					$diff = date_diff( $expiry_date, $current_date );

					if ( $diff->invert === 1 && $diff->days === absint( $n_days ) ) {
						learndash_notifications_send_notification( $n, $u_id, $course_ID, $lesson_ID, $topic_ID, $quiz->ID );
					}
				}
			}
		}
	}
}

add_action( 'plugins_loaded', [ 'LD_Quiz_Deadline', 'loader' ] );
