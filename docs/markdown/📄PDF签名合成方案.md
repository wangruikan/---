# 📄 PDF签名合成方案

## 方案说明

使用**前端合成**的方式，将签名图片添加到PDF中：

1. ✅ **后端生成不带签名的PDF** 
2. ✅ **后端返回PDF base64 + 签名图片URL**
3. ✅ **前端使用pdf-lib.js合成**
4. ✅ **前端自动下载最终PDF**

## 文件清单

### 1. 后端API接口
- **文件：** `routes/api.php`
- **路由：** `GET /api/get-pdf-for-merge`
- **功能：** 返回不带签名的PDF数据

### 2. 后端控制器
- **文件：** `app/Http/Controllers/EmployeeController.php`
- **方法：** `getPdfForMerge()`
- **返回数据：**
  ```json
  {
    "success": true,
    "data": {
      "pdf_base64": "JVBERi0xLjQ...",
      "signature_url": "http://localhost:8000/storage/signatures/xxx.png",
      "employee_name": "张三"
    },
    "message": "PDF数据获取成功"
  }
  ```

### 3. 前端合成页面
- **文件：** `public/pdf-merge.html`
- **库：** pdf-lib.js (1.17.1)
- **功能：** 
  - 获取PDF数据
  - 下载签名图片
  - 合成PDF和签名
  - 自动下载文件

## 立即测试

### 访问合成页面

打开浏览器：
```
http://localhost:8000/pdf-merge.html
```

### 操作步骤

1. 点击 **"🎨 生成带签名的PDF"** 按钮
2. 等待进度条完成（约2-3秒）
3. 浏览器自动下载 **`张三_入职登记表.pdf`**
4. 打开PDF查看签名（在最后一页右下角）

## 工作流程

### 流程图

```
┌─────────────┐
│  点击按钮   │
└──────┬──────┘
       │
       ↓
┌──────────────────┐
│ 1. 获取PDF数据   │ ← /api/get-pdf-for-merge
└────────┬─────────┘
         │
         ↓
┌──────────────────┐
│ 2. 解码PDF base64│
└────────┬─────────┘
         │
         ↓
┌──────────────────┐
│ 3. 下载签名图片  │
└────────┬─────────┘
         │
         ↓
┌──────────────────┐
│ 4. 嵌入签名图片  │
└────────┬─────────┘
         │
         ↓
┌──────────────────┐
│ 5. 保存并下载PDF │
└──────────────────┘
```

### 详细步骤

#### 1. 获取PDF数据
```javascript
const response = await fetch('/api/get-pdf-for-merge');
const result = await response.json();
```

#### 2. 解码PDF
```javascript
const pdfBytes = Uint8Array.from(
    atob(result.data.pdf_base64), 
    c => c.charCodeAt(0)
);
const pdfDoc = await PDFDocument.load(pdfBytes);
```

#### 3. 下载签名图片
```javascript
const signatureResponse = await fetch(result.data.signature_url);
const signatureArrayBuffer = await signatureResponse.arrayBuffer();
```

#### 4. 嵌入签名
```javascript
const signatureImage = await pdfDoc.embedPng(signatureArrayBuffer);

const pages = pdfDoc.getPages();
const lastPage = pages[pages.length - 1];

lastPage.drawImage(signatureImage, {
    x: 425,  // X坐标
    y: 110,  // Y坐标
    width: 120,
    height: 50
});
```

#### 5. 下载PDF
```javascript
const modifiedPdfBytes = await pdfDoc.save();
const blob = new Blob([modifiedPdfBytes], { type: 'application/pdf' });
const url = URL.createObjectURL(blob);

const link = document.createElement('a');
link.href = url;
link.download = '张三_入职登记表.pdf';
link.click();
```

## 签名位置说明

### PDF坐标系统

- **原点：** 左下角 (0, 0)
- **X轴：** 从左到右
- **Y轴：** 从下到上
- **A4尺寸：** 595 × 842 points

### 当前签名位置

```javascript
const sigWidth = 120;   // 签名宽度
const sigHeight = 50;   // 签名高度
const sigX = 425;       // X坐标：距离左边425
const sigY = 110;       // Y坐标：距离底部110
```

**位置：** 右下角

### 调整签名位置

如果需要改变签名位置，修改坐标：

```javascript
// 右上角
const sigX = width - sigWidth - 70;
const sigY = height - sigHeight - 70;

// 左下角
const sigX = 70;
const sigY = 110;

// 居中
const sigX = (width - sigWidth) / 2;
const sigY = (height - sigHeight) / 2;
```

## 优势

