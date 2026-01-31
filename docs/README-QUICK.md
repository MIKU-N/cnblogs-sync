# CNBlogs 同步插件 - 快速开始

一个 WordPress 插件，用于将文章自动同步到 CNBlogs。

## 📋 文档

- **[安装说明](INSTALLATION.md)** - 如何安装和激活插件
- **[快速诊断](QUICK-DIAGNOSIS.md)** - 测试连接问题排查指南
- **[修复日志](docs/FIXES.md)** - 所有修复和优化的详细说明
- **[详细文档](docs/README.md)** - 项目完整文档

## ✨ 功能

- ✅ 自动同步文章到 CNBlogs
- ✅ 支持自动更新和删除
- ✅ MetaWeblog API 支持
- ✅ WordPress 6.9 兼容
- ✅ 简洁的后台界面
- ✅ 支持覆盖升级

## 🚀 快速使用

1. 上传插件到 `wp-content/plugins/cnblogs-sync/`
2. 在 WordPress 后台激活插件
3. 进入 **CNBlogs 同步** → **设置**
4. 输入你的 CNBlogs 用户名和令牌
5. 点击"测试连接"验证
6. 启用"自动同步"功能

## 🔧 最近修复

**v1.0.1** (2026-01-31):
- ✅ 修复后台界面布局（CSS Grid 两列）
- ✅ 修复测试连接按钮无响应
- ✅ 修复 JSON 解析错误
- ✅ 删除冗余的设置页面描述
- ✅ 隐藏 API URL（自动使用默认值）
- ✅ 支持覆盖升级

详见 [修复日志](docs/FIXES.md)

## 📝 许可证

GPLv2 或更高版本 - 详见 [LICENSE](LICENSE) 文件

---

更多详情请查看 [完整文档](docs/README.md)
