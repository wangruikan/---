# 🔧 修复 - 移除 status 字段查询

## ❌ 错误信息

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'status' in 'where clause'
SQL: select * from `users` where `account_set_id` = 1 
     and `status` = active 
     and `approval_level` in (2, 3, 4)
```

## 🔍 问题原因

`users` 表中没有 `status` 字段，但代码中尝试查询该字段。

## ✅ 解决方案

### 修改前
```php
$approvers = \App\Models\User::where('account_set_id', $accountSetId)
    ->where('status', 'active')  // ❌ users 表没有 status 字段
    ->whereIn('approval_level', [2, 3, 4])
    ->orderBy('approval_level')
    ->get();
```

### 修改后
```php
$approvers = \App\Models\User::where('account_set_id', $accountSetId)
    ->whereIn('approval_level', [2, 3, 4])  // ✅ 只查询审批级别
    ->orderBy('approval_level')
    ->get();
```

## 📝 修改位置

### 文件：`app/Http/Controllers/InvoiceApplicationController.php`

**位置1**：`submit()` 方法（第614-617行）
**位置2**：`resubmit()` 方法（第754-757行）

## ✅ 验证

现在重新提交发票申请，应该可以成功创建审批流程了！

---

**完成时间**：2025-11-02  
**状态**：✅ 已修复

