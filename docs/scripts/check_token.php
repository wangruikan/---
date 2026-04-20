<?php
/**
 * 检查 Token 是否有效
 * 使用方法: php check_token.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

// 从命令行参数获取 token，或使用默认值
$fullToken = $argv[1] ?? '596|XtV37szARI5rSlznqkFDSyQqD470BZ1Pqu1p84Vb26772bfa';

echo "=== Token 检查工具 ===\n\n";
echo "检查的 Token: {$fullToken}\n\n";

// 解析 token
$parts = explode('|', $fullToken, 2);
if (count($parts) !== 2) {
    echo "❌ Token 格式错误，应该是 {id}|{token}\n";
    exit(1);
}

$tokenId = $parts[0];
$plainTextToken = $parts[1];

echo "Token ID: {$tokenId}\n";
echo "Token 明文: {$plainTextToken}\n\n";

// 查询数据库中的 token 记录
$tokenRecord = DB::table('personal_access_tokens')->where('id', $tokenId)->first();

if (!$tokenRecord) {
    echo "❌ 数据库中不存在 ID 为 {$tokenId} 的 token 记录\n\n";
    
    // 显示最近的 token 记录
    echo "最近的 token 记录:\n";
    $recentTokens = DB::table('personal_access_tokens')
        ->orderBy('id', 'desc')
        ->limit(5)
        ->get(['id', 'tokenable_type', 'tokenable_id', 'name', 'created_at']);
    
    foreach ($recentTokens as $t) {
        echo "  ID: {$t->id}, Type: {$t->tokenable_type}, User: {$t->tokenable_id}, Name: {$t->name}, Created: {$t->created_at}\n";
    }
    exit(1);
}

echo "✅ 找到 token 记录:\n";
echo "  ID: {$tokenRecord->id}\n";
echo "  Tokenable Type: {$tokenRecord->tokenable_type}\n";
echo "  Tokenable ID: {$tokenRecord->tokenable_id}\n";
echo "  Name: {$tokenRecord->name}\n";
echo "  Created: {$tokenRecord->created_at}\n";
echo "  Last Used: {$tokenRecord->last_used_at}\n\n";

// 验证 token 哈希
$hashedToken = hash('sha256', $plainTextToken);
echo "计算的哈希: {$hashedToken}\n";
echo "数据库哈希: {$tokenRecord->token}\n\n";

if ($hashedToken === $tokenRecord->token) {
    echo "✅ Token 哈希验证通过!\n";
} else {
    echo "❌ Token 哈希不匹配!\n";
}

// 检查关联的用户/员工是否存在
if ($tokenRecord->tokenable_type === 'App\\Models\\Employee') {
    $employee = DB::table('employees')->where('id', $tokenRecord->tokenable_id)->first();
    if ($employee) {
        echo "\n✅ 关联的员工存在: {$employee->name} (ID: {$employee->id})\n";
    } else {
        echo "\n❌ 关联的员工不存在!\n";
    }
} elseif ($tokenRecord->tokenable_type === 'App\\Models\\User') {
    $user = DB::table('users')->where('id', $tokenRecord->tokenable_id)->first();
    if ($user) {
        echo "\n✅ 关联的用户存在: {$user->name} (ID: {$user->id})\n";
    } else {
        echo "\n❌ 关联的用户不存在!\n";
    }
}

echo "\n=== 检查完成 ===\n";
