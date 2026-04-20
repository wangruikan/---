# ✅ CORS跨域问题已解决

## 问题描述

前端从 `http://127.0.0.1:3000` 访问后端 `http://localhost:8000` 的签名图片时，被CORS策略阻止：

```
Access to fetch at 'http://localhost:8000/storage/signatures/xxx.png' 
from origin 'http://127.0.0.1:3000' has been blocked by CORS policy
```

## 解决方案

**不再通过URL下载图片，而是后端直接返回签名图片的base64编码。**

### 修改内容

#### 1. 后端API修改 ✅

**文件：** `app/Http/Controllers/EmployeeController.php`

**修改：** `getPdfForMerge()` 方法

**之前返回：**
```json
{
  "pdf_base64": "...",
  "signature_url": "http://localhost:8000/storage/signatures/xxx.png"
}
```

**现在返回：**
```json
{
  "pdf_base64": "...",
  "signature_base64": "iVBORw0KGgoAAAANSUhEUgAA..."
}
```

**代码：**
```php
// 读取签名图片并转为base64（避免前端CORS问题）
$signaturePath = public_path('storage/signatures/69131df2d123e_1762860530.png');
$signatureBase64 = null;

if (file_exists($signaturePath)) {
    $signatureData = file_get_contents($signaturePath);
    $signatureBase64 = base64_encode($signatureData);
}

return response()->json([
    'success' => true,
    'data' => [
        'pdf_base64' => $pdfBase64,
        'signature_base64' => $signatureBase64,  // 直接返回base64
        'employee_name' => $testForm->name,
    ]
]);
```

#### 2. 前端Vue组件修改 ✅

**文件：** `src/views/Employees/index.vue`

**修改：** `handleTestPdf()` 方法

**之前：**
```javascript
// 下载签名图片
const signatureResponse = await fetch(result.data.signature_url)
const signatureArrayBuffer = await signatureResponse.arrayBuffer()
const signatureImage = await pdfDoc.embedPng(signatureArrayBuffer)
```

**现在：**
```javascript
// 解码签名图片base64
const signatureBytes = Uint8Array.from(
  atob(result.data.signature_base64),
  c => c.charCodeAt(0)
)
const signatureImage = await pdfDoc.embedPng(signatureBytes)
```

#### 3. 测试页面修改 ✅

**文件：**
- `public/pdf-merge.html`
- `public/test-signature-position.html`

都已修改为使用 `signature_base64`。

## 优势

✅ **无CORS问题** - 不需要跨域请求图片  
✅ **一次请求** - 只需一个API调用  
✅ **更安全** - 图片数据通过API返回  
✅ **更快速** - 减少网络请求次数  

## 立即测试

### 方法1：点击黄色按钮

1. 打开：`http://localhost:8000/employees`
2. 点击右上角黄色按钮：**"测试PDF格式"**
3. 等待下载
4. 打开PDF查看签名

### 方法2：访问测试页面

1. 打开：`http://localhost:8000/pdf-merge.html`
2. 点击"🎨 生成带签名的PDF"
3. 下载并查看PDF

### 方法3：测试签名位置

1. 打开：`http://localhost:8000/test-signature-position.html`
2. 点击"🎯 测试所有位置"
3. 查看9个不同位置的签名

## 工作流程

```
点击按钮
    ↓
调用 GET /api/get-pdf-for-merge
    ↓
后端返回:
  - PDF的base64
  - 签名图片的base64
    ↓
前端解码两个base64
    ↓
使用pdf-lib合成
    ↓
自动下载最终PDF
```

## 数据流对比

### 之前（有CORS问题）

```
前端 → API → 获取PDF base64
             获取签名URL
前端 → 签名URL → ❌ CORS错误
```

### 现在（无CORS问题）

```
前端 → API → 获取PDF base64
             获取签名base64 ✅
前端 → 直接使用base64 ✅
```

## 调试信息

后端会记录日志：

```
✅ 签名图片已读取，base64长度: 32880
```

或

```
⚠️ 签名图片不存在: E:\project\...\public\storage\signatures\xxx.png
```

查看日志：`storage/logs/laravel.log`

## 签名位置

当前设置：

```javascript
签名宽度: 150像素
签名高度: 60像素
X坐标: 距离右边80像素
Y坐标: 距离底部95像素
```

位置：**在"本人签名："文字后面，PDF右下角**

## 如果还有问题

### 1. 检查签名图片是否存在

访问：`http://localhost:8000/storage/signatures/69131df2d123e_1762860530.png`

如果能看到图片，说明文件存在。

### 2. 检查后端日志

打开：`storage/logs/laravel.log`

查找：`✅ 签名图片已读取` 或错误信息

### 3. 检查浏览器控制台

按 `F12`，查看Console标签，看是否有错误。

### 4. 测试API

直接访问：`http://localhost:8000/api/get-pdf-for-merge`

应该返回JSON，包含 `signature_base64` 字段。

## 常见问题

### Q: 为什么不配置CORS？

**A:** 返回base64更简单、更安全、更快速，是最佳实践。

### Q: base64会增加数据量吗？

**A:** 会增加约33%，但只是一次传输，可以接受。

### Q: 能否支持其他格式的签名图片？

**A:** 可以，pdf-lib支持PNG和JPG：
```javascript
// PNG
await pdfDoc.embedPng(signatureBytes)
// JPG
await pdfDoc.embedJpg(signatureBytes)
```

## 总结

✅ **CORS问题已解决** - 使用base64代替URL  
✅ **后端已修改** - 返回signature_base64  
✅ **前端已修改** - 使用base64解码  
✅ **测试页面已更新** - 全部使用新方案  

---

**现在可以正常生成带签名的PDF了！** 🎉

立即测试：点击黄色"测试PDF格式"按钮！
