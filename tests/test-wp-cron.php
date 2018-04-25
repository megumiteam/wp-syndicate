<?php

class WP_Syndicate_WP_Cron_Test extends WP_UnitTestCase {

	private $feeds = array( 'sample-1' => 'https://raw.githubusercontent.com/megumiteam/wp-syndicate/master/tests/testdata/sample-1.xml' );
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
		$feed_id = $this->create_data( $key, $this->feeds['sample-1'] );

		$event = wp_next_scheduled( 'wp_syndicate_' . $key . '_import', array( $feed_id ) );

		$this->assertInternalType( 'int', $event );
		$this->assertLessThanOrEqual( time() + 5 * 60, $event );
	}

	/**
	 * @取り込みの設定をゴミ箱に変更すればスケジュールから削除される。復元で再登録
	 */
	function test_set_import_schedule_to_trash() {
		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-1'] );

		$event = wp_next_scheduled( 'wp_syndicate_' . $key . '_import', array( $feed_id ) );

		$this->assertInternalType( 'int', $event );
		$this->assertLessThanOrEqual( time() + 5 * 60, $event );

		wp_trash_post( $feed_id );

		$event = wp_next_scheduled( 'wp_syndicate_' . $key . '_import', array( $feed_id ) );
		$this->assertEquals( false, $event );

		wp_untrash_post( $feed_id );
		$event = wp_next_scheduled( 'wp_syndicate_' . $key . '_import', array( $feed_id ) );
		$this->assertEquals( true, $event );
	}

	function create_data( $key, $feed, $post_status = 'publish', $post_type = 'post', $mode = 'insert', $user_id = 1 ) {
		$post_id = $this->factory->post->create( array( 'post_type' => 'wp-syndicate', 'post_name' => $key ) );
		add_post_meta( $post_id, 'wp_syndicate-feed-url', $feed );
		add_post_meta( $post_id, 'wp_syndicate-feed-retrieve-term', 5 );
		add_post_meta( $post_id, 'wp_syndicate-author-id', $user_id );
		add_post_meta( $post_id, 'wp_syndicate-default-post-status', $post_status );
		add_post_meta( $post_id, 'wp_syndicate-default-post-type', $post_type );
		add_post_meta( $post_id, 'wp_syndicate-registration-method', $mode );

		return $post_id;
	}
}
