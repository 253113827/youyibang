<?php
// AI服务API - 集成Claude Opus 4.5和中医知识库
require_once '../vendor/autoload.php';

class AIAPIService {
    private $claudeApiKey = 'your_claude_api_key_here'; // 实际使用时替换为真实API密钥
    
    public function handleRequest($method, $parts) {
        switch ($method) {
            case 'POST':
                if (count($parts) === 2 && $parts[1] === 'analyze') {
                    return $this->analyzeSymptoms();
                } elseif (count($parts) === 2 && $parts[1] === 'food-analysis') {
                    return $this->analyzeFood();
                } elseif (count($parts) === 2 && $parts[1] === 'tcm-advice') {
                    return $this->getTcmAdvice();
                }
                break;
        }
        
        http_response_code(404);
        return ['error' => 'Endpoint not found'];
    }
    
    private function analyzeSymptoms() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 模拟AI分析结果（实际应调用Claude API）
        $symptoms = $data['symptoms'] ?? '';
        $patientInfo = $data['patientInfo'] ?? [];
        
        // 基于症状的中医辨证分析
        $diagnosis = $this->getDiagnosisFromSymptoms($symptoms);
        $medication = $this->getMedicationSuggestions($diagnosis);
        $lifestyle = $this->getLifestyleSuggestions($diagnosis);
        $food = $this->getFoodSuggestions($diagnosis);
        
