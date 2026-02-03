#!/bin/bash

# 有医帮飞书接入 - 自动化部署脚本
# 运行此脚本即可完成90%的配置

echo "🚀 开始飞书接入自动化部署..."

# 1. 检查必要文件
echo "1. 检查文件..."
FILES=("api/feishu.php" "config/feishu-config.json")
for file in "${FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "❌ 缺少文件: $file"
        exit 1
    fi
done

# 2. 验证配置
echo "2. 验证配置..."
if grep -q "cli_a9f5df44deb85cef" config/feishu-config.json; then
    echo "✓ App ID 配置正确"
else
    echo "✗ App ID 未配置"
    exit 1
fi

# 3. 创建部署目录
echo "3. 创建部署目录..."
mkdir -p deploy/logs

# 4. 生成最终配置
echo "4. 生成最终配置..."
cat > deploy/final_config.json << EOF
{
  "status": "ready",
  "app_id": "cli_a9f5df44deb85cef",
  "verification_token": "LNWam6XB7sI7LyMwQr1WObT0zhNoWVGI",
  "encrypt_key": "WWQghpFLk6UQsJy3JMuu3fnQPpVFeXhJ",
  "ready_for_bot_webhook": true,
  "next_step": "请在飞书开放平台创建机器人并提供Webhook URL"
}
EOF

# 5. 生成测试命令
echo "5. 生成测试命令..."
cat > deploy/test_commands.txt << EOF
# 测试飞书API
curl -X POST http://localhost:3000/api/feishu/webhook \\
  -H "Content-Type: application/json" \\
  -d '{
    "type": "message",
    "event": {
      "message": {
        "content": "\"帮助\""
      }
    }
  }'

# 部署到生产环境
npm run build && cp -r dist/* /var/www/youyibang/
EOF

echo ""
echo "✅ 飞书接入准备完成！"
echo "文件已生成在 deploy/ 目录"
echo "下一步：获取Bot Webhook URL并更新配置"
echo "我可以立即帮你完成最后一步！"