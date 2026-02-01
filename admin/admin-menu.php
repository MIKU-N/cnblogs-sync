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
        __('CNBlogs 同步', 'cnblogs-sync'),
        __('CNBlogs 同步', 'cnblogs-sync'),
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
        wp_die(esc_html__('您没有权限访问此页面', 'cnblogs-sync'));
    }

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
    ?>
    <div class="wrap cnblogs-sync-wrap">
        <h1><?php esc_html_e('CNBlogs 同步', 'cnblogs-sync'); ?></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=cnblogs-sync-settings&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('设置', 'cnblogs-sync'); ?></a>
            <a href="?page=cnblogs-sync-settings&tab=status" class="nav-tab <?php echo $active_tab == 'status' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('同步状态', 'cnblogs-sync'); ?></a>
            <a href="?page=cnblogs-sync-settings&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('同步日志', 'cnblogs-sync'); ?></a>
            <a href="?page=cnblogs-sync-settings&tab=about" class="nav-tab <?php echo $active_tab == 'about' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('关于', 'cnblogs-sync'); ?></a>
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
            add_settings_error('cnblogs_sync_settings', 'cnblogs_sync_updated', __('设置已保存', 'cnblogs-sync'), 'updated');
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
                                <?php esc_html_e('启用 CNBlogs 同步', 'cnblogs-sync'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" id="cnblogs_sync_enable" name="cnblogs_sync_options[enable]" value="1" <?php checked($options['enable']); ?>>
                            <p class="description">
                                <?php esc_html_e('启用此选项以激活 CNBlogs 同步功能', 'cnblogs-sync'); ?>
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
                                <?php esc_html_e('CNBlogs 用户名', 'cnblogs-sync'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="cnblogs_sync_username" name="cnblogs_sync_options[cnblogs_username]" value="<?php echo esc_attr($options['cnblogs_username']); ?>" class="regular-text" placeholder="<?php esc_attr_e('输入您的 CNBlogs 用户名', 'cnblogs-sync'); ?>">
                            <p class="description">
                                <?php esc_html_e('您登录 CNBlogs 时使用的用户名', 'cnblogs-sync'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- MetaWeblog 访问令牌 -->
                    <tr>
                        <th scope="row">
                            <label for="cnblogs_sync_password">
                                <?php esc_html_e('MetaWeblog 访问令牌', 'cnblogs-sync'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="password" id="cnblogs_sync_password" name="cnblogs_sync_options[cnblogs_password]" value="<?php echo esc_attr($options['cnblogs_password']); ?>" class="regular-text" placeholder="<?php esc_attr_e('输入您的 MetaWeblog 访问令牌', 'cnblogs-sync'); ?>">
                            <p class="description">
                                <?php esc_html_e('CNBlogs MetaWeblog API 访问令牌', 'cnblogs-sync'); ?>
                            </p>
                            <button type="button" id="cnblogs_test_connection" class="button button-secondary" style="display: block; margin-top: 8px;">
                                <?php esc_html_e('测试连接', 'cnblogs-sync'); ?>
                            </button>
                            <span id="connection_test_result"></span>
                        </td>
                    </tr>

                    <!-- 自动同步 -->
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('自动同步', 'cnblogs-sync'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="cnblogs_sync_options[auto_sync]" value="1" <?php checked($options['auto_sync']); ?>>
                                <?php esc_html_e('发布新文章时自动同步到 CNBlogs', 'cnblogs-sync'); ?>
                            </label><br>

                            <label style="margin-top: 10px; display: block;">
                                <input type="checkbox" name="cnblogs_sync_options[auto_sync_updates]" value="1" <?php checked($options['auto_sync_updates']); ?>>
                                <?php esc_html_e('更新文章时自动同步到 CNBlogs', 'cnblogs-sync'); ?>
                            </label>

                            <label style="margin-top: 10px; display: block;">
                                <input type="checkbox" name="cnblogs_sync_options[sync_delete]" value="1" <?php checked($options['sync_delete']); ?>>
                                <?php esc_html_e('删除文章时也从 CNBlogs 删除', 'cnblogs-sync'); ?>
                            </label>

                            <p class="description">
                                <?php esc_html_e('禁用自动同步时，您可以手动同步文章', 'cnblogs-sync'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- 文章发布设置 -->
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('文章发布选项', 'cnblogs-sync'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="cnblogs_sync_options[add_source_link]" value="1" <?php checked($options['add_source_link']); ?>>
                                <?php esc_html_e('在同步的文章末尾添加原文链接', 'cnblogs-sync'); ?>
                            </label><br>

                            <div style="margin-top: 10px;">
                                <label for="cnblogs_source_link_mode">
                                    <?php esc_html_e('原文链接写入方式', 'cnblogs-sync'); ?>
                                </label>
                                <select id="cnblogs_source_link_mode" name="cnblogs_sync_options[source_link_mode]" style="margin-left: 6px;">
                                    <option value="append" <?php selected($options['source_link_mode'], 'append'); ?>><?php esc_html_e('追加到正文', 'cnblogs-sync'); ?></option>
                                    <option value="struct" <?php selected($options['source_link_mode'], 'struct'); ?>><?php esc_html_e('写入 Source 字段（暂不生效）', 'cnblogs-sync'); ?></option>
                                </select>
                            </div>

                            <div style="margin-top: 10px;">
                                <label for="cnblogs_source_link_text">
                                    <?php esc_html_e('原文链接自定义文案/来源名称', 'cnblogs-sync'); ?>
                                </label>
                                <input type="text" id="cnblogs_source_link_text" name="cnblogs_sync_options[source_link_text]" value="<?php echo esc_attr($options['source_link_text']); ?>" class="regular-text" style="margin-left: 6px;" placeholder="<?php esc_attr_e('例如：原文链接 或 我的博客', 'cnblogs-sync'); ?>">
                                <p class="description">
                                    <?php esc_html_e('留空则默认使用站点名称；追加到正文时会作为链接标题；写入 Source 字段时会作为 name。', 'cnblogs-sync'); ?>
                                </p>
                            </div>

                            <label style="margin-top: 10px; display: block;">
                                <input type="checkbox" name="cnblogs_sync_options[publish_immediately]" value="1" <?php checked($options['publish_immediately']); ?>>
                                <?php esc_html_e('立即发布（取消则为草稿）', 'cnblogs-sync'); ?>
                            </label>

                            <label style="margin-top: 10px; display: block;">
                                <input type="checkbox" name="cnblogs_sync_options[sync_advanced_fields]" value="1" <?php checked($options['sync_advanced_fields']); ?>>
                                <?php esc_html_e('同步标签、分类与摘要', 'cnblogs-sync'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('如果在同步过程中遇到错误，请尝试关闭此选项', 'cnblogs-sync'); ?>
                            </p>

                            <p class="description">
                                <?php esc_html_e('原文链接会帮助读者更好地了解文章的来源', 'cnblogs-sync'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- 卸载设置 -->
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('卸载选项', 'cnblogs-sync'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="cnblogs_sync_options[delete_data_on_uninstall]" value="1" <?php checked($options['delete_data_on_uninstall']); ?>>
                                <?php esc_html_e('卸载插件时删除所有设置和数据', 'cnblogs-sync'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('勾选此项将在插件卸载时清除所有配置和同步记录。如果您只是暂时停用或升级，建议保留。', 'cnblogs-sync'); ?>
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
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_pages = ceil($total / $per_page);

    ?>

    <div>

        <?php if ($total > 0): ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('文章标题', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('同步状态', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('CNBlogs ID', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('同步时间', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('操作', 'cnblogs-sync'); ?></th>
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
                                    <?php esc_html_e('编辑', 'cnblogs-sync'); ?>
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
            <p><?php esc_html_e('暂无同步记录', 'cnblogs-sync'); ?></p>
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
                        <th><?php esc_html_e('时间', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('操作', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('文章 ID', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('CNBlogs ID', 'cnblogs-sync'); ?></th>
                        <th><?php esc_html_e('详情', 'cnblogs-sync'); ?></th>
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
                <?php printf(esc_html__('总共 %d 条日志记录', 'cnblogs-sync'), count($log)); ?>
            </p>

        <?php else: ?>
            <p><?php esc_html_e('暂无日志记录', 'cnblogs-sync'); ?></p>
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
            <p><?php esc_html_e('通过 MetaWeblog 协议将 WordPress 文章自动同步到博客园（CNBlogs）。', 'cnblogs-sync'); ?></p>
            
            <h3><?php esc_html_e('项目地址', 'cnblogs-sync'); ?></h3>
            <p>
                <a href="https://github.com/MIKU-N/cnblogs-sync" target="_blank" class="button button-primary">
                    <?php esc_html_e('访问 GitHub 仓库', 'cnblogs-sync'); ?>
                </a>
            </p>
            <p class="description">
                <?php esc_html_e('查看源代码、提交贡献或 Star 此项目。', 'cnblogs-sync'); ?>
            </p>
            
            <h3><?php esc_html_e('问题反馈', 'cnblogs-sync'); ?></h3>
            <p>
                <a href="https://github.com/MIKU-N/cnblogs-sync/issues" target="_blank" class="button">
                    <?php esc_html_e('提交 Issue', 'cnblogs-sync'); ?>
                </a>
            </p>
            <p class="description">
                <?php esc_html_e('如果您在使用过程中遇到任何问题或有功能建议，请在 GitHub Issues 中反馈。', 'cnblogs-sync'); ?>
            </p>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h3><?php esc_html_e('功能特性', 'cnblogs-sync'); ?></h3>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><?php esc_html_e('支持发布和更新文章时自动同步', 'cnblogs-sync'); ?></li>
                <li><?php esc_html_e('支持手动同步', 'cnblogs-sync'); ?></li>
                <li><?php esc_html_e('自动处理分类（如不存在则创建）', 'cnblogs-sync'); ?></li>
                <li><?php esc_html_e('支持自定义原文链接格式', 'cnblogs-sync'); ?></li>
                <li><?php esc_html_e('支持 Markdown 格式同步', 'cnblogs-sync'); ?></li>
            </ul>
        </div>
    </div>
    <?php
}