        return [
            'success' => true,
            'analysis' => [
                'diagnosis' => [
                    'title' => '初步诊断建议',
                    'content' => $diagnosis,
                    'confidence' => 85
                ],
                'medication' => [
                    'title' => '用药建议',
                    'content' => $medication,
                    'confidence' => 92
                ],
                'lifestyle' => [
                    'title' => '生活调养建议',
                    'content' => $lifestyle,
                    'confidence' => 78
                ],
                'food' => [
                    'title' => '食疗推荐',
                    'content' => $food,
                    'confidence' => 88
                ]
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function analyzeFood() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $foods = $data['foods'] ?? [];
        $totalCalories = 0;
        $nutrients = ['protein' => 0, 'fat' => 0, 'carbs' => 0];
        
        // 模拟食物营养分析（实际应调用食物数据库）
        foreach ($foods as $food) {
            $calories = $this->getFoodCalories($food);
            $totalCalories += $calories;
            
            $nutrient = $this->getFoodNutrients($food);
            $nutrients['protein'] += $nutrient['protein'];
            $nutrients['fat'] += $nutrient['fat'];
            $nutrients['carbs'] += $nutrient['carbs'];
        }
        
        // 中医食疗建议
        $tcmAdvice = $this->getTcmFoodAdvice($foods);
        
        return [
            'success' => true,
            'analysis' => [
                'totalCalories' => $totalCalories,
                'nutrients' => $nutrients,
                'foods' => $foods,
                'tcmAdvice' => $tcmAdvice
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getTcmAdvice() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $condition = $data['condition'] ?? '';
        $constitution = $data['constitution'] ?? '平和质';
        
        $advice = [
            'diet' => [],
            'exercise' => [],
            'lifestyle' => []
        ];
        
        // 根据体质和病症生成中医建议
        switch ($constitution) {
            case '平和质':
                $advice['diet'] = ['饮食宜清淡易消化', '多吃新鲜蔬菜水果', '适量蛋白质摄入'];
                $advice['exercise'] = ['规律运动，每周3-5次', '太极拳、八段锦等传统功法', '避免过度劳累'];
                $advice['lifestyle'] = ['保持心情舒畅', '规律作息，早睡早起', '避免熬夜'];
                break;
            case '气虚质':
                $advice['diet'] = ['多吃补气食物：山药、黄芪', '避免生冷寒凉', '适量温补'];
                $advice['exercise'] = ['温和运动为主', '避免剧烈运动', '散步、太极'];
                $advice['lifestyle'] = ['注意保暖', '避免过度思虑', '保证充足睡眠'];
                break;
            default:
                $advice['diet'] = ['根据具体体质调整', '咨询专业中医师', '个性化食疗方案'];
                $advice['exercise'] = ['适度运动', '循序渐进', '因人制宜'];
                $advice['lifestyle'] = ['调整生活习惯', '保持良好心态', '定期复诊'];
        }
        
        // 病症特定建议
        if (strpos($condition, '胃炎') !== false) {
            $advice['diet'][] = '慢性胃炎：饮食清淡，避免辛辣刺激';
            $advice['lifestyle'][] = '饭后适当活动，避免立即躺卧';
        }
        
        return [
            'success' => true,
            'advice' => $advice,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // 辅助方法
    private function getDiagnosisFromSymptoms($symptoms) {
        if (strpos($symptoms, '胃痛') !== false || strpos($symptoms, '消化不良') !== false) {
            return '脾胃虚弱证，主要表现为食欲不振、腹胀便溏、神疲乏力';
        } elseif (strpos($symptoms, '失眠') !== false || strpos($symptoms, '心烦') !== false) {
            return '肝郁化火证，主要表现为烦躁易怒、失眠多梦、口苦咽干';
        } else {
            return '需进一步详细问诊，建议面诊确认辨证分型';
        }
    }
    
    private function getMedicationSuggestions($diagnosis) {
        if (strpos($diagnosis, '脾胃虚弱') !== false) {
            return '党参15g、白术12g、茯苓12g、甘草6g，加陈皮9g、砂仁6g，健脾益气';
        } elseif (strpos($diagnosis, '肝郁化火') !== false) {
            return '柴胡12g、黄芩9g、白芍12g、甘草6g,加龙胆草6g、栀子9g，疏肝泻火';
        } else {
            return '根据具体辨证分型开具处方，建议由执业中医师面诊后确定';
        }
    }
    
    private function getLifestyleSuggestions($diagnosis) {
        if (strpos($diagnosis, '脾胃虚弱') !== false) {
            return '饮食宜清淡易消化，避免生冷油腻；规律作息，避免过度劳累；适当运动如散步、太极拳';
        } else {
            return '保持心情舒畅，避免情绪波动；规律作息，保证充足睡眠；适度运动，增强体质';
        }
    }
    
    private function getFoodSuggestions($diagnosis) {
        if (strpos($diagnosis, '脾胃虚弱') !== false) {
            return '山药粥、薏米红豆汤、莲子百合羹，适合脾胃虚弱体质；避免生冷寒凉食物';
        } else {
            return '根据体质选择合适食物，春季宜养肝，可食用菠菜、韭菜等时令蔬菜';
        }
    }
    
    private function getFoodCalories($food) {
        $calories = [
            '米饭' => 130,
            '面条' => 110,
            '瘦肉' => 143,
            '鱼' => 116,
            '蔬菜' => 25,
            '水果' => 50,
            '鸡蛋' => 155,
            '牛奶' => 42
        ];
        
        foreach ($calories as $name => $calorie) {
            if (strpos($food, $name) !== false) {
                return $calorie;
            }
        }
        
        return 100; // 默认值
    }
    
    private function getFoodNutrients($food) {
        return [
            'protein' => mt_rand(5, 20),
            'fat' => mt_rand(2, 15),
            'carbs' => mt_rand(10, 50)
        ];
    }
    
    private function getTcmFoodAdvice($foods) {
        $advice = [];
        
        foreach ($foods as $food) {
            if (strpos($food, '菠菜') !== false) {
                $advice[] = '菠菜：养血止血，适合春季食用，但脾胃虚寒者不宜多食';
            } elseif (strpos($food, '韭菜') !== false) {
                $advice[] = '韭菜：温中行气，助阳散寒，适合阳虚体质，阴虚火旺者慎用';
            } elseif (strpos($food, '枸杞') !== false) {
                $advice[] = '枸杞：滋补肝肾，明目益精，适合肝肾不足者，感冒发热期间不宜';
            } elseif (strpos($food, '山药') !== false) {
                $advice[] = '山药：健脾益肺，补肾固精，适合脾胃虚弱、肺肾两虚者';
            }
        }
        
        if (empty($advice)) {
            $advice[] = '建议根据个人体质选择合适食物，春季养生以养肝为主';
        }
        
        return $advice;
    }
}

$aiService = new AIAPIService();
echo json_encode($aiService->handleRequest($_SERVER['REQUEST_METHOD'], $parts));
?>