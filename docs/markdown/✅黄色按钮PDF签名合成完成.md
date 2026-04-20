# ✅ 黄色按钮PDF签名合成完成

## 功能说明

点击**人员档案管理**页面的黄色**"测试PDF格式"**按钮，将自动：
1. ✅ 生成不带签名的PDF
2. ✅ 在前端合成签名图片
3. ✅ 签名位置在"本人签名："后面
4. ✅ 自动下载最终PDF

## 测试步骤

### 1. 打开人员档案管理页面

访问：`http://localhost:8000/employees`

### 2. 点击黄色按钮

找到页面右上角的黄色按钮：**"测试PDF格式"**

### 3. 等待处理

系统会显示：`正在生成带签名的PDF，请稍候...`

### 4. 自动下载

成功后会自动下载：`张三_入职登记表.pdf`

### 5. 检查签名

打开PDF文件，查看最后一页的"本人签名："位置，应该能看到签名图片。

## 签名位置参数

根据你提供的PDF截图，我设置的签名位置：

```javascript
const sigWidth = 150   // 签名宽度：150像素
const sigHeight = 60   // 签名高度：60像素
const sigX = width - sigWidth - 80  // 距离右边80像素
const sigY = 95        // 距离底部95像素
```

**位置：** 在"本人签名："文字的右侧，PDF最后一页的右下角区域

## 如何调整签名位置

如果签名位置不准确，修改 `src/views/Employees/index.vue` 文件的 `handleTestPdf` 方法：

### 向右移动签名
```javascript
const sigX = width - sigWidth - 60  // 减小数值（从80改为60）
```

### 向左移动签名
```javascript
const sigX = width - sigWidth - 100  // 增大数值（从80改为100）
```

### 向上移动签名
```javascript
const sigY = 110  // 增大数值（从95改为110）
```

### 向下移动签名
```javascript
const sigY = 80  // 减小数值（从95改为80）
```

### 调整签名大小

```javascript
const sigWidth = 180   // 增大宽度
const sigHeight = 70   // 增大高度
```

或

```javascript
const sigWidth = 120   // 减小宽度
const sigHeight = 50   // 减小高度
```

## 代码位置

**文件：** `src/views/Employees/index.vue`

**方法：** `handleTestPdf` (约1933行)

**关键代码：**
```javascript
// 6. 在"本人签名："后面添加签名
const sigWidth = 150  // 签名宽度
const sigHeight = 60  // 签名高度
const sigX = width - sigWidth - 80  // 距离右边80
const sigY = 95  // 距离底部95

lastPage.drawImage(signatureImage, {
  x: sigX,
  y: sigY,
  width: sigWidth,
  height: sigHeight,
  opacity: 1
})
```

## 工作流程

```
点击黄色按钮
    ↓
调用 /api/get-pdf-for-merge
    ↓
获取PDF base64 + 签名图片URL
    ↓
前端使用pdf-lib解码PDF
    ↓
下载签名图片
    ↓
将签名嵌入PDF（150x60像素）
    ↓
位置：右下角，"本人签名："后面
    ↓
保存并自动下载
    ↓
文件名：张三_入职登记表.pdf
```

## 完整实现

### 后端API

**路由：** `GET /api/get-pdf-for-merge`

**返回数据：**
```json
{
  "success": true,
  "data": {
    "pdf_base64": "JVBERi0xLjQ...",
    "signature_url": "http://localhost:8000/storage/signatures/xxx.png",
    "employee_name": "张三"
  }
}
```

### 前端处理

**文件：** `src/views/Employees/index.vue`

**库：** pdf-lib（已导入）

**流程：**
1. fetch获取PDF数据
2. PDFDocument.load()加载PDF
3. embedPng()嵌入签名图片
4. drawImage()绘制到指定位置
5. save()保存
6. 自动下载

## 签名图片来源

**当前测试图片：**
```
http://localhost:8000/storage/signatures/69131df2d123e_1762860530.png
```

**实际使用：**
- 从数据库的 `onboarding_forms` 表获取
- 字段：`signature`
- 路径格式：`signatures/文件名.png`

## 优势

✅ **无需后端处理图片** - 避免mpdf图片兼容性问题  
✅ **前端灵活控制** - 可以随时调整位置和大小  
✅ **用户体验好** - 点击按钮直接下载  
✅ **可视化调试** - 浏览器F12可查看日志  

## 调试方法

### 1. 打开浏览器开发者工具

按 `F12` 打开开发者工具

### 2. 切换到Console标签

查看日志输出

### 3. 点击测试按钮

观察输出的日志

### 4. 检查PDF尺寸

控制台会输出：
```
PDF页面尺寸: { width: 595.28, height: 841.89 }
```

### 5. 检查签名位置

根据输出调整坐标

## 常见问题

### Q: 签名位置不对怎么办？

**A:** 修改 `sigX` 和 `sigY` 的值：
```javascript
const sigX = width - sigWidth - 80  // 调整80这个值
const sigY = 95  // 调整95这个值
```

### Q: 签名太小/太大？

**A:** 修改宽度和高度：
```javascript
const sigWidth = 150  // 调整宽度
const sigHeight = 60  // 调整高度
```

### Q: 签名图片不存在？

**A:** 确认图片URL可以访问：
```
http://localhost:8000/storage/signatures/69131df2d123e_1762860530.png
```

在浏览器直接打开这个URL，看是否能看到图片。

### Q: PDF下载后打不开？

**A:** 检查浏览器控制台是否有错误，确认pdf-lib库已正确加载。

## 下一步

如果测试成功，可以：
1. 将此方法应用到实际的员工PDF生成中
2. 从数据库读取员工的真实签名
3. 批量导出带签名的PDF

## 测试清单

- [ ] 点击黄色"测试PDF格式"按钮
- [ ] 看到"正在生成带签名的PDF"提示
- [ ] PDF自动下载
- [ ] 打开PDF文件
- [ ] 找到最后一页的"本人签名："位置
- [ ] 确认签名图片在正确位置
- [ ] 签名大小合适（150x60像素）
- [ ] 签名清晰可见

## 总结

✅ **黄色按钮已配置** - 点击触发PDF签名合成  
✅ **签名位置已设置** - 在"本人签名："后面  
✅ **签名大小已调整** - 150x60像素  
✅ **自动下载** - 无需手动操作  

---

**现在可以点击黄色按钮测试了！** 🎉

如果位置不对，告诉我需要向哪个方向移动，我会帮你调整坐标！
