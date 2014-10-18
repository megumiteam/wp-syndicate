<?php
class WP_SYND_logger {
	private static $instance;
	
	public static function get_instance() {
		if ( isset( self::$instance ) )
			return self::$instance;
		
		self::$instance = new WP_SYND_logger;
		return self::$instance;
	}
	
	private function __construct() {}
	
	public function success( $title, $msg ) {
		$args = array(
					'post_title'    => $title,
					'post_content'  => $msg,
					'post_author'   => 1,
					'post_status'   => 'publish',
					'post_type'     => 'wp-syndicate-log'
				);
		$post_id = wp_insert_post($args);
		if($post_id) {
   			wp_set_object_terms($post_id, 'success', 'log-category', true );
		}
		
		return $post_id;
	}
	
	public function error( $title, $msg ) {
		$args = array(
					'post_title'    => $title,
					'post_content'  => $msg,
					'post_author'   => 1,
					'post_status'   => 'publish',
					'post_type'     => 'wp-syndicate-log'
				);
		$post_id = wp_insert_post($args);
		if($post_id) {
   			wp_set_object_terms($post_id, 'error', 'log-category', true );
		}

		return $post_id;
	}
}


class WP_SYND_Log_Operator {
	private $event = 'wp_syndicate_log_delete_log';
	private $key = 'twicedaily';

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( $this->event, array( $this, 'delete_log' ) );
		add_filter( 'manage_posts_columns', array( $this, 'manage_posts_columns' ) );
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
			'edit_published_feed_logs'
		);

		$role = get_role( 'administrator' );
		foreach ( $capabilities as $cap ) {
    		$role->add_cap( $cap );
		}
		register_post_type( 'wp-syndicate-log', 
	    							array( 
	    								'labels' => array( 'name' => __( 'Syndication Log', WPSYND_DOMAIN ) ),
	    								'public' => true,
	    								'publicly_queryable' => false,
										'has_archive' => false,
	    								'hierarchical' => false,
	    								'supports' => array( 'title', 'editor' ),
	    								'rewrite' => false,
	    								'can_export' => true,
	    								'capability_type' => 'feed_log',
    									'capabilities'    => $capabilities,
    									'map_meta_cap' => true
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
	        	));
	}

	public function set_event() {
		$action_time = time() + 60;
		if ( wp_next_scheduled( $this->event ) )
			wp_clear_scheduled_hook( $this->event );

		wp_schedule_event( $action_time, $this->key, $this->event );
		spawn_cron( $action_time );
	}
	
	public function delete_event() {
		wp_clear_scheduled_hook( $this->event );
	}
	
	public function delete_log() {
		global $wpdb;
		$options = get_option( 'wp_syndicate_options', 14 );
		$term_day = $options['delete_log_term'] != '' ? $options['delete_log_term'] : 7;
		$term = "-" . $term_day . " day";
		$date = date_i18n( 'Y/m/d H:i:s', strtotime($term) );
		
		$results = $wpdb->get_results($wpdb->prepare(
			'SELECT ID FROM '. $wpdb->posts. ' WHERE post_type="wp-syndicate-log" AND post_status="publish" AND post_date<%s',
			$date ));
			
		if ($results) {
			foreach ( $results as $result ) {
				wp_delete_post( $result->ID, true );
			}
		}
	}

	public function manage_posts_columns( $posts_columns ) {
		global $post_type;
		if ( !is_object_in_taxonomy( $post_type, 'log-category' ) )
			return $posts_columns;

		$new_columns = array();
		foreach ( $posts_columns as $column_name => $column_display_name ) {
			if ( $column_name == 'categories' ) {
				$new_columns['status'] = __( 'status', WPSYND_DOMAIN );
				add_action( 'manage_posts_custom_column', array( $this, 'add_column' ), 10, 2 );
			}
			$new_columns[$column_name] = $column_display_name;
		}
		return $new_columns;
	}

	public function add_column($column_name, $post_id) {
		$post_id = (int)$post_id;

		if ( $column_name == 'status') {
			$term = array_shift(get_the_terms($post->ID, 'log-category'));
			if ( $term )
    			echo esc_html($term->name);
		}
	}

	public function restrict_manage_posts() {
		global $post_type;
		if ( is_object_in_taxonomy( $post_type, 'log-category' ) ) {
			$terms = get_terms('log-category');
			$get = isset($_GET['term']) ? $_GET['term'] : '';
	?>
		<select name="term">
			<option value="0"></option>
			<?php foreach( $terms as $term ) : ?>
			<option <?php selected( $get, $term->slug ); ?> value="<?php echo $term->slug; ?>"><?php echo $term->name; ?></option>
			<?php endforeach; ?>
		</select>
		<input type="hidden" name="taxonomy" value="log-category" />
	<?php
		}
	}
}