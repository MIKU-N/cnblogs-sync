<?php
/**
 * 管理菜单和页面处理
 * 
 * 创建 WordPress 管理菜单，处理设置页面的渲染和表单提交
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册管理菜单
 * 
 * 在 WordPress 管理菜单中添加 CNBlogs Sync 菜单
 * 
 * @return void
 */
function cnblogs_sync_register_admin_menu() {
    // 将菜单添加到设置菜单下
    add_options_page(
        __('CNBlogs Sync', 'cnblogs-sync'),
        __('CNBlogs Sync', 'cnblogs-sync'),
        'manage_options',
        'cnblogs-sync-settings',
        'cnblogs_sync_render_main_page'
    );
}

// 只在 WordPress 环境中且 WP 函数存在时注册菜单
if (!wp_installing() && function_exists('add_action')) {
    add_action('admin_menu', 'cnblogs_sync_register_admin_menu');
}

/**
 * 注册插件设置
 * 
 * 定义插件的设置选项
 * 
 * @return void
 */
function cnblogs_sync_register_settings() {
    register_setting(
        'cnblogs_sync_settings_group',
        'cnblogs_sync_options',
        array(
            'sanitize_callback' => 'cnblogs_sync_sanitize_options'
        )
    );

    // 不显示设置区域标题
}

// 只在 WordPress 环境中且 WP 函数存在时注册设置
if (!wp_installing() && function_exists('add_action')) {
    add_action('admin_init', 'cnblogs_sync_register_settings');
}

/**
 * 设置区域回调
 * 
 * @return void
 */
function cnblogs_sync_section_callback() {
}

/**
 * 高级设置区域回调
 * 
 * @return void
 */
function cnblogs_sync_advanced_section_callback() {
}

/**
 * 消毒和验证选项
 * 
 * @param array $input 表单提交的数据
 * @return array 消毒后的数据
 */
function cnblogs_sync_sanitize_options($input) {
    $output = array();

    // 启用/禁用
    $output['enable'] = !empty($input['enable']) ? 1 : 0;

    // API URL
    $output['cnblogs_api_url'] = esc_url_raw($input['cnblogs_api_url'] ?? 'https://www.cnblogs.com/api/metaweblog/new');

    // 用户名和密码
    $output['cnblogs_username'] = sanitize_text_field($input['cnblogs_username'] ?? '');
    $output['cnblogs_password'] = sanitize_text_field($input['cnblogs_password'] ?? '');

    // 其他选项
    $output['auto_sync'] = !empty($input['auto_sync']) ? 1 : 0;
    $output['auto_sync_updates'] = !empty($input['auto_sync_updates']) ? 1 : 0;
    $output['add_source_link'] = !empty($input['add_source_link']) ? 1 : 0;
    $output['sync_delete'] = !empty($input['sync_delete']) ? 1 : 0;
    $output['sync_status'] = sanitize_text_field($input['sync_status'] ?? 'published');
    $output['publish_immediately'] = !empty($input['publish_immediately']) ? 1 : 0;
    $output['source_link_mode'] = in_array(($input['source_link_mode'] ?? 'append'), array('append', 'struct'), true)
        ? $input['source_link_mode']
        : 'append';
    $output['source_link_text'] = sanitize_text_field($input['source_link_text'] ?? '');
    
    // 卸载选项
    $output['delete_data_on_uninstall'] = !empty($input['delete_data_on_uninstall']) ? 1 : 0;
    
    // 高级同步选项
    $output['sync_advanced_fields'] = !empty($input['sync_advanced_fields']) ? 1 : 0;

    return $output;
}

/**
 * 渲染主页面（带 Tabs）
 */
function cnblogs_sync_render_main_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'cnblogs-sync'));
    }

    if (isset($_GET['tab'])) {
        $active_tab = sanitize_text_field(wp_unslash($_GET['tab']));
    } else {
        $active_tab = 'settings';
    }
    ?>
    <div class="wrap cnblogs-sync-wrap">
        <h1><?php esc_html_e('CNBlogs Sync', 'cnblogs-sync'); ?></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=cnblogs-sync-settings&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Settings', 'cnblogs-sync'); ?></a>
            <a href="?page=cnblogs-sync-settings&tab=status" class="nav-tab <?php echo $active_tab == 'status' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Sync Status', 'cnblogs-sync'); ?></a>
            <a href="?page=cnblogs-sync-settings&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Sync Logs', 'cnblogs-sync'); ?></a>
            <a href="?page=cnblogs-sync-settings&tab=about" class="nav-tab <?php echo $active_tab == 'about' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('About', 'cnblogs-sync'); ?></a>
        </h2>
        
        <?php
        switch ($active_tab) {
            case 'status':
                cnblogs_sync_render_status_content();
                break;
            case 'logs':
                cnblogs_sync_render_logs_content();
                break;
            case 'about':
                cnblogs_sync_render_about_content();
                break;
            case 'settings':
            default:
                cnblogs_sync_render_settings_content();
                break;
        }
        ?>
    </div>
    <?php
}

