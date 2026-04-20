# FileManager 通用文件管理组件

## 功能

- ✅ 文件列表展示
- ✅ 文件预览（图片、PDF）
- ✅ 文件下载
- ✅ 文件删除
- ✅ 文件大小格式化
- ✅ 上传来源标识

## 使用方法

### 1. 导入组件

```vue
<script setup>
import FileManager from '@/components/FileManager.vue'
</script>
```

### 2. 使用组件

```vue
<template>
  <FileManager
    :files="resignationCertificates"
    :loading="loading"
    :show-delete="true"
    download-api-path="/employees/resignation-certificates/:id/download"
    delete-api-path="/employees/resignation-certificates/:id"
    file-path-field="file_path"
    file-name-field="file_name"
    @refresh="loadCertificates"
    @delete="handleDeleteSuccess"
  />
</template>
```

### 3. Props 说明

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| files | Array | 否 | [] | 文件列表数据 |
| loading | Boolean | 否 | false | 是否加载中 |
| showUpload | Boolean | 否 | false | 是否显示上传按钮（预留） |
| showDelete | Boolean | 否 | true | 是否显示删除按钮 |
| downloadApiPath | String | 是 | - | 下载 API 路径，`:id` 会被替换为文件ID |
| deleteApiPath | String | 否 | '' | 删除 API 路径，`:id` 会被替换为文件ID |
| filePathField | String | 否 | 'file_path' | 文件路径字段名 |
| fileNameField | String | 否 | 'file_name' | 文件名字段名 |

### 4. Events 说明

| 事件名 | 参数 | 说明 |
|--------|------|------|
| refresh | - | 需要刷新列表时触发 |
| delete | file | 删除成功后触发，返回被删除的文件对象 |

### 5. 文件数据格式

```javascript
{
  id: 1,                    // 文件ID（必须）
  file_name: '文件名.jpg',   // 文件名
  file_path: 'path/to/file', // 文件路径（相对于 storage）
  file_size: 1024000,        // 文件大小（字节）
  upload_source: 'miniprogram', // 上传来源：miniprogram | pc
  created_at: '2025-12-27T10:00:00' // 上传时间
}
```

## 完整示例

### 离职证明管理

```vue
<template>
  <el-tab-pane label="离职证明" name="resignation">
    <FileManager
      :files="resignationCertificates"
      :loading="certificatesLoading"
      :show-delete="true"
      download-api-path="/employees/resignation-certificates/:id/download"
      delete-api-path="/employees/resignation-certificates/:id"
      @refresh="loadResignationCertificates"
    />
  </el-tab-pane>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import FileManager from '@/components/FileManager.vue'
import request from '@/api/request'

const resignationCertificates = ref([])
const certificatesLoading = ref(false)

const loadResignationCertificates = async () => {
  try {
    certificatesLoading.value = true
    const response = await request({
      url: `/employees/${employeeId}/resignation-certificates`,
      method: 'get'
    })
    resignationCertificates.value = response.data
  } catch (error) {
    console.error('加载失败:', error)
  } finally {
    certificatesLoading.value = false
  }
}

onMounted(() => {
  loadResignationCertificates()
})
</script>
```

## 后端要求

### 1. 下载接口

必须支持返回文件流，并设置正确的响应头：

```php
public function download($id)
{
    $file = File::findOrFail($id);
    $filePath = storage_path('app/public/' . $file->file_path);
    
    if (!file_exists($filePath)) {
        return response()->json(['success' => false, 'message' => '文件不存在'], 404);
    }
    
    $contentType = $file->file_type ?: 'application/octet-stream';
    $fileSize = @filesize($filePath) ?: null;
    
    // 清除输出缓冲
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
    
    // 使用 stream 响应
    return response()->stream(function () use ($filePath) {
        $handle = fopen($filePath, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }
    }, 200, array_filter([
        'Content-Type' => $contentType,
        'Content-Disposition' => 'attachment; filename="' . $file->file_name . '"',
        'Content-Length' => $fileSize,
        'Cache-Control' => 'private, max-age=0, no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
        'Accept-Ranges' => 'bytes',
    ]));
}
```

### 2. 删除接口

```php
public function destroy($id)
{
    $file = File::findOrFail($id);
    
    // 删除文件
    if (Storage::disk('public')->exists($file->file_path)) {
        Storage::disk('public')->delete($file->file_path);
    }
    
    // 删除记录
    $file->delete();
    
    return response()->json(['success' => true, 'message' => '删除成功']);
}
```

## 注意事项

1. **文件路径**：组件假设文件存储在 `storage/app/public/` 目录下，通过 `/storage/` 路径访问
2. **API 路径**：下载和删除 API 路径中的 `:id` 会被自动替换为文件ID
3. **CORS**：确保后端配置了正确的 CORS 设置
4. **权限**：确保用户有权限访问和删除文件

## 扩展

如果需要添加上传功能，可以监听 `upload` 事件并实现上传逻辑。
