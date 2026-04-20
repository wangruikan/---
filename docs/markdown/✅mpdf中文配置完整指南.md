# ✅ mpdf中文配置完整指南

## 最新修改（自动语言检测）

### 配置说明

```php
// 创建mpdf实例，启用自动语言字体检测
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',               // UTF-8模式
    'format' => 'A4',                // A4纸张
    'autoScriptToLang' => true,      // ✅ 自动检测语言
    'autoLangToFont' => true,        // ✅ 自动选择字体
    'margin_top' => 15,
    'margin_bottom' => 15,
    'margin_left' => 15,
    'margin_right' => 15,
]);

// 再次设置（确保生效）
$mpdf->autoScriptToLang = true;
$mpdf->autoLangToFont = true;

$mpdf->WriteHTML($html);
$pdfContent = $mpdf->Output('', 'S');
```

### CSS样式（不指定字体）

```css
body {
    /* 不指定font-family，让mpdf自动选择 */
    font-size: 12px;
    line-height: 1.5;
    color: #000;
    padding: 15px;
}
```

## 测试步骤

### 1. 清除缓存
```bash
php artisan cache:clear
php artisan view:clear
php artisan config:clear
```

### 2. 访问测试URL
```
http://127.0.0.1:8000/api/test-pdf-form
```

### 3. 检查结果
- ✅ 中文正常显示
- ❌ 仍然乱码 → 继续下面的步骤

## 如果还是乱码

### 方案1：检查mpdf中文字体是否存在

运行以下命令检查：
```bash
ls vendor/mpdf/mpdf/ttfonts/ | grep -i sun
```

应该看到类似：
```
Sun-ExtA.ttf
Sun-ExtB.ttf
```

### 方案2：手动指定中文字体

修改模板，在HTML中使用`lang`属性：

```html
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>入职登记表</title>
</head>
<body>
    ...
</body>
</html>
```

### 方案3：在CSS中明确指定可用字体

```css
body {
    font-family: 'Sun-ExtA', 'freeserif', sans-serif;
    font-size: 12px;
}
```

### 方案4：使用Dompdf + 中文字体文件

如果mpdf还是不行，可以切换回Dompdf，但需要添加中文字体：

1. **下载中文字体**（如：思源黑体、微软雅黑）
2. **安装字体到Dompdf**
   ```bash
   cd vendor/dompdf/dompdf
   php load_font.php "SimSun" /path/to/simsun.ttf
   ```
3. **在CSS中使用**
   ```css
   body {
       font-family: 'SimSun', sans-serif;
   }
   ```

## 完整的测试代码

如果上面都不行，运行这个最简单的测试：

```php
// 创建一个最简单的测试路由
Route::get('/test-mpdf-simple', function() {
    try {
        $html = '<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"></head>
<body>
    <h1>测试中文</h1>
    <p>这是一段中文测试文字</p>
    <p>姓名：张三</p>
    <p>部门：技术部</p>
</body>
</html>';
        
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->WriteHTML($html);
        
        return response($mpdf->Output('', 'S'))
            ->header('Content-Type', 'application/pdf');
            
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});
```

访问：`http://127.0.0.1:8000/api/test-mpdf-simple`

## mpdf支持的中文字体列表

| 字体名 | 文件 | 说明 |
|-------|------|------|
| Sun-ExtA | Sun-ExtA.ttf | Sun字体扩展A |
| Sun-ExtB | Sun-ExtB.ttf | Sun字体扩展B |
| freeserif | FreeSerif.ttf | 自由衬线体 |
| freesans | FreeSans.ttf | 自由无衬线体 |

## 调试命令

### 查看mpdf配置
```php
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8']);
dd($mpdf->available_fonts);  // 查看可用字体
```

### 查看生成的HTML
```php
$html = view('pdf.onboarding_form', ['form' => $testForm])->render();
dd($html);  // 检查HTML是否正确
```

### 查看错误日志
```bash
tail -f storage/logs/laravel.log
```

## 常见错误和解决

### 错误1：方框乱码 □□□
**原因**：字体不支持中文字符  
**解决**：启用autoLangToFont

### 错误2：PDF生成失败
**原因**：内存不足或字体缺失  
**解决**：
```php
ini_set('memory_limit', '256M');
```

### 错误3：部分中文显示，部分乱码
**原因**：字体不完整  
**解决**：使用Sun-ExtA和Sun-ExtB字体

## 推荐配置（最终版本）

```php
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font_size' => 12,
    'default_font' => '',  // 留空，让mpdf自动选择
    'autoScriptToLang' => true,
    'autoLangToFont' => true,
    'margin_top' => 15,
    'margin_bottom' => 15,
    'margin_left' => 15,
    'margin_right' => 15,
]);

$mpdf->autoScriptToLang = true;
$mpdf->autoLangToFont = true;
```

HTML模板：
```html
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>入职登记表</title>
    <style>
        body {
            /* 不指定font-family */
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- 你的内容 -->
</body>
</html>
```

---

## 立即测试

访问：**http://127.0.0.1:8000/api/test-pdf-form**

如果还是乱码，请：
1. 查看Laravel日志（storage/logs/laravel.log）
2. 确认mpdf版本（composer show mpdf/mpdf）
3. 提供具体的错误信息

**理论上autoLangToFont应该能自动处理中文！** 🎯
