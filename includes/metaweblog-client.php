<?php
/**
 * MetaWeblog 客户端类
 * 
 * 实现 MetaWeblog XML-RPC 协议与 CNBlogs API 的通信
 * MetaWeblog 是一个标准的博客发布协议，支持创建、编辑、删除文章
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MetaWeblog 客户端 - 处理与 CNBlogs 的 XML-RPC 通信
 */
class MetaWeblog_Client {

    /**
     * API 端点 URL
     * 
     * @var string
     */
    private $api_url = '';

    /**
     * CNBlogs 用户名
     * 
     * @var string
     */
    private $username = '';

    /**
     * CNBlogs 密码
     * 
     * @var string
     */
    private $password = '';

    /**
     * 博客 ID
     * 
     * @var string
     */
    private $blog_id = '';

    /**
     * cURL 超时时间（秒）
     * 
     * @var int
     */
    private $timeout = 120;

    /**
     * 是否验证 SSL 证书
     * 
     * @var bool
     */
    private $verify_ssl = true;

    /**
     * 构造函数
     * 
     * @param string $api_url   MetaWeblog API 端点
     * @param string $username  CNBlogs 用户名
     * @param string $password  CNBlogs 密码
     * @param string $blog_id   博客 ID (可选)
     */
    public function __construct($api_url, $username, $password, $blog_id = '1') {
        $this->api_url = $api_url;
        $this->username = $username;
        $this->password = $password;
        $this->blog_id = $blog_id;
    }

    /**
     * 发送 XML-RPC 请求到 MetaWeblog API
     * 
     * 此方法构建 XML-RPC 请求体并通过 cURL 发送到 MetaWeblog 端点
     * 然后解析返回的 XML 响应
     * 
     * @param string $method    XML-RPC 方法名
     * @param array  $params    方法参数
     * @return mixed XML-RPC 返回值（需要解析）
     * @throws Exception 当请求失败或返回错误时
     */
    private function request($method, $params = array()) {
        // 构建 XML-RPC 请求
        $request_body = $this->build_xml_rpc_request($method, $params);
        
        // 调试日志
        error_log('CNBlogs Sync: 发送 XML-RPC 请求 - 方法: ' . $method . ', URL: ' . $this->api_url);
        
        // 记录完整的 XML 请求体用于调试
        error_log('CNBlogs Sync: XML-RPC 请求体: ' . $request_body);

        // 暂时添加过滤器以确保超时设置生效（防止被其他插件修改）
        add_filter('http_request_timeout', array($this, 'filter_http_request_timeout'));

        // 使用 wp_remote_post 发送请求（WordPress 推荐方式）
        $response = wp_remote_post(
            $this->api_url,
            array(
                'method' => 'POST',
                'timeout' => $this->timeout,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'Content-Type' => 'text/xml',
                    'User-Agent' => 'CNBlogs-Sync-WordPress/' . CNBLOGS_SYNC_VERSION
                ),
                'body' => $request_body,
                'sslverify' => $this->verify_ssl
            )
        );

        // 移除过滤器
        remove_filter('http_request_timeout', array($this, 'filter_http_request_timeout'));

        // 检查是否有错误
        if (is_wp_error($response)) {
            throw new Exception(sprintf(
                __('网络请求失败: %s', 'cnblogs-sync'),
                $response->get_error_message()
            ));
        }

        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        error_log('CNBlogs Sync: API 响应 - HTTP 代码: ' . $http_code . ', 响应体长度: ' . strlen($body) . ' 字节');
        
        // 专门为 blogger.getUsersBlogs 或响应体较短的情况记录原始 XML
        if ($method === 'blogger.getUsersBlogs' || strlen($body) < 1000) {
            error_log('CNBlogs Sync: 原始 API 响应体 (' . $method . '): ' . $body);
        }

        if ($http_code !== 200) {
            throw new Exception(sprintf(
                __('API 返回错误: HTTP %d', 'cnblogs-sync'),
                $http_code
            ));
        }

