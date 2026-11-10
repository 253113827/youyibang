-- 有医帮 门诊接诊工作台 建表（追加，IF NOT EXISTS 可重复执行）
USE youyibang;

CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  gender ENUM('男','女','未知') DEFAULT '未知',
  age VARCHAR(10) DEFAULT '',
  birthdate DATE NULL,
  phone VARCHAR(20) DEFAULT '',
  archive_no VARCHAR(30) DEFAULT '',
  allergy VARCHAR(500) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  case_id INT NULL,
  doctor_id INT NOT NULL DEFAULT 1,
  status ENUM('waiting','in_visit','done') NOT NULL DEFAULT 'waiting',
  reg_fee DECIMAL(8,2) NOT NULL DEFAULT 0,
  registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  INDEX (doctor_id, status, registered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS drug_dict (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  spec VARCHAR(100) DEFAULT '',
  manufacturer VARCHAR(100) DEFAULT '',
  type ENUM('western','chinese') NOT NULL DEFAULT 'western',
  unit VARCHAR(20) DEFAULT '',
  default_usage VARCHAR(100) DEFAULT '口服',
  default_freq VARCHAR(20) DEFAULT 'qd',
  price DECIMAL(8,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  INDEX (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS procedure_dict (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('exam','treat','material') NOT NULL DEFAULT 'exam',
  price DECIMAL(8,2) NOT NULL DEFAULT 0,
  INDEX (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS opd_records (
  visit_id INT PRIMARY KEY,
  chief_complaint TEXT,
  present_illness TEXT,
  allergy_history TEXT,
  physical_exam TEXT,
  diagnosis TEXT,
  auxiliary_exam JSON,
  treatments JSON,
  notes TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  type ENUM('western','chinese','infusion') NOT NULL DEFAULT 'western',
  items JSON,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (visit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rx_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  items JSON,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 预置演示数据：30 种常用西药
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT
'阿莫西林胶囊' AS name,'0.25g×24粒' AS spec,'华北制药' AS manufacturer,'western' AS type,'粒' AS unit,'口服' AS default_usage,'q8h' AS default_freq,15.80 AS price,500 AS stock) t
WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='阿莫西林胶囊');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '布洛芬缓释胶囊','0.3g×20粒','中美史克','western','粒','口服','q12h',22.50,300) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='布洛芬缓释胶囊');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '对乙酰氨基酚片','0.5g×16片','西南药业','western','片','口服','q8h',8.60,400) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='对乙酰氨基酚片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '头孢克肟分散片','50mg×12片','广州白云山','western','片','口服','q12h',26.00,200) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='头孢克肟分散片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '阿奇霉素片','0.25g×6片','辉瑞','western','片','口服','qd',38.00,150) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='阿奇霉素片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '奥美拉唑肠溶胶囊','20mg×14粒','阿斯利康','western','粒','口服','qd',29.90,250) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='奥美拉唑肠溶胶囊');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '雷贝拉唑钠肠溶片','10mg×7片','济诺','western','片','口服','qd',32.00,180) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='雷贝拉唑钠肠溶片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '铝碳酸镁咀嚼片','0.5g×20片','拜耳','western','片','咀嚼','qid',25.00,220) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='铝碳酸镁咀嚼片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '多潘立酮片','10mg×30片','西安杨森','western','片','口服','tid',18.50,260) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='多潘立酮片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '蒙脱石散','3g×10袋','博福-益普生','western','袋','冲服','tid',16.80,300) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='蒙脱石散');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '双歧杆菌三联活菌胶囊','210mg×24粒','海斯制药','western','粒','口服','tid',39.00,200) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='双歧杆菌三联活菌胶囊');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '氨氯地平片','5mg×28片','辉瑞','western','片','口服','qd',42.00,180) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='氨氯地平片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '硝苯地平控释片','30mg×7片','拜新同','western','片','口服','qd',36.00,150) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='硝苯地平控释片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '美托洛尔缓释片','47.5mg×7片','阿斯利康','western','片','口服','qd',33.00,120) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='美托洛尔缓释片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '阿托伐他汀钙片','20mg×7片','辉瑞','western','片','口服','qn',48.00,140) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='阿托伐他汀钙片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '二甲双胍缓释片','0.5g×30片','中美华东','western','片','口服','bid',21.00,300) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='二甲双胍缓释片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '格列美脲片','2mg×15片','赛诺菲','western','片','口服','qd',35.00,100) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='格列美脲片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '氯雷他定片','10mg×6片','上海先灵','western','片','口服','qd',19.90,350) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='氯雷他定片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '西替利嗪滴剂','10ml','UCB','western','瓶','口服','qd',36.00,120) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='西替利嗪滴剂');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '氨溴索口服溶液','100ml','上海勃林格','western','瓶','口服','tid',23.50,260) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='氨溴索口服溶液');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '复方甘草片','100片','太极集团','western','片','口服','tid',9.50,300) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='复方甘草片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '连花清瘟胶囊','0.35g×24粒','以岭药业','western','粒','口服','tid',13.80,500) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='连花清瘟胶囊');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '板蓝根颗粒','10g×20袋','白云山','western','袋','冲服','tid',11.00,400) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='板蓝根颗粒');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '开塞露','20ml','信谊','western','支','外用','prn',2.50,600) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='开塞露');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '左氧氟沙星片','0.5g×7片','第一三共','western','片','口服','qd',28.00,160) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='左氧氟沙星片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '甲钴胺片','0.5mg×100片','卫材','western','片','口服','tid',25.00,180) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='甲钴胺片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '维生素C片','0.1g×100片','华中医药','western','片','口服','tid',6.00,500) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='维生素C片');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '地塞米松磷酸钠注射液','5mg×1支','遂成药业','western','支','肌注','qd',3.20,200) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='地塞米松磷酸钠注射液');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '0.9%氯化钠注射液','250ml','科伦药业','western','瓶','静滴','qd',4.50,300) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='0.9%氯化钠注射液');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '阿莫西林克拉维酸钾分散片','0.375g×10片','华北制药','western','片','口服','q8h',34.00,150) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='阿莫西林克拉维酸钾分散片');

