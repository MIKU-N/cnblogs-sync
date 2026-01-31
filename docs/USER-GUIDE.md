# CNBlogs Sync - 用户指南

## 目录

1. [快速开始](#快速开始)
2. [安装指南](#安装指南)
3. [配置设置](#配置设置)
4. [使用方法](#使用方法)
5. [常见问题](#常见问题)
6. [故障排查](#故障排查)
7. [升级说明](#升级说明)

---

## 快速开始

### 5 分钟快速上手

1. **下载插件**
   - 下载最新版本的 CNBlogs Sync

2. **安装插件**
   - 进入 WordPress 后台 → 插件 → 安装插件
   - 上传 cnblogs-sync 文件夹
   - 激活插件

3. **获取凭证**
   - 登录 CNBlogs：https://www.cnblogs.com/
   - 进入账户设置 → MetaWeblog 访问令牌
   - 复制令牌（这是一个生成的密钥，不是你的 CNBlogs 密码！）

4. **配置插件**
   - 进入 WordPress 后台 → CNBlogs Sync → 设置
   - 输入用户名
   - 输入 MetaWeblog 访问令牌
   - 点击"测试连接"验证
   - 启用插件

5. **开始同步**
   - 进入文章列表页面
   - 找到要同步的文章
   - 点击"同步到 CNBlogs"链接
   - 页面会自动重新加载以显示同步状态

---

## 安装指南

### 前置条件

在安装 CNBlogs Sync 之前，请确保：

- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本
- 有效的 CNBlogs 账户
- PHP cURL 扩展已启用
- PHP libxml 扩展已启用

### 检查系统要求

1. **检查 WordPress 版本**
   - 进入 WordPress 后台 → 仪表板
   - 版本号显示在页面底部

2. **检查 PHP 版本**
   - 进入 WordPress 后台 → 工具 → 网站健康
   - 查看"服务器"部分

3. **检查 PHP 扩展**
   - 进入后台创建一个新文章
   - 在内容中输入代码块：
   ```php
   <?php
   echo extension_loaded('curl') ? 'cURL: OK' : 'cURL: 缺失';
   echo '<br>';
   echo extension_loaded('libxml') ? 'libxml: OK' : 'libxml: 缺失';
   ?>
   ```

### 安装步骤

#### 方法 1：通过 WordPress 后台（推荐）

1. 进入 WordPress 后台
2. 导航到 **插件** → **安装插件**
3. 点击"上传插件"
4. 选择 cnblogs-sync 压缩包
5. 点击"立即安装"
6. 激活插件

#### 方法 2：通过 FTP

1. 使用 FTP 或文件管理器连接到服务器
2. 进入 `wp-content/plugins/` 目录
3. 上传 `cnblogs-sync` 文件夹
4. 进入 WordPress 后台激活插件

#### 方法 3：使用 WordPress CLI

```bash
wp plugin install cnblogs-sync --activate
```

---

## 配置设置

### 基本设置

#### 1. 获取 MetaWeblog 令牌

⚠️ **重要**：这与你的 CNBlogs 密码不同！

**获取步骤**：
1. 登录 CNBlogs：https://www.cnblogs.com/
2. 点击右上角用户头像
3. 进入**账户设置**
4. 找到 **MetaWeblog 访问令牌** 部分
5. 复制显示的令牌

#### 2. 配置插件设置

1. 进入 WordPress 后台 → **CNBlogs Sync** → **设置**
2. **API 地址**：默认为 `https://www.cnblogs.com/api/metaweblog/new`（需要修改为自己的地址）
3. **CNBlogs 用户名**：输入你的 CNBlogs 账户名
4. **MetaWeblog 访问令牌**：粘贴从第 1 步获取的令牌
5. **启用同步**：勾选此选项以启用插件

#### 3. 测试连接

1. 点击"测试连接"按钮
2. 等待验证完成
3. **成功**：显示 ✓ 连接成功！
4. **失败**：检查凭证或查看故障排查部分

### 高级设置

当前版本暂无额外的高级设置。

---

## 使用方法

### 在文章列表页面同步

这是最快捷的同步方式。

1. 进入 WordPress 后台 → **文章**
2. 找到要同步的文章
3. 在文章行操作中点击 **同步到 CNBlogs**
4. 等待同步完成（通常 2-3 秒）
5. 页面会自动刷新显示同步状态

**同步状态指示**：
- ✓ 已同步 - 文章已成功同步到 CNBlogs
- ✗ 失败 - 同步失败，查看错误信息
- ⏳ 待处理 - 尚未同步或正在处理

### 在文章编辑页面同步

如果你在编辑文章时想立即同步：

1. 进入文章编辑页面
2. 在右侧边栏找到 **CNBlogs 同步** Meta Box
3. 查看当前的同步状态
4. 点击 **立即同步** 按钮

**Meta Box 显示内容**：
- 同步状态（已同步/失败/未同步）
- CNBlogs 文章 ID（如已同步）
- 首次同步时间
- 最后更新时间
- 错误信息（如失败）
- CNBlogs 文章链接（如已同步）

### 查看同步历史

每次同步的详细信息都被记录在数据库中。

**记录内容**：
- WordPress 文章 ID
- CNBlogs 文章 ID
- 同步状态
- 同步时间
- 错误信息（如有）
- CNBlogs 文章 URL

---

## 常见问题

### Q：如何重新生成 MetaWeblog 令牌？

**A**：
1. 登录 CNBlogs 账户设置
2. 找到 MetaWeblog 访问令牌部分
3. 点击"重新生成"或"刷新"按钮
4. 复制新令牌
5. 更新 CNBlogs Sync 插件设置中的令牌
6. 点击"测试连接"验证

### Q：一篇文章可以同步多次吗？

**A**：是的。当你编辑 WordPress 文章后，可以再次点击"同步到 CNBlogs"，插件会自动更新 CNBlogs 上的对应文章。更新时间会被记录。

### Q：删除 WordPress 文章是否会删除 CNBlogs 上的文章？

**A**：不会。当前版本不支持自动删除。你可以手动到 CNBlogs 网站删除对应的文章。

### Q：支持哪些文章格式？

**A**：支持标准的 WordPress 文章格式：
- 纯文本
- HTML
- Markdown（如果你使用 Markdown 插件）
- 媒体（图片、视频嵌入）

### Q：可以同步到多个 CNBlogs 账户吗？

**A**：不可以。

### Q：插件会影响网站性能吗？

**A**：不会。
- 同步是异步的，不会阻塞用户界面
- 背景处理不会影响前端性能
- 如果同步缓慢，可能是网络问题

---

## 故障排查

### 问题 1：测试连接失败

#### 症状
显示"连接失败"或具体的错误信息

#### 可能原因和解决方案

**原因 1：用户名或令牌错误**
```
错误信息：HTTP 401 或 Invalid Username/Password
```
- 确认用户名没有多余空格
- 重新复制 MetaWeblog 令牌，确保完整
- 在浏览器中登录 CNBlogs 确认账户有效

**原因 2：令牌已过期**
```
错误信息：Access Denied
```
- 访问 CNBlogs 账户设置
- 重新生成 MetaWeblog 令牌
- 更新插件设置中的令牌
- 再次测试连接

**原因 3：API URL 错误**
```
错误信息：HTTP 404
```
- 使用默认 API 地址：`https://www.cnblogs.com/api/metaweblog/new`
- 检查是否有输入错误
- 清除自定义 API 地址

**原因 4：网络问题**
```
错误信息：Network error 或 Connection timeout
```
- 检查网络连接
- 检查防火墙设置
- 确认服务器能访问外网
- 验证 PHP cURL 扩展已启用

#### 启用调试日志

为了获得更详细的错误信息：

1. 编辑 WordPress 根目录的 `wp-config.php`
2. 查找：
   ```php
   define('WP_DEBUG', false);
   ```
3. 改为：
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
4. 保存文件
5. 再次进行测试连接
6. 检查 `wp-content/debug.log` 文件查看详细日志

### 问题 2：测试连接返回"博客列表为空"

#### 症状
连接测试返回警告：显示"连接成功！但博客列表为空"

#### 可能原因

1. **CNBlogs 账户没有博客**
   - 登录 CNBlogs 确认有至少一个博客
   - 如果没有，先在 CNBlogs 创建一个

2. **MetaWeblog 令牌不正确**
   - 重新获取 MetaWeblog 令牌
   - 确保复制的是完整的令牌

3. **令牌已过期或被撤销**
   - 在 CNBlogs 账户设置中重新生成令牌
   - 更新插件设置

#### 解决步骤

1. 登录 CNBlogs：https://www.cnblogs.com/
2. 确认有博客存在
3. 到账户设置重新获取/重新生成 MetaWeblog 令牌
4. 在 CNBlogs Sync 插件设置中更新令牌
5. 再次点击"测试连接"

### 问题 3：文章列表同步按钮无响应

#### 症状
点击"同步到 CNBlogs"没有任何反应，没有报错

#### 可能原因

1. **JavaScript 未加载**
   - 打开浏览器开发工具（F12）→ Console
   - 应该看到 `CNBlogs Sync: admin.js loaded` 的日志

2. **jQuery 未加载**
   - 检查浏览器 Console，查看是否有 jQuery 相关错误

3. **AJAX URL 配置错误**
   - 检查 Console 中 `cnblogs_sync_data` 对象

#### 调试步骤

1. 打开浏览器开发工具（F12）
2. 进入 **Console** 选项卡
3. 看应该看到：
   ```
   CNBlogs Sync: admin.js loaded
   CNBlogs Sync: Data object available {...}
   CNBlogs Sync: DOM ready, setting up event listeners
   ```

4. 点击"同步到 CNBlogs"按钮，应该看到：
   ```
   CNBlogs Sync: Quick sync link clicked for post [ID]
   CNBlogs Sync: Sending sync request for post [ID]
   ```

5. 如果没看到这些日志，可能是脚本没加载。尝试：
   - 清除浏览器缓存
   - 禁用其他插件
   - 切换主题测试

### 问题 4：同步失败

#### 症状
点击同步后显示"✗ 失败"或错误提示

#### 可能原因

1. **文章内容过大**
   - CNBlogs 可能有内容大小限制
   - 缩短文章内容或删除某些媒体

2. **包含不支持的内容**
   - 某些特殊格式或代码可能不被支持
   - 尝试简化文章内容

3. **CNBlogs 服务器问题**
   - 稍后重试
   - 检查 CNBlogs 网站状态

4. **插件权限问题**
   - 确认当前用户具有编辑文章权限
   - 检查 WordPress 用户角色设置

#### 获取详细错误信息

1. 启用 WordPress 调试日志（见上文）
2. 进入文章编辑页面
3. 查看右侧 Meta Box 中的错误信息
4. 检查 `wp-content/debug.log` 中的详细错误

---

## 升级说明

### 升级前检查

- ✅ 备份 WordPress 数据库
- ✅ 备份插件设置（截图保存用户名等信息，但不要保存令牌）
- ✅ 检查 WordPress 版本兼容性

### 升级步骤

#### 方法 1：通过 WordPress 后台（推荐）

1. 进入 WordPress 后台 → **插件**
2. 找到 CNBlogs Sync
3. 点击"停用"
4. 点击"删除"（这不会删除数据，只删除文件）
5. 进入**安装插件** → **上传插件**
6. 选择新版本的压缩包
7. 激活插件

#### 方法 2：通过 FTP

1. 通过 FTP 连接到服务器
2. 进入 `wp-content/plugins/`
3. 备份 `cnblogs-sync` 文件夹（可选）
4. 删除旧版本的 `cnblogs-sync` 文件夹
5. 上传新版本的 `cnblogs-sync` 文件夹
6. 进入 WordPress 后台激活插件

#### 方法 3：仅覆盖更新

如果只是更新代码文件而不重新安装：

1. 通过 FTP 连接
2. 进入 `wp-content/plugins/cnblogs-sync/`
3. 用新版本的文件覆盖对应的文件
4. **不要删除** `wp-content/plugins/cnblogs-sync/` 文件夹

### 版本兼容性

| 升级路径 | 兼容性 | 迁移需求 |
|---------|-------|--------|
| 1.0.0 → 1.0.1 | ✅ 完全兼容 | 无 |
| 1.0.1 → 1.0.2 | ✅ 完全兼容 | 无 |
| 1.0.2 → 1.0.3 | ✅ 完全兼容 | 自动创建数据表 |
| 任意版本 → 最新 | ✅ 完全兼容 | 无 |

### 升级后检查

1. **验证插件激活**
   - 进入 WordPress 后台
   - 确认 CNBlogs Sync 显示为"已激活"

2. **测试连接**
   - 进入 CNBlogs Sync 设置
   - 点击"测试连接"
   - 确保返回成功

3. **检查同步功能**
   - 进入文章列表
   - 尝试同步一篇文章
   - 验证同步成功

---

## 获取帮助

### 文档

- [快速诊断](QUICK-DIAGNOSIS.md) - 诊断连接问题
- [安装说明](INSTALLATION.md) - 详细安装步骤
- [版本历史](CHANGELOG.md) - 所有版本更新
- [快速参考](QUICK-REFERENCE.md) - 快速查询表

### 反馈和报告

如果遇到未在本指南中列出的问题：

1. 收集以下信息：
   - WordPress 版本
   - PHP 版本
   - 浏览器和版本
   - 完整的错误信息
   - `wp-content/debug.log` 的相关部分（不包含敏感信息）

2. 在 GitHub 提交 Issue：
   https://github.com/MIKU-N/cnblogs-sync/issues

3. 提供尽可能多的细节，包括：
   - 你已经尝试过的解决方案
   - 问题的重现步骤
   - 预期的行为 vs 实际行为

---

## 提示和最佳实践

### 保持安全

- ✅ 定期更新插件
- ✅ 不要在公共场所分享 MetaWeblog 令牌
- ✅ 定期检查 CNBlogs 账户活动
- ✅ 如果怀疑令牌泄露，立即重新生成

### 提高效率

- ✅ 在文章发布前进行测试连接
- ✅ 使用文章列表快速同步批量更新
- ✅ 定期检查同步状态
- ✅ 在编辑重要文章时记录 CNBlogs 链接

### 维护

- ✅ 定期检查 WordPress 更新
- ✅ 保持 PHP 版本最新
- ✅ 备份数据库
- ✅ 监控 `debug.log` 文件大小

