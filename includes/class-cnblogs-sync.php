<?php
/**
 * CNBlogs Sync 主类
 * 
 * 管理插件的核心功能，包括初始化、钩子绑定、文章发布同步等
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 主插件类 - 管理所有核心功能
 */
class CNBLOGS_Sync {
    
    /**
     * 单例实例
     * 
     * @var CNBLOGS_Sync
     */
    private static $instance = null;

    /**
     * MetaWeblog 客户端实例
     * 
     * @var MetaWeblog_Client
     */
    private $metaweblog_client = null;

    /**
     * 插件选项
     * 
     * @var array
     */
    private $options = array();

    /**
     * 获取单例实例
     * 
     * @return CNBLOGS_Sync
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     * 
     * 初始化插件，绑定所有必要的钩子
     */
    private function __construct() {
        // 安全检查：确保 WordPress 已加载
        if (!function_exists('get_option')) {
            return;
        }

        // 加载选项
        $this->options = get_option('cnblogs_sync_options', array());

        // 初始化 MetaWeblog 客户端
        if (class_exists('MetaWeblog_Client')) {
            $this->metaweblog_client = new MetaWeblog_Client(
                $this->options['cnblogs_api_url'] ?? 'https://www.cnblogs.com/api/metaweblog/new',
                $this->options['cnblogs_username'] ?? '',
                $this->options['cnblogs_password'] ?? '',
                $this->options['cnblogs_blog_id'] ?? '1'
            );
            // 显式设置超时时间为 120 秒，防止大文章同步或网络延迟导致 cURL error 28
            $this->metaweblog_client->set_timeout(120);
        } else {
            // 记录错误但不中断执行
            error_log('CNBlogs Sync: MetaWeblog_Client 类未找到');
        }

        // 绑定钩子
        $this->setup_hooks();
    }

    /**
     * 设置所有必要的钩子
     * 
     * @return void
     */
    private function setup_hooks() {
        // 文章发布时的同步
        add_action('publish_post', array($this, 'sync_post_to_cnblogs'), 10, 1);

        // 文章更新时的同步
        add_action('post_updated', array($this, 'on_post_updated'), 10, 3);

        // 文章删除时的处理
        add_action('delete_post', array($this, 'on_post_deleted'), 10, 1);

        // 定时同步任务
        add_action('cnblogs_sync_scheduled_sync', array($this, 'scheduled_sync'));

        // 加载脚本和样式
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX 处理
        add_action('wp_ajax_cnblogs_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_cnblogs_sync_single_post', array($this, 'ajax_sync_single_post'));
        add_action('wp_ajax_cnblogs_get_sync_status', array($this, 'ajax_get_sync_status'));

        // 在编辑文章页面显示同步状态
        add_action('add_meta_boxes', array($this, 'add_sync_meta_box'));

        // 在文章列表页面添加同步状态列和快速操作
        add_filter('manage_post_columns', array($this, 'add_sync_column'));
        add_action('manage_post_custom_column', array($this, 'display_sync_column'), 10, 2);
        add_filter('post_row_actions', array($this, 'add_quick_sync_action'), 10, 2);
    }