### ✅ 可靠性高
- 不依赖mpdf对图片的支持
- 前端pdf-lib库非常稳定
- 支持所有现代浏览器

### ✅ 灵活性强
- 可以自由调整签名位置
- 可以添加多个签名
- 可以添加其他元素（文字、图片等）

### ✅ 用户体验好
- 实时进度显示
- 自动下载文件
- 美观的UI界面

### ✅ 易于维护
- 前后端分离
- 代码结构清晰
- 容易调试

## 常见问题

### Q: 签名图片不显示怎么办？

**A:** 确认签名图片URL可以访问：
```
http://localhost:8000/storage/signatures/69131df2d123e_1762860530.png
```

在浏览器中直接打开这个URL，看是否能看到图片。

### Q: PDF下载后打开是空白的？

**A:** 检查浏览器控制台是否有错误信息：
- 按F12打开开发者工具
- 切换到Console标签
- 查看红色错误信息

### Q: 签名位置不对怎么调整？

**A:** 修改 `pdf-merge.html` 中的坐标：
```javascript
const sigX = 425;  // 调整这个值（X坐标）
const sigY = 110;  // 调整这个值（Y坐标）
```

### Q: 能否添加多个签名？

**A:** 可以！在第8步之后再添加：
```javascript
// 第一个签名
lastPage.drawImage(signatureImage, {
    x: 425, y: 110,
    width: 120, height: 50
});

// 第二个签名
lastPage.drawImage(anotherSignature, {
    x: 100, y: 110,
    width: 120, height: 50
});
```

### Q: 如何在不同页面添加签名？

**A:** 获取指定页面：
```javascript
const firstPage = pages[0];  // 第1页
const secondPage = pages[1]; // 第2页
```

## 集成到Vue项目

### 1. 安装依赖

```bash
npm install pdf-lib
```

### 2. 创建组件

```vue
<template>
  <div>
    <el-button 
      type="primary" 
      @click="generatePdf"
      :loading="loading">
      生成带签名的PDF
    </el-button>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { PDFDocument } from 'pdf-lib';
import { ElMessage } from 'element-plus';

const loading = ref(false);

const generatePdf = async () => {
  loading.value = true;
  
  try {
    // 1. 获取PDF数据
    const response = await fetch('/api/get-pdf-for-merge');
    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.message);
    }
    
    // 2. 解码PDF
    const pdfBytes = Uint8Array.from(
      atob(result.data.pdf_base64), 
      c => c.charCodeAt(0)
    );
    const pdfDoc = await PDFDocument.load(pdfBytes);
    
    // 3. 下载签名图片
    const signatureResponse = await fetch(result.data.signature_url);
    const signatureArrayBuffer = await signatureResponse.arrayBuffer();
    
    // 4. 嵌入签名
    const signatureImage = await pdfDoc.embedPng(signatureArrayBuffer);
    const pages = pdfDoc.getPages();
    const lastPage = pages[pages.length - 1];
    const { width } = lastPage.getSize();
    
    lastPage.drawImage(signatureImage, {
      x: width - 190,
      y: 110,
      width: 120,
      height: 50
    });
    
    // 5. 保存并下载
    const modifiedPdfBytes = await pdfDoc.save();
    const blob = new Blob([modifiedPdfBytes], { type: 'application/pdf' });
    const url = URL.createObjectURL(blob);
    
    const link = document.createElement('a');
    link.href = url;
    link.download = `${result.data.employee_name}_入职登记表.pdf`;
    link.click();
    
    ElMessage.success('PDF生成成功');
    
  } catch (error) {
    console.error(error);
    ElMessage.error('PDF生成失败: ' + error.message);
  } finally {
    loading.value = false;
  }
};
</script>
```

## 技术栈

- **后端：** Laravel + mpdf
- **前端：** pdf-lib.js 1.17.1
- **PDF库：** https://pdf-lib.js.org/

## 测试清单

- [ ] 访问 `http://localhost:8000/pdf-merge.html`
- [ ] 点击生成按钮
- [ ] 查看进度条动画
- [ ] 确认PDF自动下载
- [ ] 打开PDF文件
- [ ] 检查签名是否在右下角
- [ ] 检查签名是否清晰
- [ ] 检查PDF内容是否完整

## 总结

✅ **后端：** 生成不带签名的PDF，返回base64  
✅ **前端：** 使用pdf-lib.js合成签名  
✅ **测试页面：** `http://localhost:8000/pdf-merge.html`  
✅ **API接口：** `/api/get-pdf-for-merge`  
✅ **签名位置：** 最后一页右下角

---

**现在可以完美地在前端合成PDF和签名了！** 🎉

**立即体验：** http://localhost:8000/pdf-merge.html
