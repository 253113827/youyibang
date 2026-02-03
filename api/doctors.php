<?php
// 医生API - 医生信息管理
require_once '../vendor/autoload.php';

class DoctorsAPI {
    private $doctors = [
        [
            'id' => 1,
            'name' => '王医生',
            'gender' => 'male',
            'age' => 42,
            'phone' => '13800138000',
            'licenseNumber' => 'ZY123456789',
            'specialty' => '中医内科',
            'hospital' => '北京中医药大学附属医院',
            'experience' => 12,
            'rating' => 4.8,
            'patients' => 1250,
            'online' => true,
            'availableSlots' => ['2026-02-02 10:00', '2026-02-02 14:00', '2026-02-03 09:00']
        ],
        [
            'id' => 2,
            'name' => '李医生',
            'gender' => 'female',
            'age' => 38,
            'phone' => '13900139000',
            'licenseNumber' => 'ZY987654321',
            'specialty' => '中医妇科',
            'hospital' => '上海中医药大学附属龙华医院',
            'experience' => 8,
            'rating' => 4.9,
            'patients' => 890,
            'online' => false,
            'availableSlots' => ['2026-02-03 15:00', '2026-02-04 10:00']
        ]
    ];
    
    public function handleRequest($method, $parts) {
        switch ($method) {
            case 'GET':
                if (count($parts) === 1) {
                    return $this->getDoctors();
                } elseif (count($parts) === 2 && is_numeric($parts[1])) {
                    return $this->getDoctor($parts[1]);
                }
                break;
            case 'POST':
                if (count($parts) === 1) {
                    return $this->createDoctor();
                }
                break;
            case 'PUT':
                if (count($parts) === 2 && is_numeric($parts[1])) {
                    return $this->updateDoctor($parts[1]);
                }
                break;
            case 'DELETE':
                if (count($parts) === 2 && is_numeric($parts[1])) {
                    return $this->deleteDoctor($parts[1]);
                }
                break;
        }
        
        http_response_code(404);
        return ['error' => 'Endpoint not found'];
    }
    
    private function getDoctors() {
        // 模拟搜索参数
        $search = $_GET['q'] ?? '';
        $specialty = $_GET['specialty'] ?? '';
        
        $result = $this->doctors;
        
        if ($search) {
            $result = array_filter($result, function($doctor) use ($search) {
                return stripos($doctor['name'], $search) !== false || 
                       stripos($doctor['specialty'], $search) !== false ||
                       stripos($doctor['hospital'], $search) !== false;
            });
        }
        
        if ($specialty) {
            $result = array_filter($result, function($doctor) use ($specialty) {
                return stripos($doctor['specialty'], $specialty) !== false;
            });
        }
        
        return [
            'success' => true,
            'data' => array_values($result),
            'total' => count($result)
        ];
    }
    
    private function getDoctor($id) {
        $doctor = array_filter($this->doctors, function($d) use ($id) {
            return $d['id'] == $id;
        });
        
        if (empty($doctor)) {
            http_response_code(404);
            return ['error' => 'Doctor not found'];
        }
        
        return [
            'success' => true,
            'data' => reset($doctor)
        ];
    }
    
    private function createDoctor() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $newDoctor = [
            'id' => count($this->doctors) + 1,
            'name' => $data['name'] ?? '新医生',
            'gender' => $data['gender'] ?? 'unknown',
            'age' => $data['age'] ?? 30,
            'phone' => $data['phone'] ?? '',
            'licenseNumber' => $data['licenseNumber'] ?? '',
            'specialty' => $data['specialty'] ?? '未知',
            'hospital' => $data['hospital'] ?? '未知医院',
            'experience' => $data['experience'] ?? 0,
            'rating' => 0,
            'patients' => 0,
            'online' => false,
            'availableSlots' => []
        ];
        
        $this->doctors[] = $newDoctor;
        
        return [
            'success' => true,
            'message' => 'Doctor created successfully',
            'data' => $newDoctor
        ];
    }
    
    private function updateDoctor($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        foreach ($this->doctors as &$doctor) {
            if ($doctor['id'] == $id) {
                foreach ($data as $key => $value) {
                    if (isset($doctor[$key])) {
                        $doctor[$key] = $value;
                    }
                }
                return [
                    'success' => true,
                    'message' => 'Doctor updated successfully',
                    'data' => $doctor
                ];
            }
        }
        
        http_response_code(404);
        return ['error' => 'Doctor not found'];
    }
    
    private function deleteDoctor($id) {
        foreach ($this->doctors as $index => $doctor) {
            if ($doctor['id'] == $id) {
                unset($this->doctors[$index]);
                $this->doctors = array_values($this->doctors);
                return [
                    'success' => true,
                    'message' => 'Doctor deleted successfully'
                ];
            }
        }
        
        http_response_code(404);
        return ['error' => 'Doctor not found'];
    }
}

$doctorsAPI = new DoctorsAPI();
echo json_encode($doctorsAPI->handleRequest($_SERVER['REQUEST_METHOD'], $parts));
?>