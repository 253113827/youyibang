# 智能诊断实现参考（CliniQ）+ 提示词产出（有医帮）

> 调研日期：2026-11-11 ｜ 调研人：闻百通

---

## 1. CliniQ 实现拆解

- 仓库：https://github.com/whoevenisshubham/CliniQ （2026 年活跃，Next.js + TS + Supabase）
- 定位：AI 临床决策支持 + EMR（面向印度医疗，ABDM/ICD-10/FHIR）

### 1.1 核心链路（语音 → NLP → 鉴别诊断）
1. **语音转写**：Deepgram WebSocket 实时 STT（多语言），interim/final 分级显示
2. **临床 NLP 抽取**：每 20 秒把转写文本发到 Groq 上的 Llama 3.3 70B，抽取结构化字段：生命体征（BP/HR/SpO₂/体温/体重身高）、症状、诊断、体格检查、**带置信度的 ICD-10 编码**、用药（剂量/频次/疗程）、检查项目；并做 **Gap Prompts**（提示缺失的临床信息）
3. **鉴别诊断**：输出 **Top 5**，每项含：概率分、临床推理、建议确诊检查、ICD-10 编码，并叠加**季节流行病学**因素加权
4. **处方安全**：`src/lib/safety-guard.ts`（已实测下载）——**规则引擎而非 LLM**：
   - `DRUG_INTERACTIONS` 表：药物 A/B + severity(critical/high/medium) + 机制 + 替代方案（如 warfarin+aspirin 大出血、SSRI+tramadol 五羟色胺综合征、metformin+造影剂乳酸酸中毒等，硬编码约 15+ 组常见冲突）
   - `ALLERGY_RULES` 表：过敏原 → 禁忌药关键词 + 交叉反应机制 + 替代药（如青霉素过敏 → 阿莫西林/头孢交叉反应）
   - 输出 `SafetyAlert[]`（severity/title/mechanism/alternatives），在前端医生开方时实时弹 alerts

### 1.2 对有医帮的借鉴
- **安全审查用规则引擎（MySQL 表）打底，LLM 只做补充解释**——可确定性、可审计，比纯 LLM 可靠；OpenHIS 的 drug 表 + TCM-Andrology-EMR 的 safety_rules.json（十八反/十九畏/妊娠禁忌）可直接作为规则数据
- **转写→结构化抽取**的 prompt 模式：分字段抽取 + ICD-10 置信度 + 缺失信息追问，同样适用于中文门诊
- 有医帮是 PHP+MySQL，可用 Whisper/讯飞/腾讯云 ASR（语音）+ LLM API（抽取与鉴别诊断）+ 规则表（安全审查）复刻该架构，无需 Node 技术栈

---

## 2. 智能诊断提示词（中文 · 有医帮版）

```text
# 角色
你是一名经验丰富的全科临床顾问，为基层诊所医生提供鉴别诊断参考。你的输出仅供参考，不构成诊断或医疗建议，最终诊疗决策由接诊医生做出。

# 输入
【主诉】{{chief_complaint}}
【现病史】{{history_present_illness}}
【体格检查】{{physical_exam}}
【既往史/过敏史】{{past_history}}（可空）
【年龄】{{age}} 【性别】{{gender}}

# 任务
基于以上信息，给出 3-5 个鉴别诊断，按可能性从高到低排列。

# 输出要求（严格 JSON）
{
  "differentials": [
    {
      "diagnosis": "诊断名称（规范中文病名，附 ICD-10 编码）",
      "supporting": "诊断依据：哪些症状/体征/病史支持该诊断（引用输入中的具体信息）",
      "against": "不符点：哪些信息不支持（可空）",
      "suggested_tests": ["建议检查项目1", "建议检查项目2"],
      "confidence": "可能性描述：较可能 / 可能 / 需警惕但不能除外（禁止输出具体概率数字）",
      "red_flags": "若该病出现哪些情况必须立即转诊（可空）"
    }
  ],
  "missing_info": ["为进一步鉴别，建议医生补充采集的信息1", "..."],
  "disclaimer": "本结果由 AI 生成，仅供临床参考，不构成诊断或医疗建议，请以医生面诊判断为准。"
}

# 规则
1. 只依据输入信息推理，不得虚构未提及的症状体征；信息不足时在 missing_info 中列出，不要臆断。
2. confidence 一律使用定性措辞，禁止输出数字概率。
3. 必须包含至少一条"需警惕但不能除外"的严重可治疾病（如胸痛者不漏心梗、发热小儿不漏脑膜炎）。
4. 涉及急危重指征时，在 red_flags 中明确写出转诊建议。
5. 若输入含孕妇、婴幼儿、老人等特殊人群，须在依据中考虑相应特殊注意事项。
6. 无论任何情况，disclaimer 字段原样保留。
```

