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

## v2 接诊工作台升级（2026-11-11）

### 变更接口
- `save_opd`：新增可选 POST 字段 `tcm_checklist`（JSON：`{"selected":{"寒热:畏寒":true,...}}`），存 `opd_records.tcm_checklist`（新增 JSON 列，db.sql 条件 ALTER，已上服务器执行）；非法 JSON 存 NULL 不阻断。
- `get_opd`：`record` 自然回传 `tcm_checklist`（SELECT *），前端据此回显勾选状态。
- `ai_dx`：提示词与返回增加结构化字段 `associated_symptoms`（伴随症状数组，前端渲染为可点击 chip 写入现病史）与 `physical_findings`（查体）；置信度胶囊色：较可能绿/可能黄/需警惕红；`red_flags` 前端显示 🚩 转诊指征。
- `update_patient`（新增）：POST `patient_id,name,gender,age,phone,allergy`；归属校验 = 患者须存在于当前医生任一 visit；非本人患者 403「无权修改该患者」。

### 新增前端资源
- `assets/speech.js`：公共语音转文字（webkitSpeechRecognition zh-CN，continuous+interim，结果插光标处）；不支持环境（iOS<14.5 Safari 等）自动隐藏 🎤；reception 各文本区均挂载。
- `reception.php`：中医四诊勾选面板（服务端渲染 68 chip/14 行/4 组，一键插入病历）；黄色高亮编辑区(.editzone)；体格检查体温/血压/心率三内联框；处方三 tab（西药频次下拉/剂量±步进+单位/开药量+单位盒瓶袋；输液溶媒/滴速；中药剂量g/炮制/特殊煎法）；患者条保存(蓝)/取消/打印按钮组+编辑档案；取消=重载 get_opd。

### 验收证据
- php -l api.php/reception.php 通过；服务器备份目录 `_backup_ui_0438/`。
- E2E（e2e_v2.sh 留档）：全链路 PASS，详见回传报告。测试数据已清零（patients/visits/opd/rx/tpl=0，diag_dict=36202）。

## v3 语音问诊升级（2026-11-11）

### 新增接口：act=organize_transcript
- POST `transcript`（必填，口语转写全文）、`visit_id` 或 `case_id`（可选，传了就做归属校验 check_visit/check_owner）、`target`（可选，主诉/现病史，整理侧重）。
- 成功：`{"ok":true,"chief_complaint":"","present_illness":"","tcm_summary":""}`（三段已去语气词/寒暄，缺项为"未提及"，末尾含"本整理由 AI 生成，请医生核对"由模型生成于 present_illness/tcm_summary 文本内）。
- 上游离线降级：HTTP 200 `{"ok":false,"note":"AI 整理暂不可用（上游模型离线）。已插入原始转写，恢复后可重新整理","raw":"<原始转写>"}`——前端将 `raw` 标注【原始转写 待整理】后插入现病史末尾，不丢数据；AI 返回非 JSON 同样走 raw 兜底。
- 录音音频不上传，只传转写文本；转写文本随病历 save_opd 持久化。

### 新增前端资源
- `assets/recorder.js`：`Recorder.mount(el, {compact, onFinal, onInterim, onStop})`。三态按钮（空闲🎤/录音中红⏹+脉冲/处理中）、mm:ss 计时器、声波纹 canvas（getUserMedia+AnalyserNode 真波形优先，失败降级随识别事件跳动的伪波形）；SpeechRecognition continuous+interim，onend 自动 restart；显示"已识别 X 字"。不支持 SpeechRecognition 的环境返回 null（页面回退 speech.js 隐藏按钮逻辑）。音频只留本地。
- `reception.php`：中栏表单顶部「语音问诊」横条（录音组件+转写全文可编辑框+【整理成病历】按钮+预览确认弹窗 mOrg：主诉→f_cc、现病史→f_pi、四诊摘要→体格检查，均追加不覆盖）。
- `case.php`：问诊输入框旁紧凑版录音组件，final 转写追加进 msgInput；不支持时回退旧版隐藏按钮。

### 文案统一
全站"AI 服务暂时不可用/请稍后重试"类提示统一为："AI 服务暂不可用（上游模型离线中，数据已保留；恢复后功能自动生效）"（api.php send/record/ask/ai_dx/ai_rx_review 降级分支与 reception.php 前端兜底文案）。降级行为本身不变。
