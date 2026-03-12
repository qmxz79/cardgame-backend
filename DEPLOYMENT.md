# 南宁拖拉机游戏 - 部署说明

## 📋 部署信息

### 主机信息
- **域名**: `g9ufmkg3.byethost22.com`
- **FTP**: `ftp.byethost22.com`
- **FTP账号**: `b22_40535504`
- **FTP密码**: `g9ufmkg3`
- **MySQL**: `sql112.byethost22.com`
- **MySQL账号**: `b22_40535504`
- **MySQL密码**: `g9ufmkg3`
- **PHP版本**: 8.3

## 🚀 部署步骤

### 步骤1：创建数据库
1. 登录 cPanel → phpMyAdmin
2. 创建数据库：`b22_40535504_cardgame`
3. 用户已存在：`b22_40535504`
4. 密码：`g9ufmkg3`
5. 导入 `database/schema.sql`

### 步骤2：上传文件
使用 FTP 客户端（FileZilla）上传所有文件到 `public_html` 目录：

**上传的文件结构：**
```
public_html/
├── index.php              # 首页（自动重定向到控制面板）
├── control.php            # 服务器控制面板
├── server.php             # WebSocket 服务器
├── composer.json          # Composer 配置
├── composer.lock          # Composer 锁定文件
├── src/                   # 源代码目录
│   ├── Classes/           # 游戏类文件
│   ├── WebSocket/         # WebSocket 服务器
│   └── Config/            # 配置文件
├── public/                # 前端文件
│   ├── test-client.html   # 测试客户端
│   ├── index.html         # 完整游戏界面
│   ├── game.html          # 增强版游戏界面
│   └── simple-demo.html   # 简单演示
└── database/              # 数据库文件
    └── schema.sql         # 数据库结构
```

### 步骤3：配置数据库
数据库配置文件已自动配置：`src/Config/database.php`

```php
<?php
return [
    'host' => 'sql112.byethost22.com',
    'database' => 'b22_40535504_cardgame',
    'username' => 'b22_40535504',
    'password' => 'g9ufmkg3',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

### 步骤4：安装依赖
如果主机支持 SSH：
```bash
cd /home/b22_40535504/public_html
composer install --no-dev --optimize-autoloader
```

如果只支持 FTP：
1. 本地运行：`composer install --no-dev --optimize-autoloader`
2. 上传整个 `vendor` 目录

### 步骤5：启动服务器
1. 访问控制面板：`http://g9ufmkg3.byethost22.com/control.php`
2. 点击 "启动服务器" 按钮
3. 检查状态是否显示 "运行中"

### 步骤6：测试游戏
访问以下链接测试游戏：
- **测试客户端**: `http://g9ufmkg3.byethost22.com/public/test-client.html`
- **完整游戏界面**: `http://g9ufmkg3.byethost22.com/public/index.html`
- **增强版游戏界面**: `http://g9ufmkg3.byethost22.com/public/game.html`
- **简单演示**: `http://g9ufmkg3.byethost22.com/public/simple-demo.html`

## 🎮 游戏玩法

### 连接服务器
1. 打开测试客户端或游戏界面
2. 输入服务器地址：`ws://g9ufmkg3.byethost22.com:8080`
3. 点击 "连接服务器"

### 登录游戏
1. 输入用户名
2. 点击 "登录"

### 创建/加入游戏
1. 创建新游戏或加入现有游戏
2. 等待4名玩家就位
3. 房主点击 "开始游戏"

### 游戏规则
- 4人2队对抗（对家为友）
- 4副扑克牌（216张）
- 主牌系统：大王 > 小王 > 主级牌 > 副级牌 > 主2 > 副2 > A...3
- 出牌规则：顺时针出牌，必须跟花色
- 分数计算：5/10/K各10分，总分480分
- 升级规则：根据闲家得分升级/下庄

## ⚠️ 注意事项

### WebSocket 支持
- 免费主机可能不支持 WebSocket
- 如果无法连接，可能需要改用 HTTP 长轮询

### 性能限制
- 免费主机性能有限，适合测试
- 并发连接数可能受限

### 进程限制
- 免费主机可能限制后台进程运行时间
- 如果服务器停止，需要重新启动

## 🔧 故障排查

### 服务器无法启动
1. 检查 PHP 版本是否为 8.3
2. 检查 `vendor` 目录是否完整
3. 检查数据库连接是否正常

### WebSocket 无法连接
1. 检查端口 8080 是否可用
2. 检查防火墙设置
3. 尝试使用其他端口

### 数据库连接失败
1. 检查数据库名称、用户名、密码
2. 检查 MySQL 服务是否运行
3. 检查数据库权限

## 📞 技术支持

如有问题，请联系：
- 项目文档：`README.md`
- 规则说明：`NANNING_RULES_SUMMARY.md`
- 测试报告：`FINAL_CHECK_REPORT.md`

---

**部署时间**: 2026-03-12  
**版本**: v1.0.0  
**状态**: ✅ 准备就绪