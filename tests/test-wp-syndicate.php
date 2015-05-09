<?php

class WP_Syndicate_Test extends WP_UnitTestCase {

	private $feed = 'https://wordpress.org/news/feed/';
	private $action;

	public function setUp() {
		parent::setUp();
		$this->action = new WP_SYND_Action();	
	}
	/**
	 * @wp_cronにスケジュールが登録されるかのテスト
	 */
	function test_set_import_schedule() {
		$key = 'test';
		$post_id = $this->factory->post->create(array('post_type' => 'wp-syndicate', 'post_name' => $key));
		add_post_meta( $post_id, 'wp_syndicate-feed-url', $this->feed );
		add_post_meta( $post_id, 'wp_syndicate-feed-retrieve-term', 5 );
		add_post_meta( $post_id, 'wp_syndicate-author-id', 1 );
		add_post_meta( $post_id, 'wp_syndicate-default-post-status', 'publish' );
		add_post_meta( $post_id, 'wp_syndicate-default-post-type', 'post' );
		add_post_meta( $post_id, 'wp_syndicate-registration-method', 'insert' );

		$event = wp_next_scheduled( 'wp_syndicate_' . $key . '_import', array( $post_id ) );

		$this->assertInternalType( 'int', $event );
		$this->assertLessThanOrEqual( time() + 5*60, $event );
	}

	/**
	 * @取り込みが正しく実施できるかのテスト
	 */
	function test_import() {
	
		$key = 'test2';
		$post_id = $this->factory->post->create(array('post_type' => 'wp-syndicate', 'post_name' => $key));
		add_post_meta( $post_id, 'wp_syndicate-feed-url', $this->feed );
		add_post_meta( $post_id, 'wp_syndicate-feed-retrieve-term', 5 );
		add_post_meta( $post_id, 'wp_syndicate-author-id', 1 );
		add_post_meta( $post_id, 'wp_syndicate-default-post-status', 'publish' );
		add_post_meta( $post_id, 'wp_syndicate-default-post-type', 'post' );
		add_post_meta( $post_id, 'wp_syndicate-registration-method', 'insert' );

		$this->action->import($post_id);
		
		$rss = fetch_feed( $this->feed );
		$rss_items = $rss->get_items(0, 50);
		
		$rss_title_array = array();
		foreach ( $rss_items as $item ) {
			$rss_title_array[] = $item->get_title();
		}
		
		$post_title_array = array();
		
		$posts = get_posts( array('posts_per_page' => 50) );
		foreach ( $posts as $post ) {
			$post_title_array[] = $post->post_title;
		}
		$this->assertEquals( $rss_title_array, $post_title_array );
		
		$logs = get_posts( array('post_type' => 'wp-syndicate-log') );

		$this->assertEquals( 1, count($logs) );
	}

	/**
	 * @取り込みが重複しないかのテスト
	 */
	function test_import_not_duplicate() {
	
		$key = 'test3';
		$post_id = $this->factory->post->create(array('post_type' => 'wp-syndicate', 'post_name' => $key));
		add_post_meta( $post_id, 'wp_syndicate-feed-url', $this->feed );
		add_post_meta( $post_id, 'wp_syndicate-feed-retrieve-term', 5 );
		add_post_meta( $post_id, 'wp_syndicate-author-id', 1 );
		add_post_meta( $post_id, 'wp_syndicate-default-post-status', 'publish' );
		add_post_meta( $post_id, 'wp_syndicate-default-post-type', 'post' );
		add_post_meta( $post_id, 'wp_syndicate-registration-method', 'insert' );

		$this->action->import($post_id);
		$this->action->import($post_id);
		
		$rss = fetch_feed( $this->feed );
		$rss_items = $rss->get_items(0, 50);
		
		$rss_title_array = array();
		foreach ( $rss_items as $item ) {
			$rss_title_array[] = $item->get_title();
		}
		
		$post_title_array = array();
		$posts = get_posts( array('posts_per_page' => 50) );
		foreach ( $posts as $post ) {
			$post_title_array[] = $post->post_title;
		}
		$this->assertEquals( $rss_title_array, $post_title_array );
		
		update_post_meta( $post_id, 'wp_syndicate-registration-method', 'insert-or-update' );
		
		$this->action->import($post_id);
		
		$post_title_array = array();
		$posts = get_posts( array('posts_per_page' => 50) );
		foreach ( $posts as $post ) {
			$post_title_array[] = $post->post_title;
		}
		$this->assertEquals( $rss_title_array, $post_title_array );
	}
	
	/**
	 * @取り込みの設定をゴミ箱に変更すればスケジュールから削除される
	 */
	function test_set_import_schedule_to_trash() {
		$key = 'test4';
		$post_id = $this->factory->post->create(array('post_type' => 'wp-syndicate', 'post_name' => $key));
		add_post_meta( $post_id, 'wp_syndicate-feed-url', $this->feed );
		add_post_meta( $post_id, 'wp_syndicate-feed-retrieve-term', 5 );
		add_post_meta( $post_id, 'wp_syndicate-author-id', 1 );
		add_post_meta( $post_id, 'wp_syndicate-default-post-status', 'publish' );
		add_post_meta( $post_id, 'wp_syndicate-default-post-type', 'post' );
		add_post_meta( $post_id, 'wp_syndicate-registration-method', 'insert' );

		$event = wp_next_scheduled( 'wp_syndicate_' . $key . '_import', array( $post_id ) );

		$this->assertInternalType( 'int', $event );
		$this->assertLessThanOrEqual( time() + 5*60, $event );
		
		wp_trash_post( $post_id );
		
		$event = wp_next_scheduled( 'wp_syndicate_' . $key . '_import', array( $post_id ) );
		$this->assertEquals( false, $event );
	}

	/**
	 * @wp_cronにログの削除スケジュールが登録されるかのテスト
	 */
	function test_set_delete_log_schedule() {

		//$event = wp_next_scheduled( 'wp_syndicate_log_delete_log' );

		//$this->assertInternalType( 'int', $event );
	}

// todo
//- クーロンにログの削除イベントが登録される
//- 取り込みのステータスチェック
//- プラグインを無効化したらイベントが削除される

}

