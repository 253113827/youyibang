<!-- 用途：有医帮 API 契约文档，供联调/验收/二次开发参考。所有接口均需登录（PHP session cookie），未登录返回 401。 -->

# 有医帮 API 契约

## 通用约定

- 入口：`https://youyibang.com/api.php?act=<操作名>`（**参数名是 `act`，不是 `action`**）
- 认证：登录后 PHP session cookie；未登录所有接口返回 `401 {"error":"未登录"}`
- 越权：操作不属于当前登录用户的医案返回 `403 {"error":"无权访问该医案"}`；医案不存在返回 `404 {"error":"医案不存在"}`
- 错误统一：`{"error":"<原因>"}` + 对应 HTTP 状态码（400/401/403/404/502）。**api.php 的 JSON 错误体直通**：站点 nginx 对 `^/api\.php$` 单独设置 `fastcgi_intercept_errors off`（全局 http 层为 on），PHP `fail(...,404)` 的 JSON 不会被 nginx 默认 404 页替换。
- `sign`/`commit_emr` 的 `sign_id`/`emr_id` 在 INSERT 后立即取 `lastInsertId()`——MySQL OK 包中的 last_insert_id 会被紧随其后的 UPDATE 语句重置为 0，故不可在 UPDATE 之后再取。
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
| `upload` | POST multipart | `case_id` `type`（面色\|舌苔\|化验单\|其他）`files[]`（多文件；须为真实图片，getimagesize 校验，扩展名白名单 jpg/jpeg/png/gif/webp/heic；字段名误写为 `files`（单数）时入口自动归一化为单元素数组，不产生 PHP Warning） | `{"images":[{id,path,type}],"ok":true}`；非图片 → `400 {"error":"非有效图片文件"}` |
| `ai_image` | POST | `id`（images.id） | `{"id":<id>,"ai_result":"<Markdown>","ok":true}` |
| `record` | POST | `case_id` | `{"content_md":"<Markdown病案>","ok":true}`；已签名锁定 → `409` |
| `record_save` | POST | `case_id` `content_md` | `{"ver":<unix时间戳>,"ok":true}`；已签名锁定 → `409`（需先 `revoke_sign` 产生新版本） |
| `ask` | POST | `case_id` `q` | `{"reply":"<回答>","ok":true}` |
| `validate` | GET/POST | `case_id` | `{"parsed":{chief_complaint,present_illness,tongue,pulse,syndrome,drugs:[{name,dose,raw}],pregnant},"errors":["<阻断项>"],"warnings":["<提示项>"],"signed":bool,"archived":bool,"ok":true}`；无草稿 → 404。errors 非空时 `sign` 被 409 拒绝 |
| `sign` | POST | `case_id` `signer_name` `sign_image`（`data:image/png;base64,` 前缀 PNG） | `{"sign_id","content_hash":"<SHA256(病历全文|时间戳|uid)>","signed_at","warnings","ok":true}`；存在 errors → `409 {"error":"存在阻断性校验错误，禁止签名","errors":[...]}`；签名后草稿锁定（record/record_save 409） |
| `revoke_sign` | POST | `case_id` | `{"revoked":true,"ok":true}`；签名标记 revoked（保留历史），草稿解锁 |
| `commit_emr` | POST | `case_id` | `{"emr_id","content_hash":"<SHA256(content_md|prev_hash)>","prev_hash","ok":true}`；未签名 → 409。追加式落库（代码中不存在 UPDATE/DELETE 路径），首条 prev_hash=`EMR_SEED`（config.php 种子 `youyibang-emr-genesis-2026`） |
| `verify_emr` | GET | `case_id` | `{"valid":bool,"broken_at":<首个被篡改记录id,0=完整>,"total":<归档总数>,"ok":true}`（重算全表哈希链） |
| `export` | GET | `case_id` | A4 打印友好 HTML（抬头/患者信息/病历全文/处方表/签名图/时间戳/哈希指纹/免责声明），浏览器 window.print() 出 PDF；OFD-H 版式为 2027 国标预留，当前过渡方案 |

### 规则引擎（rules.php）

`validate_record(parse_record(病历md))` 返回：
- **errors（阻断签名）**：病历解析失败（缺主诉/辨证/处方结构）、主诉缺失、辨证缺失、处方为空、药缺剂量（**含单药一行无剂量写法，dose=null 不再被静默跳过**）、剂量 ≤0 或 >500g（剂量 0 的药保留在 drugs 中并报"剂量不合法（≤0）"，不再被丢弃）。
- **warnings（提示）**：现病史缺失、舌象/脉象未采集、十八反/十九畏配伍禁忌（完整禁忌对表，炮制品归一：炙甘草→甘草、法半夏→半夏等）、药典剂量上限（细辛≤3g 等 20 代表药）、妊娠禁忌药（病历含妊娠字样时）。

### 前端新增

- case.php：🎤 语音输入（Web Speech API `webkitSpeechRecognition` zh-CN，不支持自动隐藏）；✅ 规则校验面板（红阻断/黄警告）；✍ canvas 手写签名板（鼠标+触摸）；🗄 归档/🔐 验证哈希链/🖨 导出按钮与"已归档"状态。

### 数据表新增

- `records` 加列 `is_signed`/`archived`；新表 `signatures`（含 revoked 软撤回、snapshot_json 校验快照、content_hash）；新表 `emr_records`（追加式哈希链，无任何 UPDATE/DELETE 代码路径）。见 db.sql。

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

