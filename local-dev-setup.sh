#!/bin/bash

# 有医帮 - 本地开发环境设置脚本
# 在本地Mac/Windows/Linux上运行

echo "🚀 开始本地开发环境设置..."

# 1. 检查Node.js和PHP
echo "1. 检查环境..."
if command -v node >/dev/null 2>&1; then
    echo "✅ Node.js 版本: $(node -v)"
else
    echo "❌ Node.js 未安装"
    echo "请先安装Node.js: https://nodejs.org/"
    exit 1
fi

if command -v php >/dev/null 2>&1; then
    echo "✅ PHP 版本: $(php -v | head -n1)"
else
    echo "⚠️ PHP 未安装，但可选（用于API服务）"
fi

# 2. 安装前端依赖
echo "2. 安装前端依赖..."
npm install

# 3. 创建本地配置
cat > .env.local << EOF
NODE_ENV=development
PORT=3000
FEISHU_APP_ID=cli_a9f5df44deb85cef
FEISHU_APP_SECRET=DWf47QZjvn0zc06EROEJIf76BOXJDXkq
FEISHU_VERIFY_TOKEN=LNWam6XB7sI7LyMwQr1WObT0zhNoWVGI
FEISHU_ENCRYPT_KEY=WWQghpFLk6UQsJy3JMuu3fnQPpVFeXhJ
FEISHU_BOT_WEBHOOK=http://localhost:3000/api/feishu/webhook
EOF

# 4. 创建启动脚本
cat > start-local.sh << EOF
#!/bin/bash
echo "🚀 启动本地开发服务器..."
echo "访问 http://localhost:3000"
echo "API服务: http://localhost:3000/api"
echo ""
npm run dev
EOF
chmod +x start-local.sh

# 5. 创建测试脚本
cat > test-local.sh << EOF
#!/bin/bash
echo "🧪 本地测试脚本"
echo "1. 测试首页..."
curl -s http://localhost:3000/ | grep -i "有医帮" && echo "✅ 首页正常"

echo "2. 测试API..."
curl -s http://localhost:3000/api/health | grep -i "ok" && echo "✅ API正常"

echo "3. 测试飞书集成..."
curl -s -X POST http://localhost:3000/api/feishu/webhook \\
  -H "Content-Type: application/json" \\
  -d '{\"type\":\"url_verification\",\"challenge\":\"test_challenge\"}' \\
  | grep -i "challenge" && echo "✅ 飞书验证正常"

echo ""
echo "🎉 本地环境准备完成！"
echo "运行 ./start-local.sh 启动服务"
echo "在浏览器中访问: http://localhost:3000"
EOF
chmod +x test-local.sh

# 6. 创建README
cat > README-LOCAL.md << EOF
# 有医帮 - 本地开发指南

## 快速开始
1. 运行: `./start-local.sh`
2. 访问: http://localhost:3000
3. 测试: `./test-local.sh`

## 功能测试
- 🍎 AI食物卡路里追踪器
- 📊 数据分析仪表板  
- 🤖 飞书机器人集成（本地测试）
- 📱 响应式设计

## 开发模式
- 前端: React + Vite
- 后端: Node.js + Express
- API: PHP (可选)

## 调试信息
- 本地API: http://localhost:3000/api
- 飞书Webhook: http://localhost:3000/api/feishu/webhook
- 管理面板: http://localhost:3000/admin

💡 提示: 使用Chrome开发者工具调试飞书消息处理
EOF

echo ""
echo "✅ 本地开发环境设置完成！"
echo "文件已生成:"
echo "- start-local.sh: 启动脚本"
echo "- test-local.sh: 测试脚本" 
echo "- README-LOCAL.md: 使用指南"
echo ""
echo "现在运行: ./start-local.sh 即可启动本地服务！"