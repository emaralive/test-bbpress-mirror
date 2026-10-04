<?php

/**
 * Tests for database value handling in admin upgrades.
 *
 * @group admin
 * @group tools
 */
class BBP_Tests_Admin_Upgrade_SQL extends BBP_UnitTestCase {

	private $groups_table;
	private $groupmeta_table;

	public function setUp(): void {
		parent::setUp();
		global $wpdb;

		$this->groups_table    = $wpdb->base_prefix . 'bp_groups';
		$this->groupmeta_table = $wpdb->base_prefix . 'bp_groups_groupmeta';
		$wpdb->query( "CREATE TEMPORARY TABLE `{$this->groups_table}` ( `id` bigint(20) unsigned NOT NULL, `status` varchar(20) NOT NULL, PRIMARY KEY (`id`) )" );
		$wpdb->query( "CREATE TEMPORARY TABLE `{$this->groupmeta_table}` ( `group_id` bigint(20) unsigned NOT NULL, `meta_key` varchar(100) NOT NULL, `meta_value` longtext NOT NULL )" );

		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/upgrade.php';
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$this->groupmeta_table}`" );
		$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$this->groups_table}`" );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 */
	public function test_group_forum_upgrade_matches_legacy_id_containing_quote() {
		global $wpdb;

		$forum_id  = $this->factory->forum->create();
		$legacy_id = "legacy'forum";
		update_post_meta( $forum_id, '_bbp_old_forum_id', $legacy_id );

		$wpdb->insert( $this->groups_table, array( 'id' => 1, 'status' => 'public' ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 2, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 1, 'meta_key' => 'forum_id', 'meta_value' => $legacy_id ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 2, 'meta_key' => 'forum_id', 'meta_value' => 'unrelated' ) );

		bbp_admin_upgrade_group_forum_relationships();

		$this->assertSame( (string) $forum_id, $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 1 ) ) );
		$this->assertSame( 'unrelated', $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 2 ) ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 */
	public function test_group_forum_upgrade_hides_private_group_forum() {
		global $wpdb;

		$forum_id = $this->factory->forum->create();
		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'private' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertSame( (string) $forum_id, $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 7 ) ) );
		$this->assertSame( array( 7 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );
		$this->assertSame( bbp_get_hidden_status_id(), bbp_get_forum_visibility( $forum_id ) );
	}
}
