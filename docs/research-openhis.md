# 开源诊所系统数据资产调研（有医帮 · 可导入字典数据）

> 调研日期：2026-11-11 ｜ 调研人：闻百通 ｜ 所有结论附来源；未核实的项标注 [NEED:xxx]
> 本地已实测下载的数据存放于 `/tmp/openhis/DB/` 和 `/tmp/icd10.csv`（如需长期保留请拷入项目目录）

---

## 1. OpenHIS 诊所系统（新致开源 / 天天开源）⭐ 核心来源

| 项 | 内容 |
|---|---|
| 官方仓库 | https://gitee.com/tntlinking-opensource/openhis-clinic （旧地址 https://gitee.com/newtouch_code/oh-cloudclinic ）|
| License | **GPL-3.0**（另有《天天开源软件（社区版）许可协议.pdf》，商用前需法务确认 GPL 传染性对闭源 SaaS 的影响） |
| 技术栈 | Java (Spring) + Vue；与有医帮 PHP+MySQL 无关，但**数据库为 MySQL，字典数据可直接借** |
| 版本 | V2.0（2026-04-10），部署包 307M：https://gitee.com/tntlinking-opensource/openhis-clinic/releases |
| 部署文档 | https://doc.openhis.org.cn/docs/部署手册/开源医疗/诊所版HIS/诊所版HIS部署手册 |

### 1.1 已实测：仓库内含完整数据库 dump ✅

稀疏克隆仓库 `DB/` 目录（已验证可行）：

```bash
git clone --depth 1 --filter=blob:none --sparse https://gitee.com/tntlinking-opensource/openhis-clinic.git
cd openhis-clinic && git sparse-checkout set DB
```

得到文件（本地实测）：
- `DB/md_yzs.sql`（17.3 MB，MySQL 完整库，103 张表）
- `DB/ureport_file.sql`（6.2 MB，报表模板）
- `DB/add_indexes_p0.sql`

> 注意：Gitee raw 直链下载大文件会要求登录（返回 55 字节提示），**必须用 git 克隆方式获取**。

### 1.2 各字典数据实测结果

| 字典 | 表 | 规模（实测 grep 计数） | 质量 |
|---|---|---|---|
| **诊断字典（含 ICD-10）** | `xt_zd`（字段 zdCode/zdmc/icd10，含 TCM 中医病名分类） | **36,202 条 INSERT** | ⭐ 高价值，中英/中西医全，可直接导入 |
| 西药/药品字典 | `drug` | 仅 254 条，多为"租户药品02"等演示数据 | ❌ 无实用价值，需另找数据源 |
| 诊疗项目 | `cost_item`（收费项目）、`cure`（治疗记录非字典） | cost_item 未发现大批量数据 | ⚠️ 表结构可参考，数据 [NEED:诊疗项目参考价数据] |
| 生产厂家 | `manufacture_factory` | 883 条 | ✅ 可直接导入 |
| 行政区划 | `administrative_division` | 6,951 条 | ✅ 可直接导入 |

### 1.3 导入可行性结论

- `xt_zd` 诊断字典：**直接可导入**。`INSERT INTO xt_zd VALUES (...)` 标准 MySQL 语句，字段含 icd10 编码与中文名，可只抽 zdCode/zdmc/icd10 三列转换进有医帮自己的诊断表。行数约 3.6 万。
- `drug`/`cost_item`：**只有表结构 + 演示数据**。价值在于**参考其表结构设计**（拆零销售 isUnpackSell、制剂量 preparation、包装/制剂/剂量三单位等字段设计很实用）。
- GPL 注意：数据表结构设计可参考；若直接复制其 SQL 文件需评估 GPL 义务。[NEED:法务对 GPL 数据边界的确认]

---

## 2. 药品字典替代数据源

### 2.1 天健 HIS 字典 CSV（医盟网 chisc.net）
- 地址：http://chisc.net/doc/view/2880.html （资源 959KB，0 积分下载）
- 内容：`drug_dict.csv`（药品字典）、`drug_name_dict.csv`（药品名称）、`drug_price_list.csv`（价格表）、`clinic_item_dict.csv`（临床诊疗项目）、`drug_form_dict`（剂型）等 27 个 CSV——**这正是西药+诊疗项目两类的现成导入源**，来自天健医院版 HIS 初始化字典。
- 规模：[NEED:下载后实测行数]（文档未标注）
- 风险：来源为旧版 HIS 资源站，价格/年代久远，仅作骨架，参考价需另核。

