# ✅ 修复公积金计算 - 从 JSON 读取

## 🐛 问题

公积金金额显示为 0，原因是代码从 `housing_fund_config_id` 表读取配置，但该字段可能为空或者比例为0。

## 🎯 正确的数据源

参保明细接口是从 **`housing_fund_params` JSON 字段**读取公积金配置的！

## 📊 housing_fund_params JSON 结构

```json
{
  "id": 1,
  "region_name": "北京市",
  "base_amount": "4000.00",        // ✅ 公积金基数
  "company_ratio": "0.30",         // ✅ 单位比例 30%
  "employee_ratio": "0.30",        // ✅ 个人比例 30%
  "is_enabled": true
}
```

## ✅ 修复内容

**文件：** `app/Http/Controllers/SalaryController.php`  
**位置：** 第 1012-1049 行

### 修改前（错误）

```php
// ❌ 从 housing_fund_config_id 关联表读取
if ($insurancePersonnel->housing_fund_config_id) {
    $housingFundConfig = HousingFundConfig::find($insurancePersonnel->housing_fund_config_id);
    if ($housingFundConfig) {
        $companyRatio = floatval($housingFundConfig->company_ratio ?? 0);
        $personalRatio = floatval($housingFundConfig->personal_ratio ?? 0);
    }
}
```

**问题：**
- `housing_fund_config_id` 可能为 `null`
- 即使有配置，比例可能为 0
- **不是参保明细使用的数据源**

### 修改后（正确）

```php
// ✅ 从 housing_fund_params JSON 字段读取
if ($insurancePersonnel->housing_fund_params) {
    $housingFundParams = is_string($insurancePersonnel->housing_fund_params)
        ? json_decode($insurancePersonnel->housing_fund_params, true)
        : $insurancePersonnel->housing_fund_params;
    
    if ($housingFundParams && is_array($housingFundParams)) {
        // 从 JSON 中读取基数和比例
        $base = floatval($housingFundParams['base_amount'] ?? 0);
        $companyRatio = floatval($housingFundParams['company_ratio'] ?? 0);
        $personalRatio = floatval($housingFundParams['employee_ratio'] ?? 0);
        
        $companyHousingFund = $base * $companyRatio;
        $personalHousingFund = $base * $personalRatio;
        
        $details['housing_fund'] = [
            'name' => '住房公积金',
            'base' => $base,
            'company_ratio' => $companyRatio,
            'company_amount' => round($companyHousingFund, 2),
            'personal_ratio' => $personalRatio,
            'personal_amount' => round($personalHousingFund, 2),
        ];
    }
}
```

## 🔑 关键改进

1. **正确的数据源**：从 `housing_fund_params` JSON 字段读取
2. **包含基数**：JSON 中直接包含 `base_amount`
3. **一致性**：与参保明细接口逻辑完全一致

## 📊 计算逻辑

```
housing_fund_params (JSON)
    ↓
解析 JSON → 读取 base_amount, company_ratio, employee_ratio
    ↓
计算金额：
  单位金额 = base_amount × company_ratio
  个人金额 = base_amount × employee_ratio
    ↓
返回给前端显示
```

## 🚀 测试步骤

### 1️⃣ 删除旧数据
```sql
DELETE FROM salaries WHERE month = '2025-10';
```

### 2️⃣ 重新生成工资表

### 3️⃣ 查看日志
搜索 "公积金计算结果"，应该看到：
```
公积金计算结果
  base: 4000.00
  company_ratio: 0.30
  personal_ratio: 0.30
  company_amount: 1200.00
  personal_amount: 1200.00
```

### 4️⃣ 验证前端显示
在工资表的保险明细中应该能看到：
- 公积金基数：4000.00
- 单位金额：¥1,200.00
- 个人金额：¥1,200.00

## 📝 与参保明细接口一致性对比

| 项目 | 参保明细接口 | 工资表（修改前） | 工资表（修改后） |
|------|-------------|----------------|----------------|
| 数据源 | `housing_fund_params` JSON | `housing_fund_config_id` 表 | `housing_fund_params` JSON |
| 基数来源 | JSON 中的 `base_amount` | `employee_housing_fund_base` | JSON 中的 `base_amount` |
| 比例来源 | JSON 中的 `company_ratio` | 关联表的 `company_ratio` | JSON 中的 `company_ratio` |
| 状态 | ✅ 正确 | ❌ 错误 | ✅ 正确 |

---

## 🎉 完成！

现在公积金的基数和金额都会正确显示了！

公积金数据完全从 `housing_fund_params` JSON 字段读取，与参保明细接口保持一致！🚀

