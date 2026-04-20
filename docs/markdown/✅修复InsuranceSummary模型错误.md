# 修复 InsuranceSummary 模型错误

## ❌ 错误信息
```
Class "App\Models\InsuranceSummary" not found
```

## 🔍 原因分析

在 `PaymentRequest` 模型中，`insuranceSummary()` 关联方法引用了不存在的 `InsuranceSummary` 模型。

实际上，保险汇总使用的是 `ProcessApproval` 模型。

## ✅ 已修复

**文件：** `app/Models/PaymentRequest.php` (第46-49行)

**修改前：**
```php
public function insuranceSummary()
{
    return $this->belongsTo(InsuranceSummary::class);
}
```

**修改后：**
```php
public function insuranceSummary()
{
    return $this->belongsTo(ProcessApproval::class, 'insurance_summary_id');
}
```

## 🎉 现在请操作

1. **刷新浏览器页面**
2. **进入"付款申请"模块**
3. **查看列表**
   - 应该能正常显示
   - 工资付款申请和保险汇总付款申请都能看到

---

**问题状态**: ✅ 已修复  
**缓存状态**: ✅ 已清除

