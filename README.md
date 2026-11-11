# 有医帮（youyibang.com）

医生 AI 助理站 + 诊所 SaaS 接诊工作台。技术栈：**PHP 8 + MySQL + 原生 HTML5/JS**（零框架，轻量可自托管）。

> ⚠️ 免责声明：本系统产出（AI 病历、诊断、方剂）**仅供参考，不构成医疗建议**，不具备医疗器械资质，须执业医师审核签字。

## 功能总览

### 1. 门诊接诊工作台（reception.php，对标商业诊所 SaaS 三栏布局）
- **左栏**：患者搜索 + 快速接诊（自动档案号）+ 今日队列（待诊/已诊，30s 自动刷新）+ 历史就诊
- **中栏**：结构化病历（主诉/现病史/过敏史/体格检查/诊断/**辅助检查计费**/治疗项目）+ **西药/输液/中药处方签**（药品字典搜索、用法/频次/剂量/天数、自动合计、处方模板保存套用）+ 打印病历处方单
- **右栏**：**AI 智能诊断**（3-5 鉴别诊断 + 置信度胶囊 + 🚩转诊指征 + 缺失信息提示 + 免责声明）+ 患者历史
- 计费口径：总额 = 药品 + 辅助检查 + 治疗项目，打印页含分项小计

### 2. 移动端接诊（m_reception.php）
手机单栏：患者卡 → 病历关键项编辑 → 处方只读 → 完成接诊；拍照/语音经医案链接复用，与电脑端 3 秒轮询同步。

### 2.5 语音转文字 + 中医四诊一页勾选（v2.2，对齐 ABC 诊所管家界面）
- **语音转文字**：`assets/speech.js` 公共模块（Web Speech API，zh-CN + continuous + interim），主诉/现病史/过敏史/体格检查/诊断/注意事项等全部文本区带 🎤 按钮；macOS Safari/Chrome 与 iPhone Safari 14.5+ 可用，不支持的环境自动隐藏
- **中医四诊勾选面板**：问诊 8 组（寒热/汗/头身/胸腹/饮食口味/睡眠/二便/其他）+ 舌象（舌色/苔色/苔质/舌形）+ 面色五色 + 脉象 10 种，全部 chip 一页勾选，不逐条提问；勾选实时汇编为结构化四诊文本一键插入病历，勾选状态存 `opd_records.tcm_checklist`（JSON，64KB 上限）回显
- 界面对齐截图：黄色高亮编辑区、处方三签 tab（+西药/+输液/+中药）行内 +/- 剂量步进与单位下拉、保存/取消/打印按钮组、体格检查体温/血压/心率内联三项、AI 伴随症状 chip 点击回填主诉

### 2.6 语音问诊录音工作台（v3）
- **真实录音 UI**（`assets/recorder.js`）：大按钮三态（🎤空闲 → 红色停止+脉冲动画 → 处理中）、mm:ss 计时器、**声波纹 canvas**（getUserMedia+AnalyserNode 真实波形；iOS 冲突时自动降级伪波形，不挡录音主流程）
- **边录边转文字**：interim 结果灰色斜体实时上屏，final 追加转写缓冲，显示"已识别 X 字"；SpeechRecognition onend 自动 restart 保证长录音不中断
- **AI 整理成病历**（act=organize_transcript）：录完一键去除语气词/重复/寒暄，输出【主诉】【现病史】【四诊摘要】三段结构化文本，**先弹预览可编辑**再确认填入；AI 离线时自动插入原始转写（标注【原始转写 待整理】），数据不丢
- 音频只留本地不上传，仅转写文本随病历保存；AI 不可用提示全站统一为"上游模型离线中，数据已保留；恢复后自动生效"

### 3. AI 医案问诊（case.php）
- **39 条中医"十问"预设问诊**（按患者性别条件渲染）+ 自由追问
- **语音问诊**（Web Speech API，不支持的设备自动隐藏）
- **拍照上传**：手机 `capture` 调起摄像头，电脑端 3 秒内同步可见
- **图片 AI 解析**：舌象（舌色/舌形/苔色/苔质/证候）、面色（五色诊+面部分区）、化验单结构化提取——全部走视觉大模型，无需单独 OCR 引擎
- **AI 整理病案**：九段结构 Markdown 病历（主诉/现病史/四诊/辅查/辨证/治法/方剂含剂量煎服法/医嘱）

