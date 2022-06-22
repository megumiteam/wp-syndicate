<?php
class WP_SYNDICATE {

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
	}

	public function init() {
		$capabilities = array(
		    'read_wp_syndicate',
			'edit_wp_syndicate',
			'delete_wp_syndicate',
			'edit_wp_syndicates',
			'edit_others_wp_syndicates',
			'publish_wp_syndicates',
			'read_private_wp_syndicates',
			'delete_wp_syndicates',
			'delete_private_wp_syndicates',
			'delete_published_wp_syndicates',
			'delete_others_wp_syndicates',
			'edit_private_wp_syndicates',
			'edit_published_wp_syndicates',
		);

		$role = get_role( 'administrator' );
		foreach ( $capabilities as $cap ) {
			$role->add_cap( $cap );
		}
		register_post_type( 'wp-syndicate',
			array(
	    								'labels' => array( 'name' => __( 'Syndication', WPSYND_DOMAIN ) ),
	    								'public' => false,
										'show_ui' => true,
	    								'publicly_queryable' => false,
										'has_archive' => false,
	    								'hierarchical' => false,
	    								'supports' => array( 'title' ),
	    								'rewrite' => false,
	    								'can_export' => true,
	    								'menu_position' => 28,
	    								'capability_type' => 'wp_syndicate',
										'capabilities'    => $capabilities,
										'map_meta_cap' => true,
										'register_meta_box_cb' => array( $this, 'add_meta_box' ),
	    							));
	}

	public function add_meta_box() {
		global $hook_suffix;

		add_meta_box( 'wp_syndicate_meta_box', __( 'configuration', WPSYND_DOMAIN ), array( $this, 'meta_box_wp_syndicate' ),'wp-syndicate' );
		if ( 'post.php' === $hook_suffix ) {
			add_meta_box( 'wp_syndicate_meta_box_import_test', __( 'Import Test', WPSYND_DOMAIN ), array( $this, 'meta_box_wp_syndicate_import_test' ),'wp-syndicate' );
		}
	}

	public function meta_box_wp_syndicate() {
		wp_nonce_field( 'wp_syndicate_meta_box', 'wp_syndicate_meta_box_nonce' );

		$feed_url = get_post_meta( get_the_ID(), 'wp_syndicate-feed-url', true );
		$feed_retrieve_term = get_post_meta( get_the_ID(), 'wp_syndicate-feed-retrieve-term', true );
		$author_id = get_post_meta( get_the_ID(), 'wp_syndicate-author-id', true );
		$statuses = get_post_statuses();
		unset( $statuses['pending'] );
		$status = get_post_meta( get_the_ID(), 'wp_syndicate-default-post-status', true );
		$post_types = get_post_types( array( 'public' => true ) );
		$post_type = get_post_meta( get_the_ID(), 'wp_syndicate-default-post-type', true );
		$registration_method = get_post_meta( get_the_ID(), 'wp_syndicate-registration-method', true );
		$user_id = get_post_meta( get_the_ID(), 'wp_syndicate-basic-auth-user', true );
		$password = get_post_meta( get_the_ID(), 'wp_syndicate-basic-auth-pass', true );

?>
<table class="form-table">
<tr><th><?php esc_html_e( 'feed URL', WPSYND_DOMAIN ) ?></th><td><input type="text" name="wp_syndicate-feed-url" size="40" value="<?php echo esc_attr( $feed_url ); ?>" /></td></tr>
<tr><th><?php esc_html_e( 'feed retrieve term', WPSYND_DOMAIN ) ?></th><td><input type="number" step="1" min="1" max="999" name="wp_syndicate-feed-retrieve-term" size="20" value="<?php echo esc_attr( $feed_retrieve_term ); ?>" /> <?php esc_html_e( 'min', WPSYND_DOMAIN ) ?></td></tr>
<tr><th><?php esc_html_e( 'Author ID', WPSYND_DOMAIN ) ?></th><td><input type="text" name="wp_syndicate-author-id" size="7" value="<?php echo esc_attr( $author_id ); ?>" /></td></tr>
<tr><th><?php esc_html_e( 'Default Post Type', WPSYND_DOMAIN ) ?></th>
<td>
<select name="wp_syndicate-default-post-type">
<?php foreach ( $post_types as $key => $val ) : ?>
	<option <?php selected( $post_type, $key ); ?> value="<?php echo esc_attr( $key ); ?>"><?php echo esc_attr( $val ); ?></option>
<?php endforeach; ?>
</select>
</td></tr>
<tr><th><?php esc_html_e( 'Default Post Status', WPSYND_DOMAIN ) ?></th>
<td>
<select name="wp_syndicate-default-post-status">
<?php foreach ( $statuses as $key => $val ) : ?>
	<option <?php selected( $status, $key ); ?> value="<?php echo esc_attr( $key ); ?>"><?php echo esc_attr( $val ); ?></option>
<?php endforeach; ?>
</select>
</td></tr>
<tr><th><?php esc_html_e( 'Registration method', WPSYND_DOMAIN ) ?></th>
<td>
<select name="wp_syndicate-registration-method">
	<option <?php selected( $registration_method, 'insert' ); ?> value="insert">insert only</option>
	<option <?php selected( $registration_method, 'insert-or-update' ); ?> value="insert-or-update">insert or update</option>
</select>
</td></tr>
<tr><th><?php esc_html_e( 'Basic Auth User ID', WPSYND_DOMAIN ) ?></th><td><input type="text" name="wp_syndicate-basic-auth-user" size="40" value="<?php echo esc_attr( $user_id ); ?>" /></td></tr>
<tr><th><?php esc_html_e( 'Basic Auth Password', WPSYND_DOMAIN ) ?></th><td><input type="password" name="wp_syndicate-basic-auth-pass" size="40" value="<?php echo esc_attr( $password ); ?>" /></td></tr>
<?php do_action( 'wp_syndicate_add_metabox' ); ?>
</table>
<?php
	}

	public function meta_box_wp_syndicate_import_test($post) {
		submit_button( 'Save and Test Excute', 'primary', 'wp_syndicate_import_test_excute' );
	}

	public function save_meta_box( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || get_post_type() !== 'wp-syndicate' || ! current_user_can( 'edit_wp_syndicate', $post_id )
				|| ! isset( $_POST['wp_syndicate_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['wp_syndicate_meta_box_nonce'], 'wp_syndicate_meta_box' ) ) {
			return;
		}

		isset( $_POST['wp_syndicate-feed-url'] ) && update_post_meta( $post_id, 'wp_syndicate-feed-url', esc_url( $_POST['wp_syndicate-feed-url'] ) );
		isset( $_POST['wp_syndicate-feed-retrieve-term'] ) && update_post_meta( $post_id, 'wp_syndicate-feed-retrieve-term', absint( $_POST['wp_syndicate-feed-retrieve-term'] ) );
		isset( $_POST['wp_syndicate-author-id'] ) && update_post_meta( $post_id, 'wp_syndicate-author-id', absint( $_POST['wp_syndicate-author-id'] ) );
		isset( $_POST['wp_syndicate-default-post-status'] ) && update_post_meta( $post_id, 'wp_syndicate-default-post-status', trim( $_POST['wp_syndicate-default-post-status'] ) );
		isset( $_POST['wp_syndicate-default-post-type'] ) && update_post_meta( $post_id, 'wp_syndicate-default-post-type', trim( $_POST['wp_syndicate-default-post-type'] ) );
		isset( $_POST['wp_syndicate-registration-method'] ) && update_post_meta( $post_id, 'wp_syndicate-registration-method', trim( $_POST['wp_syndicate-registration-method'] ) );
		isset( $_POST['wp_syndicate-basic-auth-user'] ) && update_post_meta( $post_id, 'wp_syndicate-basic-auth-user', trim( $_POST['wp_syndicate-basic-auth-user'] ) );
		isset( $_POST['wp_syndicate-basic-auth-pass'] ) && update_post_meta( $post_id, 'wp_syndicate-basic-auth-pass', trim( $_POST['wp_syndicate-basic-auth-pass'] ) );

		if ( isset( $_POST['wp_syndicate_import_test_excute'] ) ) {
			remove_action( 'save_post', array( $this, 'save_meta_box' ) );
			$action = new WP_SYND_Action();
			$action->import( $post_id );
			add_action( 'save_post', array( $this, 'save_meta_box' ) );
		}
		
		do_action( 'wp_syndicate_save_meta_box', $post_id );
	}
}
new WP_SYNDICATE();
