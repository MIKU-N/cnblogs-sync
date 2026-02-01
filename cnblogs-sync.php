<?php
/**
 * Plugin Name: CNBlogs Sync
 * Plugin URI: https://github.com/MIKU-N/cnblogs-sync
 * Description: 通过 MetaWeblog 协议将 WordPress 文章同步到 CNBlogs
 * Version: 1.2.0
 * Author: MIKU-N
 * Author URI: https://blog.im.ci
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cnblogs-sync
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// 防止直接访问插件文件
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('CNBLOGS_SYNC_VERSION', '1.1.1');
define('CNBLOGS_SYNC_DIR', plugin_dir_path(__FILE__));
define('CNBLOGS_SYNC_URL', plugin_dir_url(__FILE__));
define('CNBLOGS_SYNC_BASENAME', plugin_basename(__FILE__));

// 加载插件依赖文件
if (!require_once CNBLOGS_SYNC_DIR . 'includes/class-cnblogs-sync.php') {
    wp_die('CNBlogs Sync: 无法加载核心类文件');
}
if (!require_once CNBLOGS_SYNC_DIR . 'includes/metaweblog-client.php') {
    wp_die('CNBlogs Sync: 无法加载 MetaWeblog 客户端');
}
if (!require_once CNBLOGS_SYNC_DIR . 'admin/admin-menu.php') {
    wp_die('CNBlogs Sync: 无法加载管理菜单');
}

/**
 * 初始化插件
 * 
 * 此函数在 plugins_loaded 钩子上执行，确保所有 WordPress 组件都已加载
 * 
 * @return void
 */
function cnblogs_sync_init() {
    // 加载插件文本域（多语言支持）
    load_plugin_textdomain(
        'cnblogs-sync',
        false,
        dirname(CNBLOGS_SYNC_BASENAME) . '/languages/'
    );

    // 创建插件主类实例
    CNBLOGS_Sync::get_instance();
}
add_action('plugins_loaded', 'cnblogs_sync_init');

/**
 * 插件激活时的处理
 * 
 * 检查 PHP 版本，创建必要的数据库表和选项
 * 
 * @return void
 */
function cnblogs_sync_activate() {
    // 检查 PHP 版本
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        wp_die(
            esc_html__('CNBlogs Sync 需要 PHP 7.4 或更高版本', 'cnblogs-sync'),
            esc_html__('插件激活失败', 'cnblogs-sync')
        );
    }

    // 检查 cURL 扩展
    if (!extension_loaded('curl')) {
        wp_die(
            esc_html__('CNBlogs Sync 需要启用 PHP cURL 扩展', 'cnblogs-sync'),
            esc_html__('插件激活失败', 'cnblogs-sync')
        );
    }

    // 创建数据库表用于存储同步记录
    cnblogs_sync_create_tables();

    // 初始化插件选项
    if (!get_option('cnblogs_sync_options')) {
        add_option('cnblogs_sync_options', array(
            'enable' => false,
            'cnblogs_api_url' => 'https://www.cnblogs.com/api/metaweblog/new',
            'cnblogs_username' => '',
            'cnblogs_password' => '',
            'sync_status' => 'published',
            'add_source_link' => true,
            'source_link_mode' => 'append',
            'source_link_text' => '',
            'auto_sync' => false
        ));
    }

    // 创建定时任务
    if (!wp_next_scheduled('cnblogs_sync_scheduled_sync')) {
        wp_schedule_event(time(), 'hourly', 'cnblogs_sync_scheduled_sync');
    }
}
register_activation_hook(__FILE__, 'cnblogs_sync_activate');

/**
 * 创建同步记录数据库表
 * 
 * 此表用于记录每篇文章的同步状态、cnblogs ID 等信息
 * 
 * @return void
 */
function cnblogs_sync_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'cnblogs_sync_records';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        cnblogs_post_id varchar(255) NOT NULL,
        cnblogs_post_url varchar(500),
        sync_time datetime DEFAULT CURRENT_TIMESTAMP,
        last_sync_time datetime,
        sync_status varchar(20) DEFAULT 'synced' COMMENT '同步状态: synced, failed, pending',
        error_message longtext,
        PRIMARY KEY (id),
        UNIQUE KEY post_id (post_id),
        KEY cnblogs_post_id (cnblogs_post_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * 插件停用时的处理
 * 
 * 清理定时任务
 * 
 * @return void
 */
function cnblogs_sync_deactivate() {
    // 清除定时任务
    wp_clear_scheduled_hook('cnblogs_sync_scheduled_sync');
}
register_deactivation_hook(__FILE__, 'cnblogs_sync_deactivate');

// Uninstall logic moved to uninstall.php

/**
 * 在插件列表页面添加设置链接
 * 
 * 在插件列表中的插件名称下方显示"设置"链接
 * 
 * @param array $links 插件操作链接数组
 * @param string $plugin_file 插件文件路径
 * @return array 修改后的链接数组
 */
function cnblogs_sync_add_plugin_action_links($links, $plugin_file) {
    // 只在本插件的列表项中添加链接
    if ($plugin_file === CNBLOGS_SYNC_BASENAME) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=cnblogs-sync-settings')),
            esc_html__('设置', 'cnblogs-sync')
        );
        
        // 将设置链接插入到数组的开头
        array_unshift($links, $settings_link);
    }
    
    return $links;
}

// 添加过滤器
add_filter('plugin_action_links', 'cnblogs_sync_add_plugin_action_links', 10, 2);
