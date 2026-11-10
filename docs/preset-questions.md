<!-- 用途：中医"十问"预设问诊问题集，可直接转成 PHP 数组。每个问题含 id（字段 key）、text（患者可读懂的问题）、group（分类）。 -->

# 中医十问预设问诊问题集（有医帮）

依据中医问诊"十问歌"（问寒热、问汗、问头身、问胸腹、问饮食口味、问睡眠、问二便等）整理，问题措辞通俗化，便于患者理解。每题字段 key 供 PHP 数组与数据库字段使用。

## PHP 数组结构示例

```php
$presetQuestions = [
    // group => [ ['key' => ..., 'text' => ...], ... ]
];
```

## 一、问寒热（cold_heat）

| key | 问题 |
| --- | --- |
| cold_heat_fever | 您最近有没有发烧或自觉发热？体温大概多少度？ |
| cold_heat_chill | 您怕冷吗？是全身怕冷、手脚冰凉，还是吹风后才觉得冷？ |
| cold_heat_alternate | 您有没有一会儿觉得冷、一会儿觉得热（忽冷忽热）的情况？ |
| cold_heat_time | 发热或怕冷一般在什么时间比较明显（如下午、傍晚、夜里）？ |
| cold_heat_relief | 加衣盖被或喝热水后，怕冷的感觉能缓解吗？ |

## 二、问汗（sweat）

| key | 问题 |
| --- | --- |
| sweat_presence | 您平时容易出汗吗？什么情况下出汗（活动、吃饭、睡觉时）？ |
| sweat_night | 您睡觉时会不会出汗，醒来后发现身上有汗（盗汗）？ |
| sweat_location | 出汗主要在哪些部位（头面、手心、胸口、全身）？ |
| sweat_amount | 出汗量多吗？出汗后觉得乏力或心慌吗？ |
| sweat_cold | 有没有出汗时皮肤发凉、汗出后怕冷的情况？ |

## 三、问头身（head_body）

| key | 问题 |
| --- | --- |
| head_body_headache | 您有头痛吗？痛在哪个部位（前额、两侧、头顶、后脑）？ |
| head_body_nature | 头痛是胀痛、刺痛、昏沉感还是一跳一跳地痛？什么时候加重？ |
| head_body_dizzy | 您有头晕吗？是站立时头晕，还是总觉得天旋地转？ |
| head_body_bodypain | 您身上（关节、肌肉、腰背）有酸痛、沉重或麻木的地方吗？ |
| head_body_trigger | 这些不适在劳累后加重，还是天气变化时加重？ |

## 四、问胸腹（chest_abdomen）

| key | 问题 |
| --- | --- |
| chest_abdomen_chest | 您有胸闷、心慌或胸口痛吗？疼痛会放射到别处（如左肩、后背）吗？ |
| chest_abdomen_breath | 您会觉得气短、呼吸费力，稍微活动就喘吗？ |
| chest_abdomen_abdominal | 您腹部有疼痛或胀满感吗？具体在哪个位置（上腹、肚脐周围、下腹）？ |
| chest_abdomen_digest | 您有没有反酸、烧心、嗳气（打饱嗝）或恶心的情况？ |
| chest_abdomen_press | 腹部按上去会痛吗？按着舒服还是更不舒服？ |

## 五、问饮食口味（diet_taste）

| key | 问题 |
| --- | --- |
| diet_taste_appetite | 您最近胃口怎么样？想吃东西吗？ |
| diet_taste_thirst | 您觉得口渴吗？想喝凉水还是热水？ |
| diet_taste_bitter | 您有没有口苦、口甜、口淡无味或口中发黏的感觉？ |
| diet_taste_flavor | 有没有嘴里没味道但想喝水，或喝水不多的情况？ |
| diet_taste_food | 平时饮食偏好吗（喜辣、喜凉、喜油腻）？饭量有无明显变化？ |

## 六、问睡眠（sleep）

| key | 问题 |
| --- | --- |
| sleep_quality | 您的睡眠怎么样？入睡困难、容易醒，还是醒后难以再睡？ |
| sleep_dream | 您睡觉多梦吗？做梦后醒来觉得累吗？ |
| sleep_duration | 您一般睡几个小时？白天会困倦吗？ |
| sleep_sneeze_snore | 您睡觉会打呼噜、说梦话或磨牙吗？ |

## 七、问二便（bowel_urine）

| key | 问题 |
| --- | --- |
| bowel_urine_stool | 您大便正常吗？是干结、稀溏，还是先干后稀？每天几次？ |
| bowel_urine_stool_color | 大便颜色有没有异常（发黑、带血、有黏液）？ |
| bowel_urine_urine | 您小便颜色、次数正常吗？有没有尿频、尿急、尿痛或夜尿多？ |
| bowel_urine_urine_color | 小便颜色偏黄、清长，还是有泡沫？ |
| bowel_urine_difficulty | 排便或排尿时费劲、排不干净的感觉有吗？ |

## 八、问其他（other，可选用）

| key | 问题 |
| --- | --- |
| other_energy | 您最近精神、体力如何？容易累吗？ |
| other_mood | 您情绪怎么样？容易烦躁、生气或情绪低落吗？ |
| other_weight | 您体重最近有变化吗？明显增重或减重？ |
| other_menstruation | （女性）您的月经周期、量、颜色正常吗？有没有痛经？ |
| other_duration | 这些不舒服持续多久了？有没有在吃药或治疗？ |

## 备注

- 以上问题按十问歌常见分类整理，未纳入"问耳目、问渴饮"等细分场景，可按需扩展。
- 每题 key 命名规则为 `group_subitem`，与 PHP 数组下标一致，便于后端入库与前端展示。
- 部分问题需根据患者性别/年龄条件展示（如月经问题），建议前端做条件渲染。
