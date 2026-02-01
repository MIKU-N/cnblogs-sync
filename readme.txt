=== CNBlogs Sync ===
Contributors: MIKU-N
Tags: cnblogs, sync, metaweblog, blog, publish
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

将 WordPress 文章自动同步到博客园（CNBlogs）。

== Description ==

CNBlogs Sync 是一个功能强大的 WordPress 插件，可以将您的文章自动同步到博客园（CNBlogs）。通过 MetaWeblog API 协议，实现无缝的文章发布和更新。

= 主要特性 =

* 🔄 一键同步 - 从文章列表快速同步单篇文章
* 📊 同步状态显示 - 文章列表中显示同步状态
* 📝 编辑器集成 - 在文章编辑页面查看和管理同步
* 🔐 安全认证 - 使用 MetaWeblog 协议进行文章同步
* 📈 同步历史 - 记录每次同步的详细信息
* 🛠️ 详细诊断 - 内置故障排查工具
* 🚀 高效处理 - 异步同步，不影响网站性能
* 🏷️ 灵活同步 - 支持选择是否同步分类、标签及摘要
* 🔗 原文链接 - 支持自定义原文链接文案和写入方式
* 📂 自动创建分类 - 远程不存在的分类自动创建

= 使用方法 =

1. 在插件设置页面配置 CNBlogs 账户信息
2. 输入 MetaWeblog API 访问令牌
3. 测试连接确保配置正确
4. 在文章列表或编辑页面一键同步

= 文档与支持 =

* [快速上手指南](https://github.com/MIKU-N/cnblogs-sync/blob/main/docs/README-QUICK.md)
* [完整使用手册](https://github.com/MIKU-N/cnblogs-sync/blob/main/docs/USER-GUIDE.md)
* [GitHub 仓库](https://github.com/MIKU-N/cnblogs-sync)

== Installation ==

= 自动安装 =

1. 在 WordPress 后台进入"插件" > "安装插件"
2. 搜索"CNBlogs Sync"
3. 点击"现在安装"
4. 安装完成后点击"启用"

= 手动安装 =

1. 下载插件压缩包
2. 解压到 `wp-content/plugins/` 目录
3. 在 WordPress 后台进入"插件"页面
4. 找到 CNBlogs Sync 并点击"启用"

= 配置 =

1. 登录博客园，进入设置页面获取 MetaWeblog API 访问令牌
2. 在 WordPress 后台进入"CNBlogs Sync" > "设置"
3. 输入博客园用户名和访问令牌
4. 点击"测试连接"确保配置正确
5. 根据需要调整其他同步选项

== Frequently Asked Questions ==

= 如何获取 MetaWeblog API 访问令牌？ =

1. 登录博客园（cnblogs.com）
2. 进入"设置" > "MetaWeblog"
3. 复制访问令牌

= 是否支持自动同步？ =

是的。在设置页面可以启用"发布新文章时自动同步"和"更新文章时自动同步"选项。

= 同步失败怎么办？ =

1. 检查 MetaWeblog API 访问令牌是否正确
2. 尝试关闭"同步标签、分类与摘要"选项
3. 查看文章编辑页面的错误信息
4. 参考[故障排查文档](https://github.com/MIKU-N/cnblogs-sync/blob/main/docs/USER-GUIDE.md#故障排查)

= 是否支持同步分类和标签？ =

是的。可以在设置页面启用"同步标签、分类与摘要"选项。如果博客园不存在对应分类，插件会自动创建。

= 原文链接如何配置？ =

插件支持两种原文链接模式：
1. 追加到正文末尾（支持自定义文案）
2. 写入 MetaWeblog 的 Source 字段

可在设置页面自由选择和配置。

== Screenshots ==

1. 设置页面 - 配置博客园账户信息
2. 文章列表 - 显示同步状态和快速同步按钮
3. 文章编辑器 - 同步状态面板和立即同步按钮
4. 同步状态页面 - 查看所有文章的同步历史

== Changelog ==

= 1.2.0 (2026-02-01) =
* 改进：后台菜单移动至"设置"子菜单下
* 改进：重构后台界面为标签页布局
* 新增："关于"页面，包含项目地址和反馈链接
* 新增：卸载时可选择删除所有数据和设置
* 优化：更新许可证为 GPLv2+ 以符合 WordPress 标准
* 优化：标准 uninstall.php 卸载逻辑
* 修复：SQL 兼容性问题

= 1.1.1 (2026-02-01) =
* 改进：当 CNBlogs 远程不存在文章分类时，自动创建分类后再同步
* 优化：简化后台表单中的 MetaWeblog 访问令牌描述文案
* 新增：支持自定义原文链接文案
* 新增：支持将原文链接写入 Source 字段（name/url）

= 1.1.0 (2026-02-01) =
* 修复：解决了古腾堡编辑器下同步按钮UI不刷新的问题
* 新增：在设置中增加"同步标签、分类与摘要"开关
* 优化：文档结构重构，所有详细文档移动至 docs/ 目录

= 1.0.4 (2026-01-31) =
* 修复：动态 Blog ID 支持，解决硬编码 ID 导致的发布失败
* 修复：MetaWeblog.editPost 参数顺序错误
* 增强：详细的调试日志输出

= 1.0.3 (2026-01-31) =
* 新增：文章列表页面快速同步按钮
* 新增：同步状态列显示
* 新增：文章编辑器同步面板
* 新增：数据库记录功能

= 1.0.2 (2026-01-30) =
* 修复：AJAX 请求安全性增强
* 优化：错误处理和日志记录

= 1.0.1 (2026-01-30) =
* 修复：PHP 兼容性问题
* 优化：MetaWeblog 客户端稳定性

= 1.0.0 (2026-01-29) =
* 初始版本发布
* 基础同步功能实现
* MetaWeblog API 集成

== Upgrade Notice ==

= 1.2.0 =
后台菜单位置调整至"设置"下，优化了界面并修复了兼容性问题。

= 1.1.1 =
新增分类自动创建功能和原文链接自定义选项，提升同步体验。

= 1.1.0 =
修复古腾堡编辑器兼容性问题，建议升级。

= 1.0.4 =
重要更新：修复了 Blog ID 和 editPost 参数问题，强烈建议升级。

== Privacy Policy ==

CNBlogs Sync 不会收集、存储或传输任何用户隐私数据。所有同步操作仅在您的 WordPress 站点和博客园之间进行，插件本身不会向第三方服务器发送任何数据。

同步时传输的数据包括：
* 文章标题和内容
* 文章分类和标签（如启用）
* 发布时间
* 博客园账户凭证（仅用于 API 认证）

所有敏感信息（如 API 访问令牌）存储在您的 WordPress 数据库中，不会被上传到其他服务器。

== Support ==

如需帮助或报告问题：

* GitHub Issues: https://github.com/MIKU-N/cnblogs-sync/issues
* 文档: https://github.com/MIKU-N/cnblogs-sync/blob/main/docs/README.md
