#!/bin/bash

# 有医帮 - 传统服务器部署脚本
# 适用于：Ubuntu/CentOS/Debian 服务器

echo "🚀 开始传统服务器部署..."

# 1. 检查系统环境
echo "1. 检查系统环境..."
if command -v node >/dev/null 2>&1; then
    echo "✅ Node.js 已安装"
else
    echo "⚠️ Node.js 未安装，正在安装..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt-get install -y nodejs
    sudo apt-get install -y npm
    echo "✅ Node.js 安装完成"
fi

if command -v php >/dev/null 2>&1; then
    echo "✅ PHP 已安装"
else
    echo "⚠️ PHP 未安装，正在安装..."
    sudo apt-get update
    sudo apt-get install -y php php-cli php-curl php-mbstring php-xml php-zip
    echo "✅ PHP 安装完成"
fi

# 2. 安装依赖
echo "2. 安装项目依赖..."
npm install
composer require firebase/php-jwt

# 3. 配置环境变量
echo "3. 配置环境变量..."
cat > .env << EOF
NODE_ENV=production
PORT=3000
FEISHU_APP_ID=cli_a9f5df44deb85cef
FEISHU_APP_SECRET=DWf47QZjvn0zc06EROEJIf76BOXJDXkq
FEISHU_VERIFY_TOKEN=LNWam6XB7sI7LyMwQr1WObT0zhNoWVGI
FEISHU_ENCRYPT_KEY=WWQghpFLk6UQsJy3JMuu3fnQPpVFeXhJ
FEISHU_BOT_WEBHOOK=https://your-domain.com/api/feishu/webhook
EOF

# 4. 创建启动脚本
cat > start.sh << EOF
#!/bin/bash
echo "🚀 启动有医帮服务..."
npm run start
EOF
chmod +x start.sh

# 5. 创建systemd服务
cat > youyibang.service << EOF
[Unit]
Description=YouYiBang Service
After=network.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$(pwd)
ExecStart=$(pwd)/start.sh
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

# 6. 安装并启动服务
echo "6. 安装服务..."
sudo cp youyibang.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable youyibang
sudo systemctl start youyibang

# 7. 防火墙配置
echo "7. 配置防火墙..."
sudo ufw allow 3000
sudo ufw reload

echo ""
echo "🎉 部署完成！"
echo "访问 http://你的服务器IP:3000 即可使用"
echo ""
echo "下一步："
echo "1. 在飞书开放平台创建机器人"
echo "2. 复制Webhook URL到 config/feishu-config-final.json 的 bot_webhook 字段"
echo "3. 重启服务: sudo systemctl restart youyibang"
echo ""
echo "需要我帮你生成Nginx反向代理配置吗？"