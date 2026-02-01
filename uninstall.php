<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Cnblogs_Sync
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 获取插件选项以检查用户是否请求删除数据
$options = get_option( 'cnblogs_sync_options' );

// 如果选项不存在或未勾选"卸载时删除数据"，则不执行清理操作
if ( empty( $options ) || empty( $options['delete_data_on_uninstall'] ) ) {
    return;
}

// 删除插件选项
delete_option( 'cnblogs_sync_options' );
delete_option( 'cnblogs_sync_sync_log' );

// 删除数据库表
$table_name = $wpdb->prefix . 'cnblogs_sync_records';
// Direct query is safe here as we use prefix.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// 删除文章元数据 - 使用 SQL 直接删除比循环更高效
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( 
	$wpdb->prepare( 
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s, %s)",
		'_cnblogs_synced',
		'_cnblogs_post_id',
		'_cnblogs_sync_time',
		'_cnblogs_sync_error',
		'_cnblogs_sync_pending'
	)
);
