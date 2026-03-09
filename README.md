# Card Game Backend

卡牌游戏后端项目 - 基于 PHP + WebSocket

## 🎯 项目结构

```
/var/www/cardgame/
├── composer.json           # Composer 配置
├── server.php              # WebSocket 服务器入口
├── start-server.sh         # 启动脚本
├── cardgame-ws.service     # Systemd 服务配置
├── src/
│   ├── Classes/
│   │   ├── Card.php       # 卡牌类
│   │   └── Deck.php       # 卡组类
│   ├── WebSocket/
│   │   └── GameServer.php # WebSocket 服务器核心
│   └── Config/
│       └── database.php   # 数据库配置
├── public/
│   └── test-client.html   # 测试客户端
└── database/
    └── schema.sql         # 数据库表结构
```

## 🚀 快速开始

### 1. 检查服务状态

```bash
# WebSocket 服务器
sudo systemctl status cardgame-ws

# 查看日志
sudo journalctl -u cardgame-ws -f
```

### 2. 启动/停止服务器

```bash
# 启动
sudo systemctl start cardgame-ws

# 停止
sudo systemctl stop cardgame-ws

# 重启
sudo systemctl restart cardgame-ws

# 开机自启
sudo systemctl enable cardgame-ws
```

### 3. 手动启动（调试用）

```bash
cd /var/www/cardgame
php server.php
```

## 🎮 测试

### 使用测试客户端

打开浏览器访问：
```
http://localhost/test-client.html
```

### 使用命令行测试

```bash
# 安装 wscat
npm install -g wscat

# 连接
wscat -c ws://localhost:8080

# 发送登录消息
{"action":"login","username":"test"}

# 创建游戏
{"action":"createGame"}

# 加入游戏
{"action":"joinGame","gameId":"game_xxx"}
```

## 📡 WebSocket API

### 连接
- **URL:** `ws://localhost:8080`
- **协议:** WebSocket

### 消息格式

所有消息使用 JSON 格式：

```json
{
  "action": "action_name",
  "data": {}
}
```

### 客户端 → 服务器

| Action | 参数 | 说明 |
|--------|------|------|
| `ping` | - | 心跳检测 |
| `login` | username, password | 登录 |
| `createGame` | - | 创建游戏房间 |
| `joinGame` | gameId | 加入游戏 |
| `startGame` | gameId | 开始游戏 |
| `drawCard` | gameId | 抽牌 |
| `playCard` | gameId, cardIndex | 打出卡牌 |
| `attack` | gameId, cardIndex, target | 攻击 |
| `endTurn` | gameId | 结束回合 |
| `leaveGame` | gameId | 离开游戏 |

### 服务器 → 客户端

| Type | 说明 |
|------|------|
| `welcome` | 连接成功 |
| `pong` | 心跳响应 |
| `loginSuccess` | 登录成功 |
| `gameCreated` | 游戏创建成功 |
| `gameJoined` | 加入游戏成功 |
| `gameStarted` | 游戏开始 |
| `yourHand` | 你的手牌 |
| `cardDrawn` | 抽牌结果 |
| `cardPlayed` | 卡牌打出 |
| `attack` | 攻击事件 |
| `turnChanged` | 回合变更 |
| `gameOver` | 游戏结束 |
| `error` | 错误信息 |

## 🗄️ 数据库

### 连接信息
- **Host:** localhost
- **Database:** cardgame
- **Username:** usr
- **Password:** 123456

### 数据表

| 表名 | 说明 |
|------|------|
| `users` | 用户表 |
| `cards` | 卡牌表 |
| `decks` | 卡组表 |
| `deck_cards` | 卡组 - 卡牌关联 |
| `games` | 游戏对局表 |
| `game_actions` | 游戏动作历史 |

## 🔧 配置

### 数据库配置

编辑 `src/Config/database.php`

### WebSocket 端口

默认端口：**8080**

修改 `server.php` 中的端口号

## 📝 示例代码

### JavaScript 连接示例

```javascript
const ws = new WebSocket('ws://localhost:8080');

ws.onopen = () => {
    console.log('Connected!');
    ws.send(JSON.stringify({
        action: 'login',
        username: 'player1'
    }));
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
};
```

### PHP 连接示例

```php
$ws = new WebSocket('ws://localhost:8080');
$ws->send(json_encode([
    'action' => 'login',
    'username' => 'player1'
]));
```

## 🐛 故障排查

### 服务器无法启动

```bash
# 查看错误日志
sudo journalctl -u cardgame-ws -n 50

# 检查端口占用
sudo ss -tuln | grep 8080

# 检查 PHP 版本
php -v
```

### 数据库连接失败

```bash
# 测试数据库连接
mysql -u usr -p123456 cardgame -e "SELECT 1"

# 检查 MySQL 服务
sudo systemctl status mysql
```

## 📚 类说明

### Card 类

卡牌基础类，包含：
- 卡牌属性（名称、描述、类型、费用等）
- 游戏状态（横置、召唤失调、当前攻防）
- 操作方法（攻击、横置、重置、受伤等）

### Deck 类

卡组管理类，包含：
- 卡组管理（洗牌、抽牌、添加卡牌）
- 游戏区域（手牌、战场、墓地、除外区）
- 回合管理（开始回合、结束回合、法力管理）

### GameServer 类

WebSocket 服务器核心，处理：
- 客户端连接/断开
- 游戏房间管理
- 玩家状态同步
- 游戏逻辑处理

---

**开发时间:** 2026-03-09  
**版本:** 1.0.0  
**作者:** Leon Lee
