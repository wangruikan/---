# 🔧 修复：员工 project_ids 字段查询

## 问题描述

生成工资表时报错：
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'project_id' in 'where clause'
```

## 根本原因

**数据结构误解**：
- ❌ 原以为：`employees` 表有 `project_id` 字段（单个项目）
- ✅ 实际情况：`employees` 表是 `project_ids` 字段（JSON 数组，多个项目）

一个员工可以同时属于多个项目！

## 解决方案

### 修改前（错误）
```php
$allEmployees = Employee::whereIn('project_id', $projectIds)
    ->where('status', 'active')
    ->get();
```

### 修改后（正确）
```php
$allEmployees = Employee::where('account_set_id', $accountSetId)
    ->where(function($query) use ($projectIds) {
        foreach ($projectIds as $projectId) {
            $query->orWhereJsonContains('project_ids', $projectId);
        }
    })
    ->get()
    ->unique('id');
```

**注意**：
- 去掉了 `->where('status', 'active')`，因为 `employees` 表没有 `status` 字段
- `status` 字段在 `employee_projects` 中间表中，但我们使用的是 `project_ids` JSON 字段

## 关键改进

### 1. JSON 字段查询
使用 `whereJsonContains()` 查询 JSON 数组字段：
```php
$query->orWhereJsonContains('project_ids', $projectId);
```

### 2. 部门名称处理
如果员工属于多个选中的项目，部门名称用顿号连接：
```php
$employeeProjectNames = [];
if (is_array($employee->project_ids)) {
    foreach ($employee->project_ids as $empProjectId) {
        if (in_array($empProjectId, $projectIds)) {
            $project = $allProjects->firstWhere('id', $empProjectId);
            if ($project) {
                $employeeProjectNames[] = $project->name;
            }
        }
    }
}
$departmentName = implode('、', $employeeProjectNames);
```

## 业务场景示例

### 数据结构
```
员工表：
| id  | name | project_ids      |
|-----|------|------------------|
| 101 | 张三 | [1]              |
| 102 | 李四 | [2]              |
| 103 | 王五 | [1, 2]           | ← 同时在2个项目
| 104 | 赵六 | [1, 2, 3]        | ← 同时在3个项目
```

### 查询结果
选择项目 [1, 2] 生成工资表：
```
查到的员工：
- 张三（project_ids: [1]）       → 部门：公园项目
- 李四（project_ids: [2]）       → 部门：老年公寓
- 王五（project_ids: [1, 2]）    → 部门：公园项目、老年公寓
- 赵六（project_ids: [1, 2, 3]） → 部门：公园项目、老年公寓

✅ 每个员工只生成一条工资记录
```

## 测试验证

### 测试 1：单项目员工
- 员工只属于项目A
- 部门显示：项目A

### 测试 2：跨项目员工
- 员工属于项目A和项目B
- 选择项目A、B生成
- 部门显示：项目A、项目B

### 测试 3：部分匹配
- 员工属于项目A、B、C
- 只选择项目A、B生成
- 部门显示：项目A、项目B（不显示C）

## 影响范围

✅ 只修改了 `SalaryController.php` 的 `generate()` 方法
✅ 不影响其他功能
✅ 不需要数据库修改
✅ 向后兼容单项目生成

## 部署步骤

1. 代码已修改完成
2. 清理缓存：
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   ```
3. 直接测试使用

## 完成时间

2024-11-01