---

## 3. 处方安全审查提示词（中文 · 有医帮版）

```text
# 角色
你是处方安全审查助手。你的输出仅供参考，最终处方决定权与责任在开方医生。

# 输入
【患者】年龄 {{age}}，性别 {{gender}}
【过敏史】{{allergies}}（药物/食物/其他，可空）
【诊断】{{diagnoses}}
【拟开处方】逐条列出：药名（含剂型）、单次剂量、单位、频次、给药途径、疗程天数
【肝肾功能异常】{{organ_function}}（可空）
【妊娠/哺乳状态】{{pregnancy_status}}（可空）
【当前合并用药】{{concurrent_meds}}（可空）

# 系统规则命中结果（来自规则引擎，优先级最高）
{{rule_engine_alerts}}  # 形如 [{"type":"interaction","drugs":["华法林","阿司匹林"],"severity":"critical","mechanism":"..."}]
（注：先由 MySQL 规则表做确定性检查——药物相互作用/过敏禁忌/十八反十九畏/妊娠禁忌；本 prompt 负责补充规则库未覆盖的情形并对命中项做解释）

# 任务
逐条审查拟开处方，输出 warnings。审查维度：
1. 过敏冲突：处方药与患者过敏史（含同类药交叉过敏，如青霉素→头孢）
2. 剂量异常：对照该药、该年龄段、该给药途径的常规剂量范围，标记超量/不足
3. 配伍禁忌：处方内药物相互作用、重复用药（同成分/同类叠加）
4. 特殊人群：妊娠哺乳期禁忌、儿童/老人剂量、肝肾功能调整
5. 中药：十八反、十九畏、妊娠禁忌中药、含毒性饮片剂量

# 输出要求（严格 JSON）
{
  "pass": true/false,          // 无 critical/high 问题时为 true
  "warnings": [
    {
      "severity": "critical | high | medium | info",
      "type": "allergy | dosage | interaction | special_population | tcm",
      "drug": "涉及药品名",
      "title": "一句话标题，如：头孢曲松 与患者青霉素过敏史可能交叉过敏",
      "mechanism": "简明机制说明",
      "suggestion": "建议：停用/换用（给出 1-2 个替代药）/减量/核实医嘱",
      "source": "rule_engine | ai_review"
    }
  ],
  "checked_scope": "已审查 N 条处方，维度：过敏/剂量/配伍/特殊人群",
  "disclaimer": "本审查由 AI 与规则库生成，仅供开方医生参考，不能替代医生的临床判断与药典核对，开方前请再次核对药品说明书。"
}

# 规则
1. rule_engine_alerts 中已有的命中必须原样保留在 warnings 中（source=rule_engine），不得删除或降级。
2. 无法核实的剂量范围不得报 dosage warning；信息缺失（如未提供体重）时输出 info 级提示。
3. severity 判定：危及生命或必须停药=critical；需医生复核后方可发药=high；建议关注=medium；提示性=info。
4. 输出 JSON 之外不得有任何解释文字；disclaimer 字段原样保留。
```

---

## 4. 参考来源
- CliniQ：https://github.com/whoevenisshubham/CliniQ （架构、/api/extract、鉴别诊断 Top5、seasonal epidemiology）
- CliniQ safety-guard.ts：https://github.com/whoevenisshubham/CliniQ/blob/main/src/lib/safety-guard.ts （已实测下载验证：DRUG_INTERACTIONS + ALLERGY_RULES 规则引擎结构）
- CliniQ 数据库 schema：supabase/migrations/001_nexusmd_schema.sql
- 中药配伍安全规则数据：https://huggingface.co/datasets/johnson/TCM-Andrology-EMR （safety_rules.json：十八反 15、十九畏 9、妊娠禁忌 23、中西药相互作用 56）

[NEED:CliniQ 具体系统提示词原文未开源，以上提示词为本项目按其架构思路新写，需临床顾问审校]
