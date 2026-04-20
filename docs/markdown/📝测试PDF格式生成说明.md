# 📝 测试PDF格式生成说明

## 测试方式

### 方式1：PC端界面测试（推荐）

1. **打开员工管理页面**
   - 访问：http://127.0.0.1:8080/#/employees
   - 或从菜单：人员管理 → 人员档案管理

2. **点击测试按钮**
   - 在搜索栏右侧找到 **"测试PDF格式"** 按钮（黄色）
   - 点击后会自动在新窗口打开生成的PDF

### 方式2：直接访问URL

在浏览器中直接访问：
```
http://127.0.0.1:8000/api/employees/test-pdf
```

## 测试数据

测试PDF包含完整的模拟数据：

### 基本信息
- 姓名：张三
- 性别：男
- 民族：汉
- 身份证：430281200401096273
- 学历：本科
- 专业：计算机科学与技术

### 学习简历（2条）
1. 2006.09-2009.06 深圳市第一中学 高中
2. 2009.09-2012.06 深圳大学 本科

### 工作经历（2条）
1. 2012.07-2018.06 深圳科技有限公司 软件开发
2. 2018.07-2024.12 腾讯科技有限公司 高级工程师

### 家庭情况（3条）
1. 李四 配偶 深圳市人民医院
2. 张五 父亲 退休
3. 王六 母亲 退休

## PDF格式特点

### 1. 标准格式
- ✅ 公司名称：新工人力资源服务有限公司
- ✅ 标题：入 职 登 记 表（字间距加大）
- ✅ 日期格式：2025年10月31日

### 2. 表格布局
- ✅ 基本信息表（照片框在右上角）
- ✅ 学习简历表（带rowspan）
- ✅ 工作经历表（带rowspan）
- ✅ 家庭情况表（带rowspan）
- ✅ 其他信息表

### 3. 样式特点
- ✅ 标签单元格：灰色背景
- ✅ 边框：黑色实线
- ✅ 字体：14px基础大小
- ✅ 签名区域：底部独立

## 文件位置

### 控制器方法
```php
app/Http/Controllers/EmployeeController.php
→ testOnboardingFormPdf()
```

### 路由
```php
routes/api.php
→ Route::get('/test-pdf', [EmployeeController::class, 'testOnboardingFormPdf']);
```

### PDF模板
```php
resources/views/pdf/onboarding_form.blade.php  // 正式模板
resources/views/pdf/test_onboarding_form.blade.php  // 测试模板
```

## 可能的问题

### 1. 中文乱码
**原因**：字体不支持中文
**解决**：确保Dompdf配置了支持中文的字体

### 2. 404错误
**原因**：路由未正确注册
**解决**：运行 `php artisan route:clear`

### 3. 500错误
**原因**：代码错误或依赖问题
**解决**：查看 `storage/logs/laravel.log`

### 4. PDF无法打开
**原因**：生成失败
**解决**：检查错误日志

## 调试命令

```bash
# 清除缓存
php artisan cache:clear
php artisan route:clear
php artisan config:clear

# 查看路由
php artisan route:list | grep test-pdf

# 查看日志
tail -f storage/logs/laravel.log
```

## 正式使用

测试通过后，正式的入职登记表PDF生成：

1. **单个员工**
   - 选择一个员工
   - 批量操作 → 导出入职登记表PDF

2. **批量导出**
   - 选择多个员工
   - 批量操作 → 导出入职登记表PDF
   - 生成ZIP包含所有PDF

## 下一步

如果测试PDF格式不满意，需要调整：

1. **修改模板样式**
   - 编辑 `resources/views/pdf/onboarding_form.blade.php`
   - 调整CSS样式

2. **修改表格结构**
   - 调整colspan/rowspan
   - 调整列宽度

3. **修改字段映射**
   - 确保字段名称正确
   - 检查数据格式

---

**点击"测试PDF格式"按钮即可立即查看效果！** 🎉