### 4. 合规 EMR 流程
```
问诊/语音 → AI 病历草稿（四诊齐全）→ 规则引擎校验（提示面板）→ EMR 哈希链落库 → 打印/导出
```
- **规则引擎（rules.php）**：errors 阻断（主诉/辨证/处方缺失、剂量≤0或>500g）+ warnings（**十八反十九畏全表**、药典剂量上限 20 味、妊娠禁忌、舌脉缺失）；处方安全审查含过敏史交叉匹配（如青霉素过敏×阿莫西林）。校验结果以红/黄面板提示，不做硬阻断
- **EMR 防篡改**：`emr_records` 追加式表 + prev_hash 哈希链（SHA256），代码中不存在任何 UPDATE/DELETE 路径；`verify_emr` 一键验链
- **电子签名**：已于 2026-11-11 按需求移除（含 canvas 签名板与 sign/revoke_sign 接口），EMR 归档不再要求签名；signatures 表保留但代码不再读写
- **导出**：A4 打印版式（归档时间戳/EMR 哈希指纹/免责声明）；OFD-H 版式为 2027 国标预留

### 5. 数据字典
- **diag_dict：36,202 条诊断**（ICD-10 编码 + 中文病名 + 中医病名），诊断框实时联想
- drug_dict 50 种常用药品 + procedure_dict 10 项诊疗项目（演示数据，可自行扩充）
- 来源与合规说明见 `db.sql` 注释与 `docs/research-openhis.md`

## 目录结构
```
├── index.php          # 入口：医案列表 + 顶部模块导航
├── login.php/logout.php
├── reception.php      # 三栏接诊工作台（桌面/≤768px 自适应）
├── m_reception.php    # 手机端接诊
├── case.php           # AI 医案问诊详情
├── api.php            # 全部 API（act 路由，登录+归属校验+PDO 预处理）
├── rules.php          # 处方/病历规则引擎（十八反十九畏/剂量/妊娠）
├── presets.php        # 39 条十问预设
├── config.example.php # 配置模板（复制为 config.php 填入真实值）
├── db.sql             # 全部建表语句 + 演示数据
├── db_opd.sql         # 接诊工作台增量建表（已在 db.sql 合并的可忽略）
├── data/diag_dict.sql # 36,202 条诊断字典（导入用）
├── data/md_yzs.sql    # OpenHIS 原始 dump（GPL-3.0，未入库，见 docs）
├── extract_diag.py    # 从 OpenHIS dump 抽取诊断字典的脚本
├── mock_llm.py        # 本地 mock OpenAI 兼容服务（联调用）
├── e2e.sh             # 端到端冒烟脚本
└── docs/              # 全部文档
    ├── api.md             # API 契约（act/入参/返回 JSON/错误码）
    ├── architecture.md    # 架构设计
    ├── preset-questions.md# 十问问题集
    ├── prompts.md         # 舌象/面色/化验单/病案整理提示词
    ├── research-openhis.md# OpenHIS 数据资产调研
    └── research-aidx.md   # 智能诊断提示词调研（参考 CliniQ 模式）
```

## 快速部署（宝塔/Nginx 均可）
1. 代码放到站点根目录，PHP 8+，`chown www uploads/`（自建 uploads/、uploads/signs/）
2. 复制 `config.example.php` → `config.php`，填入数据库与 LLM 配置
3. 导入 `mysql -u youyibang -p youyibang < db.sql`，再导入 `data/diag_dict.sql`
4. 大模型：任何 OpenAI 兼容端点（`$LLM_ENDPOINTS` 数组按顺序故障切换），视觉模型用于图片解析
5. nginx 注意：不要对 `/api.php` 开 `fastcgi_intercept_errors`（会吞 JSON 错误体）；改 PHP 后重启 php-fpm（OPcache）
6. 登录默认账号在 users 表（首次部署后请立即改密）

完整 API 契约见 **docs/api.md**，架构见 **docs/architecture.md**。

## 安全设计
- 全部 SQL 走 PDO 预处理；输出 htmlspecialchars；上传 getimagesize 白名单校验；医案/就诊双重归属校验（403/404 语义明确）
- 敏感配置不入库：`config.php` 已 gitignore，仓库只提供 example 模板

