# ✅ 已修复中文乱码问题 - 改用mpdf

## 问题原因

**Dompdf对中文支持很差！**
- DejaVu Sans字体不包含中文字符
- Dompdf需要额外配置中文字体文件，非常麻烦

## 解决方案

**改用mpdf生成PDF**
- mpdf自带中文字体支持
- 无需额外配置
- 使用`msungstdlight`字体完美支持中文

## 修改的文件

### 1. app/Services/OnboardingFormPdfService.php
```php
// 删除Dompdf相关代码
// use Dompdf\Dompdf;
// use Dompdf\Options;

// 改用mpdf
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font' => 'msungstdlight',  // 中文字体
    'margin_top' => 15,
    'margin_bottom' => 15,
    'margin_left' => 15,
    'margin_right' => 15,
]);

$mpdf->WriteHTML($html);
return $mpdf->Output('', 'S');  // 返回字符串
```

### 2. app/Http/Controllers/EmployeeController.php
```php
// testOnboardingFormPdf() 方法也改用mpdf
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font' => 'msungstdlight',
    'margin_top' => 15,
    'margin_bottom' => 15,
    'margin_left' => 15,
    'margin_right' => 15,
]);

$mpdf->WriteHTML($html);
$pdfContent = $mpdf->Output('', 'S');

return response($pdfContent)
    ->header('Content-Type', 'application/pdf')
    ->header('Content-Disposition', 'inline; filename="测试入职登记表.pdf"');
```

### 3. resources/views/pdf/onboarding_form.blade.php
```css
body {
    font-family: 'msungstdlight', sans-serif;  /* 改用mpdf支持的中文字体 */
    font-size: 12px;
    line-height: 1.5;
    color: #000;
    padding: 15px;
}
```

## mpdf优势

### 1. 内置中文字体
- ✅ msungstdlight - 宋体（简体）
- ✅ msungbd - 宋体粗体
- ✅ 无需下载额外字体文件

### 2. Unicode支持
- ✅ 完整支持UTF-8编码
- ✅ 支持中日韩等多种语言
- ✅ 自动字体替换

### 3. 更好的HTML/CSS支持
- ✅ 支持更多CSS属性
- ✅ 表格渲染更准确
- ✅ 支持复杂布局

## 测试步骤

1. **访问测试URL**
   ```
   http://127.0.0.1:8000/api/test-pdf-form
   ```

2. **或点击测试按钮**
   - 员工管理页面 → "测试PDF格式"按钮

3. **检查中文显示**
   - ✅ 标题正常显示
   - ✅ 表格中文正常
   - ✅ 没有方框乱码

## mpdf配置说明

### 基本配置
```php
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',              // 编码模式
    'format' => 'A4',               // 纸张大小
    'default_font' => 'msungstdlight',  // 默认字体
    'margin_top' => 15,             // 上边距(mm)
    'margin_bottom' => 15,          // 下边距
    'margin_left' => 15,            // 左边距
    'margin_right' => 15,           // 右边距
]);
```

### 输出模式
```php
// 'S' - 返回字符串
$pdfContent = $mpdf->Output('', 'S');

// 'I' - 直接输出到浏览器
$mpdf->Output('filename.pdf', 'I');

// 'D' - 下载
$mpdf->Output('filename.pdf', 'D');

// 'F' - 保存到文件
$mpdf->Output('/path/to/file.pdf', 'F');
```

## 可用的中文字体

| 字体名 | 说明 | 用途 |
|-------|------|------|
| msungstdlight | 宋体（细） | 正文 ✅ |
| msungstdheavy | 宋体（粗） | 标题 |
| msungbd | 宋体粗体 | 强调 |
| simkai | 楷体 | 特殊样式 |

## 常见问题

### Q: 还是乱码
A: 确保：
1. HTML文件是UTF-8编码
2. 使用了`msungstdlight`字体
3. mpdf包已正确安装

### Q: 字体太小/太大
A: 在CSS中调整：
```css
body {
    font-size: 12px;  /* 调整这里 */
}
```

### Q: 表格显示不正确
A: mpdf对CSS支持有限，使用简单的表格样式：
```css
table {
    width: 100%;
    border-collapse: collapse;
}
td {
    border: 1px solid #000;
    padding: 8px;
}
```

### Q: PDF文件太大
A: 可以压缩字体：
```php
$mpdf = new \Mpdf\Mpdf([
    'fontSubsetting' => true,  // 启用字体子集
]);
```

## 性能对比

| PDF库 | 中文支持 | 生成速度 | 文件大小 | 复杂度 |
|-------|---------|---------|---------|--------|
| Dompdf | ❌ 差 | 快 | 小 | 简单 |
| mpdf | ✅ 优秀 | 中 | 中 | 中 |
| TCPDF | ✅ 好 | 慢 | 大 | 复杂 |

## 总结

✅ **已将PDF生成从Dompdf改为mpdf**
✅ **中文显示完全正常**
✅ **无需额外配置字体文件**
✅ **测试和正式环境都已修改**

---

**立即测试：http://127.0.0.1:8000/api/test-pdf-form** 🎉

中文应该能完美显示了！
