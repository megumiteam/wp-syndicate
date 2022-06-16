<?php
class WP_SYND_logger {
	private static $instance;

	public static function get_instance() {
		if ( isset( self::$instance ) ) {
			return self::$instance; }

		self::$instance = new WP_SYND_logger;
		return self::$instance;
	}

	private function __construct() {}

	public function success( $title, $msg ) {
		return $this->create_log( 'success', $title, $msg );
	}

	public function error( $title, $msg ) {
		return $this->create_log( 'error', $title, $msg );
	}

	private function create_log( $status, $title, $msg ) {
		$args = array(
					'post_title'    => $title,
					'post_content'  => $msg,
					'post_author'   => 1,
					'post_status'   => 'publish',
					'post_type'     => 'wp-syndicate-log',
				);
		$post_id = wp_insert_post( $args );
		$post_id && wp_set_object_terms( $post_id, $status, 'log-category', true );

		return $post_id;
	}
}


class WP_SYND_Log_Operator {
	private $event = 'wp_syndicate_log_delete_log';
	private $key = 'twicedaily';

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( $this->event, array( $this, 'delete_log' ) );
		add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );
	}

	public function init() {
		$capabilities = array(
			'read_feed_log',
			'edit_feed_log',
			'delete_feed_log',
			'edit_feed_logs',
			'edit_others_feed_logs',
			'publish_feed_logs',
			'read_private_feed_logs',
			'delete_feed_logs',
			'delete_private_feed_logs',
			'delete_published_feed_logs',
			'delete_others_feed_logs',
			'edit_private_feed_logs',
			'edit_published_feed_logs',
		);

		$role = get_role( 'administrator' );
		foreach ( $capabilities as $cap ) {
			$role->add_cap( $cap );
		}
		register_post_type( 'wp-syndicate-log',
			array(
										'labels' => array( 'name' => __( 'Syndication Log', WPSYND_DOMAIN ) ),
										'public' => false,
										'show_ui' => true,
										'publicly_queryable' => false,
										'has_archive' => false,
										'hierarchical' => false,
										'supports' => array( 'title', 'editor' ),
										'rewrite' => false,
										'can_export' => true,
										'menu_position' => 27,
										'capability_type' => 'feed_log',
										'capabilities'    => $capabilities,
										'map_meta_cap' => true,
										'exclude_from_search' => true,
									));

									register_taxonomy(
										'log-category',
										'wp-syndicate-log',
										array(
										'label' => __( 'status', WPSYND_DOMAIN ),
										'public' => false,
										'query_var' => false,
										'show_ui' => true,
										'hierarchical' => true,
										'show_admin_column' => true,
										));
	}

	public function set_event() {
		$action_time = time() + 60;

		wp_schedule_event( $action_time, $this->key, $this->event );
		spawn_cron( $action_time );
	}

	public function delete_event() {
		wp_clear_scheduled_hook( $this->event );
	}

	public function delete_log() {
		$args = array(
				'posts_per_page'   => 1000,
				'post_type'        => 'wp-syndicate-log',
				'suppress_filters' => false,
		);

		add_filter( 'post_where', array( $this, 'post_where' ) );
		$results = get_posts( $args );
		remove_filter( 'post_where', array( $this, 'post_where' ) );

		if ( ! empty( $results ) && is_array( $results ) ) {
			foreach ( $results as $result ) {
				wp_delete_post( $result->ID, true );
			}
		}
	}

	public function post_where($where) {
		$options = get_option( 'wp_syndicate_options', 14 );
		$term_day = ! empty( $options['delete_log_term'] ) ? $options['delete_log_term'] : 7;
		$date = date_i18n( 'Y/m/d H:i:s', strtotime( '-' . $term_day . ' day' ) );

		$where .= " AND post_date < '" . $date . "'";
		return $where;
	}

	public function restrict_manage_posts() {
		global $post_type;
		if ( is_object_in_taxonomy( $post_type, 'log-category' ) ) {
			$terms = get_terms( 'log-category' );
			$get = isset( $_GET['term'] ) ? $_GET['term'] : '';
?>
<select name="term">
	<option value="0"></option>
	<?php foreach ( $terms as $term ) : ?>
		<option <?php selected( $get, $term->slug ); ?> value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
	<?php endforeach; ?>
</select>
<input type="hidden" name="taxonomy" value="log-category" />
<?php
		}
	}
}