# 门诊接诊工作台（OPD）v2 追加契约

## 页面

- `reception.php`：桌面三栏工作台（左=今日队列+历史就诊，30s 轮询；中=结构化病历+处方签（西药/输液/中药 tab、药品/项目搜索、自动合计、模板）；右=AI 智能诊断+患者历史）。≤768px 折叠为 队列/接诊/AI 三视图切换。
- `m_reception.php`：移动端单栏接诊（患者卡→病历关键项编辑+处方只读→「完成接诊」），拍照/语音经「进入医案」跳 case.php 复用。

## 新表

patients / visits(status: waiting|in_visit|done) / drug_dict(50 种预置) / procedure_dict(10 项) / opd_records(visit_id 主键) / prescriptions(items JSON, total) / rx_templates(doctor_id 维度)。见 db.sql 追加段。

## 新增 act（全部登录+check_visit 越权校验+PDO 预处理）

| act | 方法 | 入参 | 返回 |
| --- | --- | --- | --- |
| `create_patient` | POST | name*(必填) gender age phone allergy birthdate reg_fee | `{patient_id, visit_id, archive_no}`（生成 waiting visit，档案号 OPD+6位） |
| `queue` | GET | - | `{today:[{id,status,reg_fee,registered_at,case_id,patient_id,name,gender,age,phone}], history:[{id,finished_at,diagnosis,name,uid}]}`（仅本人，今日+历史已诊30条） |
| `save_opd` | POST | visit_id, chief_complaint, present_illness, allergy_history, physical_exam, diagnosis, auxiliary_exam JSON, treatments JSON, notes, rx_items JSON(可选,含 type) | `{visit_id, total}`；waiting→in_visit；rx_items 按 type 分落 prescriptions |

**total 计费口径（2026-11-11 修复）**：`total = Σ(rx_items 各项 单价×数量) + Σ(auxiliary_exam 各项 单价×次数) + Σ(treatments 各项 单价×次数)`。辅助检查/治疗条目缺 `price`/`times` 字段或值非法时按 0 计，不报错；`times` 缺省视为 1。`print_opd` 的 grand 合计同口径，并在版面输出"分项小计（药品/辅助检查/治疗项目）"与"合计金额"两行；reception.php 中栏 JS `calcTotals()` 同步三类合计。
| `get_opd` | GET | visit_id | `{patient, record|null, prescriptions:{western/infusion/chinese:{items,total}}, history}` |
| `search_drug` | GET | kw | `{drugs:[…15 条]}`（name/spec LIKE） |
| `search_proc` | GET | kw | `{procs:[…]}` |
| `ai_dx` | POST | visit_id | `{dx:[{name,symptoms[],exam,tests}], note:"AI 建议仅供参考"}`；上游离线 → `{dx:[],note:"AI 服务暂不可用"}`（不报错） |
| `save_rx_template` | POST | name, items JSON | `{id}` |
| `get_templates` | GET | - | `{templates:[{id,name,items}]}` |
| `finish_visit` | POST | visit_id | `{visit_id, status:"done"}`（置 finished_at） |
| `print_opd` | GET | visit_id | A4 HTML（患者信息/病历/辅助检查/治疗/处方表/合计金额/医师签名），window.print() 出 PDF |

## 行为说明

- ai_dx 提示词为自拟版本（3-5 鉴别诊断 JSON：诊断名/伴随症状/查体/检验建议），前端渲染胶囊标签并固定显示"⚠️ AI 建议仅供参考"。待 research-aidx.md 就绪后可替换 prompts。
- 修复记录：辅助函数（check_visit/patient_history）曾误放 switch 内导致"undefined function"——PHP 条件定义只在执行路径经过时生效；已移至 switch 之前。
- 移动端 save_opd 仅编辑主诉/诊断/注意事项，其余字段以空值覆盖（移动端场景病历由桌面端录入为主）。

## 数据与提示词升级 v2.1

- `diag_dict`：36,202 条诊断字典（ICD-10 + 中医病名），抽取自 OpenHIS xt_zd（GPL-3.0，来源见 db.sql 注释）。`act=search_diag&kw=` 返回 `{diags:[{zd_code,name,name_tcm,icd_code,category}]}`；reception.php 诊断输入框联想。
- `act=ai_dx` 升级：按 research-aidx.md 鉴别诊断提示词，返回 `{differentials:[{diagnosis,supporting,against,suggested_tests[],confidence,red_flags}], missing_info[], disclaimer}`；离线降级 `{differentials:[],missing_info:[],note:"AI 服务暂不可用"}`。
- `act=ai_rx_review`（POST visit_id）：处方安全审查。规则引擎层（rules.php 十八反/十九畏/妊娠/药典 + 过敏史含青霉素→β-内酰胺交叉匹配）优先，AI 补充层合并去重；返回 `{pass, warnings:[{severity,type,drug,title,mechanism,suggestion,source:rule_engine|ai_review}], note, disclaimer}`；LLM 离线时仅返回规则引擎结果（已实测：青霉素过敏+乌头反半夏均命中 source=rule_engine）。
- rules.php 本轮零改动；6 组规则回归（十八反×2/十九畏/妊娠/药典/无命中）全符合预期。
- safety_rules.json（TCM-Andrology-EMR）：huggingface.co 本机不可达，未并入（rules.php 已内置等价配对表，功能不受损）。