/**
 * 渲染设置内容
 * 
 * 显示 CNBlogs 同步设置表单
 * 
 * @return void
 */
function cnblogs_sync_render_settings_content() {
    // 获取当前选项
    $options = get_option('cnblogs_sync_options', array());
    $defaults = array(
        'enable' => false,
        'cnblogs_api_url' => 'https://www.cnblogs.com/api/metaweblog/new',
        'cnblogs_username' => '',
        'cnblogs_password' => '',
        'auto_sync' => false,
        'auto_sync_updates' => false,
        'add_source_link' => true,
        'sync_delete' => false,
        'sync_status' => 'published',
        'publish_immediately' => true,
        'sync_advanced_fields' => true, // 默认开启
        'source_link_mode' => 'append',
        'source_link_text' => '',
        'delete_data_on_uninstall' => false
    );
    $options = wp_parse_args($options, $defaults);
    ?>

    <div>
        <?php
        // 显示设置保存的通知
        if (isset($_GET['settings-updated'])) {
            add_settings_error('cnblogs_sync_settings', 'cnblogs_sync_updated', __('Settings saved.', 'cnblogs-sync'), 'updated');
        }
        settings_errors('cnblogs_sync_settings');
        ?>

        <form method="post" action="options.php">
            <?php
            settings_fields('cnblogs_sync_settings_group');
            ?>

            <table class="form-table cnblogs-grid" role="presentation">
                <tbody>
                    <!-- 启用/禁用 -->
                    <tr>
                        <th scope="row">
                            <label for="cnblogs_sync_enable">
                                <?php esc_html_e('Enable CNBlogs Sync', 'cnblogs-sync'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" id="cnblogs_sync_enable" name="cnblogs_sync_options[enable]" value="1" <?php checked($options['enable']); ?>>
                            <p class="description">
                                <?php esc_html_e('Enable this option to activate CNBlogs synchronization.', 'cnblogs-sync'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- API URL -->
                    <tr>
                        <th scope="row">
                            <label for="cnblogs_sync_api_url">
                                <?php esc_html_e('MetaWeblog API URL', 'cnblogs-sync'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" id="cnblogs_sync_api_url" name="cnblogs_sync_options[cnblogs_api_url]" value="<?php echo esc_attr($options['cnblogs_api_url']); ?>" class="regular-text" placeholder="https://www.cnblogs.com/api/metaweblog/new">
                        </td>
                    </tr>

                    <!-- 用户名 -->
                    <tr>
                        <th scope="row">
                            <label for="cnblogs_sync_username">
                                <?php esc_html_e('CNBlogs Username', 'cnblogs-sync'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="cnblogs_sync_username" name="cnblogs_sync_options[cnblogs_username]" value="<?php echo esc_attr($options['cnblogs_username']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Enter your CNBlogs username', 'cnblogs-sync'); ?>">
                            <p class="description">
                                <?php esc_html_e('Your login username for CNBlogs.', 'cnblogs-sync'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- MetaWeblog 访问令牌 -->
                    <tr>
                        <th scope="row">
                            <label for="cnblogs_sync_password">
                                <?php esc_html_e('MetaWeblog Access Token', 'cnblogs-sync'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="password" id="cnblogs_sync_password" name="cnblogs_sync_options[cnblogs_password]" value="<?php echo esc_attr($options['cnblogs_password']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Enter your MetaWeblog access token', 'cnblogs-sync'); ?>">
                            <p class="description">
                                <?php esc_html_e('CNBlogs MetaWeblog API Access Token', 'cnblogs-sync'); ?>
                            </p>
                            <button type="button" id="cnblogs_test_connection" class="button button-secondary" style="display: block; margin-top: 8px;">
                                <?php esc_html_e('Test Connection', 'cnblogs-sync'); ?>
                            </button>
                            <span id="connection_test_result"></span>
                        </td>
                    </tr>

                    <!-- 自动同步 -->
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Automatic Sync', 'cnblogs-sync'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="cnblogs_sync_options[auto_sync]" value="1" <?php checked($options['auto_sync']); ?>>
                                <?php esc_html_e('Automatically sync to CNBlogs when publishing posts', 'cnblogs-sync'); ?>
                            </label><br>

                            <label style="margin-top: 10px; display: block;">
                                <input type="checkbox" name="cnblogs_sync_options[auto_sync_updates]" value="1" <?php checked($options['auto_sync_updates']); ?>>
                                <?php esc_html_e('Automatically sync to CNBlogs when updating posts', 'cnblogs-sync'); ?>
                            </label>

                            <label style="margin-top: 10px; display: block;">
                                <input type="checkbox" name="cnblogs_sync_options[sync_delete]" value="1" <?php checked($options['sync_delete']); ?>>
                                <?php esc_html_e('Delete from CNBlogs when deleting posts in WordPress', 'cnblogs-sync'); ?>
                            </label>

                            <p class="description">
                                <?php esc_html_e('You can manually sync posts when automatic sync is disabled.', 'cnblogs-sync'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- 文章发布设置 -->
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Post Publishing Options', 'cnblogs-sync'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="cnblogs_sync_options[add_source_link]" value="1" <?php checked($options['add_source_link']); ?>>
                                <?php esc_html_e('Add original link at the end of synced posts', 'cnblogs-sync'); ?>
                            </label><br>

                            <div style="margin-top: 10px;">
                                <label for="cnblogs_source_link_mode">
                                    <?php esc_html_e('Source Link Mode', 'cnblogs-sync'); ?>
                                </label>
                                <select id="cnblogs_source_link_mode" name="cnblogs_sync_options[source_link_mode]" style="margin-left: 6px;">
                                    <option value="append" <?php selected($options['source_link_mode'], 'append'); ?>><?php esc_html_e('Append to content', 'cnblogs-sync'); ?></option>
                                    <option value="struct" <?php selected($options['source_link_mode'], 'struct'); ?>><?php esc_html_e('Write to Source field (Not currently supported)', 'cnblogs-sync'); ?></option>
                                </select>
                            </div>

                            <div style="margin-top: 10px;">
                                <label for="cnblogs_source_link_text">
                                    <?php esc_html_e('Custom Source Link Text/Site Name', 'cnblogs-sync'); ?>
                                </label>
                                <input type="text" id="cnblogs_source_link_text" name="cnblogs_sync_options[source_link_text]" value="<?php echo esc_attr($options['source_link_text']); ?>" class="regular-text" style="margin-left: 6px;" placeholder="<?php esc_attr_e('e.g., Original Link or My Blog', 'cnblogs-sync'); ?>">
                                <p class="description">
                                    <?php esc_html_e('Leave empty to use site name. Used as link title when appended to content, or as name when writing to Source field.', 'cnblogs-sync'); ?>
                                </p>
                            </div>

                            <label style="margin-top: 10px; display: block;">
                                <input type="checkbox" name="cnblogs_sync_options[publish_immediately]" value="1" <?php checked($options['publish_immediately']); ?>>
                                <?php esc_html_e('Publish Immediately (otherwise save as draft)', 'cnblogs-sync'); ?>
                            </label>

                            <label style="margin-top: 10px; display: block;">
                                <input type="checkbox" name="cnblogs_sync_options[sync_advanced_fields]" value="1" <?php checked($options['sync_advanced_fields']); ?>>
                                <?php esc_html_e('Sync Tags, Categories, and Excerpts', 'cnblogs-sync'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Try disabling this if you encounter errors during synchronization.', 'cnblogs-sync'); ?>
                            </p>

                            <p class="description">
                                <?php esc_html_e('Original links help readers find the source of the article.', 'cnblogs-sync'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- 卸载设置 -->
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Uninstall Options', 'cnblogs-sync'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="cnblogs_sync_options[delete_data_on_uninstall]" value="1" <?php checked($options['delete_data_on_uninstall']); ?>>
                                <?php esc_html_e('Delete all settings and data when uninstalling', 'cnblogs-sync'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Check this to clear all configuration and sync records when the plugin is uninstalled. Not recommended if you are just tentatively deactivating or upgrading.', 'cnblogs-sync'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>

    <?php
}

/**
 * 渲染同步状态内容
 * 
 * 显示所有文章的同步状态
 * 
 * @return void
 */
function cnblogs_sync_render_status_content() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cnblogs_sync_records';

    // 获取分页参数
    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 20;
    $offset = ($paged - 1) * $per_page;

    // 查询同步记录
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
    $records = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, p.post_title, p.post_status 
         FROM $table_name r 
         LEFT JOIN {$wpdb->posts} p ON r.post_id = p.ID 
         ORDER BY r.sync_time DESC 
         LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));

    // 获取总数
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_pages = ceil($total / $per_page);

    ?>

    <div>

        <?php if ($total > 0): ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Post Title', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('Sync Status', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('CNBlogs ID', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('Sync Time', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('Actions', 'cnblogs-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td>
                                <?php
                                if ($record->post_status === 'publish') {
                                    echo '<strong>';
                                    echo esc_html($record->post_title);
                                    echo '</strong>';
                                } else {
                                    echo esc_html($record->post_title);
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $status_class = $record->sync_status === 'synced' ? 'success' : ($record->sync_status === 'failed' ? 'error' : 'pending');
                                echo '<span class="status-' . esc_attr($status_class) . '">';
                                echo esc_html(ucfirst($record->sync_status));
                                echo '</span>';
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html($record->cnblogs_post_id ?: '-'); ?>
                            </td>
                            <td>
                                <?php echo esc_html($record->sync_time ?: '-'); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($record->post_id)); ?>" class="button button-small">
                                    <?php esc_html_e('Edit', 'cnblogs-sync'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="pagination">
                        <?php
                        for ($i = 1; $i <= $total_pages; $i++) {
                            $current = $i === $paged ? 'current' : '';
                            echo '<a href="' . esc_url(add_query_arg('paged', $i)) . '" class="' . esc_attr($current) . '">' . intval($i) . '</a> ';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <p><?php esc_html_e('No sync records found.', 'cnblogs-sync'); ?></p>
        <?php endif; ?>
    </div>

    <?php
}

/**
 * 渲染同步日志内容
 * 
 * 显示同步操作的日志
 * 
 * @return void
 */
function cnblogs_sync_render_logs_content() {
    $log = get_option('cnblogs_sync_sync_log', array());

    // 反向排序日志（最新的在前）
    $log = array_reverse($log);

    ?>

    <div>

        <?php if (!empty($log)): ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('Action', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('Post ID', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('CNBlogs ID', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('Details', 'cnblogs-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log as $entry): ?>
                        <tr>
                            <td>
                                <?php echo esc_html($entry['time']); ?>
                            </td>
                            <td>
                                <?php
                                $type_class = $entry['type'];
                                echo '<span class="log-' . esc_attr($type_class) . '">';
                                echo esc_html(ucfirst($entry['type']));
                                echo '</span>';
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html($entry['post_id']); ?>
                            </td>
                            <td>
                                <?php echo esc_html($entry['cnblogs_id'] ?: '-'); ?>
                            </td>
                            <td>
                                <?php echo esc_html($entry['message'] ?: '-'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 20px;">
                <?php
                /* translators: %d: Number of log entries */
                printf(esc_html__('Total %d log entries', 'cnblogs-sync'), count($log));
                ?>
            </p>

        <?php else: ?>
            <p><?php esc_html_e('No log records found.', 'cnblogs-sync'); ?></p>
        <?php endif; ?>
    </div>

    <?php
}

/**
 * 渲染关于内容
 * 
 * 显示插件的相关信息
 * 
 * @return void
 */
function cnblogs_sync_render_about_content() {
    
    // 获取插件数据
    // 注意：如果在某些上下文中 get_plugin_data 不可用，需要包含 plugin.php
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/cnblogs-sync/cnblogs-sync.php' );
    ?>
    <div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2 class="title"><?php esc_html_e('CNBlogs Sync', 'cnblogs-sync'); ?> v<?php echo esc_html($plugin_data['Version']); ?></h2>
            <p><?php esc_html_e('Automatically sync WordPress posts to CNBlogs via MetaWeblog protocol.', 'cnblogs-sync'); ?></p>
            
            <h3><?php esc_html_e('Project Homepage', 'cnblogs-sync'); ?></h3>
            <p>
                <a href="https://github.com/MIKU-N/cnblogs-sync" target="_blank" class="button button-primary">
                    <?php esc_html_e('Visit GitHub Repository', 'cnblogs-sync'); ?>
                </a>
            </p>
            <p class="description">
                <?php esc_html_e('View source code, contribute, or star this project.', 'cnblogs-sync'); ?>
            </p>
            
            <h3><?php esc_html_e('Feedback', 'cnblogs-sync'); ?></h3>
            <p>
                <a href="https://github.com/MIKU-N/cnblogs-sync/issues" target="_blank" class="button">
                    <?php esc_html_e('Submit Issue', 'cnblogs-sync'); ?>
                </a>
            </p>
            <p class="description">
                <?php esc_html_e('If you encounter any issues or have feature suggestions, please report them on GitHub Issues.', 'cnblogs-sync'); ?>
            </p>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h3><?php esc_html_e('Features', 'cnblogs-sync'); ?></h3>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><?php esc_html_e('Automatically sync when publishing or updating posts', 'cnblogs-sync'); ?></li>
                <li><?php esc_html_e('Support manual sync', 'cnblogs-sync'); ?></li>
                <li><?php esc_html_e('Automatically handle categories (create if not exists)', 'cnblogs-sync'); ?></li>
                <li><?php esc_html_e('Support custom source link format', 'cnblogs-sync'); ?></li>
                <li><?php esc_html_e('Support Markdown format sync', 'cnblogs-sync'); ?></li>
            </ul>
        </div>
    </div>
    <?php
}
