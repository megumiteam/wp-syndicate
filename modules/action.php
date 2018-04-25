<?php
class WP_SYND_Action {
	private $args = array(
					'post_type' => 'wp-syndicate',
					'posts_per_page' => -1,
					'post_status' => 'publish'
				);
	private $post;
	private $host;
	private $match_count = 0;
	private $media_id;
	private $is_enclosure = false;
	private $enclosure_url;


	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'publish_to_trash', array( $this, 'publish_to_trash' ));
		add_action( 'save_post', array( $this, 'set_event' ) );
		add_action( 'admin_head', array( $this, 'ping' ) );
		add_action( 'template_redirect', array( $this, 'ping' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		$posts = get_posts($this->args);

		if ( empty($posts) )
			return;

		foreach ( $posts as $post ) {
			add_action( 'wp_syndicate_' . $post->post_name . '_import', array( $this, 'import' ) );
		}
	}

	public function publish_to_trash($post) {
		if ( 'wp-syndicate' !== $post->post_type )
			return;

		$hook = 'wp_syndicate_' .  str_replace( '__trashed', '', $post->post_name ) . '_import';
		if ( wp_next_scheduled( $hook, array( $post->ID )) )
			var_dump(wp_clear_scheduled_hook( $hook, array( $post->ID ) ));
	}

	public function ping() {
		$posts = get_posts($this->args);
		if ( empty($posts) )
			return;

		foreach ( $posts as $post ) {
			$key = $post->post_name;
			$hook = 'wp_syndicate_' . $key . '_import';

			if ( !wp_next_scheduled( $hook, array( $post->ID ) ) ) {
				$this->set_event($post->ID);
				$subject = '[' . get_bloginfo( 'name' ) . ']' . __( 'WP Cron Error', WPSYND_DOMAIN );
				$msg  = sprintf( __( '%s of WP Cron restart, because it stopped.', WPSYND_DOMAIN ), $hook ) . "\n". __( 'action time', WPSYND_DOMAIN ). ':' . date_i18n('Y/m/d:H:i:s') . "\n\n\n";
				$msg .= admin_url();
				WP_SYND_logger::get_instance()->error( $subject, $msg );
				$options = get_option( 'wp_syndicate_options' );
				wp_mail( $options['error_mail'], $subject, $msg );
			}
		}
	}

	public function cron_schedules($schedules) {
		$posts = get_posts($this->args);

		if ( empty($posts) ) {
			return $schedules;
		}

		foreach ( $posts as $post ) {
			$key = 'wp_syndicate_' . $post->post_name;
			$interval_min = get_post_meta( $post->ID, 'wp_syndicate-feed-retrieve-term', true );
			$interval = intval($interval_min)*60;
			$display = get_the_title( $post->ID );
			$schedules[$key] = array( 'interval' => $interval, 'display' => $display );
		}

		return $schedules;
	}

	public function set_event($post_id) {

		if ( wp_is_post_revision($post_id) )
			return;

		if ( 'wp-syndicate' != get_post_type($post_id) )
			return;

		$post = get_post( $post_id );
		if ( !is_object($post) )
			return;

		$key = $post->post_name;
		$interval_min = get_post_meta( $post_id, 'wp_syndicate-feed-retrieve-term', true );
		$interval = intval($interval_min)*60;
		$action_time = time() + $interval;

		$hook = 'wp_syndicate_' . $key . '_import';
		$event = 'wp_syndicate_' . $key;

		if ( wp_next_scheduled( $hook, array( $post_id )) )
			wp_clear_scheduled_hook( $hook, array( $post_id ) );

		wp_schedule_event( $action_time, $event, $hook, array( $post_id )  );
		spawn_cron( $action_time );
	}

	public function import($post_id) {
		global $allowedposttags;

		$allowedposttags = apply_filters( 'wp_syndicate_allowedposttags', $allowedposttags );

		$options = get_option( 'wp_syndicate_options' );
		$post = get_post($post_id);

		if ( !is_object($post) )
			return;

		$this->media_id = $post_id;
		$feed_url = html_entity_decode(get_post_meta( $post_id, 'wp_syndicate-feed-url', true ), ENT_QUOTES, 'UTF-8');

		add_action('wp_feed_options', function(&$feed){
			$feed->set_timeout(30);
			$feed->force_feed(true);
			$feed->enable_cache(false);
		}, 10);

		add_filter( 'wp_feed_cache_transient_lifetime' , array( $this, 'return_0' ) );
		$rss = fetch_feed( $feed_url );
		remove_filter( 'wp_feed_cache_transient_lifetime' , array( $this, 'return_0' ) );
		if ( is_wp_error( $rss ) ) {
			$subject = '[' . get_bloginfo( 'name' ) . ']' . __( 'feed import failed', WPSYND_DOMAIN );
			$msg  = sprintf( __( 'An error occurred at a feed retrieval of %s', WPSYND_DOMAIN ), $post->post_name ) . "\n". __( 'action time', WPSYND_DOMAIN ). ':' . date_i18n('Y/m/d:H:i:s') . "\n\n\n";
			$msg .= __( 'below error message', WPSYND_DOMAIN ) . "\n";
			$msg .= $rss->get_error_message()."\n\n";
			$msg .= __( 'feed URL', WPSYND_DOMAIN ) . ':' . $feed_url;
			$error_post_id = WP_SYND_logger::get_instance()->error( $subject, $msg );
			$msg .= admin_url('/post.php?post=' . $error_post_id . '&action=edit');
			wp_mail( $options['error_mail'], $subject, $msg );
			return;
		}

		$url = parse_url($rss->get_base());
		$this->host = $url['host'];

		$rss_items = $rss->get_items(0, 50);
		$post_ids = array();
		$flg = true;
		$registration_method = get_post_meta( $post_id, 'wp_syndicate-registration-method', true );
		$post_type = get_post_meta( $post_id, 'wp_syndicate-default-post-type', true );
		foreach ( $rss_items as $item ) {
			$this->is_enclosure = false;

			//投稿ID取得
			$slug = $post->post_name . '_' . $item->get_id();
			$set_post = get_page_by_path( sanitize_title($slug), OBJECT, $post_type );
			$set_post_id = $set_post == null ? '' : $set_post->ID;

			if ( empty($set_post_id) ) {
				global $wpdb;
				$db_ret = $wpdb->get_row( $wpdb->prepare( "SELECT count(1) as cnt FROM $wpdb->postmeta WHERE meta_key='%s'", $slug) );
				if ( $db_ret === null || $db_ret->cnt !== '0' )
					continue;
			}

			if ( $registration_method == 'insert' && is_object($set_post) ) {
				continue;
			}

			$updated = empty($set_post_id) ? false : true;
			$is_skip = apply_filters( 'wp_syndicate_is_skip', false, $item, $updated, $set_post_id, $post_id );
			if ( $is_skip ) {
				continue;
			}

			$post_args = array(
								'ID' => $set_post_id,
								'post_name' => $slug,
								'post_date' => apply_filters( 'wp_syndicate_get_date', $item->get_date('Y/m/d H:i:s'), $post_id ),
 								'post_title' => apply_filters( 'wp_syndicate_get_title', $item->get_title(), $post_id ),
								'post_content' => '',
							);
			if ( !$updated ) {
				$post_args['post_author'] = get_post_meta( $post_id, 'wp_syndicate-author-id', true );
				$post_args['post_status'] = get_post_meta( $post_id, 'wp_syndicate-default-post-status', true );
				$post_args['post_type'] = get_post_meta( $post_id, 'wp_syndicate-default-post-type', true );
			}
			$this->post = new wp_post_helper($post_args);

			//画像の登録
			if ( $updated ) {
				$images = get_attached_media( 'image', $set_post_id );
				if ( is_array( $images ) ) {
					foreach ( $images as $image ) {
						wp_delete_attachment( $image->ID );
					}
				}
			}

			$content = apply_filters( 'the_content', $item->get_content() );
			if ( $item->get_enclosure() && !empty($item->get_enclosure()->link) ) {
				$this->is_enclosure = true;
				$this->set_enclosure( $item->get_enclosure()->link );
			}

			$this->match_count = 0;
			$content = preg_replace_callback( '#<img([^>]*)src=["\']([^"\']+)["\']([^>]*)>#i',  array($this, 'update_link'), $content, -1 );
			$this->match_count = 0;
			$this->post->set(array(
								'post_content' => apply_filters( 'wp_syndicate_get_content', $content, $post_id )
							));
			$this->post->add_meta( 'wp_syndicate-origin-of-syndication-slug', $post->post_name, true );
			$this->post->add_meta( 'wp_syndicate-origin-of-syndication-siteurl', $this->host, true );

			$update_post_id = $this->post->update();
			if ( !$update_post_id ) {
				$flg = false;
			} else {
				update_post_meta( $update_post_id, $slug, 1 );
				do_action( 'wp_syndicate_save_post', $update_post_id, $item, $updated, $post_id );
				$post_ids[] = $update_post_id;
			}
		}

		if ( $flg ) {
			$subject = '[' . get_bloginfo( 'name' ) . ']' . __( 'feed import success', WPSYND_DOMAIN );
			$msg = __( 'feed URL:', WPSYND_DOMAIN ) . $feed_url . "\n";
			$msg .= sprintf( __( 'Feed acquisition completion of %s', WPSYND_DOMAIN ), $post->post_name ) . "\n" . __( 'action time', WPSYND_DOMAIN ). ':' . date_i18n('Y/m/d:H:i:s') . "\n\n\n";
			$msg .= __( 'below ID data updates', WPSYND_DOMAIN ) . "\n";
			$msg .= implode( "\n", $post_ids );
			WP_SYND_logger::get_instance()->success( $subject, $msg );
		} else {
			$subject = '[' . get_bloginfo( 'name' ) . ']' . __( 'feed import failed', WPSYND_DOMAIN );
			$msg = __( 'feed URL:', WPSYND_DOMAIN ) . $feed_url . "\n";
			$msg .= sprintf( __( 'Failed to some data registration of %s', WPSYND_DOMAIN ), $post->post_name ) . "\n". __( 'action time', WPSYND_DOMAIN ). ':' . date_i18n('Y/m/d:H:i:s') . "\n\n\n";
			$msg .= __( 'below ID data updates', WPSYND_DOMAIN ) . "\n";
			$msg .= implode( "\n", $post_ids );
			$error_post_id = WP_SYND_logger::get_instance()->error( $subject, $msg );
			$msg .= admin_url('/post.php?post=' . $error_post_id . '&action=edit');
			wp_mail( $options['error_mail'], $subject, $msg );
		}

	}

	public function return_0( $seconds ) {
		return 0;
	}

	public function update_link( $matches ) {

		if ( is_array($matches) && array_key_exists(2, $matches) && isset($this->post) && is_object($this->post) && is_a($this->post, 'wp_post_helper') ) {
			$args    = array();
			$user    = get_post_meta( $this->media_id, 'wp_syndicate-basic-auth-user', true );
			$pass    = get_post_meta( $this->media_id, 'wp_syndicate-basic-auth-pass', true );
			if ( !empty($user) && !empty($pass) ) {
				$args = array(
					'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ) )
				);
			}

			$args['timeout'] = 30;
			if ( $media = remote_get_file($matches[2], '', $args) ) {

				if ( $this->is_enclosure === true ) {
					$thumnail_flg = false;
				} else {
					$thumnail_flg = $this->match_count > 0 ? false : true;
				}

				$url = preg_split( '/wp-content/', $media );
				$url = home_url( 'wp-content' . $url[1] );

				if ( $url == $this->enclosure_url ) {
					$thumnail_flg = true;
				}

				$this->post->add_media($media, '', '', '', $thumnail_flg);
				$this->match_count++;

				return apply_filters( 'wp_syndicate_return_img', '<img' . $matches[1] . 'src="' . $url . '"' . $matches[3] . '>', $thumnail_flg, $url, $this->enclosure_url, $this->match_count );
			} else {
				return $matches[0];
			}
		}
		return $matches[0];
	}

	public function set_enclosure($link) {
		if ( !empty($link) && is_object($this->post) && is_a($this->post, 'wp_post_helper') ) {
			$args    = array();
			$user    = get_post_meta( $this->media_id, 'wp_syndicate-basic-auth-user', true );
			$pass    = get_post_meta( $this->media_id, 'wp_syndicate-basic-auth-pass', true );
			if ( !empty($user) && !empty($pass) ) {
				$args = array(
					'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ) )
				);
			}

			$args['timeout'] = 30;
			if ( $media = remote_get_file($link, '', $args) ) {
				$this->post->add_media($media, '', '', '', true);
				$url = preg_split( '/wp-content/', $media );
				$url = home_url( 'wp-content' . $url[1] );
				$this->enclosure_url = $url;
			}
		}
	}
}
new WP_SYND_Action();
