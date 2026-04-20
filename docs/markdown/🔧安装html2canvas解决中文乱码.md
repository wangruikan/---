# 🔧 安装 html2canvas 解决中文乱码

## 问题
PDF中文显示乱码，因为jsPDF默认不支持中文字体。

## 解决方案
使用 `html2canvas` 将HTML渲染为图片，然后插入PDF中。这样可以完美显示中文！

---

## 📦 安装步骤

### 1. 打开终端（PowerShell）

### 2. 进入项目目录
```powershell
cd E:\project\re_li_zi_yuan\re_li_zi_yuan(1)\re_li_zi_yuan
```

### 3. 安装 html2canvas
```powershell
npm install html2canvas
```

### 4. 等待安装完成
看到类似这样的提示就成功了：
```
+ html2canvas@1.4.1
added 1 package
```

### 5. 刷新浏览器
按 **Ctrl + F5** 强制刷新

---

## ✅ 完成后测试

1. 进入"汇总申请"页面
2. 点击"发起流程"
3. 保存后点击"填写表格生成PDF"
4. 填写表单并生成PDF
5. 打开PDF查看 - **中文应该正常显示了！**

---

## 🎯 原理

```
表单数据 
  ↓
隐藏的HTML区域（使用系统中文字体）
  ↓
html2canvas 转换为图片
  ↓
jsPDF 将图片插入PDF
  ↓
完美的中文PDF ✅
```

---

现在去安装吧！

