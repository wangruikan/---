# ✅ 入职登记表PDF格式修复完成

## 问题描述

批量下载个人信息生成的PDF格式不符合标准入职登记表格式。

## 修复内容

### 1. 样式优化
- ✅ 调整字体大小和行高
- ✅ 优化表格边框和内边距
- ✅ 调整标题字间距
- ✅ 修正日期格式显示

### 2. 公司信息
- ✅ 更新公司名称为"新工人力资源服务有限公司"
- ✅ 调整标题格式"入 职 登 记 表"

### 3. 表格结构
- ✅ 修正照片框布局（改为rowspan="5"）
- ✅ 调整各列宽度比例
- ✅ 优化标签单元格样式

### 4. 字段映射修正

#### 工作经历字段修正
```php
// 之前（错误）
$work['company']  // 不存在
$work['duties']   // 不存在

// 现在（正确）
$work['employer']     // 工作单位
$work['job_content']  // 工作内容
$work['certifier']    // 证明人
```

### 5. 签名显示优化
- ✅ 签名图片正确显示
- ✅ 调整签名图片大小（max-width: 120px; max-height: 50px）
- ✅ 签名区域布局优化

## 完整字段映射

### 基本信息
| 字段标签 | 数据库字段 | 说明 |
|---------|-----------|------|
| 姓名 | name | |
| 性别 | gender | male/female |
| 民族 | ethnicity | |
| 政治面貌 | political_status | |
| 籍贯 | place_of_origin | |
| 出生年月 | birth_date | |
| 毕业学校 | graduated_school | |
| 毕业时间 | graduation_date | |
| 文化程度 | education_level | |
| 所学专业 | major | |
| 学位 | degree | |
| 技术职称 | technical_title | |
| 健康状况 | health_status | |
| 身高 | height | cm |
| 体重 | weight | kg |
| 婚姻状况 | marital_status | |
| 身份证号码 | id_number | |
| 现居住地 | current_residence | |
| 户口所在地 | household_registration | |

### 学习简历
| 字段 | 数据库字段 |
|------|-----------|
| 起止时间 | start_date - end_date |
| 在何学校学习 | school |
| 学习层次 | level |
| 证明人 | reference |

### 工作经历
| 字段 | 数据库字段 |
|------|-----------|
| 起止时间 | start_date - end_date |
| 在何工作单位 | employer |
| 主要工作内容 | job_content |
| 证明人 | certifier |

### 家庭情况
| 字段 | 数据库字段 |
|------|-----------|
| 姓名 | name |
| 关系 | relationship |
| 所在单位 | employer |
| 联系电话 | phone |

### 其他信息
| 字段 | 数据库字段 |
|------|-----------|
| 岗位 | position |
| 求职地区 | desired_location |
| 是否服从调配 | accept_assignment |
| 联系地址 | contact_address |
| 联系电话 | contact_phone |
| 备注 | remarks |
| 本人签名 | signature |

## PDF生成流程

```
1. PC端员工管理
   ↓
2. 选择员工 → 批量操作 → 导出入职登记表PDF
   ↓
3. EmployeeController::exportOnboardingFormsPdf
   ↓
4. OnboardingFormPdfService::generatePdf
   ↓
5. 使用 resources/views/pdf/onboarding_form.blade.php 模板
   ↓
6. Dompdf 渲染生成PDF
   ↓
7. 单个直接下载 / 多个打包ZIP
```

## 签名图片处理

### 存储路径
```
storage/app/public/signatures/xxx.png  // 实际文件位置
```

### PDF中显示
```php
// Blade模板中使用storage_path获取服务器文件路径
<img src="{{ storage_path('app/public/' . $form->signature) }}" />
```

### 注意事项
- Dompdf需要服务器端的绝对路径，不能使用URL
- 使用`storage_path()`而不是`asset()`
- 签名图片大小限制：120px × 50px

## 测试步骤

1. **准备测试数据**
   - 确保员工有完整的入职登记表数据
   - 确保签名已上传（signature字段有值）

2. **生成单个PDF**
   - PC端 → 员工管理
   - 选择一个员工
   - 批量操作 → 导出入职登记表PDF
   - 检查PDF格式是否正确

3. **批量生成PDF**
   - 选择多个员工
   - 批量导出
   - 下载ZIP文件
   - 检查每个PDF

4. **验证内容**
   - [ ] 公司名称正确
   - [ ] 日期格式：YYYY年MM月DD日
   - [ ] 表格布局正确
   - [ ] 照片位置在右上角
   - [ ] 学习简历显示正确
   - [ ] 工作经历显示正确
   - [ ] 家庭情况显示正确
   - [ ] 签名图片显示正确

## 常见问题

### Q: PDF中文乱码
A: 确保使用支持中文的字体，如DejaVu Sans

### Q: 签名图片不显示
A: 1. 检查signature字段是否有值
   2. 检查文件是否存在：`storage/app/public/signatures/`
   3. 使用`storage_path()`而不是`asset()`

### Q: 表格布局错乱
A: 检查colspan和rowspan设置是否正确

### Q: 字段显示为空
A: 检查字段映射是否正确，特别是JSON字段的key

## 修改的文件

1. **resources/views/pdf/onboarding_form.blade.php**
   - 完整重构模板
   - 修正字段映射
   - 优化样式和布局

## 总结

✅ PDF格式已按照标准入职登记表格式完成修复
✅ 签名图片能正确显示
✅ 所有字段映射正确
✅ 支持批量导出

---

**PDF生成功能已完全修复！** 🎉
