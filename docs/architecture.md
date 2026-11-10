# 有医帮（youyibang.com）架构设计 — 主控留档

## 一、系统定位
医生 AI 助理站：预设问诊 → 拍照（面色/舌苔/化验单）→ 大模型解析 → 生成结构化病案与推荐诊断/参考方剂。手机与电脑通过同一医案页轮询同步。

## 二、技术栈
- 后端：PHP 7.4+（宝塔自带，PDO/MySQLi 预处理），无框架，单入口按 action 路由（api.php）
- 数据库：MySQL（youyibang / xiaomao），UTF8MB4
- 前端：原生 HTML5 + JS，移动端优先；拍照 `<input capture="environment">`
- 大模型：OpenAI 兼容 `http://beiheng.heiyu.space:1314/v1`（用户确认：仅此外网端点；10.0.0.128:19170 为用户局域网地址，服务器不可用，已从 config 移除），Key `ollama`，模型 `glm-5.3-flash`，支持 reasoning，上下文 1M
- OCR：不做专门引擎，视觉大模型直接解析图片 base64

## 三、数据表
- users（单医生账号 admin）
- cases：患者基本信息 + 状态
- messages：问诊对话（role: doctor/patient/ai/system）
- images：照片路径 + 类型（面/舌/化验单/其他）+ ai_result
- records：AI 生成的病案（Markdown），医生可编辑

## 四、同步机制
医案详情页 `setInterval` 每 3 秒 GET `api.php?action=sync&case_id=N&after_id=M` 增量拉取新消息/图片/解析结果/病案版本，前端增量渲染。手机上传后电脑 3 秒内可见。

## 五、AI 链路
1. 问诊：预设十问序列 + 自由追问；AI 可基于对话上下文生成追问建议
2. 图片解析：按图片类型选用提示词（见 docs/prompts.md），base64 走 vision
3. 病案生成：全部对话 + 图片解析结果一次输入 → 结构化 Markdown 病历（主诉/现病史/四诊/辅查/辨证/治法/方剂含剂量煎服法/医嘱）
4. 流式输出：PHP cURL → SSE 透传（X-Accel-Buffering: no）

## 六、安全与合规
- session 登录、PDO 预处理、上传文件白名单校验、config.php 600
- 页脚免责声明：AI 产出仅供参考，不构成医疗建议，不具备医疗器械资质

## 七、部署
宝塔站点根目录（服务器上确认），代码 rsync 上传，SQL 导入建表，服务器上实测大模型连通后写入 config.php（双地址故障切换）。
