# 飞书接入指南

## 1. 创建飞书应用

1. 登录 [飞书开放平台](https://open.feishu.cn/)
2. 进入「应用管理」→「创建应用」
3. 选择「机器人」类型
4. 填写应用名称：**有医帮健康助手**
5. 配置权限：
   - 消息接收：`message:receive`
   - 消息发送：`message:send`
   - 用户信息：`contact:user`

## 2. 获取配置信息

在应用详情页获取：
- **App ID**: `cli_XXXXXXXXXXXXXXXX`
- **App Secret**: `xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
- **Verification Token**: `your_verification_token`
- **Encrypt Key**: `your_encrypt_key`

## 3. 配置Webhook

1. 在「事件订阅」中添加：
   - 事件类型：`message.receive`
   - 请求URL: `https://your-domain.com/api/feishu/webhook`
   - 加密方式：明文模式（开发阶段）

2. 在「机器人」设置中获取：
   - Webhook URL: `https://open.feishu.cn/open-apis/bot/v2/hook/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`

## 4. 部署配置

### 环境变量配置
```env
FEISHU_APP_ID=cli_XXXXXXXXXXXXXXXX
FEISHU_APP_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
FEISHU_VERIFICATION_TOKEN=your_verification_token
FEISHU_ENCRYPT_KEY=your_encrypt_key
FEISHU_BOT_WEBHOOK=https://open.feishu.cn/open-apis/bot/v2/hook/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

### API端点
- `POST /api/feishu/webhook` - 接收飞书消息
- `POST /api/feishu/message` - 发送消息到飞书
- `GET /api/feishu/verify` - 验证签名（用于事件订阅）

## 5. 功能说明

### 支持的关键词
| 关键词 | 功能 |
|--------|------|
| `健康`、`医生` | 返回健康服务介绍 |
| `食疗`、`食物` | 返回中医食疗建议 |
| `帮助`、`菜单` | 显示功能菜单 |

### 交互式卡片
- 健康服务介绍
- 医生注册入口
- 患者服务入口
- 网站链接

## 6. 测试方法

### 本地测试
```bash
# 启动本地服务器
cd youyibang-web
npm start

# 测试API
curl -X POST http://localhost:3000/api/feishu/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "type": "message",
    "event": {
      "message": {
        "content": "\"健康咨询\""
      }
    }
  }'
```

### 飞书测试
1. 将机器人添加到群组
2. 发送消息测试：
   - `健康`
   - `食疗`
   - `帮助`

## 7. 安全注意事项
- 生产环境请使用HTTPS
- 定期轮换App Secret
- 验证消息签名防止伪造
- 限制IP访问（可选）

## 8. 错误处理
- `401 Unauthorized`: 签名验证失败
- `404 Not Found`: 端点不存在
- `500 Internal Server Error`: 服务器内部错误

---

**有医帮健康科技 © 2026**