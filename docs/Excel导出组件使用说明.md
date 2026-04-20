# Excel 导出组件使用说明

## 概述

`useExcelExport` 是一个通用的 Excel 导出组合式函数（Composable），封装了导出 Excel 的常用逻辑，可以在任何 Vue 组件中复用。

## 文件位置

```
src/composables/useExcelExport.js
```

## 基本用法

### 1. 在组件中导入

```vue
<script setup>
import { useExcelExport } from '@/composables/useExcelExport'

const { exportToExcel, exporting } = useExcelExport()
</script>
```

### 2. 添加导出按钮

```vue
<template>
  <el-button 
    type="success" 
    :icon="Download" 
    @click="handleExport" 
    :loading="exporting"
  >
    导出Excel
  </el-button>
</template>
```

### 3. 实现导出函数

```javascript
const handleExport = async () => {
  await exportToExcel({
    url: '/api/your-export-endpoint',
    params: {
      // 你的筛选参数
      start_date: '2025-01-01',
      end_date: '2025-12-31'
    },
    filename: '导出数据.xlsx'
  })
}
```

## API 说明

### exportToExcel(options)

导出 Excel 文件的主函数。

#### 参数 options

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| url | string | 是 | - | 导出接口地址 |
| params | object | 否 | {} | 请求参数（筛选条件等） |
| filename | string | 否 | 自动生成 | 下载的文件名 |
| method | string | 否 | 'get' | 请求方法（get/post） |

#### 返回值

返回一个 Promise，导出成功时 resolve，失败时 reject。

### exporting

一个响应式的布尔值，表示当前是否正在导出。可用于：
- 按钮的 loading 状态
- 禁用其他操作
- 显示加载提示

## 完整示例

### 发票汇总导出示例

```vue
<template>
  <div class="invoice-summary-page">
    <div class="page-header">
      <h1>发票汇总</h1>
      <el-button 
        type="success" 
        :icon="Download" 
        @click="handleExport" 
        :loading="exporting"
      >
        导出Excel
      </el-button>
    </div>

    <!-- 筛选表单 -->
    <el-form :inline="true" :model="searchForm">
      <el-form-item label="申请日期">
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
        />
      </el-form-item>
      
      <el-form-item label="项目名称">
        <el-input v-model="searchForm.project_name" />
      </el-form-item>
      
      <el-form-item>
        <el-button type="primary" @click="handleSearch">查询</el-button>
      </el-form-item>
    </el-form>

    <!-- 数据表格 -->
    <el-table :data="tableData" />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Download } from '@element-plus/icons-vue'
import { useExcelExport } from '@/composables/useExcelExport'

const { exportToExcel, exporting } = useExcelExport()

const dateRange = ref([])
const searchForm = reactive({
  project_name: ''
})

const handleExport = async () => {
  // 构建导出参数（与查询参数一致）
  const params = {
    project_name: searchForm.project_name
  }
  
  if (dateRange.value && dateRange.value.length === 2) {
    params.start_date = dateRange.value[0]
    params.end_date = dateRange.value[1]
  }
  
  // 调用导出
  await exportToExcel({
    url: '/invoice-summaries/export',
    params,
    filename: `发票汇总_${new Date().getTime()}.xlsx`
  })
}
</script>
```

## 后端接口要求

### 1. 返回类型

后端接口必须返回 Excel 文件的二进制数据，并设置正确的响应头：

```php
// Laravel 示例
public function export(Request $request)
{
    // ... 生成 Excel 文件
    
    $fileName = '发票汇总_' . date('YmdHis') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . urlencode($fileName) . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
}
```

### 2. 响应头

必须设置以下响应头：

- `Content-Type`: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
- `Content-Disposition`: `attachment;filename="文件名.xlsx"`
- `Cache-Control`: `max-age=0`

### 3. 参数处理

后端应该接收并处理前端传递的筛选参数：

```php
public function export(Request $request)
{
    $query = Model::query();
    
    // 应用筛选条件
    if ($request->has('start_date')) {
        $query->where('date', '>=', $request->start_date);
    }
    
    if ($request->has('end_date')) {
        $query->where('date', '<=', $request->end_date);
    }
    
    if ($request->has('project_name')) {
        $query->where('project_name', 'like', '%' . $request->project_name . '%');
    }
    
    $data = $query->get();
    
    // 生成 Excel...
}
```

## 使用 PhpSpreadsheet 生成 Excel

推荐使用 PhpSpreadsheet 库生成 Excel 文件：

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

public function export(Request $request)
{
    // 获取数据
    $data = $this->getData($request);
    
    // 创建 Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('数据导出');
    
    // 设置表头
    $headers = ['序号', '名称', '金额', '日期'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }
    
    // 设置表头样式
    $sheet->getStyle('A1:D1')->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E0E0E0']
        ]
    ]);
    
    // 填充数据
    $row = 2;
    foreach ($data as $index => $item) {
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $item->name);
        $sheet->setCellValue('C' . $row, $item->amount);
        $sheet->setCellValue('D' . $row, $item->date);
        $row++;
    }
    
    // 自动调整列宽
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // 生成文件名
    $fileName = '数据导出_' . date('YmdHis') . '.xlsx';
    
    // 创建 Writer
    $writer = new Xlsx($spreadsheet);
    
    // 输出到浏览器
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . urlencode($fileName) . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
}
```

## 错误处理

组件内置了错误处理：

- 自动显示加载提示
- 自动显示成功/失败消息
- 防止重复导出（导出中时点击按钮会提示）
- 自动清理下载链接

## 注意事项

1. **文件名编码**：文件名会自动进行 URL 编码，支持中文文件名
2. **超时设置**：默认超时时间为 60 秒，适合大数据量导出
3. **内存管理**：导出完成后会自动清理 Blob URL，避免内存泄漏
4. **并发控制**：同一时间只能进行一次导出操作
5. **响应类型**：必须设置 `responseType: 'blob'`，这在组件内部已处理

## 扩展用法

### 使用 POST 方法

```javascript
await exportToExcel({
  url: '/api/export',
  method: 'post',
  params: {
    ids: [1, 2, 3, 4, 5],
    filters: { ... }
  }
})
```

### 动态文件名

```javascript
const handleExport = async () => {
  const month = searchForm.period || new Date().toISOString().slice(0, 7)
  
  await exportToExcel({
    url: '/invoice-summaries/export',
    params: searchForm,
    filename: `发票汇总_${month}.xlsx`
  })
}
```

### 导出前确认

```javascript
const handleExport = async () => {
  try {
    await ElMessageBox.confirm('确定要导出当前筛选条件下的数据吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'info'
    })
    
    await exportToExcel({
      url: '/api/export',
      params: searchForm
    })
  } catch (error) {
    // 用户取消
  }
}
```

## 已应用的页面

- 发票汇总（`/invoice-summary`）
- 出款汇总（`/payment-summaries`）

## 相关文件

- 组合式函数：`src/composables/useExcelExport.js`
- 示例页面：`src/views/InvoiceSummary/index.vue`
- 后端控制器：`app/Http/Controllers/InvoiceSummaryController.php`
