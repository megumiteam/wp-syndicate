<?php

class WP_Syndicate_Import_Test extends WP_UnitTestCase {

	private $feeds = array(
						'sample-1' => 'https://raw.githubusercontent.com/megumiteam/wp-syndicate/master/tests/testdata/sample-1.xml',
						'sample-2' => 'https://raw.githubusercontent.com/megumiteam/wp-syndicate/master/tests/testdata/sample-2.xml',
						'sample-3' => 'https://raw.githubusercontent.com/megumiteam/wp-syndicate/master/tests/testdata/sample-3.xml',
						'sample-4' => 'https://raw.githubusercontent.com/megumiteam/wp-syndicate/master/tests/testdata/sample-4.xml',
						'sample-5' => 'https://raw.githubusercontent.com/megumiteam/wp-syndicate/master/tests/testdata/sample-5.xml',
						);
	private $action;

	public function setUp() {
		parent::setUp();
		$this->action = new WP_SYND_Action();
	}

	/**
	 * @取り込みの各項目が正しく取り込まれるかテスト
	 */
	function test_import() {

		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-1'] );

		$this->action->import( $feed_id );

		$posts = get_posts( array( 'posts_per_page' => 50 ) );
		$this->assertEquals( 1, count( $posts ) );

		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		// タイトル
		$this->assertEquals( 'sample-1', $post->post_title );
		// 本文
		$this->assertEquals( '<p>sample sample</p>' . "\n", $post->post_content );
		// 投稿ステータス
		$this->assertEquals( 'publish', $post->post_status );
		// アイキャッチ
		$this->assertRegExp( '/sample-1\.jpg$/', wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) ) );
		// 投稿者
		$this->assertEquals( '1', $post->post_author );
		// 投稿タイプ
		$this->assertEquals( 'post', $post->post_type );
		// 投稿日
		$this->assertEquals( '2015-06-26 12:00:00', $post->post_date );

		$logs = get_posts( array( 'post_type' => 'wp-syndicate-log' ) );
		$this->assertEquals( 1, count( $logs ) );
	}

	/**
	 * @privateでの取り込みテスト
	 */
	function test_import_status_private() {
		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-3'], 'private' );
		$this->action->import( $feed_id );
		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		$this->assertEquals( 'private', $post->post_status );
	}

	/**
	 * @draftでの取り込みテスト
	 */
	function test_import_status_draft() {
		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-3'], 'draft' );
		$this->action->import( $feed_id );
		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );

		$this->assertEquals( 'draft', $post->post_status );
	}

	/**
	 * @post_type=pageでの取り込みテスト
	 */
	function test_import_type_page() {
		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-3'], 'publish', 'page' );
		$this->action->import( $feed_id );
		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'page' );
		$this->assertEquals( 'page', $post->post_type );
	}

	/**
	 * @投稿者の取り込みテスト
	 */
	function test_import_author() {
		$key = 'test';
		$user_id = $this->factory->user->create();
		$feed_id = $this->create_data( $key, $this->feeds['sample-3'], 'publish', 'post', 'insert-or-update', $user_id );
		$this->action->import( $feed_id );
		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		$this->assertEquals( $user_id, $post->post_author );
	}

	/**
	 * @enclosureタグが無いときは本文1枚目の画像がアイキャッチに登録される
	 */
	function test_import_not_enclosure() {
		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-5'] );
		$this->action->import( $feed_id );
		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		// アイキャッチ
		$this->assertRegExp( '/sample-5\.jpg$/', wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) ) );
	}

	/**
	 * @insertモードの時は上書きされないテスト
	 */
	function test_import_insert() {
		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-3'], 'publish', 'post', 'insert' );
		$this->action->import( $feed_id );
		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		$this->assertEquals( 'sample-3', $post->post_title );

		update_post_meta( $feed_id, 'wp_syndicate-feed-url', $this->feeds['sample-4'] );
		$this->action->import( $feed_id );
		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		$this->assertEquals( 'sample-3', $post->post_title );

	}

	/**
	 * @insert or upddatモードの時は上書きされるテスト
	 */
	function test_import_inser_or_update() {
		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-3'], 'publish', 'post', 'insert-or-update' );
		$this->action->import( $feed_id );
		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		$this->assertEquals( 'sample-3', $post->post_title );

		update_post_meta( $feed_id, 'wp_syndicate-feed-url', $this->feeds['sample-4'] );
		$this->action->import( $feed_id );
		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		$this->assertEquals( 'sample-4', $post->post_title );
	}

	/**
	 * @同じ記事が重複して取り込まれないテスト
	 */
	function test_import_not_duplicate() {

		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-3'] );

		$this->action->import( $feed_id );
		$posts = get_posts( array( 'posts_per_page' => 50 ) );
		$this->assertEquals( 1, count( $posts ) );

		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		$this->assertEquals( 'sample-3', $post->post_title );
		$this->assertEquals( '<p>hoge</p>' . "\n", $post->post_content );
		$this->assertEquals( 'publish', $post->post_status );
		$this->assertEquals( '1', $post->post_author );
		$this->assertEquals( 'post', $post->post_type );
		$this->assertEquals( '2015-06-26 14:00:00', $post->post_date );

		$logs = get_posts( array( 'post_type' => 'wp-syndicate-log' ) );
		$this->assertEquals( 1, count( $logs ) );

		$this->action->import( $feed_id );
		$posts = get_posts( array( 'posts_per_page' => 50 ) );
		$this->assertEquals( 1, count( $posts ) );
		$logs = get_posts( array( 'post_type' => 'wp-syndicate-log' ) );
		$this->assertEquals( 2, count( $logs ) );

		update_post_meta( $feed_id, 'wp_syndicate-registration-method', 'insert-or-update' );
		$this->action->import( $feed_id );
		$posts = get_posts( array( 'posts_per_page' => 50 ) );
		$this->assertEquals( 1, count( $posts ) );
		$logs = get_posts( array( 'post_type' => 'wp-syndicate-log' ) );
		$this->assertEquals( 3, count( $logs ) );
	}

	/**
	 * @insert or upddatモードの各項目が正しく更新出来ているかテスト
	 */
	function test_import_update() {

		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-1'], 'publish', 'post', 'insert-or-update' );

		$this->action->import( $feed_id );

		$posts = get_posts( array( 'posts_per_page' => 50 ) );
		$this->assertEquals( 1, count( $posts ) );

		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		// タイトル
		$this->assertEquals( 'sample-1', $post->post_title );
		// 本文
		$this->assertEquals( '<p>sample sample</p>' . "\n", $post->post_content );
		// 投稿ステータス
		$this->assertEquals( 'publish', $post->post_status );
		// アイキャッチ
		$this->assertRegExp( '/sample-1\.jpg$/', wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) ) );
		// 投稿者
		$this->assertEquals( '1', $post->post_author );
		// 投稿タイプ
		$this->assertEquals( 'post', $post->post_type );
		// 投稿日
		$this->assertEquals( '2015-06-26 12:00:00', $post->post_date );

		$logs = get_posts( array( 'post_type' => 'wp-syndicate-log' ) );
		$this->assertEquals( 1, count( $logs ) );

		update_post_meta( $feed_id, 'wp_syndicate-feed-url', $this->feeds['sample-2'] );

		$this->action->import( $feed_id );

		$posts = get_posts( array( 'posts_per_page' => 50 ) );
		$this->assertEquals( 1, count( $posts ) );

		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		// タイトル
		$this->assertEquals( 'sample-2', $post->post_title );
		// 本文
		$this->assertEquals( '<p>sample sample sample</p>' . "\n", $post->post_content );
		// 投稿ステータス
		$this->assertEquals( 'publish', $post->post_status );
		// アイキャッチ
		$this->assertRegExp( '/sample-2\.jpg$/', wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) ) );
		// 投稿者
		$this->assertEquals( '1', $post->post_author );
		// 投稿タイプ
		$this->assertEquals( 'post', $post->post_type );
		// 投稿日
		$this->assertEquals( '2015-06-26 13:00:00', $post->post_date );

		$logs = get_posts( array( 'post_type' => 'wp-syndicate-log' ) );
		$this->assertEquals( 2, count( $logs ) );
	}

	/**
	 * @insert or upddatモードの上書き時にpost_status, authorは変更されないことをテスト
	 */
	function test_import_update_meta() {

		$key = 'test';
		$feed_id = $this->create_data( $key, $this->feeds['sample-3'], 'publish', 'post', 'insert-or-update' );

		$this->action->import( $feed_id );
		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		// タイトル
		$this->assertEquals( 'sample-3', $post->post_title );
		// 本文
		$this->assertEquals( '<p>hoge</p>' . "\n", $post->post_content );
		// 投稿ステータス
		$this->assertEquals( 'publish', $post->post_status );
		// 投稿者
		$this->assertEquals( 1, $post->post_author );
		// 投稿タイプ
		$this->assertEquals( 'post', $post->post_type );
		// 投稿日
		$this->assertEquals( '2015-06-26 14:00:00', $post->post_date );

		$user_id = $this->factory->user->create();
		wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft', 'post_author' => $user_id ) );
		update_post_meta( $feed_id, 'wp_syndicate-feed-url', $this->feeds['sample-4'] );

		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );

		$this->action->import( $feed_id );

		$posts = get_posts( array( 'posts_per_page' => 50, 'post_type' => 'any' ) );
		$this->assertEquals( 1, count( $posts ) );

		$post = get_page_by_path( sanitize_title( $key.'_100' ), OBJECT, 'post' );
		// タイトル
		$this->assertEquals( 'sample-4', $post->post_title );
		// 本文
		$this->assertEquals( '<p>hoge fuga</p>' . "\n", $post->post_content );
		// 投稿ステータス
		$this->assertEquals( 'draft', $post->post_status );
		// 投稿者
		$this->assertEquals( $user_id, $post->post_author );
		// 投稿タイプ
		$this->assertEquals( 'post', $post->post_type );
		// 投稿日
		$this->assertEquals( '2015-06-26 15:00:00', $post->post_date );

		$logs = get_posts( array( 'post_type' => 'wp-syndicate-log' ) );
		$this->assertEquals( 2, count( $logs ) );
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
