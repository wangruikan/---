# ✅ 字段名修复 - row_number 改为 seq_number

## ❌ 问题

执行 SQL 时报错：

```
1064 - You have an error in your SQL syntax
```

**原因**：`row_number` 是 MySQL 的保留关键字（用于窗口函数）

---

## ✅ 解决方案

将字段名从 `row_number` 改为 `seq_number`

---

## 📝 已修复的文件

### 1. SQL 脚本

#### `update_salaries_table_structure.sql`
```sql
-- 修改前
ALTER TABLE salaries 
ADD COLUMN row_number INT NULL COMMENT '序号' AFTER id;

-- 修改后
ALTER TABLE salaries 
ADD COLUMN seq_number INT NULL COMMENT '序号' AFTER id;
```

#### `create_salaries_table.sql`
```sql
-- 已更新为使用 seq_number
CREATE TABLE IF NOT EXISTS salaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seq_number INT NULL COMMENT '序号',
    ...
```

---

### 2. 后端代码

#### `app/Http/Controllers/SalaryController.php`

**generate() 方法**：
```php
// 修改前
$rowNumber = 1;
'row_number' => $rowNumber++,

// 修改后
$seqNumber = 1;
'seq_number' => $seqNumber++,
```

**details() 方法**：
```php
// 修改前
'row_number' => $salary->row_number,

// 修改后
'seq_number' => $salary->seq_number,
```

---

### 3. 前端代码

#### `src/views/Salaries/index.vue`

```vue
<!-- 修改前 -->
<el-table-column prop="row_number" label="序号" />

<!-- 修改后 -->
<el-table-column prop="seq_number" label="序号" />
```

---

## 🚀 立即执行

### 步骤1：执行更新的 SQL

现在可以正常执行了：

```sql
-- 1. 添加岗位字段到员工表
SOURCE add_position_to_employees.sql;

-- 2. 更新工资表结构（已修复）
SOURCE update_salaries_table_structure.sql;
```

---

### 步骤2：验证字段

```sql
-- 查看 salaries 表结构
DESC salaries;

-- 应该看到：
-- seq_number | int | YES | | NULL |
```

---

## ✅ 完成

现在所有文件都使用 `seq_number` 字段，不会再有 MySQL 语法错误！

### 字段对照

| 旧字段名 | 新字段名 | 说明 |
|----------|----------|------|
| `row_number` ❌ | `seq_number` ✅ | 序号字段 |

---

## 📚 MySQL 保留关键字说明

常见的保留关键字（不能直接用作字段名）：
- `row_number` - 窗口函数
- `rank` - 窗口函数
- `order` - ORDER BY
- `group` - GROUP BY
- `select` - SELECT
- `from` - FROM
- `where` - WHERE
- `table` - CREATE TABLE

**解决办法**：
1. ✅ **推荐**：使用其他名称（如 `seq_number`）
2. ⚠️ 使用反引号：`` `row_number` ``（不推荐，容易忘记）

---

现在可以正常执行 SQL 了！🎉