## 数据来源与许可
- `data/diag_dict.sql` 抽取自 [OpenHIS 诊所版](https://gitee.com/tntlinking-opensource/openhis-clinic)（GPL-3.0）`xt_zd` 表——**如商用请自行评估 GPL 边界**
- 智能诊断提示词参考 [CliniQ](https://github.com/whoevenisshubham/CliniQ) 的"规则引擎打底 + LLM 补充"模式自行撰写

## 路线图
- [ ] LLM 上游恢复后：ai_dx/ai_rx_review 在线验证
- [ ] OFD-H 合规版式导出（GB/T 48666-2026，2027 前对齐）
- [ ] 牙科牙位图（FDI）表单
- [ ] 拼音简拼搜索、收费/药房/库存模块

## 更新日志

### 2026-11-11 接诊工作台 UI 1:1 还原（v3）
- `reception.php` 整体重写视觉层，1:1 还原「ABC诊所管家」截图布局：
  - 深蓝导航栏（ABC 式 logo + 诊所名 + 模块菜单 + 右侧医保端/帮助/退出）
  - 次级工具条：全局搜索 + 绿色＋接诊 + 门诊面包屑 + 费用预览/保存/取消/打印 + 会话/历史/影像/AI诊断页签
  - 左栏患者队列：性别配色头像(♂蓝/♀粉)、时间右对齐、待诊/已诊状态字标、底部小工具区（叫号/工作汇总/预约看板）
  - 中栏：患者条（♀头像+性别年龄电话+医生科室chip+挂号费红字+自费）；病历表单改为「标签左列 + 淡黄编辑区右列」行式布局，体格检查行内联体温/血压/心率三输入
  - 处方签：＋西药/输液/中药处方按钮行 + 选择/保存模板；药表蓝名/口服/q12h/步进器剂量/天数/开药量/行合计；＋输入药名行内搜索
  - 右栏智能诊断：编号式鉴别诊断卡片（1. 2. 3.…）、伴随症状 chip 可点击写入病历、检验蓝标、转诊指征红标
- 顺手修复两个既有 JS 致命 bug（原代码整段脚本无法解析）：
  1. `openVisit()` 内 `const r` 重复声明 → 改为 `rec`
  2. `rxSheet()` 调用了从未定义的 `rxRow()` → 删除死调用（`rxTable()` 内部自建行）
- 本地开发：`php -S 127.0.0.1:8090 -t 项目目录`，MySQL 8 + `db.sql`/`db_opd.sql`/`data/diag_dict.sql`
- 旧版 UI 备份见本地 `reception_old_ui.php`（不入库）

## 实时语音转写（双通道，2026-11-11）
- **通道1 浏览器内置识别**：SpeechRecognition，interim 实时灰色上屏（Chrome 需可达 Google；不可达时自动降级）
- **通道2 云端分片转写**：MediaRecorder 每 4s 分片 → `POST api.php?act=asr_chunk` → 转发到 `$ASR_ENDPOINTS`（OpenAI 兼容 `/v1/audio/transcriptions`，多端点故障切换），文字实时追加
- 错误可视化：权限被拒/网络不可达/未检测到语音均有中文状态提示，不再静默 0 字
- 本机自建 ASR：`python asr_server.py`（faster-whisper small，端口 8765，需 `.asr_venv`：`pip install faster-whisper fastapi uvicorn python-multipart av`）
- 浏览器识别一旦出字，分片通道自动让位，避免重复；两通道计数共用「已识别 X 字」

## 生产架构（2026-11-11）
- **服务器**：43.160.196.55（OpenCloudOS + 宝塔），站点根 /www/wwwroot/youyibang.com
- **ASR**：服务器本地 systemd `yyb-asr`（faster-whisper base，端口 18765，模型经 HF 镜像 hf-mirror.com 下载；低内存可 ASR_MODEL=tiny）
- **LLM**：内网 M5 网关（glm-5.3-flash）经 **Mac SSH 反向隧道** 引入服务器 127.0.0.1:18770
  - Mac 守护脚本 `llm_tunnel_keeper.sh`（断线 5s 重连）；服务器 config.php `$LLM_ENDPOINTS` 第一位为隧道端点，故障自动切换
  - glm-5.3-flash 为思考型模型：`llm_chat` 已加 `max_tokens=4096` + `chat_template_kwargs.enable_thinking=false`
- **php-fpm**：request_terminate_timeout 已调至 300（LLM 长请求）
- **浏览器分片转写**：WebAudio PCM 4s 切片 + 前端编码 WAV 16k 上传（绕开 WebKit MediaRecorder 不支持 timeslice 的缺陷），勿用 MediaRecorder
- 改 PHP 后需 `kill -USR2 <php-fpm master pid>` 或宝塔重载；前端 JS 改动请同步 bump `?v=` 版本号防缓存

## AI 智能诊断「一键采纳」（2026-11-11）
- AI 生成鉴别诊断后，每个卡片带「✅ 采纳此诊断」按钮：
  - 诊断名（去 ICD-10 编码）→ 追加到「诊断」栏
  - 查体所见 → 追加到「体格检查」
  - 建议检验 + 方案检查 → 追加到「辅助检查」（价格 0 待核对）
  - 点击采纳时实时调用 `api.php?act=ai_treatment` 按患者年龄/性别/过敏史生成该诊断的基层标准处方 → 写入西药处方签；用药交代 → 「注意事项」
- 架构说明：诊断列表与治疗方案**分两次请求**（ai_dx 短输出 + ai_treatment 按需生成），避免网关对长响应的确定性截断；`llm_chat` 已加传输完整性校验+每端点重试 2 次（隧道瞬断产生的部分响应不再被误用）
- 采纳后按钮变「✅ 已采纳」防重复；药品价格为 0 需医生核对（AI 不生成价格，防止编造）

### 实时转写故障排查记录（2026-11-11 16:10）
- **症状**：前端正常发分片（nginx 日志 200），但界面 0 字，后端报「Connection timed out」
- **根因 1**：ASR 端点为 async def + 同步转写 → 转写期间 FastAPI 事件循环被阻塞，整个服务无响应 → 已改为同步 def（线程池）+ ASR_LOCK 非阻塞锁（忙时返回 {"text":""} 丢弃分片，防堆积雪崩）
- **根因 2**：PHP 收到 ASR「忙」响应（text=""）时误判失败，继续尝试永远不可达的 beiheng/内网端点 → $ASR_ENDPOINTS 生产只保留本机 18765；text 字段存在即视为成功（空=静音/忙丢弃）
- **根因 3**：php-fpm 环境可能有 http_proxy → 所有内部 curl 显式 CURLOPT_PROXY=>''
- **修复后**：生产连续 4 片压测全部 1.4s 返回正确转写

## 版本 v2026.11.11-r2（用户验收通过）
- 实时转写全链路稳定：WebAudio PCM 4s 分片 → 服务器本机 faster-whisper base → 实时出字；连续压测 1.4s/片
- 智能诊断一键采纳（诊断+查体+检查+处方+注意事项）生产验收通过
- 用户实测原话："很好 可以用了"

## v2026.11.11-r3 修复与增强
1. **中医四诊勾选自动入病历**：保存时自动将勾选内容汇编为「【四诊摘要】」段写入现病史（替换式幂等，多次保存不重复）；面板内「📋 同步到现病史」按钮可手动预览同步；打开旧病历时勾选状态自动回显
2. **智能诊断采纳支持中西药双标准**：每个鉴别诊断卡片提供「✅ 西药方案」「🌿 中药方案」两个采纳入口；中药方案由 AI 按中医辨证生成饮片处方（含克数/炮制/特殊煎法/配伍用意）+ 推荐方剂名（写入诊断栏）+ 医嘱；西药方案保持原有标准。同一诊断的两类方案可分别各采纳一次，防重复

## v2026.11.11-r4 修复与增强
1. **字段级话筒（🎤）修复**：原基于 Web Speech API（Chrome 需连 Google，国内静默失败）→ 重写为 MediaRecorder 录音 → 停止后上传 asr_chunk（服务器 whisper）→ 文本插入光标处。主诉/现病史/过敏史/诊断/注意事项/体格检查等 11 处可用；生产实测外放语音转写正确插入
2. **AI 智能诊断分中西医双模式**：「🤖 西医鉴别诊断」按 ICD-10 出鉴别诊断（出处：《内科学》人卫版/指南/药典）；「🌿 中医辨证论治」按证型辨证（出处：《方剂学》新世纪教材/《伤寒论》等经典），信息不足时明确"待辨证不推荐方剂"（符合"未辨证不得处方"规范）。两种模式结果独立缓存，可切换参考
3. **采纳处方防重复询问**：处方签已有 AI 药行时，再次采纳会询问「清空重写 / 追加在后」
4. **整理成病历分中西医标准**：西医按《病历书写基本规范》，中医按《中医病历书写基本规范》（四诊摘要含望闻问切）；弹窗标注当前标准
