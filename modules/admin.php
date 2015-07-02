<?php
add_action( 'admin_menu', 'wp_syndicate_admin_menu' );
function wp_syndicate_admin_menu() {
	add_options_page( 'WP Syndicate', 'WP Syndicate', 'manage_options', 'wp_syndicate', 'wp_syndicate_options_page' );
}

function wp_syndicate_options_page() {
?>
<div class="wrap">

<h2>WP Syndicate</h2>

<form action="options.php" method="post">
<?php settings_fields( 'wp_syndicate_options' ); ?>
<?php do_settings_sections( 'wp_syndicate' ); ?>

<p class="submit"><input name="Submit" type="submit" value="<?php esc_attr_e( 'save' ); ?>" class="button-primary" /></p>
</form>

</div>
<?php
}

add_action( 'admin_init', 'wp_syndicate_admin_init' );
function wp_syndicate_admin_init() {
	register_setting( 'wp_syndicate_options', 'wp_syndicate_options', 'wp_syndicate_options_validate' );

	add_settings_section( 'wp_syndicate_main', __( 'configuration', WPSYND_DOMAIN ), 'wp_syndicate_section_text', 'wp_syndicate' );

	add_settings_field( 'wp_syndicate_error_mail', __( 'error mail recipient', WPSYND_DOMAIN ), 'wp_syndicate_setting_error_mail',
	'wp_syndicate', 'wp_syndicate_main' );
	add_settings_field( 'wp_syndicate_delete_log_term', __( 'feed log delete term', WPSYND_DOMAIN ), 'wp_syndicate_setting_delete_log_term',
	'wp_syndicate', 'wp_syndicate_main' );
}

function wp_syndicate_section_text() {
}

function wp_syndicate_setting_error_mail() {
	$options = get_option( 'wp_syndicate_options' );

	echo '<input id="wp_syndicate_error_mail" name="wp_syndicate_options[error_mail]" size="40" type="text" value="' . esc_attr( $options['error_mail'] ) . '" />';
}

function wp_syndicate_setting_delete_log_term() {
	$options = get_option( 'wp_syndicate_options' );

	echo '<input id="wp_syndicate_delete_log_term" name="wp_syndicate_options[delete_log_term]" size="5" type="text" value="' . esc_attr( $options['delete_log_term'] ) . '" /> ' . esc_html( __( 'day', WPSYND_DOMAIN ) );
}

function wp_syndicate_options_validate( $input ) {
	$newinput = array();
	$newinput['error_mail'] = trim( $input['error_mail'] );
	$newinput['delete_log_term'] = absint( $input['delete_log_term'] );
	return $newinput;
}

?>
