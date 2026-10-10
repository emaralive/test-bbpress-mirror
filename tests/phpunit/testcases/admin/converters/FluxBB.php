<?php

/**
 * Tests for the FluxBB converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_FluxBB extends BBP_UnitTestCase {

	/**
	 * @var FluxBB
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/FluxBB.php';

		$reflection      = new ReflectionClass( 'FluxBB' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * @covers FluxBB::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_upgrade_fields_are_mapped_for_standard_schema() {
		$this->converter->setup_globals();

		$field_map     = $this->get_field_map( $this->converter );
		$class_mapping = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );
		$salt_mapping  = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'salt' ) );

		$this->assertCount( 1, $class_mapping );
		$this->assertSame( 'user', reset( $class_mapping )['to_type'] );
		$this->assertSame( 'FluxBB', reset( $class_mapping )['default'] );
		$this->assertCount( 0, $salt_mapping );
	}

	/**
	 * @covers FluxBB::setup_globals
	 * @ticket BBP3684
	 */
	public function test_legacy_salt_is_mapped_when_source_field_exists() {
		$source_db = new class() {
			public $prefix      = 'flux_';
			public $connected   = false;
			public $connections = 0;

			public function db_connect( $allow_bail = true ) {
				$this->connected = true;
				++$this->connections;

				return true;
			}

			public function get_var( $query ) {
				return $this->connected && "SHOW COLUMNS FROM flux_users LIKE 'salt'" === $query
					? 'salt'
					: null;
			}
		};
		$set_opdb  = Closure::bind(
			function( $converter, $database ) {
				$converter->opdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_opdb( $this->converter, $source_db );
		$this->converter->setup_globals();

		$field_map = $this->get_field_map( $this->converter );
		$this->assertSame( 0, $source_db->connections );
		$this->assertCount( 0, wp_filter_object_list( $field_map, array( 'from_fieldname' => 'salt' ) ) );

		$this->converter->convert_table( 'tags', 1 );

		$field_map    = $this->get_field_map( $this->converter );
		$salt_mapping = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'salt' ) );

		$this->assertCount( 1, $salt_mapping );
		$this->assertSame( 'user', reset( $salt_mapping )['to_type'] );
		$this->assertSame( '', reset( $salt_mapping )['to_fieldname'] );
		$this->assertSame( 1, $source_db->connections );
	}

	/**
	 * @covers FluxBB::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_hash_and_salt() {
		$this->assertSame(
			array(
				'hash' => '4da3e69b6b919a4f6e010aaac999a3de40d4f5d3',
				'salt' => "a\\\\b\\'",
			),
			$this->converter->callback_savepass(
				'4da3e69b6b919a4f6e010aaac999a3de40d4f5d3',
				array( 'salt' => "a\\b'" )
			)
		);
	}

	/**
	 * @covers FluxBB::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_survives_user_meta_storage() {
		$user_id  = $this->factory->user->create();
		$metadata = $this->converter->callback_savepass(
			'4da3e69b6b919a4f6e010aaac999a3de40d4f5d3',
			array( 'salt' => "a\\b'" )
		);

		update_user_meta( $user_id, '_bbp_password', $metadata );

		$this->assertSame(
			array(
				'hash' => '4da3e69b6b919a4f6e010aaac999a3de40d4f5d3',
				'salt' => "a\\b'",
			),
			get_user_meta( $user_id, '_bbp_password', true )
		);
	}

	/**
	 * @covers FluxBB::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_normalizes_missing_and_null_salts() {
		$hash = '0bcf1df3cb81df3908d74d46b7fa9dd036b3b3c2';

		$this->assertSame(
			array( 'hash' => $hash, 'salt' => '' ),
			$this->converter->callback_savepass( $hash, array() )
		);
		$this->assertSame(
			array( 'hash' => $hash, 'salt' => '' ),
			$this->converter->callback_savepass( $hash, array( 'salt' => null ) )
		);
	}

	/**
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_fluxbb_1_5_sha1_hash() {
		$pass = serialize(
			array(
				'hash' => '0bcf1df3cb81df3908d74d46b7fa9dd036b3b3c2',
				'salt' => ''
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_treats_null_salt_as_unsalted() {
		$pass = serialize(
			array(
				'hash' => '0bcf1df3cb81df3908d74d46b7fa9dd036b3b3c2',
				'salt' => null
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_fluxbb_1_3_salted_sha1_hash() {
		$pass = serialize(
			array(
				'hash' => '4da3e69b6b919a4f6e010aaac999a3de40d4f5d3',
				'salt' => 'abc12345'
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_fluxbb_1_2_md5_hash() {
		$pass = serialize(
			array(
				'hash' => '5c8315e93cb86e3fcbf9a92673545161',
				'salt' => ''
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_invalid_metadata() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array(), 'salt' => 'salt' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'hash', 'salt' => array() ) ) ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'fluxbb-imported-user' ) );
		$set_wpdb = Closure::bind(
			function( $converter, $database ) {
				$converter->wpdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_wpdb( $this->converter, $wpdb );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => '0bcf1df3cb81df3908d74d46b7fa9dd036b3b3c2',
				'salt' => ''
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'FluxBB' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'fluxbb-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers FluxBB::callback_forum_reply_count
	 * @ticket 3370
	 */
	public function test_callback_forum_reply_count_excludes_topic_starters() {
		$this->assertSame( 2, $this->converter->callback_forum_reply_count( 3, array( 'num_topics' => 1 ) ) );
		$this->assertSame( 0, $this->converter->callback_forum_reply_count( 1, array( 'num_topics' => 2 ) ) );
	}

	/**
	 * @covers FluxBB::callback_topic_reply_count
	 * @ticket 3370
	 */
	public function test_callback_topic_reply_count_preserves_fluxbb_count() {
		$this->assertSame( 2, $this->converter->callback_topic_reply_count( 2 ) );
		$this->assertSame( 0, $this->converter->callback_topic_reply_count( -1 ) );
	}

	/**
	 * @covers FluxBB::setup_globals
	 * @covers BBP_Converter_Base::convert_table
	 * @ticket 3370
	 */
	public function test_imports_replies_without_importing_the_topic_starter_as_a_reply() {
		global $wpdb;

		$source_prefix = $wpdb->prefix . 'flux_';
		$tables        = array( 'forums', 'topics', 'posts', 'users' );
		$converter     = new FluxBB();
		$set_source_db = Closure::bind(
			function( $instance, $database ) {
				$instance->opdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$source_db         = new class( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ) extends BBP_Converter_DB {
			private $test_connected = false;

			public function db_connect( $allow_bail = true ) {
				if ( $this->test_connected ) {
					return true;
				}

				$this->test_connected = parent::db_connect( $allow_bail );
				return $this->test_connected;
			}
		};
		$source_db->prefix = $source_prefix;
		$source_db->db_connect( false );
		$set_source_db( $converter, $source_db );

		foreach ( $tables as $table ) {
			$source_db->query( "DROP TABLE IF EXISTS {$source_prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		try {
			$source_db->query( "CREATE TABLE {$source_prefix}forums (id int, num_topics int, num_posts int, forum_name varchar(255), forum_desc text, disp_position int)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$source_db->query( "CREATE TABLE {$source_prefix}topics (id int, num_replies int, forum_id int, first_post_id int, subject varchar(255), sticky int, posted int, closed int)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$source_db->query( "CREATE TABLE {$source_prefix}posts (id int, topic_id int, poster_id int, poster_ip varchar(45), message text, posted int)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$source_db->query( "CREATE TABLE {$source_prefix}users (id int)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$source_db->insert( $source_prefix . 'forums', array(
				'id'            => 10,
				'num_topics'    => 1,
				'num_posts'     => 3,
				'forum_name'    => 'FluxBB forum',
				'forum_desc'    => 'Fixture forum',
				'disp_position' => 1,
			) );
			$source_db->insert( $source_prefix . 'topics', array(
				'id'            => 20,
				'num_replies'   => 2,
				'forum_id'      => 10,
				'first_post_id' => 100,
				'subject'       => 'FluxBB topic',
				'sticky'        => 0,
				'posted'        => 1600000000,
				'closed'        => 0,
			) );

			foreach ( array(
				array( 100, 'Topic starter' ),
				array( 101, 'First reply' ),
				array( 102, 'Second reply' ),
			) as $post ) {
				$source_db->insert( $source_prefix . 'posts', array(
					'id'        => $post[0],
					'topic_id'  => 20,
					'poster_id' => 0,
					'poster_ip' => '127.0.0.1',
					'message'   => $post[1],
					'posted'    => 1600000000 + $post[0],
				) );
			}

			$this->assertFalse( $converter->convert_forums( 0 ), get_option( '_bbp_converter_query' ) . ' / ' . $source_db->last_error );
			$this->assertFalse( $converter->convert_topics( 0 ) );
			$this->assertFalse( $converter->convert_replies( 0 ) );

			$forum_id = (int) $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_bbp_old_forum_id' AND meta_value = '10'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$topic_id = (int) $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_bbp_old_topic_id' AND meta_value = '20'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$replies  = get_posts( array(
				'post_type'      => bbp_get_reply_post_type(),
				'post_parent'    => $topic_id,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			) );

			$this->assertNotEmpty( $forum_id );
			$this->assertNotEmpty( $topic_id );
			$this->assertSame( array( 'First reply', 'Second reply' ), wp_list_pluck( $replies, 'post_content' ) );
			$this->assertSame( 2, bbp_get_topic_reply_count( $topic_id, true ) );
			$this->assertSame( 2, bbp_get_forum_reply_count( $forum_id, false, true ) );
		} finally {
			foreach ( $tables as $table ) {
				$source_db->query( "DROP TABLE IF EXISTS {$source_prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
	}

	/**
	 * Get a converter's protected field map.
	 *
	 * @param FluxBB $converter Converter instance.
	 * @return array Converter field map.
	 */
	private function get_field_map( $converter ) {
		$get_field_map = Closure::bind(
			function( $instance ) {
				return $instance->field_map;
			},
			null,
			'BBP_Converter_Base'
		);

		return $get_field_map( $converter );
	}
}
