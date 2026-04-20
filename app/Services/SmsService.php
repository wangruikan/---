<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * 短信服务类
 * 支持腾讯云短信服务
 */
class SmsService
{
    /**
     * 发送短信
     * 
     * @param string $phone 手机号
     * @param string $templateId 短信模板ID
     * @param array $params 模板参数
     * @return array
     */
    public static function send($phone, $templateId, $params = [])
    {
        try {
            // 检查配置
            if (!env('SMS_ENABLED', false)) {
                Log::info('短信功能未启用', ['phone' => $phone]);
                return [
                    'success' => false,
                    'message' => '短信功能未启用'
                ];
            }

            $secretId = env('TENCENT_SMS_SECRET_ID');
            $secretKey = env('TENCENT_SMS_SECRET_KEY');
            $sdkAppId = env('TENCENT_SMS_SDK_APP_ID');
            $signName = env('TENCENT_SMS_SIGN_NAME');

            if (!$secretId || !$secretKey || !$sdkAppId || !$signName) {
                Log::error('短信配置不完整');
                return [
                    'success' => false,
                    'message' => '短信配置不完整'
                ];
            }

            // 调用腾讯云短信API
            $result = self::sendTencentSms($phone, $templateId, $params, $secretId, $secretKey, $sdkAppId, $signName);

            Log::info('短信发送结果', [
                'phone' => $phone,
                'template' => $templateId,
                'result' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('短信发送异常', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '短信发送失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 发送合同待签署通知短信（提交合同时发送）
     * 
     * @param string $phone 手机号
     * @param string $employeeName 员工姓名
     * @param string $contractType 合同类型
     * @return array
     */
    public static function sendContractPendingNotice($phone, $employeeName, $contractType = '')
    {
        $templateId = env('TENCENT_SMS_TEMPLATE_CONTRACT_PENDING');
        
        // 腾讯云短信模板参数是数组格式，按顺序传递
        // 例如：模板内容为 "{1}您好，您有一份{2}待签署，请及时登录小程序查看并签署。"
        $params = [
            $employeeName,  // {1}
            $contractType ?: '合同'  // {2}
        ];

        return self::send($phone, $templateId, $params);
    }

    /**
     * 调用腾讯云短信API
     * 
     * @param string $phone 手机号
     * @param string $templateId 模板ID
     * @param array $params 模板参数
     * @param string $secretId SecretId
     * @param string $secretKey SecretKey
     * @param string $sdkAppId SDK AppId
     * @param string $signName 签名内容
     * @return array
     */
    private static function sendTencentSms($phone, $templateId, $params, $secretId, $secretKey, $sdkAppId, $signName)
    {
        // 腾讯云短信API地址
        $apiUrl = 'https://sms.tencentcloudapi.com/';
        
        // 当前时间戳
        $timestamp = time();
        
        // 请求体
        $payload = [
            'PhoneNumberSet' => ['+86' . $phone],  // 手机号需要加国际区号
            'SmsSdkAppId' => $sdkAppId,
            'TemplateId' => $templateId,
            'TemplateParamSet' => $params,  // 模板参数数组
            'SignName' => $signName,
        ];
        
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        // 生成签名
        $authorization = self::generateTencentSignature($secretId, $secretKey, $payloadJson, $timestamp);
        
        // 发送请求
        try {
            $response = Http::withHeaders([
                'Authorization' => $authorization,
                'Content-Type' => 'application/json',
                'X-TC-Action' => 'SendSms',
                'X-TC-Version' => '2021-01-11',
                'X-TC-Timestamp' => $timestamp,
                'X-TC-Region' => 'ap-guangzhou',
            ])->post($apiUrl, $payload);
            
            $result = $response->json();
            
            // 检查响应
            if (isset($result['Response']['SendStatusSet'][0]['Code']) && 
                $result['Response']['SendStatusSet'][0]['Code'] === 'Ok') {
                return [
                    'success' => true,
                    'message' => '短信发送成功',
                    'data' => $result
                ];
            } else {
                $errorMessage = $result['Response']['SendStatusSet'][0]['Message'] ?? 
                               $result['Response']['Error']['Message'] ?? 
                               '短信发送失败';
                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'data' => $result
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '短信发送请求失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 生成腾讯云API签名（TC3-HMAC-SHA256）
     * 
     * @param string $secretId
     * @param string $secretKey
     * @param string $payload
     * @param int $timestamp
     * @return string
     */
    private static function generateTencentSignature($secretId, $secretKey, $payload, $timestamp)
    {
        $service = 'sms';
        $host = 'sms.tencentcloudapi.com';
        $algorithm = 'TC3-HMAC-SHA256';
        $date = gmdate('Y-m-d', $timestamp);
        
        // 1. 拼接规范请求串
        $httpRequestMethod = 'POST';
        $canonicalUri = '/';
        $canonicalQueryString = '';
        $canonicalHeaders = "content-type:application/json\nhost:" . $host . "\n";
        $signedHeaders = 'content-type;host';
        $hashedRequestPayload = hash('SHA256', $payload);
        $canonicalRequest = $httpRequestMethod . "\n" .
                           $canonicalUri . "\n" .
                           $canonicalQueryString . "\n" .
                           $canonicalHeaders . "\n" .
                           $signedHeaders . "\n" .
                           $hashedRequestPayload;
        
        // 2. 拼接待签名字符串
        $credentialScope = $date . '/' . $service . '/tc3_request';
        $hashedCanonicalRequest = hash('SHA256', $canonicalRequest);
        $stringToSign = $algorithm . "\n" .
                       $timestamp . "\n" .
                       $credentialScope . "\n" .
                       $hashedCanonicalRequest;
        
        // 3. 计算签名
        $secretDate = hash_hmac('SHA256', $date, 'TC3' . $secretKey, true);
        $secretService = hash_hmac('SHA256', $service, $secretDate, true);
        $secretSigning = hash_hmac('SHA256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('SHA256', $stringToSign, $secretSigning);
        
        // 4. 拼接 Authorization
        $authorization = $algorithm . ' ' .
                        'Credential=' . $secretId . '/' . $credentialScope . ', ' .
                        'SignedHeaders=' . $signedHeaders . ', ' .
                        'Signature=' . $signature;
        
        return $authorization;
    }
}