-- 20 种常用中药
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '金银花','10g/袋','安国药材','chinese','g','煎服','qd',0.12,1000) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='金银花');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '连翘','10g/袋','安国药材','chinese','g','煎服','qd',0.10,1000) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='连翘');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '甘草','10g/袋','安国药材','chinese','g','煎服','qd',0.08,1000) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='甘草');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '黄芪','10g/袋','内蒙黄芪','chinese','g','煎服','qd',0.15,1000) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='黄芪');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '当归','10g/袋','岷县当归','chinese','g','煎服','qd',0.20,800) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='当归');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '党参','10g/袋','山西党参','chinese','g','煎服','qd',0.16,800) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='党参');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '白术','10g/袋','浙江白术','chinese','g','煎服','qd',0.14,800) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='白术');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '茯苓','10g/袋','云南茯苓','chinese','g','煎服','qd',0.12,1000) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='茯苓');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '半夏','10g/袋','甘肃半夏','chinese','g','煎服','qd',0.18,600) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='半夏');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '陈皮','10g/袋','新会陈皮','chinese','g','煎服','qd',0.10,800) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='陈皮');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '川芎','10g/袋','四川川芎','chinese','g','煎服','qd',0.15,600) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='川芎');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '白芍','10g/袋','亳州白芍','chinese','g','煎服','qd',0.13,800) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='白芍');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '熟地黄','10g/袋','怀庆地黄','chinese','g','煎服','qd',0.17,600) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='熟地黄');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '麦冬','10g/袋','浙八味','chinese','g','煎服','qd',0.22,500) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='麦冬');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '黄芩','10g/袋','河北黄芩','chinese','g','煎服','qd',0.14,700) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='黄芩');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '板蓝根','10g/袋','安国药材','chinese','g','煎服','qd',0.09,800) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='板蓝根');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '薄荷','10g/袋','亳州薄荷','chinese','g','煎服','qd',0.11,500) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='薄荷');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '桔梗','10g/袋','亳州桔梗','chinese','g','煎服','qd',0.12,600) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='桔梗');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '丹参','10g/袋','山东丹参','chinese','g','煎服','qd',0.13,700) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='丹参');
INSERT INTO drug_dict (name,spec,manufacturer,type,unit,default_usage,default_freq,price,stock) SELECT * FROM (SELECT '酸枣仁','10g/袋','邢台酸枣仁','chinese','g','煎服','qd',0.30,400) t WHERE NOT EXISTS (SELECT 1 FROM drug_dict WHERE name='酸枣仁');

-- 10 项诊疗/检查项目
INSERT INTO procedure_dict (name,type,price) SELECT '血常规','exam',25.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM procedure_dict WHERE name='血常规');
INSERT INTO procedure_dict (name,type,price) SELECT '尿常规','exam',18.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM procedure_dict WHERE name='尿常规');
INSERT INTO procedure_dict (name,type,price) SELECT '心电图','exam',35.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM procedure_dict WHERE name='心电图');
INSERT INTO procedure_dict (name,type,price) SELECT '胸部X光片','exam',120.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM procedure_dict WHERE name='胸部X光片');
INSERT INTO procedure_dict (name,type,price) SELECT '血糖检测','exam',15.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM procedure_dict WHERE name='血糖检测');
INSERT INTO procedure_dict (name,type,price) SELECT '肝功能检查','exam',85.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM procedure_dict WHERE name='肝功能检查');
INSERT INTO procedure_dict (name,type,price) SELECT '针灸治疗','treat',60.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM procedure_dict WHERE name='针灸治疗');
INSERT INTO procedure_dict (name,type,price) SELECT '拔罐','treat',40.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM procedure_dict WHERE name='拔罐');
INSERT INTO procedure_dict (name,type,price) SELECT '补牙','treat',280.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM procedure_dict WHERE name='补牙');
INSERT INTO procedure_dict (name,type,price) SELECT '洗牙','treat',150.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM procedure_dict WHERE name='洗牙');