        // 解析 XML-RPC 响应
        return $this->parse_xml_rpc_response($body);
    }

    /**
     * 构建 XML-RPC 请求
     * 
     * 创建标准的 XML-RPC 方法调用格式
     * 
     * @param string $method 方法名
     * @param array  $params 参数
     * @return string XML-RPC 请求体
     */
    private function build_xml_rpc_request($method, $params = array()) {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<methodCall>\n";
        $xml .= "  <methodName>" . htmlspecialchars($method) . "</methodName>\n";
        $xml .= "  <params>\n";

        foreach ($params as $param) {
            $xml .= "    <param>\n";
            $xml .= "      " . $this->encode_xml_rpc_value($param);
            $xml .= "    </param>\n";
        }

        $xml .= "  </params>\n";
        $xml .= "</methodCall>";

        return $xml;
    }

    /**
     * 编码 XML-RPC 值
     * 
     * 将 PHP 数据类型转换为 XML-RPC 格式
     * 
     * @param mixed $value 要编码的值
     * @return string XML-RPC 格式的值
     */
    private function encode_xml_rpc_value($value) {
        if (is_string($value)) {
            // 检查看起来像 ISO8601 日期的字符串 (YYYYMMDDTHH:MM:SS)
            if (preg_match('/^\d{8}T\d{2}:\d{2}:\d{2}$/', $value)) {
                 return "<value><dateTime.iso8601>" . $value . "</dateTime.iso8601></value>";
            }
            // 字符串类型 - 需要转义
            return "<value><string>" . htmlspecialchars($value, ENT_XML1, 'UTF-8') . "</string></value>";
        } elseif (is_int($value) || is_float($value)) {
            // 数值类型
            return "<value><int>" . intval($value) . "</int></value>";
        } elseif (is_bool($value)) {
            // 布尔类型
            return "<value><boolean>" . ($value ? '1' : '0') . "</boolean></value>";
        } elseif (is_array($value)) {
            // 数组类型 - 检查是否为关联数组（映射）或索引数组（数组）
            if ($this->is_associative_array($value)) {
                // 映射（键值对）
                $xml = "<value><struct>";
                foreach ($value as $key => $val) {
                    $xml .= "<member>";
                    $xml .= "<name>" . htmlspecialchars($key) . "</name>";
                    $xml .= $this->encode_xml_rpc_value($val);
                    $xml .= "</member>";
                }
                $xml .= "</struct></value>";
                return $xml;
            } else {
                // 数组（索引数组）
                $xml = "<value><array><data>";
                foreach ($value as $val) {
                    $xml .= $this->encode_xml_rpc_value($val);
                }
                $xml .= "</data></array></value>";
                return $xml;
            }
        } else {
            // 默认作为字符串处理
            return "<value><string>" . htmlspecialchars((string)$value) . "</string></value>";
        }
    }

    /**
     * 检查数组是否为关联数组（键值对）
     * 
     * @param array $array 要检查的数组
     * @return bool 如果是关联数组返回 true
     */
    private function is_associative_array($array) {
        $keys = array_keys($array);
        return $keys !== array_keys($keys);
    }

    /**
     * 解析 XML-RPC 响应
     * 
     * 从 XML-RPC 响应中提取返回值或错误信息
     * 
     * @param string $xml_response XML 响应内容
     * @return mixed 解析的返回值
     * @throws Exception 当响应包含错误时
     */
    private function parse_xml_rpc_response($xml_response) {
        // 抑制 XML 错误
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xml_response);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new Exception(__('无法解析 XML 响应', 'cnblogs-sync'));
        }

        // 检查是否为错误响应
        // 注意: simplexml_load_string 返回的是跟元素，所以不需要再访问根元素名称 (methodResponse)
        if (isset($xml->fault)) {
            $fault = $xml->fault->value;
            
            // 尝试从 struct 中提取错误信息
            $fault_code = 0;
            $fault_string = '未知错误';
            
            if (isset($fault->struct->member)) {
                foreach ($fault->struct->member as $member) {
                    $name = (string)$member->name;
                    if ($name === 'faultCode' && isset($member->value->int)) {
                        $fault_code = (int)$member->value->int;
                    } elseif ($name === 'faultString' && isset($member->value->string)) {
                        $fault_string = (string)$member->value->string;
                    }
                }
            }
            
            throw new Exception(sprintf(
                __('MetaWeblog 错误 [%d]: %s', 'cnblogs-sync'),
                $fault_code,
                $fault_string
            ));
        }

        // 提取正常响应
        if (isset($xml->params->param->value)) {
            $parsed = $this->parse_xml_rpc_value($xml->params->param->value);
            
            // 检查返回值是否为空或无效
            // 对于数组类型，即使为空也是有效的（如空的博客列表）
            if (!is_array($parsed) && empty($parsed) && !is_bool($parsed) && $parsed !== 0) {
                throw new Exception(__('API 返回为空，请检查用户名和密码是否正确', 'cnblogs-sync'));
            }
            
            return $parsed;
        }

        return null;
    }

    /**
     * 解析 XML-RPC 值
     * 
     * 将 XML-RPC 格式的值转换为 PHP 数据类型
     * 
     * @param SimpleXMLElement $value XML-RPC 值元素
     * @return mixed 解析后的 PHP 值
     */
    private function parse_xml_rpc_value($value) {
        // 没有指定类型时，默认为字符串
        // 注意：不能使用 isset() 检查函数返回值，应该使用 null 比较
        $children = $value->children();
        
        if (null === $children || $children->count() === 0) {
            return (string)$value;
        }

        if (isset($children->string)) {
            return (string)$children->string;
        } elseif (isset($children->int) || isset($children->i4)) {
            return (int)($children->int ?: $children->i4);
        } elseif (isset($children->boolean)) {
            return (bool)(int)$children->boolean;
        } elseif (isset($children->double)) {
            return (float)$children->double;
        } elseif (isset($children->array)) {
            $result = array();
            foreach ($children->array->data->value as $item) {
                $result[] = $this->parse_xml_rpc_value($item);
            }
            return $result;
        } elseif (isset($children->struct)) {
            $result = array();
            foreach ($children->struct->member as $member) {
                $name = (string)$member->name;
                $result[$name] = $this->parse_xml_rpc_value($member->value);
            }
            return $result;
        } else {
            // 无类型标记，假设为字符串
            return (string)$value;
        }
    }

    /**
     * 创建新文章
     * 
     * 使用 MetaWeblog.newPost 方法在 CNBlogs 上创建新文章
     * 
     * @param array $post_data 文章数据
     *   - title: 文章标题
     *   - description: 文章内容
     *   - categories: 分类数组
     *   - tags: 标签数组
     *   - dateCreated: 发布时间戳
     * @param bool $publish 是否立即发布
     * @return string CNBlogs 文章 ID
     * @throws Exception 当 API 调用失败时
     */
    public function new_post($post_data, $publish = true) {
        error_log('CNBlogs Sync: 开始创建新文章 - Blog ID: ' . $this->blog_id);
        
        // 准备 MetaWeblog 格式的数据 - 严格遵循 struct Post 定义顺序
        // 1. dateCreated
        // 2. description
        // 3. title
        $metaweblog_post = array(
            'dateCreated' => isset($post_data['dateCreated'])
                ? $this->timestamp_to_iso8601($post_data['dateCreated'])
                : $this->timestamp_to_iso8601(time()),
            'description' => $post_data['description'],
            'title' => $post_data['title']
        );

        // 恢复可选参数
        // 4. categories (optional)
        if (!empty($post_data['categories'])) {
            $metaweblog_post['categories'] = $post_data['categories'];
        }

        // 15. mt_excerpt (optional)
        if (!empty($post_data['excerpt'])) {
            $metaweblog_post['mt_excerpt'] = $post_data['excerpt'];
        }

        // 16. mt_keywords (optional)
        if (!empty($post_data['tags'])) {
            $metaweblog_post['mt_keywords'] = implode(',', $post_data['tags']);
        }

        // 17. wp_slug (optional)
        if (!empty($post_data['wp_slug'])) {
            $metaweblog_post['wp_slug'] = $post_data['wp_slug'];
        }

        // 日志记录请求参数详情
        error_log('CNBlogs Sync: newPost 参数详情 - ' . json_encode([
            'blog_id' => $this->blog_id,
            'username' => $this->username,
            'title' => $metaweblog_post['title'],
            'len_desc' => strlen($metaweblog_post['description']),
            'categories' => isset($metaweblog_post['categories']) ? $metaweblog_post['categories'] : [],
            'publish' => $publish
        ]));

        // 调用 MetaWeblog API
        try {
            $post_id = $this->request('metaWeblog.newPost', array(
                (string)$this->blog_id,  // blogid - 确保是字符串
                $this->username,  // username
                $this->password,  // password
                $metaweblog_post,  // struct
                (bool)$publish     // publish - 确保是布尔值
            ));
        } catch (Exception $e) {
            error_log('CNBlogs Sync: newPost 异常 - ' . $e->getMessage());
            throw $e;
        }

        if (!$post_id) {
            throw new Exception(__('无法创建文章：API 返回空值', 'cnblogs-sync'));
        }

        return (string)$post_id;
    }

    /**
     * 编辑现有文章
     * 
     * 使用 MetaWeblog.editPost 方法更新 CNBlogs 上的文章
     * 标准 MetaWeblog API: editPost(postid, username, password, struct, publish)
     * 
     * @param string $post_id   CNBlogs 文章 ID
     * @param array  $post_data 新的文章数据
     * @return bool 更新是否成功
     * @throws Exception 当 API 调用失败时
     */
    public function edit_post($post_id, $post_data) {
        // 调试日志：记录编辑操作开始
        error_log('CNBlogs Sync: 开始编辑文章 - postid: ' . $post_id);
        
        // 准备 MetaWeblog 格式的数据 - 严格遵循 struct Post 定义顺序
        $metaweblog_post = array(
            'dateCreated' => isset($post_data['dateCreated'])
                ? $this->timestamp_to_iso8601($post_data['dateCreated'])
                : $this->timestamp_to_iso8601(time()),
            'description' => $post_data['description'],
            'title' => $post_data['title']
        );

        if (!empty($post_data['categories'])) {
            $metaweblog_post['categories'] = $post_data['categories'];
        }

        if (!empty($post_data['excerpt'])) {
            $metaweblog_post['mt_excerpt'] = $post_data['excerpt'];
        }

        if (!empty($post_data['tags'])) {
            $metaweblog_post['mt_keywords'] = implode(',', $post_data['tags']);
        }
        
        if (!empty($post_data['wp_slug'])) {
            $metaweblog_post['wp_slug'] = $post_data['wp_slug'];
        }

        // 调试日志：记录即将发送的数据结构
        error_log('CNBlogs Sync: editPost 数据结构 - 标题: ' . $metaweblog_post['title'] . 
                  ', 内容长度: ' . strlen($metaweblog_post['description']) . 
                  ', 分类: ' . json_encode($metaweblog_post['categories'] ?? []) .
                  ', 标签: ' . ($metaweblog_post['mt_keywords'] ?? ''));

        // 调用 MetaWeblog API
        // 标准 MetaWeblog API 参数顺序: postid, username, password, struct, publish
        error_log('CNBlogs Sync: 调用 metaWeblog.editPost - postid: ' . $post_id . 
                  ', username: ' . $this->username . ', publish: 1');
        
        // 生成请求体用于调试
        $request_params = array(
            $post_id,              // postid
            $this->username,       // username
            $this->password,       // password
            $metaweblog_post,      // struct
            1                      // publish
        );
        
        error_log('CNBlogs Sync: editPost 完整参数 - ' . json_encode([
            'postid' => $post_id,
            'postid_type' => gettype($post_id),
            'username' => $this->username,
            'struct_keys' => array_keys($metaweblog_post),
            'publish' => 1
        ]));
        
        $result = $this->request('metaWeblog.editPost', $request_params);

        if ($result) {
            error_log('CNBlogs Sync: editPost 成功，返回值: ' . var_export($result, true));
        } else {
            error_log('CNBlogs Sync: editPost 返回 false 或空值');
        }

        return (bool)$result;
    }

    /**
     * 删除文章
     * 
     * 使用 blogger.deletePost 方法删除 CNBlogs 上的文章
     * 
     * @param string $post_id CNBlogs 文章 ID
     * @return bool 删除是否成功
     * @throws Exception 当 API 调用失败时
     */
    public function delete_post($post_id) {
        $result = $this->request('blogger.deletePost', array(
            '1',                   // appKey
            $post_id,              // postid
            $this->username,       // username
            $this->password,       // password
            1                      // publish
        ));

        return (bool)$result;
    }

    /**
     * 获取分类列表
     * 
     * 使用 metaWeblog.getCategories 方法获取 CNBlogs 的所有分类
     * 
     * @return array 分类列表
     * @throws Exception 当 API 调用失败时
     */
    public function get_categories() {
        $categories = $this->request('metaWeblog.getCategories', array(
            $this->blog_id,        // blogid
            $this->username,       // username
            $this->password        // password
        ));

        return is_array($categories) ? $categories : array();
    }

    /**
     * 获取文章列表
     * 
     * 使用 metaWeblog.getRecentPosts 方法获取最近发布的文章
     * 
     * @param int $count 获取文章数量
     * @return array 文章列表
     * @throws Exception 当 API 调用失败时
     */
    public function get_recent_posts($count = 10) {
        $posts = $this->request('metaWeblog.getRecentPosts', array(
            $this->blog_id,        // blogid
            $this->username,       // username
            $this->password,       // password
            $count                 // numberOfPosts
        ));

        return is_array($posts) ? $posts : array();
    }

    /**
     * 将时间戳转换为 ISO 8601 格式
     * 
     * MetaWeblog 协议要求日期时间为 ISO 8601 格式
     * 格式: YYYYMMDDTHH:MM:SS
     * 
     * @param int $timestamp Unix 时间戳
     * @return string ISO 8601 格式的日期时间
     */
    private function timestamp_to_iso8601($timestamp) {
        return date('Ymd\TH:i:s', $timestamp);
    }

    /**
     * 从 ISO 8601 格式转换为时间戳
     * 
     * @param string $iso8601_string ISO 8601 格式的字符串
     * @return int Unix 时间戳
     */
    private function iso8601_to_timestamp($iso8601_string) {
        return strtotime($iso8601_string);
    }

    /**
     * 获取用户的博客列表
     * 
     * 使用 blogger.getUsersBlogs 方法获取用户在 CNBlogs 下的博客信息
     * 
     * 返回结构：array of struct BlogInfo
     * 每个 BlogInfo 包含：
     *   - string blogid
     *   - string url  
     *   - string blogName
     * 
     * @return array 博客列表，若为空表示用户没有博客
     * @throws Exception 当 API 调用失败时
     */
    public function get_users_blogs() {
        error_log('CNBlogs Sync: 调用 blogger.getUsersBlogs 方法');
        
        $blogs = $this->request('blogger.getUsersBlogs', array(
            'cnblogs-sync-wp',    // appKey
            $this->username,     // username
            $this->password      // password
        ));

        error_log('CNBlogs Sync: blogger.getUsersBlogs 原始返回类型: ' . gettype($blogs));

        // 验证返回结构
        if (!is_array($blogs)) {
            // 如果不是数组，尝试转换对象
            if (is_object($blogs)) {
                error_log('CNBlogs Sync: 转换返回的对象为数组');
                $blogs = (array)$blogs;
            } else {
                error_log('CNBlogs Sync: 返回的不是数组或对象，类型: ' . gettype($blogs) . ', 值: ' . var_export($blogs, true));
                // 返回空数组，这可能表示没有博客
                return array();
            }
        }

        // 验证数组中的每个元素是否有预期的 BlogInfo 结构
        error_log('CNBlogs Sync: 博客列表包含 ' . count($blogs) . ' 个博客');
        
        if (count($blogs) > 0) {
            // 验证第一个博客的结构
            $first_blog = reset($blogs);
            error_log('CNBlogs Sync: 第一个博客的类型: ' . gettype($first_blog));
            if (is_array($first_blog)) {
                error_log('CNBlogs Sync: 第一个博客的键: ' . implode(', ', array_keys($first_blog)));
                error_log('CNBlogs Sync: 第一个博客的内容: ' . json_encode($first_blog));
            } else {
                error_log('CNBlogs Sync: 第一个博客的内容: ' . var_export($first_blog, true));
            }
        }

        return $blogs;
    }

    /**
     * 获取单篇文章
     * 
     * 使用 metaWeblog.getPost 方法获取指定文章的内容
     * 
     * @param string $post_id 文章 ID
     * @return array 文章数据
     * @throws Exception 当 API 调用失败时
     */
    public function get_post($post_id) {
        $post = $this->request('metaWeblog.getPost', array(
            $post_id,
            $this->username,
            $this->password
        ));

        return is_array($post) ? $post : array();
    }

    /**
     * 上传媒体对象
     * 
     * 使用 metaWeblog.newMediaObject 上传文件
     * 参数：blogid, username, password, FileData
     * FileData 结构: array('name' => '', 'type' => '', 'bits' => '')
     * 
     * @param array $media_data 包含 name, type, bits (base64) 等键
     * @return array 返回 UrlData 结构，包含 url 字段
     * @throws Exception 当 API 调用失败时
     */
    public function new_media_object($media_data) {
        // 构建 FileData 结构
        $file_struct = array(
            'name' => $media_data['name'] ?? '',
            'type' => $media_data['type'] ?? 'application/octet-stream',
            'bits' => $media_data['bits'] ?? ''
        );

        // 调用 metaWeblog.newMediaObject
        // 参数: blogid, username, password, FileData
        $result = $this->request('metaWeblog.newMediaObject', array(
            $this->blog_id,         // blogid
            $this->username,        // username
            $this->password,        // password
            $file_struct            // FileData
        ));

        // 返回 UrlData 结构 (包含 url 字段)
        return is_array($result) ? $result : array('url' => '');
    }

    /**
     * 创建新分类
     * 
     * 使用 wp.newCategory 创建新的文章分类
     * 参数：blog_id, username, password, WpCategory
     * WpCategory 结构: array('name' => '', 'slug' => '', 'parent_id' => 0, 'description' => '')
     * 
     * @param array $category_data 包含 name, slug, parent_id, description 等键
     * @return integer 返回新创建的分类 ID
     * @throws Exception 当 API 调用失败时
     */
    public function new_category($category_data) {
        // 构建 WpCategory 结构
        // 必需字段: name, parent_id
        // 可选字段: slug, description
        $category_struct = array(
            'name' => $category_data['name'] ?? '',
            'parent_id' => intval($category_data['parent_id'] ?? 0)
        );
        
        // 添加可选字段
        if (isset($category_data['slug'])) {
            $category_struct['slug'] = $category_data['slug'];
        }
        if (isset($category_data['description'])) {
            $category_struct['description'] = $category_data['description'];
        }

        // 调用 wp.newCategory
        // 参数: blog_id, username, password, WpCategory
        $result = $this->request('wp.newCategory', array(
            $this->blog_id,             // blog_id
            $this->username,            // username
            $this->password,            // password
            $category_struct            // WpCategory
        ));

        // 返回分类 ID (整数)
        return (int)$result;
    }

    public function set_timeout($timeout) {
        $this->timeout = (int)$timeout;
    }

    /**
     * 设置是否验证 SSL 证书
     * 
     * @param bool $verify 是否验证
     * @return void
     */
    public function set_verify_ssl($verify) {
        $this->verify_ssl = (bool)$verify;
    }

    /**
     * 强制设置 HTTP 请求超时时间的回调函数
     * 
     * @param int $timeout_value 当前超时值
     * @return int 修改后的超时值
     */
    public function filter_http_request_timeout($timeout_value) {
        return max($timeout_value, $this->timeout);
    }
}