### 2.2 NMPA 国家药监局（权威药品目录）
- 官方查询（国产/进口药品，可按批准文号核对）：https://www.nmpa.gov.cn → 数据查询；第三方镜像查询平台：https://www.drugfuture.com（中国药品注册数据库）
- 爬虫项目（输出 JSONL，含国内 H/S、进口 H/S 四个数据集）：https://github.com/mengguiyouziyi/nmpa — Playwright 采集，单段默认 1 万条。合规注意：爬官网需控制频率并评估合规性。
- **原研药数据库**（CC-BY-SA 4.0，结构化 YAML/JSON/CSV，含批准文号/规格/厂家）：https://github.com/dongzhenye/yuanyanyao — 规模为"原研药"范围（约数百~千级）[NEED:实测行数]。
- hint-lab chinese-medical-kg 已集成 **NMPA 药物 19,551 条**：https://github.com/hint-lab/chinese-medical-kg — 可从其 SQLite/ontology 直接抽取。

**结论**：有医帮诊所场景药品字典建议 ≈ 2,000–5,000 常用药骨架（天健 csv 抽取）+ NMPA 批准文号核验字段；用法默认值/参考价无权威开源全集，[NEED:参考价需人工或对接医药 B2B 数据]。

### 2.3 中药字典
- **lingzhi-tcm-data**：https://github.com/dfg55666/lingzhi-tcm-data — 310 味常用中药材 + 808 首方剂，JSON+Gzip，jsDelivr CDN 可直下，~375KB。✅ 诊所常用量级正好，**首选**。
- TCM-Andrology-EMR 附带的 herb_dictionary.jsonl（462 味药，含性味归经/剂量/禁忌/十八反十九畏安全规则）：https://huggingface.co/datasets/johnson/TCM-Andrology-EMR （CC BY-SA 4.0，含 safety_rules.json：十八反 15、十九畏 9、妊娠禁忌 23）——对"处方安全审查"功能极有用。
- 18,910 条中药材数据库（付费）：https://datasn.io/collections/医疗卫生/products/...（MySQL/CSV 可购）[NEED:价格确认]。
- tcmkbspider（MIT，爬中医药知识服务平台，CSV 输出）：https://github.com/eninem123/tcmkbspider
- 结论：常用中药 300–500 味即覆盖诊所场景，lingzhi-tcm-data + TCM-Andrology-EMR 组合够用。

---

## 3. ICD-10 诊断字典

| 来源 | 规模 | 说明 |
|---|---|---|
| **chaseliu/ICD-10-CN**（MIT）https://github.com/chaseliu/ICD-10-CN | disease.csv **实测 1,586 行**（code+disease 中文，三位类目级别）+ disease_catalog.csv + diseases.json | 已实测下载 `/tmp/icd10.csv`：`A00 霍乱`、`A01 伤寒和副伤寒`…格式干净可直接导入 |
| **国家卫健委临床版 2.0**（via hint-lab）https://github.com/hint-lab/chinese-medical-kg | **35,849 条疾病**（含中文名+编码） | 最全的临床版，仓库自带 SQLite/API，可导出 |
| OpenHIS `xt_zd` | 36,202 条（含 ICD-10 + 中医病名） | 见 1.2，与临床版量级一致 |
| 和鲸社区镜像（CC0）https://www.heywhale.com/mw/dataset/5e4d410780da780037c03126 | 168.3KB，csv+json | chaseliu 数据集的镜像，许可最宽松 |

**结论**：轻量用 chaseliu（1.6k 三位码）；诊所完整用 OpenHIS xt_zd 或 hint-lab 临床版 2.0（3.6 万条）。

---

## 4. 汇总：建议导入清单

| 字典 | 推荐源 | 规模 | 动作 |
|---|---|---|---|
| 诊断 ICD-10 | OpenHIS `xt_zd`（或 hint-lab 临床版 2.0） | 3.6 万条 | 从 md_yzs.sql 抽列转换导入 |
| 西药字典骨架 | 天健 HIS `drug_dict.csv` + `yuan yan yao` | 千级 | 下载 CSV → 清洗 → 映射有医帮药品表 |
| 批准文号/厂家 | NMPA（yuananyao CC-BY-SA 4.0 / hint-lab 19,551 条） | 千–2万 | 补充核验字段 |
| 中药字典 | lingzhi-tcm-data（310 味）+ TCM-Andrology-EMR（462 味 + 配伍禁忌规则） | ~500 味 | JSON 直下，转换导入 |
| 诊疗项目+参考价 | 天健 `clinic_item_dict.csv`（骨架）；参考价 [NEED:需地方医疗服务价格项目文件或人工定价] | — | 骨架导入 + 各诊所自维护 |
| 厂家字典 | OpenHIS `manufacture_factory` | 883 条 | 直接抽取 |

## 5. 主要来源 URL
- https://gitee.com/tntlinking-opensource/openhis-clinic （含 DB/ 目录与 V2.0 发行版）
- http://chisc.net/doc/view/2880.html
- https://github.com/chaseliu/ICD-10-CN
- https://github.com/hint-lab/chinese-medical-kg
- https://github.com/dongzhenye/yuanyanyao
- https://github.com/mengguiyouziyi/nmpa
- https://github.com/dfg55666/lingzhi-tcm-data
- https://huggingface.co/datasets/johnson/TCM-Andrology-EMR
- https://www.heywhale.com/mw/dataset/5e4d410780da780037c03126
