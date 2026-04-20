<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>人力资源管理系统</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
        }
        .logo {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        h1 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }
        .subtitle {
            color: #666;
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        .status {
            background: #e8f5e8;
            color: #2d5a2d;
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
            border-left: 4px solid #4caf50;
        }
        .api-link {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            margin: 0.5rem;
            transition: background 0.3s;
        }
        .api-link:hover {
            background: #5a6fd8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🏢</div>
        <h1>人力资源管理系统</h1>
        <p class="subtitle">Human Resource Management System</p>
        
        <div class="status">
            ✅ Laravel 后端服务运行正常<br>
            ✅ 数据库连接成功<br>
            ✅ API 接口可用
        </div>
        
        <div>
            <a href="/api/test" class="api-link">测试 API</a>
            <a href="/api/employees" class="api-link">员工管理</a>
        </div>
        
        <p style="margin-top: 2rem; color: #999; font-size: 0.9rem;">
            系统版本: 1.0.0 | 开发环境
        </p>
    </div>
</body>
</html>
