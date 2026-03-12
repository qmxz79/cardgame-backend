<?php
/**
 * 南宁拖拉机游戏 - WebSocket 服务器控制面板
 * 
 * 功能：
 * 1. 查看服务器状态
 * 2. 启动服务器
 * 3. 停止服务器
 */

// 配置
$pidFile = '/tmp/cardgame-server.pid';
$serverFile = __DIR__ . '/server.php';

// 检查服务器状态
function getServerStatus($pidFile) {
    if (!file_exists($pidFile)) {
        return ['status' => 'stopped', 'pid' => null];
    }
    
    $pid = trim(file_get_contents($pidFile));
    exec("ps -p $pid 2>/dev/null", $output, $returnCode);
    
    if ($returnCode === 0) {
        return ['status' => 'running', 'pid' => $pid];
    } else {
        unlink($pidFile);
        return ['status' => 'stopped', 'pid' => null];
    }
}

// 启动服务器
function startServer($serverFile, $pidFile) {
    // 检查文件是否存在
    if (!file_exists($serverFile)) {
        return ['error' => '服务器文件不存在: ' . $serverFile];
    }
    
    // 使用 nohup 启动后台进程
    $command = 'nohup php ' . escapeshellarg($serverFile) . ' > /dev/null 2>&1 & echo $!';
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        return ['error' => '启动失败，返回码: ' . $returnCode];
    }
    
    $pid = trim($output[0]);
    if (empty($pid)) {
        return ['error' => '无法获取进程ID'];
    }
    
    file_put_contents($pidFile, $pid);
    return ['status' => 'started', 'pid' => $pid];
}

// 停止服务器
function stopServer($pidFile) {
    if (!file_exists($pidFile)) {
        return ['error' => '服务器未运行'];
    }
    
    $pid = trim(file_get_contents($pidFile));
    exec("kill $pid 2>/dev/null", $output, $returnCode);
    
    unlink($pidFile);
    
    if ($returnCode === 0) {
        return ['status' => 'stopped'];
    } else {
        return ['error' => '停止失败'];
    }
}

// 处理请求
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$result = [];

switch ($action) {
    case 'status':
        $result = getServerStatus($pidFile);
        break;
    case 'start':
        $result = startServer($serverFile, $pidFile);
        break;
    case 'stop':
        $result = stopServer($pidFile);
        break;
    default:
        // 显示控制面板界面
        displayControlPanel();
        exit;
}

header('Content-Type: application/json');
echo json_encode($result);

// 显示控制面板界面
function displayControlPanel() {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>南宁拖拉机游戏 - 服务器控制面板</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            h1 {
                color: #333;
                text-align: center;
                margin-bottom: 30px;
            }
            .status-panel {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            .status-item {
                display: flex;
                justify-content: space-between;
                margin: 10px 0;
            }
            .status-label {
                font-weight: bold;
            }
            .status-value {
                color: #666;
            }
            .status-running {
                color: #28a745;
                font-weight: bold;
            }
            .status-stopped {
                color: #dc3545;
                font-weight: bold;
            }
            .button-group {
                display: flex;
                gap: 10px;
                margin: 20px 0;
            }
            button {
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                transition: background-color 0.3s;
            }
            .btn-start {
                background-color: #28a745;
                color: white;
            }
            .btn-start:hover {
                background-color: #218838;
            }
            .btn-stop {
                background-color: #dc3545;
                color: white;
            }
            .btn-stop:hover {
                background-color: #c82333;
            }
            .btn-refresh {
                background-color: #007bff;
                color: white;
            }
            .btn-refresh:hover {
                background-color: #0056b3;
            }
            .message {
                padding: 10px;
                margin: 10px 0;
                border-radius: 5px;
                display: none;
            }
            .message.success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .message.error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            .links {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
            .links h3 {
                margin-bottom: 15px;
            }
            .links a {
                display: block;
                margin: 5px 0;
                color: #007bff;
                text-decoration: none;
            }
            .links a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🎮 南宁拖拉机游戏 - 服务器控制面板</h1>
            
            <div class="status-panel">
                <h2>服务器状态</h2>
                <div class="status-item">
                    <span class="status-label">状态:</span>
                    <span id="status" class="status-stopped">检测中...</span>
                </div>
                <div class="status-item">
                    <span class="status-label">进程ID:</span>
                    <span id="pid">-</span>
                </div>
                <div class="status-item">
                    <span class="status-label">服务器文件:</span>
                    <span id="server-file"><?php echo basename($serverFile); ?></span>
                </div>
            </div>
            
            <div class="button-group">
                <button class="btn-refresh" onclick="checkStatus()">🔄 刷新状态</button>
                <button class="btn-start" onclick="startServer()">▶️ 启动服务器</button>
                <button class="btn-stop" onclick="stopServer()">⏹️ 停止服务器</button>
            </div>
            
            <div id="message" class="message"></div>
            
            <div class="links">
                <h3>游戏链接</h3>
                <a href="public/test-client.html" target="_blank">测试客户端</a>
                <a href="public/index.html" target="_blank">完整游戏界面</a>
                <a href="public/game.html" target="_blank">增强版游戏界面</a>
                <a href="public/simple-demo.html" target="_blank">简单演示</a>
            </div>
        </div>
        
        <script>
            // 页面加载时检查状态
            window.onload = function() {
                checkStatus();
            };
            
            // 检查服务器状态
            function checkStatus() {
                fetch('?action=status')
                    .then(response => response.json())
                    .then(data => {
                        updateStatus(data);
                    })
                    .catch(error => {
                        showMessage('检查状态失败: ' + error, 'error');
                    });
            }
            
            // 启动服务器
            function startServer() {
                fetch('?action=start', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            showMessage('启动失败: ' + data.error, 'error');
                        } else {
                            showMessage('服务器已启动 (PID: ' + data.pid + ')', 'success');
                            updateStatus(data);
                        }
                    })
                    .catch(error => {
                        showMessage('启动失败: ' + error, 'error');
                    });
            }
            
            // 停止服务器
            function stopServer() {
                fetch('?action=stop', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            showMessage('停止失败: ' + data.error, 'error');
                        } else {
                            showMessage('服务器已停止', 'success');
                            updateStatus(data);
                        }
                    })
                    .catch(error => {
                        showMessage('停止失败: ' + error, 'error');
                    });
            }
            
            // 更新状态显示
            function updateStatus(data) {
                const statusEl = document.getElementById('status');
                const pidEl = document.getElementById('pid');
                
                if (data.status === 'running') {
                    statusEl.textContent = '运行中';
                    statusEl.className = 'status-running';
                    pidEl.textContent = data.pid || '-';
                } else {
                    statusEl.textContent = '已停止';
                    statusEl.className = 'status-stopped';
                    pidEl.textContent = '-';
                }
            }
            
            // 显示消息
            function showMessage(text, type) {
                const messageEl = document.getElementById('message');
                messageEl.textContent = text;
                messageEl.className = 'message ' + type;
                messageEl.style.display = 'block';
                
                setTimeout(() => {
                    messageEl.style.display = 'none';
                }, 5000);
            }
        </script>
    </body>
    </html>
    <?php
}