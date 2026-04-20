<template>
  <div class="file-manager">
    <el-table
      :data="files"
      v-loading="loading"
      border
      style="width: 100%"
    >
      <el-table-column prop="file_name" label="文件名" min-width="200" />
      <el-table-column prop="file_size" label="文件大小" width="120">
        <template #default="{ row }">
          {{ formatFileSize(row.file_size) }}
        </template>
      </el-table-column>
      <el-table-column prop="upload_source" label="上传来源" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="row.upload_source === 'miniprogram' ? 'success' : 'primary'">
            {{ row.upload_source === 'miniprogram' ? '小程序' : 'PC端' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="上传时间" width="170">
        <template #default="{ row }">
          {{ row.created_at ? row.created_at.substring(0,19).replace('T',' ') : '-' }}
        </template>
      </el-table-column>
      <el-table-column label="操作" :width="showUpload ? '250' : '200'" fixed="right">
        <template #default="{ row }">
          <el-button size="small" type="success" link @click="handlePreview(row)">
            <el-icon><View /></el-icon> 预览
          </el-button>
          <el-button size="small" type="primary" link @click="handleDownload(row)">
            <el-icon><Download /></el-icon> 下载
          </el-button>
          <el-button v-if="showDelete" size="small" type="danger" link @click="handleDelete(row)">
            <el-icon><Delete /></el-icon> 删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>
    
    <el-empty v-if="!files || files.length === 0" description="暂无文件" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { View, Download, Delete } from '@element-plus/icons-vue'
import request from '@/api/request'

const props = defineProps({
  // 文件列表
  files: {
    type: Array,
    default: () => []
  },
  // 是否显示上传按钮
  showUpload: {
    type: Boolean,
    default: false
  },
  // 是否显示删除按钮
  showDelete: {
    type: Boolean,
    default: true
  },
  // 下载 API 路径（必须支持 responseType: 'blob'）
  downloadApiPath: {
    type: String,
    required: true
  },
  // 删除 API 路径
  deleteApiPath: {
    type: String,
    default: ''
  },
  // 文件存储路径字段名
  filePathField: {
    type: String,
    default: 'file_path'
  },
  // 文件名字段名
  fileNameField: {
    type: String,
    default: 'file_name'
  },
  // 是否加载中
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['refresh', 'delete'])

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (!bytes) return '-'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

// 预览文件
const handlePreview = (file) => {
  try {
    const filePath = file[props.filePathField]
    if (!filePath) {
      ElMessage.warning('文件路径不存在')
      return
    }
    
    // 生成文件 URL
    const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
    const fileUrl = `${baseURL}/storage/${filePath}`
    
    const filename = file[props.fileNameField].toLowerCase()
    
    // 图片、PDF 等直接在新窗口打开
    if (filename.endsWith('.jpg') || filename.endsWith('.jpeg') || filename.endsWith('.png') || 
        filename.endsWith('.gif') || filename.endsWith('.webp') || filename.endsWith('.pdf')) {
      window.open(fileUrl, '_blank')
      ElMessage.success('正在新窗口打开...')
    } else {
      ElMessage.warning('不支持预览该文件类型，请使用下载功能')
    }
  } catch (error) {
    console.error('预览失败:', error)
    ElMessage.error('预览失败')
  }
}

// 下载文件
const handleDownload = async (file) => {
  try {
    if (!file.id) {
      ElMessage.warning('文件ID不存在')
      return
    }
    
    ElMessage.info('正在下载，请稍候...')
    
    // 使用传入的 API 路径
    const apiPath = props.downloadApiPath.replace(':id', file.id)
    
    // 使用 request 下载，设置 responseType 为 blob
    const response = await request({
      url: apiPath,
      method: 'get',
      responseType: 'blob'
    })
    
    // 创建 Blob - 使用 application/octet-stream 强制下载
    const blob = new Blob([response], { type: 'application/octet-stream' })
    const url = window.URL.createObjectURL(blob)
    
    // 创建下载链接
    const link = document.createElement('a')
    link.href = url
    link.download = file[props.fileNameField] || '文件'
    link.style.display = 'none'
    
    document.body.appendChild(link)
    link.click()
    
    // 清理
    setTimeout(() => {
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    }, 100)
    
    ElMessage.success('下载成功')
  } catch (error) {
    console.error('下载失败:', error)
    ElMessage.error('下载失败')
  }
}

// 删除文件
const handleDelete = async (file) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除文件"${file[props.fileNameField]}"吗？`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
      }
    )
    
    if (props.deleteApiPath) {
      // 使用传入的 API 路径
      const apiPath = props.deleteApiPath.replace(':id', file.id)
      await request({
        url: apiPath,
        method: 'delete'
      })
    }
    
    ElMessage.success('删除成功')
    emit('delete', file)
    emit('refresh')
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  }
}
</script>

<style scoped>
.file-manager {
  width: 100%;
}
</style>