    /**
     * 加载管理端脚本和样式
     * 
     * 只在 CNBlogs Sync 页面加载必要的资源
     * 
     * @param string $hook_suffix WordPress 页面钩子后缀
     * @return void
     */
    public function enqueue_admin_assets($hook_suffix) {
        // 验证钩子后缀参数类型
        if (!is_string($hook_suffix)) {
            return;
        }

        // 检查是否在 CNBlogs 相关页面或文章编辑页面
        $should_load = false;
        
        // 在插件管理页面加载资源
        if (strpos($hook_suffix, 'cnblogs') !== false) {
            $should_load = true;
        }
        
        // 在文章编辑页面也加载（用于显示同步按钮）
        if (in_array($hook_suffix, array('post.php', 'post-new.php', 'edit.php'))) {
            $should_load = true;
        }

        if (!$should_load) {
            return;
        }

        // 注册并加载 CSS（推荐在 WordPress 6.9+ 中先 register 再 enqueue）
        wp_register_style(
            'cnblogs-sync-admin',
            CNBLOGS_SYNC_URL . 'assets/admin.css',
            array(),
            CNBLOGS_SYNC_VERSION,
            'all'
        );
        wp_enqueue_style('cnblogs-sync-admin');

        // 注册并加载 JavaScript
        wp_register_script(
            'cnblogs-sync-admin',
            CNBLOGS_SYNC_URL . 'assets/admin.js',
            array('jquery'),
            CNBLOGS_SYNC_VERSION,
            true
        );
        wp_enqueue_script('cnblogs-sync-admin');

        // 本地化脚本（必须在 enqueue 后调用，确保脚本已注册）
        wp_localize_script('cnblogs-sync-admin', 'cnblogs_sync_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cnblogs_sync_nonce'),
            'strings' => array(
                'testing' => __('测试中...', 'cnblogs-sync'),
                'success' => __('成功', 'cnblogs-sync'),
                'error' => __('错误', 'cnblogs-sync'),
                'syncing' => __('同步中...', 'cnblogs-sync'),
            )
        ));
    }

    /**
     * 将文章同步到 CNBlogs
     * 
     * 此方法在文章发布时自动调用，如果启用了自动同步
     * 
     * @param int $post_id 文章 ID
     * @return bool 同步是否成功
     */
    public function sync_post_to_cnblogs($post_id) {
        // 防止自动保存触发
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        
        // 防止修订版本触发
        if (wp_is_post_revision($post_id)) {
            return false;
        }

        // 检查是否启用了 CNBlogs 同步
        if (empty($this->options['enable'])) {
            return false;
        }

        // 检查是否启用了自动同步
        if (empty($this->options['auto_sync'])) {
            return false;
        }

        // 获取文章对象
        $post = get_post($post_id);

        // 检查文章是否存在且是发布状态
        if (!$post || $post->post_status !== 'publish') {
            return false;
        }

        // 检查是否已经同步过
        if (get_post_meta($post_id, '_cnblogs_synced', true)) {
            return true;
        }

        // 执行同步
        return $this->do_sync_post($post_id);
    }

    /**
     * 自动检测并更新 Blog ID
     * 
     * 如果当前 Blog ID 为默认值 '1'，尝试自动获取正确的 Blog ID
     * 
     * @return void
     */
    private function ensure_correct_blog_id() {
        $current_blog_id = $this->options['cnblogs_blog_id'] ?? '1';
        
        // 当 Blog ID 为默认值 '1' 或纯数字 ID时尝试自动获取正确的 Blog App ID
        // CNBlogs 的 newPost 接口要求 BlogID 为 URL 中的 Blog App 名 (如 'srs')，而不是数字 ID
        if ($current_blog_id === '1' || is_numeric($current_blog_id)) {
            try {
                error_log('CNBlogs Sync: 检测到 Blog ID 为 "' . $current_blog_id . '"，尝试自动获取正确的 Blog App ID...');
                $blogs = $this->metaweblog_client->get_users_blogs();
                
                if (!empty($blogs)) {
                    if (is_object($blogs)) {
                        $blogs = (array)$blogs;
                    }
                    
                    $blog_info = reset($blogs);
                    $new_blog_id = '';
                    $blog_url = '';
                    
                    if (is_array($blog_info)) {
                        $new_blog_id = $blog_info['blogid'] ?? '';
                        $blog_url = $blog_info['url'] ?? '';
                    } elseif (is_object($blog_info)) {
                        $new_blog_id = $blog_info->blogid ?? '';
                        $blog_url = $blog_info->url ?? '';
                    }

                    // 尝试从 URL 中解析 Blog App 名 (例如 https://www.cnblogs.com/srs/)
                    // 只有解析成功时才替换数字 ID
                    if (!empty($blog_url) && preg_match('/cnblogs\.com\/([^\/]+)\/?/i', $blog_url, $matches)) {
                        if (!empty($matches[1])) {
                             $parsed_id = $matches[1];
                             error_log('CNBlogs Sync: 从 URL 目前解析出 Blog App ID: ' . $parsed_id);
                             $new_blog_id = $parsed_id;
                        }
                    }
                    
                    if (!empty($new_blog_id) && $new_blog_id !== '1' && $new_blog_id !== $current_blog_id) {
                        // 更新选项
                        $this->options['cnblogs_blog_id'] = (string)$new_blog_id;
                        update_option('cnblogs_sync_options', $this->options);
                        
                        // 更新客户端实例的 Blog ID
                        $this->metaweblog_client = new MetaWeblog_Client(
                            $this->options['cnblogs_api_url'],
                            $this->options['cnblogs_username'],
                            $this->options['cnblogs_password'],
                            $this->options['cnblogs_blog_id']
                        );
                        // 确保新实例也设置了足够的超时时间
                        $this->metaweblog_client->set_timeout(120);
                        
                        error_log('CNBlogs Sync: 自动更新 Blog ID 成功: ' . $new_blog_id);
                    }
                }
            } catch (Exception $e) {
                error_log('CNBlogs Sync: 自动获取 Blog ID 失败: ' . $e->getMessage());
            }
        }
    }

    /**
     * 执行实际的文章同步操作
     * 
     * @param int $post_id 文章 ID
     * @return bool 同步是否成功
     */
    private function do_sync_post($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return false;
        }

        try {
            // 确保 Blog ID 正确
            $this->ensure_correct_blog_id();

            // 准备文章数据
            $post_data = $this->prepare_post_data($post);

            // 验证分类是否存在，不存在则创建后再同步，防止 CNBlogs 报错 (500 Object reference)
            if (!empty($post_data['categories'])) {
                try {
                    $server_categories = $this->metaweblog_client->get_categories();
                    $valid_names = array();
                    foreach ($server_categories as $cat) {
                        // 兼容数组/对象，优先取 description，其次 title
                        if (is_array($cat)) {
                            $name = $cat['description'] ?? $cat['title'] ?? '';
                        } else {
                            $name = $cat->description ?? $cat->title ?? '';
                        }
                        if ($name) {
                            $valid_names[] = $name;
                        }
                    }

                    $filtered = array();
                    foreach ($post_data['categories'] as $cat_name) {
                        if (in_array($cat_name, $valid_names, true)) {
                            $filtered[] = $cat_name;
                            continue;
                        }

                        try {
                            $new_cat_id = $this->metaweblog_client->new_category(array(
                                'name' => $cat_name,
                                'parent_id' => 0
                            ));

                            if ($new_cat_id) {
                                $valid_names[] = $cat_name;
                                $filtered[] = $cat_name;
                                error_log('CNBlogs Sync: 已创建远程分类 "' . $cat_name . '" (ID: ' . $new_cat_id . ')');
                            } else {
                                error_log('CNBlogs Sync: 创建远程分类失败 "' . $cat_name . '"');
                            }
                        } catch (Exception $e) {
                            error_log('CNBlogs Sync: 创建远程分类失败 "' . $cat_name . '": ' . $e->getMessage());
                        }
                    }

                    $post_data['categories'] = $filtered;
                } catch (Exception $e) {
                    error_log('CNBlogs Sync: 获取远程分类失败，尝试创建分类后继续: ' . $e->getMessage());
                    $filtered = array();
                    foreach ($post_data['categories'] as $cat_name) {
                        try {
                            $new_cat_id = $this->metaweblog_client->new_category(array(
                                'name' => $cat_name,
                                'parent_id' => 0
                            ));

                            if ($new_cat_id) {
                                $filtered[] = $cat_name;
                                error_log('CNBlogs Sync: 已创建远程分类 "' . $cat_name . '" (ID: ' . $new_cat_id . ')');
                            } else {
                                error_log('CNBlogs Sync: 创建远程分类失败 "' . $cat_name . '"');
                            }
                        } catch (Exception $inner) {
                            error_log('CNBlogs Sync: 创建远程分类失败 "' . $cat_name . '": ' . $inner->getMessage());
                        }
                    }
                    $post_data['categories'] = $filtered;
                }
            }

            // 检查文章是否已同步过
            $cnblogs_post_id = get_post_meta($post_id, '_cnblogs_post_id', true);

            if ($cnblogs_post_id) {
                // 已同步 - 调用 editPost 更新文章
                error_log('CNBlogs Sync: 文章已同步，执行更新操作 - postid: ' . $post_id . ', cnblogs_post_id: ' . $cnblogs_post_id);
                
                $result = $this->metaweblog_client->edit_post(
                    $cnblogs_post_id,
                    $post_data
                );

                if ($result) {
                    update_post_meta($post_id, '_cnblogs_sync_time', current_time('mysql'));
                    $this->log_sync('update', $post_id, $cnblogs_post_id);
                    return true;
                }

                throw new Exception(__('编辑文章失败', 'cnblogs-sync'));

            } else {
                // 未同步 - 调用 newPost 创建新文章
                error_log('CNBlogs Sync: 文章未同步，执行创建操作 - postid: ' . $post_id);
                
                $cnblogs_post_id = $this->metaweblog_client->new_post(
                    $post_data,
                    !empty($this->options['publish_immediately'])
                );

                if (!$cnblogs_post_id) {
                    throw new Exception(__('CNBlogs API 返回无效的 ID', 'cnblogs-sync'));
                }

                // 保存同步信息
                $this->save_sync_record($post_id, $cnblogs_post_id);

                // 记录同步日志
                $this->log_sync('success', $post_id, $cnblogs_post_id);

                return true;
            }

        } catch (Exception $e) {
            // 记录错误
            $this->log_sync('error', $post_id, '', $e->getMessage());
            
            // 保存错误信息到文章元数据
            update_post_meta($post_id, '_cnblogs_sync_error', $e->getMessage());

            return false;
        }
    }

    /**
     * 准备要同步到 CNBlogs 的文章数据
     * 
     * 此方法处理文章的转换、格式化、链接等
     * 
     * @param WP_Post $post WordPress 文章对象
     * @return array 格式化后的文章数据
     */
    private function prepare_post_data($post) {
        // 处理文章内容 - 转换 WordPress 特定的标记
        $content = $post->post_content;

        // 转换古腾堡块编辑器格式
        if (function_exists('has_blocks') && has_blocks($post)) {
            // 如果是块编辑器文章，内容通常已经是 HTML（带有注释），直接使用即可
            // 不需要特别的转换，因为 WordPress 的 post_content 已经包含了 HTML
        }

        // 处理短代码
        $content = do_shortcode($content);

        // 基本的 HTML 清理，MetaWeblog API 接受 HTML，但某些特殊字符可能会引起问题
        // 一般来说，只要 XML 编码正确，HTML 应该是安全的
        
        // 移除 WordPress 自动添加的段落标签（如果内容看起来像是手写的 HTML）
        // if (!empty($content) && strpos($content, '<') === false) {
        //    $content = wpautop($content);
        // }

        // 添加原文链接（如果启用）
        if (!empty($this->options['add_source_link'])) {
            $source_text = trim($this->options['source_link_text'] ?? '');
            $source_name = $source_text !== '' ? $source_text : get_bloginfo('name');
            $source_url = get_permalink($post);
            $source_mode = $this->options['source_link_mode'] ?? 'append';

            if ($source_mode !== 'struct') {
                // 追加到正文
                $label = $source_text !== '' ? $source_text : __('原文链接', 'cnblogs-sync');
                $source_link = sprintf(
                    "\n\n---\n**%s：** [%s](%s)",
                    $label,
                    $source_name,
                    $source_url
                );
                $content .= $source_link;
            }
        }

        $data = array(
            'title' => $post->post_title,
            'description' => $content,
            'dateCreated' => strtotime($post->post_date),
        );

        if (!empty($this->options['add_source_link'])) {
            $source_text = trim($this->options['source_link_text'] ?? '');
            $source_name = $source_text !== '' ? $source_text : get_bloginfo('name');
            $source_url = get_permalink($post);
            $source_mode = $this->options['source_link_mode'] ?? 'append';

            if ($source_mode === 'struct') {
                $data['source'] = array(
                    'name' => $source_name,
                    'url' => $source_url
                );
            }
        }

        // 检查高级同步选项（分类、标签、摘要等）
        // 默认开启，如果在设置中关闭了此选项，则跳过这些字段
        $sync_advanced = isset($this->options['sync_advanced_fields']) ? $this->options['sync_advanced_fields'] : true;

        if ($sync_advanced) {
            // 获取标签
            $tags = wp_get_post_tags($post->ID, array('fields' => 'names'));

            // 获取分类
            $categories = wp_get_post_categories($post->ID, array('fields' => 'names'));

            $data['categories'] = !empty($categories) ? $categories : array();
            $data['tags'] = !empty($tags) ? $tags : array();
            $data['excerpt'] = $post->post_excerpt;
            $data['wp_slug'] = $post->post_name;
        }

        return $data;
    }

    /**
     * 转换古腾堡块编辑器格式为 HTML
     * 
     * @param string $content 块编辑器内容
     * @return string 转换后的 HTML
     */
    private function convert_gutenberg_blocks($content) {
        // 使用 WordPress 的块渲染器
        if (function_exists('render_block_core_paragraph')) {
            // 这里可以实现更复杂的块转换逻辑
            // 目前使用简单的正则表达式处理
        }
        return $content;
    }

    /**
     * 保存同步记录到数据库
     * 
     * @param int    $post_id          WordPress 文章 ID
     * @param string $cnblogs_post_id   CNBlogs 文章 ID
     * @return void
     */
    private function save_sync_record($post_id, $cnblogs_post_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cnblogs_sync_records';

        // 检查是否已存在记录
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE post_id = %d",
            $post_id
        ));

        if ($existing) {
            // 更新现有记录
            $wpdb->update(
                $table_name,
                array(
                    'cnblogs_post_id' => $cnblogs_post_id,
                    'last_sync_time' => current_time('mysql'),
                    'sync_status' => 'synced',
                    'error_message' => NULL
                ),
                array('post_id' => $post_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // 插入新记录
            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'cnblogs_post_id' => $cnblogs_post_id,
                    'sync_status' => 'synced',
                    'sync_time' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s')
            );
        }

        // 同时保存到文章元数据（用于快速查询）
        update_post_meta($post_id, '_cnblogs_synced', true);
        update_post_meta($post_id, '_cnblogs_post_id', $cnblogs_post_id);
        update_post_meta($post_id, '_cnblogs_sync_time', current_time('mysql'));
        delete_post_meta($post_id, '_cnblogs_sync_error');
    }

    /**
     * 处理文章更新事件
     * 
     * 检查是否需要更新 CNBlogs 上的文章
     * 
     * @param int    $post_id      文章 ID
     * @param WP_Post $post_after   更新后的文章对象
     * @param WP_Post $post_before  更新前的文章对象
     * @return void
     */
    public function on_post_updated($post_id, $post_after, $post_before) {
        // 只处理已发布的文章
        if ($post_after->post_status !== 'publish') {
            return;
        }

        // 检查是否已同步过
        $cnblogs_post_id = get_post_meta($post_id, '_cnblogs_post_id', true);
        if (!$cnblogs_post_id) {
            return;
        }

        // 检查是否启用了自动同步更新
        if (empty($this->options['auto_sync_updates'])) {
            return;
        }

        // 执行更新
        $this->do_update_post($post_id, $cnblogs_post_id);
    }

    /**
     * 更新 CNBlogs 上的文章
     * 
     * @param int    $post_id          WordPress 文章 ID
     * @param string $cnblogs_post_id   CNBlogs 文章 ID
     * @return bool 更新是否成功
     */
    private function do_update_post($post_id, $cnblogs_post_id) {
        try {
            $post = get_post($post_id);
            $post_data = $this->prepare_post_data($post);

            $result = $this->metaweblog_client->edit_post(
                $cnblogs_post_id,
                $post_data
            );

            if ($result) {
                update_post_meta($post_id, '_cnblogs_sync_time', current_time('mysql'));
                $this->log_sync('update', $post_id, $cnblogs_post_id);
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->log_sync('error', $post_id, $cnblogs_post_id, 'Update: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 处理文章删除事件
     * 
     * 记录文章删除，可选地从 CNBlogs 删除
     * 
     * @param int $post_id 文章 ID
     * @return void
     */
    public function on_post_deleted($post_id) {
        global $wpdb;

        // 获取同步记录
        $table_name = $wpdb->prefix . 'cnblogs_sync_records';
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT cnblogs_post_id FROM $table_name WHERE post_id = %d",
            $post_id
        ));

        if (!$record) {
            return;
        }

        // 检查是否启用了同步删除
        if (!empty($this->options['sync_delete'])) {
            try {
                $this->metaweblog_client->delete_post($record->cnblogs_post_id);
                $this->log_sync('delete', $post_id, $record->cnblogs_post_id);
            } catch (Exception $e) {
                $this->log_sync('error', $post_id, $record->cnblogs_post_id, 'Delete: ' . $e->getMessage());
            }
        }

        // 删除数据库记录
        $wpdb->delete(
            $table_name,
            array('post_id' => $post_id),
            array('%d')
        );
    }

    /**
     * 定时同步任务
     * 
     * 此任务在定时任务中执行，同步所有待同步的文章
     * 
     * @return void
     */
    public function scheduled_sync() {
        // 查询所有待同步的文章
        $args = array(
            'posts_per_page' => -1,
            'post_type' => 'post',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_cnblogs_sync_pending',
                    'value' => true
                )
            )
        );

        $posts = get_posts($args);

        foreach ($posts as $post) {
            $this->sync_post_to_cnblogs($post->ID);
        }
    }

    /**
     * AJAX: 测试连接
     * 
     * 测试与 CNBlogs 的连接
     * 
     * @return void
     */
    public function ajax_test_connection() {
        // 清理任何之前的输出缓冲，防止 JSON 解析错误
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        // 启用输出缓冲，捕获可能的 PHP 错误输出
        ob_start();
        
        try {
            // 验证 nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cnblogs_sync_nonce')) {
                ob_end_clean();
                wp_send_json_error(__('安全验证失败', 'cnblogs-sync'));
            }

            // 检查权限
            if (!current_user_can('manage_options')) {
                ob_end_clean();
                wp_send_json_error(__('您没有权限执行此操作', 'cnblogs-sync'));
            }

            $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
            $password = isset($_POST['password']) ? sanitize_text_field($_POST['password']) : '';

            // 验证用户名和密码
            if (empty($username)) {
                ob_end_clean();
                wp_send_json_error(__('请输入 CNBlogs 用户名', 'cnblogs-sync'));
            }

            if (empty($password)) {
                ob_end_clean();
                wp_send_json_error(__('请输入 MetaWeblog 访问令牌（密码）', 'cnblogs-sync'));
            }

            // 使用用户配置的 API URL，如果没有配置则使用默认值
            $api_url = isset($this->options['cnblogs_api_url']) && !empty($this->options['cnblogs_api_url'])
                ? $this->options['cnblogs_api_url']
                : 'https://www.cnblogs.com/api/metaweblog/new';

            // 验证 API URL
            if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
                ob_end_clean();
                wp_send_json_error(__('API URL 格式不正确', 'cnblogs-sync'));
            }

            $client = new MetaWeblog_Client(
                $api_url,
                $username,
                $password
            );

            // 尝试获取用户博客列表来验证凭证
            error_log('CNBlogs Sync: 开始测试连接 - API URL: ' . $api_url . ', 用户名: ' . $username);
            $blogs = $client->get_users_blogs();
            error_log('CNBlogs Sync: get_users_blogs 返回 - 类型: ' . gettype($blogs) . ', 元素数量: ' . (is_array($blogs) ? count($blogs) : 'N/A'));

            // 清理缓冲区
            ob_end_clean();

            // 博客列表为空有两种可能：
            // 1. 用户确实没有博客（但认证成功）
            // 2. 认证失败，API 返回了空数组
            // 我们无法直接区分，但如果没有异常抛出，说明连接是成功的
            
            if (empty($blogs)) {
                // 虽然博客列表为空，但没有异常说明连接成功
                wp_send_json_success(array(
                    'message' => __('连接成功！但获取到的博客列表为空。请确保你的 CNBlogs 账户有博客，或检查 MetaWeblog 访问令牌是否正确。', 'cnblogs-sync'),
                    'warning' => true,
                    'user_info' => array()
                ));
            } else {
                // 确保 $blogs 是数组
                if (is_object($blogs)) {
                    $blogs = (array)$blogs;
                }
                
                // 提取并保存 Blog ID
                $blog_info = reset($blogs);
                $blog_id = '';
                
                if (is_array($blog_info) && isset($blog_info['blogid'])) {
                    $blog_id = $blog_info['blogid'];
                } elseif (is_object($blog_info) && isset($blog_info->blogid)) {
                    $blog_id = $blog_info->blogid;
                }

                if (!empty($blog_id)) {
                    // 保存 Blog ID 到选项中
                    $current_options = get_option('cnblogs_sync_options', array());
                    $current_options['cnblogs_blog_id'] = (string)$blog_id;
                    update_option('cnblogs_sync_options', $current_options);
                    
                    error_log('CNBlogs Sync: 已保存 Blog ID: ' . $blog_id);
                }

                wp_send_json_success(array(
                    'message' => __('连接成功！已获取并保存博客 ID (' . $blog_id . ')', 'cnblogs-sync'),
                    'user_info' => is_array($blogs) ? $blogs : array()
                ));
            }

        } catch (Exception $e) {
            // 清理缓冲区
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // 获取更详细的错误信息
            $error_message = $e->getMessage();
            
            // 如果错误信息包含 HTTP 错误码，添加更多帮助信息
            if (strpos($error_message, 'HTTP') !== false) {
                $error_message .= sprintf(
                    __(' (请检查 API URL: %s 是否正确)', 'cnblogs-sync'),
                    esc_html($api_url)
                );
            }
            
            wp_send_json_error($error_message);
        }
    }

    /**
     * AJAX: 同步单篇文章
     * 
     * @return void
     */
    public function ajax_sync_single_post() {
        // 禁止显示错误，强制将错误记录到日志中，防止污染 JSON 响应
        @ini_set('display_errors', 0);

        // 开启输出缓冲区，捕获所有可能的 PHP 警告或杂散输出
        ob_start();

        // 验证 nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cnblogs_sync_nonce')) {
            ob_end_clean(); // 清除缓冲区
            wp_send_json_error(__('安全验证失败', 'cnblogs-sync'));
        }

        // 检查权限
        if (!current_user_can('edit_posts')) {
            ob_end_clean();
            wp_send_json_error(__('您没有权限执行此操作', 'cnblogs-sync'));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            ob_end_clean();
            wp_send_json_error(__('无效的文章 ID', 'cnblogs-sync'));
        }

        try {
            $result = $this->do_sync_post($post_id);
            
            // 操作完成后，清除缓冲区内容
            ob_end_clean();

            if ($result) {
                // 获取最新的同步记录以返回给前端
                global $wpdb;
                $table_name = $wpdb->prefix . 'cnblogs_sync_records';
                $record = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE post_id = %d",
                    $post_id
                ));

                wp_send_json_success(array(
                    'message' => __('文章同步成功！', 'cnblogs-sync'),
                    'synced' => true,
                    'url' => $record ? $record->cnblogs_post_url : '',
                    'time' => $record ? $record->sync_time : current_time('mysql'),
                    'last_time' => $record ? $record->last_sync_time : current_time('mysql')
                ));
            } else {
                $error = get_post_meta($post_id, '_cnblogs_sync_error', true);
                wp_send_json_error($error ?: __('同步失败，请重试', 'cnblogs-sync'));
            }
        } catch (Exception $e) {
            ob_end_clean();
            error_log('CNBlogs Sync Error: ' . $e->getMessage());
            wp_send_json_error('同步异常: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: 获取同步状态
     * 
     * @return void
     */
    public function ajax_get_sync_status() {
        // 验证 nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cnblogs_sync_nonce')) {
            wp_send_json_error(__('安全验证失败', 'cnblogs-sync'));
        }

        // 检查权限
        if (!current_user_can('read_posts')) {
            wp_send_json_error(__('您没有权限执行此操作', 'cnblogs-sync'));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(__('无效的文章 ID', 'cnblogs-sync'));
        }

        $synced = get_post_meta($post_id, '_cnblogs_synced', true);
        $cnblogs_id = get_post_meta($post_id, '_cnblogs_post_id', true);
        $error = get_post_meta($post_id, '_cnblogs_sync_error', true);
        $sync_time = get_post_meta($post_id, '_cnblogs_sync_time', true);

        wp_send_json_success(array(
            'synced' => (bool)$synced,
            'cnblogs_id' => $cnblogs_id,
            'error' => $error,
            'sync_time' => $sync_time
        ));
    }

    /**
     * 记录同步日志
     * 
     * 记录所有同步操作到日志中，便于调试和查看历史
     * 
     * @param string $type   操作类型: success, error, update, delete
     * @param int    $post_id WordPress 文章 ID
     * @param string $cnblogs_id CNBlogs 文章 ID
     * @param string $message 额外信息
     * @return void
     */
    private function log_sync($type, $post_id, $cnblogs_id = '', $message = '') {
        $log = get_option('cnblogs_sync_sync_log', array());

        $log_entry = array(
            'time' => current_time('mysql'),
            'type' => $type,
            'post_id' => $post_id,
            'cnblogs_id' => $cnblogs_id,
            'message' => $message
        );

        // 保持日志最多 100 条
        $log[] = $log_entry;
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }

        update_option('cnblogs_sync_sync_log', $log);
    }

    /**
     * 在文章列表中添加同步状态列
     * 
     * @param array $columns 现有的列
     * @return array 修改后的列
     */
    public function add_sync_column($columns) {
        // 在标题列后插入同步状态列
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['cnblogs_sync_status'] = __('CNBlogs 同步状态', 'cnblogs-sync');
            }
        }
        return $new_columns;
    }

    /**
     * 显示文章列表中的同步状态
     * 
     * @param string $column_name 列名
     * @param int    $post_id     文章 ID
     * @return void
     */
    public function display_sync_column($column_name, $post_id) {
        if ($column_name !== 'cnblogs_sync_status') {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'cnblogs_sync_records';
        
        // 从数据库查询同步记录
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT sync_status, cnblogs_post_url, sync_time FROM $table_name WHERE post_id = %d",
                $post_id
            )
        );

        if (!$record) {
            echo '<span style="color: #999;">未同步</span>';
            return;
        }

        // 显示同步状态
        $status_labels = array(
            'synced' => '✓ 已同步',
            'failed' => '✗ 失败',
            'pending' => '⏳ 待处理'
        );

        $status_colors = array(
            'synced' => '#008000',
            'failed' => '#d63638',
            'pending' => '#ff6600'
        );

        $status = $record->sync_status ?? 'unknown';
        $label = $status_labels[$status] ?? $status;
        $color = $status_colors[$status] ?? '#999';

        echo sprintf(
            '<span style="color: %s;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );

        // 显示同步时间
        if ($record->sync_time) {
            echo '<br><small style="color: #999;">' . esc_html(mysql2date('Y-m-d H:i', $record->sync_time)) . '</small>';
        }
    }

    /**
     * 在文章列表行操作中添加快速同步按钮
     * 
     * @param array   $actions 现有的行操作
     * @param WP_Post $post    文章对象
     * @return array 修改后的操作
     */
    public function add_quick_sync_action($actions, $post) {
        // 只在发布状态的文章上显示
        if ($post->post_status !== 'publish') {
            return $actions;
        }

        // 检查是否启用了同步
        if (empty($this->options['enable'])) {
            return $actions;
        }

        // 检查用户权限
        if (!current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        // 添加快速同步按钮
        $actions['cnblogs_sync'] = sprintf(
            '<a href="#" class="cnblogs-quick-sync" data-post-id="%d" data-nonce="%s">%s</a>',
            esc_attr($post->ID),
            esc_attr(wp_create_nonce('cnblogs_sync_nonce')),
            __('同步到 CNBlogs', 'cnblogs-sync')
        );

        return $actions;
    }

    /**
     * 向编辑页面添加同步状态 Meta Box
     * 
     * @return void
     */
    public function add_sync_meta_box() {
        // 检查是否启用了同步
        if (empty($this->options['enable'])) {
            return;
        }

        add_meta_box(
            'cnblogs_sync_meta_box',
            __('CNBlogs 同步', 'cnblogs-sync'),
            array($this, 'display_sync_meta_box'),
            'post',
            'side',
            'default'
        );
    }

    /**
     * 显示编辑页面中的同步状态 Meta Box
     * 
     * @param WP_Post $post 文章对象
     * @return void
     */
    public function display_sync_meta_box($post) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cnblogs_sync_records';

        // 查询同步记录
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE post_id = %d",
                $post->ID
            )
        );

        // 显示同步状态
        echo '<div class="cnblogs-sync-status-container" style="margin-bottom: 15px;">';
        
        if ($record) {
            $status_labels = array(
                'synced' => __('已同步', 'cnblogs-sync'),
                'failed' => __('同步失败', 'cnblogs-sync'),
                'pending' => __('待处理', 'cnblogs-sync')
            );

            $status = $record->sync_status ?? 'unknown';
            $label = $status_labels[$status] ?? $status;

            echo '<p><strong>' . __('同步状态：', 'cnblogs-sync') . '</strong>';
            echo '<span style="color: ' . ($status === 'synced' ? '#008000' : ($status === 'failed' ? '#d63638' : '#ff6600')) . ';">';
            echo esc_html($label);
            echo '</span></p>';

            if ($record->sync_time) {
                echo '<p><small>';
                echo __('首次同步：', 'cnblogs-sync') . esc_html(mysql2date('Y-m-d H:i:s', $record->sync_time));
                echo '</small></p>';
            }

            if ($record->last_sync_time && $record->last_sync_time !== $record->sync_time) {
                echo '<p><small>';
                echo __('最后更新：', 'cnblogs-sync') . esc_html(mysql2date('Y-m-d H:i:s', $record->last_sync_time));
                echo '</small></p>';
            }

            if ($record->cnblogs_post_url) {
                echo '<p><small>';
                echo sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    esc_url($record->cnblogs_post_url),
                    __('查看 CNBlogs 文章', 'cnblogs-sync')
                );
                echo '</small></p>';
            }

            if ($record->sync_status === 'failed' && $record->error_message) {
                echo '<p style="color: #d63638;"><small><strong>';
                echo __('错误信息：', 'cnblogs-sync') . esc_html($record->error_message);
                echo '</strong></small></p>';
            }
        } else {
            echo '<p style="color: #999;">' . __('此文章尚未同步到 CNBlogs', 'cnblogs-sync') . '</p>';
        }

        echo '</div>';

        // 显示同步按钮
        echo '<div>';
        echo sprintf(
            '<button type="button" class="button button-primary cnblogs-sync-btn" data-post-id="%d" data-nonce="%s">%s</button>',
            esc_attr($post->ID),
            esc_attr(wp_create_nonce('cnblogs_sync_nonce')),
            __('立即同步', 'cnblogs-sync')
        );
        echo '</div>';

        // 添加样式和脚本处理同步按钮点击
        echo '<script type="text/javascript">
