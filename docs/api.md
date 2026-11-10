<!-- 用途：有医帮 API 契约文档，供联调/验收/二次开发参考。所有接口均需登录（PHP session cookie），未登录返回 401。 -->

# 有医帮 API 契约

## 通用约定

- 入口：`https://youyibang.com/api.php?act=<操作名>`（**参数名是 `act`，不是 `action`**）
- 认证：登录后 PHP session cookie；未登录所有接口返回 `401 {"error":"未登录"}`
- 越权：操作不属于当前登录用户的医案返回 `403 {"error":"无权访问该医案"}`；医案不存在返回 `404 {"error":"医案不存在"}`
- 错误统一：`{"error":"<原因>"}` + 对应 HTTP 状态码（400/401/403/404/502）
- 成功统一：响应 JSON 额外含 `"ok": true`

## 登录

不在 api.php。走页面表单：

- `POST /login.php`，字段 `username` / `password`（admin / youyibang2026），成功 302 → `/index.php`。

## 建案

不在 api.php。走页面表单：

- `POST /index.php`，字段 `patient_name`（必填）/ `gender`（男|女|未知）/ `age` / `chief_complaint`
- 成功 302 → `/case.php?id=<新医案ID>`，医案归属当前登录用户（cases.user_id）。

## 操作列表（act）

| act | 方法 | 入参 | 返回 |
| --- | --- | --- | --- |
| `poll` | GET | `case_id` `last_msg` `last_img` `rec_ver` | `{"messages":[{id,role,content,created_at}],"images":[{id,path,type,ai_result,created_at}],"record":{"content_md","ver"}|null,"preset":["问题文本"...],"ok":true}`（按性别过滤后的问题列表，增量） |
| `send` | POST | `case_id` `text`（消息正文，可空）`preset_index`（可选，预设题序号，非下标语义见 poll 返回顺序） | `{"user_id":<消息id>,"reply":"<AI回复>","ok":true}` |
| `upload` | POST multipart | `case_id` `type`（面色\|舌苔\|化验单\|其他）`files[]`（多文件；须为真实图片，getimagesize 校验，扩展名白名单 jpg/jpeg/png/gif/webp/heic） | `{"images":[{id,path,type}],"ok":true}`；非图片 → `400 {"error":"非有效图片文件"}` |
| `ai_image` | POST | `id`（images.id） | `{"id":<id>,"ai_result":"<Markdown>","ok":true}` |
> **注意：电子签名功能已于 2026-11-11 移除，EMR 归档不再要求签名。** 原 `sign` / `revoke_sign` 接口已删除（请求返回 404）；`records.is_signed` 列与 `signatures` 表保留但不再读写。EMR 哈希链（content_hash/prev_hash）防篡改能力原样保留。

| `record` | POST | `case_id` | `{"content_md":"<Markdown病案>","ok":true}` |
| `record_save` | POST | `case_id` `content_md` | `{"ver":<unix时间戳>,"ok":true}` |
| `ask` | POST | `case_id` `q` | `{"reply":"<回答>","ok":true}` |
| `validate` | GET/POST | `case_id` | `{"parsed":{chief_complaint,present_illness,tongue,pulse,syndrome,drugs:[{name,dose,raw}],pregnant},"errors":["<阻断项>"],"warnings":["<提示项>"],"archived":bool,"ok":true}`；无草稿 → 404。仅作提示面板，不阻断任何操作 |
| `commit_emr` | POST | `case_id` | `{"emr_id","content_hash":"<SHA256(content_md|prev_hash)>","prev_hash","ok":true}`。追加式落库（代码中不存在 UPDATE/DELETE 路径），首条 prev_hash=`EMR_SEED`（config.php 种子 `youyibang-emr-genesis-2026`） |
| `verify_emr` | GET | `case_id` | `{"valid":bool,"broken_at":<首个被篡改记录id,0=完整>,"total":<归档总数>,"ok":true}`（重算全表哈希链） |
| `export` | GET | `case_id` | A4 打印友好 HTML（抬头/患者信息/病历全文/处方表/归档时间戳/EMR content_hash/prev_hash/免责声明），浏览器 window.print() 出 PDF；OFD-H 版式为 2027 国标预留，当前过渡方案 |

### 规则引擎（rules.php）

`validate_record(parse_record(病历md))` 返回：
- **errors（阻断项，仅提示）**：病历解析失败（缺主诉/辨证/处方结构）、主诉缺失、辨证缺失、处方为空、药缺剂量、剂量 ≤0 或 >500g。
- **warnings（提示）**：现病史缺失、舌象/脉象未采集、十八反/十九畏配伍禁忌（完整禁忌对表，炮制品归一：炙甘草→甘草、法半夏→半夏等）、药典剂量上限（细辛≤3g 等 20 代表药）、妊娠禁忌药（病历含妊娠字样时）。

### 前端新增

- case.php：🎤 语音输入（Web Speech API `webkitSpeechRecognition` zh-CN，不支持自动隐藏）；✅ 规则校验面板（红阻断/黄警告，仅提示）；🗄 归档（不再要求签名）/🔐 验证哈希链/🖨 导出按钮与"已归档"状态。（电子签名板已于 2026-11-11 移除）

### 数据表新增

- `records` 加列 `is_signed`/`archived`；新表 `emr_records`（追加式哈希链，无任何 UPDATE/DELETE 代码路径）。`signatures` 表保留但自 2026-11-11 起代码不再读写。见 db.sql。

## 示例

```bash
# 登录
curl -c /tmp/c.txt -d 'username=admin&password=youyibang2026' https://youyibang.com/login.php

# 建案
curl -b /tmp/c.txt -d 'patient_name=张某&gender=男&age=35&chief_complaint=咳嗽三天' https://youyibang.com/index.php

# 发消息
curl -b /tmp/c.txt -d 'case_id=1&text=患者怕冷无汗' 'https://youyibang.com/api.php?act=send'
# → {"user_id":8,"reply":"...","ok":true}

# 轮询增量
curl -b /tmp/c.txt 'https://youyibang.com/api.php?act=poll&case_id=1&last_msg=0&last_img=0&rec_ver=0'

# 上传图片（伪装文件会被拒）
curl -b /tmp/c.txt -F 'case_id=1' -F 'type=舌苔' -F 'files[]=@/tmp/t.png' 'https://youyibang.com/api.php?act=upload'
```

## 越权语义

所有按 `case_id` 操作的 act（poll/send/upload/record/record_save/ask）及 `ai_image`（按图片反查所属医案）都先校验 `cases.user_id` 与当前登录用户一致；不一致返回 `403 {"error":"无权访问该医案"}`，医案不存在返回 404。前端收到 401 会跳转登录页。
