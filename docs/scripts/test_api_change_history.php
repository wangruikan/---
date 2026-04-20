<?php

/**
 * 测试变更历史 API 接口
 */

// 模拟 HTTP 请求
$employeeId = 58; // 使用测试员工ID
$url = "http://127.0.0.1:8000/api/employees/{$employeeId}/change-history?current_account_set_id=1";

// 获取认证 token（需要先登录）
$loginUrl = "http://127.0.0.1:8000/api/login";
$loginData = json_encode([
    'username' => 'admin',
    'password' => 'admin123'
]);

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$loginResponse = curl_exec($ch);
$loginResult = json_decode($loginResponse, true);

if (!isset($loginResult['token'])) {
    echo "❌ 登录失败\n";
    echo $loginResponse . "\n";
    exit(1);
}

$token = $loginResult['token'];
echo "✓ 登录成功，获取到 token\n\n";

// 调用变更历史 API
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP 状态码: {$httpCode}\n";
echo "响应内容:\n";
echo $response . "\n";

// 尝试解析 JSON
$result = json_decode($response, true);
if ($result) {
    echo "\n✓ JSON 解析成功\n";
    if (isset($result['success']) && $result['success']) {
        echo "✓ API 调用成功\n";
        echo "变更记录数: " . count($result['data']) . "\n";
    } else {
        echo "❌ API 返回失败: " . ($result['message'] ?? '未知错误') . "\n";
    }
} else {
    echo "\n❌ JSON 解析失败，返回的不是 JSON 格式\n";
}