(function() {
    // 兼容经典编辑器和古腾堡编辑器的初始化
    function initCNBlogsSync() {
        const metaBox = document.getElementById("cnblogs_sync_meta_box");
        if (!metaBox) return;

        const metaBoxBtn = metaBox.querySelector(".cnblogs-sync-btn");
        if (metaBoxBtn && !metaBoxBtn.dataset.bound) {
            metaBoxBtn.dataset.bound = "true";
            metaBoxBtn.addEventListener("click", function(e) {
                e.preventDefault();
                const postId = this.getAttribute("data-post-id");
                const nonce = this.getAttribute("data-nonce");
                syncPostFromMetaBox(postId, nonce, this);
            });
        }
    }

    // 初始加载
    if (document.readyState === "complete" || document.readyState === "interactive") {
        initCNBlogsSync();
    } else {
        document.addEventListener("DOMContentLoaded", initCNBlogsSync);
    }
    
    // 针对古腾堡编辑器：监听 DOM 变化
    const observer = new MutationObserver(function(mutations) {
        initCNBlogsSync();
    });
    observer.observe(document.body, { childList: true, subtree: true });

    function syncPostFromMetaBox(postId, nonce, button) {
        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = "' . esc_js(__('同步中...', 'cnblogs-sync')) . '";

        fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({
                action: "cnblogs_sync_single_post",
                post_id: postId,
                nonce: nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.textContent = "' . esc_js(__('同步成功', 'cnblogs-sync')) . '";
                button.disabled = false;
                
                // 动态更新 Meta Box 内容
                const metaBox = document.getElementById("cnblogs_sync_meta_box");
                const statusContainer = metaBox ? metaBox.querySelector(".cnblogs-sync-status-container") : null;
                
                if (statusContainer && data.data && typeof data.data === "object") {
                    const info = data.data; 
                    let html = "<p><strong>' . esc_js(__('同步状态：', 'cnblogs-sync')) . '</strong> ";
                    html += "<span style=\"color: #008000;\">' . esc_js(__('已同步', 'cnblogs-sync')) . '</span></p>";
                    
                    if (info.time) {
                        html += "<p><small>' . esc_js(__('首次同步：', 'cnblogs-sync')) . '" + info.time + "</small></p>";
                    }
                    if (info.last_time && info.last_time !== info.time) {
                        html += "<p><small>' . esc_js(__('最后更新：', 'cnblogs-sync')) . '" + info.last_time + "</small></p>";
                    }
                    if (info.url) {
                        html += "<p><small><a href=\"" + info.url + "\" target=\"_blank\">' . esc_js(__('查看 CNBlogs 文章', 'cnblogs-sync')) . '</a></small></p>";
                    }
                    statusContainer.innerHTML = html;
                } else {
                    setTimeout(function() { location.reload(); }, 1500);
                }
            } else {
                alert("' . esc_js(__('同步失败：', 'cnblogs-sync')) . '" + (data.data || "未知错误"));
                button.disabled = false;
                button.textContent = originalText;
            }
        })
        .catch(error => {
            alert("' . esc_js(__('请求失败：', 'cnblogs-sync')) . '" + error);
            button.disabled = false;
            button.textContent = originalText;
        });
    }
})();
</script>';
    }
}
