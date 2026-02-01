# CNBlogs Sync - WordPress 插件

![Version](https://img.shields.io/badge/version-1.1.1-blue)
![License](https://img.shields.io/badge/license-CC_BY--NC--SA_4.0-green)
![WordPress](https://img.shields.io/badge/wordpress-5.0+-blue)
![PHP](https://img.shields.io/badge/php-7.4+-purple)

一个 WordPress 插件，可以将文章自动同步到 [CNBlogs（博客园）](https://www.cnblogs.com/)。

## ⚠️ 许可协议

本项目采用 **[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)** 协议，**允许非商业使用**，禁止商用。

## ✨ 特性

- 🔄 **一键同步** - 从文章列表快速同步单篇文章
- 📊 **同步状态显示** - 文章列表中显示同步状态
- 📝 **编辑器集成** - 在文章编辑页面查看和管理同步
- 🔐 **安全认证** - 使用 MetaWeblog 协议进行文章同步
- 📈 **同步历史** - 记录每次同步的详细信息
- 🛠️ **详细诊断** - 内置故障排查工具
- 🚀 **高效处理** - 异步同步，不影响网站性能
- 🏷️ **灵活同步** - 支持选择是否同步分类、标签及摘要

## 🆕 最新更新 (v1.1.1)

- **改进**：当 CNBlogs 远程不存在文章分类时，自动创建分类后再同步。
- **优化**：简化后台表单中的 MetaWeblog 访问令牌描述文案。

## 🚀 快速开始

### 新用户？

1. **5 分钟快速上手** → [快速上手指南](docs/README-QUICK.md)
2. **完整使用指南** → [用户手册](docs/USER-GUIDE.md)

### 遇到问题？

- **故障排查** → [用户手册 - 故障排查](docs/USER-GUIDE.md#故障排查)
- **文档导航** → [文档目录](docs/README.md)

### 想升级？

- **版本信息** → [更新日志](docs/CHANGELOG.md)

## 📚 文档导航

所有详细文档均已整理至 [docs/](docs/) 目录：

| 文档 | 说明 | 推荐人群 |
|------|------|--------|
| [docs/README-QUICK.md](docs/README-QUICK.md) | 5 分钟快速上手 | 🆕 新用户 |
| [docs/USER-GUIDE.md](docs/USER-GUIDE.md) | 完整使用指南 | 👥 所有用户 |
| [docs/README.md](docs/README.md) | 文档目录导航 | 🔍 查找特定文档 |
| [docs/CHANGELOG.md](docs/CHANGELOG.md) | 版本历史和更新 | 📋 版本信息 |

## 📋 需求

- **WordPress** 5.0 或更高版本
- **PHP** 7.4 或更高版本
- 有效的 CNBlogs 账户
- PHP cURL 扩展
- PHP libxml 扩展

## 🔧 安装

### 快速安装（推荐）

1. **WordPress 后台**
   - 进入 WordPress 后台 → **插件** → **安装插件**
   - 上传 cnblogs-sync 文件夹
   - 激活插件

2. **FTP 安装**
   - 上传 cnblogs-sync 文件夹到 `wp-content/plugins/`
   - 进入 WordPress 后台激活

3. **命令行**
   ```bash
   wp plugin install cnblogs-sync --activate
   ```

详见 [INSTALLATION.md](INSTALLATION.md)

## ⚙️ 配置

1. **获取 MetaWeblog 令牌**
   - 登录 CNBlogs
   - 进入账户设置 → MetaWeblog 访问令牌
   - 复制令牌

2. **配置插件**
   - 进入 WordPress 后台 → **CNBlogs Sync** → **设置**
   - 输入 CNBlogs 用户名
   - 输入 MetaWeblog 访问令牌
   - 点击"测试连接"

3. **开始同步**
   - 进入文章列表
   - 点击"同步到 CNBlogs"链接

详见 [USER-GUIDE.md](USER-GUIDE.md#配置设置)

## 🎯 使用方式

### 文章列表同步（推荐）

- 进入 WordPress 后台 → **文章**
- 找到要同步的文章
- 点击 **同步到 CNBlogs** 链接
- 页面自动刷新显示同步状态

### 文章编辑器同步

- 打开文章编辑页面
- 右侧栏找到 **CNBlogs 同步** Meta Box
- 查看同步状态或点击 **立即同步**

详见 [USER-GUIDE.md](USER-GUIDE.md#使用方法)

## ❓ 常见问题

**Q：什么是 MetaWeblog 令牌？**
A：一个安全密钥，用于 API 访问，不是你的 CNBlogs 密码。可随时撤销或重新生成。

**Q：一篇文章可以同步多次吗？**
A：可以。修改文章后再次同步会更新 CNBlogs 上的文章。

**Q：删除 WordPress 文章会删除 CNBlogs 上的文章吗？**
A：不会。需要手动到 CNBlogs 删除。

更多问题见 [USER-GUIDE.md](USER-GUIDE.md#常见问题)

## 🐛 故障排查

### 连接失败？

1. 检查用户名和令牌是否正确
2. 确认 PHP cURL 扩展已启用
3. 查看 [QUICK-DIAGNOSIS.md](docs/QUICK-DIAGNOSIS.md)

### 博客列表为空？

1. 确认 CNBlogs 账户有博客
2. 重新生成 MetaWeblog 令牌
3. 查看 [EMPTY-BLOG-LIST-FIX.md](docs/EMPTY-BLOG-LIST-FIX.md)

### 同步按钮无响应？

1. 打开浏览器开发工具 (F12)
2. 查看 Console 是否有错误
3. 清除缓存，重新加载页面

更多信息见 [USER-GUIDE.md](USER-GUIDE.md#故障排查)

## 📈 版本信息

**当前版本**：1.0.4

| 版本 | 说明 |
|------|------|
| 1.0.3 | ✨ 文章管理功能、同步按钮、状态显示 |
| 1.0.2 | 🔍 诊断增强、日志改进 |
| 1.0.1 | 📝 文档完善 |
| 1.0.0 | 🚀 初始版本 |

详见 [CHANGELOG.md](CHANGELOG.md)

## 🔄 升级

从旧版本升级很简单：

1. 进入 WordPress 后台 → **插件** → **停用**
2. 点击 **删除**（不会删除数据）
3. **安装新版本**并激活
4. **测试连接**验证正常

详见 [UPGRADE-GUIDE.md](docs/UPGRADE-GUIDE.md)

## 🛡️ 安全

- ✅ 使用 MetaWeblog 令牌，不存储 CNBlogs 密码
- ✅ 令牌可随时撤销或重新生成
- ✅ 通过 WordPress nonce 防护 CSRF 攻击
- ✅ 使用 wp_remote_post 进行安全的 API 通信

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE)

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 💬 支持

- 📖 **完整文档** - [USER-GUIDE.md](USER-GUIDE.md)
- 🔍 **快速查询** - [QUICK-REFERENCE.md](docs/QUICK-REFERENCE.md)
- 🐛 **诊断工具** - [QUICK-DIAGNOSIS.md](docs/QUICK-DIAGNOSIS.md)
- 📑 **文档导航** - [DOCUMENTATION-INDEX.md](DOCUMENTATION-INDEX.md)

## 🙏 致谢

感谢 CNBlogs 提供的 MetaWeblog API！

## 🤖 AI 辅助开发声明

本项目部分代码由人工智能（AI）辅助生成。虽然所有代码均经过人工审核与测试，但在特定环境或边缘情况下仍可能存在未预见的问题。

在使用本插件前，请务必建立数据备份。开发者不对因使用本插件导致的任何数据丢失或系统故障承担法律责任。

---

**准备好了吗？** 👉 [快速开始](docs/README-QUICK.md)

**需要帮助？** 👉 [文档导航](docs/README.md)

