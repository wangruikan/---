<template>
  <div class="employee-document-manager">
    <div class="header">
      <h4>资料上传管理</h4>
      <el-alert type="info" :closable="false" show-icon style="margin: 10px 0;">
        <template #title>
          员工可在小程序中上传资料，PC端可查看、上传和重新上传。每个资料类型支持上传多个文件。
        </template>
      </el-alert>
    </div>

    <el-table
      :data="documents"
      v-loading="loading"
      border
      style="width: 100%"
    >
      <el-table-column prop="project_name" label="所属项目" width="120" />
      <el-table-column prop="document_name" label="资料名称" width="120" />
      <el-table-column label="文件类型" width="90" align="center">
        <template #default="{ row }">
          <el-tag :type="getDocumentTypeTagType(row.document_type)" size="small">
            {{ row.document_type_text }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="是否必填" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="row.is_required ? 'danger' : 'info'" size="small">
            {{ row.is_required ? '必填' : '选填' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="上传状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="row.uploaded ? 'success' : 'warning'" size="small">
            {{ row.uploaded ? `已上传(${row.file_count || 1})` : '未上传' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="已上传文件" min-width="280">
        <template #default="{ row }">
          <div v-if="row.files && row.files.length > 0" class="files-list">
            <div v-for="(file, index) in row.files" :key="file.id" class="file-item">
              <div class="file-info">
                <el-icon><Document /></el-icon>
                <span class="filename" :title="file.original_filename">{{ file.original_filename }}</span>
                <span class="file-meta">{{ file.file_size_formatted }}</span>
                <el-tag size="small" :type="file.upload_source === 'miniapp' ? 'success' : 'primary'">
                  {{ file.upload_source === 'miniapp' ? '小程序' : 'PC端' }}
                </el-tag>
              </div>
              <div class="file-actions">
                <el-button type="primary" link size="small" @click="handlePreview(file, row)">
                  <el-icon><View /></el-icon>
                </el-button>
                <el-button type="success" link size="small" @click="handleDownload(file)">
                  <el-icon><Download /></el-icon>
                </el-button>
                <el-button type="danger" link size="small" @click="handleDeleteFile(file, row)">
                  <el-icon><Delete /></el-icon>
                </el-button>
              </div>
            </div>
          </div>
          <div v-else class="no-upload">
            <el-text type="info">等待上传</el-text>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="100" align="center" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" size="small" @click="handleUpload(row)">
            <el-icon><Upload /></el-icon>
            上传
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 图片预览对话框 -->
    <el-dialog
      v-model="showPreviewDialog"
      title="资料预览"
      width="800px"
    >
      <div class="preview-container">
        <img v-if="previewFileType === 'image'" :src="previewFileUrl" alt="预览" style="max-width: 100%; height: auto;" />
        <iframe v-else-if="previewFileType === 'pdf'" :src="previewFileUrl" style="width: 100%; height: 600px; border: none;"></iframe>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Document, Clock, Files, View, Download, Upload, Delete } from '@element-plus/icons-vue'
import { getEmployeeDocuments, downloadEmployeeDocument, uploadEmployeeDocument, deleteEmployeeDocument } from '@/api/projectDocuments'

const props = defineProps({
  employeeId: {
    type: Number,
    required: true
  }
})

const loading = ref(false)
const documents = ref([])
const showPreviewDialog = ref(false)
const previewFileUrl = ref('')
const previewFileType = ref('') // 'image' or 'pdf'

watch(() => props.employeeId, (newVal) => {
  if (newVal) {
    loadDocuments()
  }
})

onMounted(() => {
  if (props.employeeId) {
    loadDocuments()
  }
})

const loadDocuments = async () => {
  loading.value = true
  try {
    const response = await getEmployeeDocuments(props.employeeId)
    if (response.success) {
      documents.value = response.data || []
    }
  } catch (error) {
    console.error('加载员工资料失败:', error)
    ElMessage.error('加载员工资料失败')
  } finally {
    loading.value = false
  }
}

const handlePreview = (file, row) => {
  if (!file || !file.file_url) {
    ElMessage.warning('文件不存在')
    return
  }

  const filename = file.original_filename.toLowerCase()
  
  // 图片在对话框中预览
  if (filename.endsWith('.jpg') || filename.endsWith('.jpeg') || filename.endsWith('.png') || filename.endsWith('.gif') || filename.endsWith('.webp')) {
    previewFileUrl.value = file.file_url
    previewFileType.value = 'image'
    showPreviewDialog.value = true
  } 
  // PDF、Word、Excel直接使用文件URL在浏览器中打开
  else if (filename.endsWith('.pdf') || filename.endsWith('.doc') || filename.endsWith('.docx') || filename.endsWith('.xls') || filename.endsWith('.xlsx') || filename.endsWith('.bin')) {
    window.open(file.file_url, '_blank')
    ElMessage.success('正在新窗口打开文档...')
  } 
  else {
    ElMessage.warning('不支持预览该文件类型，请使用下载功能')
  }
}

const handleDownload = async (file) => {
  if (!file) {
    ElMessage.warning('文件不存在')
    return
  }

  try {
    ElMessage.info('正在下载，请稍候...')

    const response = await downloadEmployeeDocument(props.employeeId, file.id)

    // 创建 Blob URL
    const blob = new Blob([response], { type: 'application/octet-stream' })
    const url = window.URL.createObjectURL(blob)

    // 创建下载链接
    const link = document.createElement('a')
    link.href = url
    link.download = file.original_filename
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

const handleDeleteFile = async (file, row) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除文件"${file.original_filename}"吗？`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
      }
    )
    
    const response = await deleteEmployeeDocument(props.employeeId, file.id)
    if (response.success) {
      ElMessage.success('删除成功')
      loadDocuments()
    } else {
      ElMessage.error(response.message || '删除失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  const date = new Date(dateTime)
  return date.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  })
}

const getDocumentTypeTagType = (type) => {
  const types = {
    image: 'success',
    pdf: 'warning',
    document: 'primary',
    all: 'info'
  }
  return types[type] || 'info'
}

// 上传文件（支持多选）
const handleUpload = (row) => {
  // 创建文件input元素
  const input = document.createElement('input')
  input.type = 'file'
  input.multiple = true // 支持多选
  
  // 根据文档类型设置accept
  const acceptTypes = getAcceptTypes(row.document_type)
  if (acceptTypes) {
    input.accept = acceptTypes
  }
  
  input.onchange = async (e) => {
    const files = Array.from(e.target.files)
    if (!files.length) return
    
    // 检查每个文件大小（10MB）
    for (const file of files) {
      if (file.size > 10 * 1024 * 1024) {
        ElMessage.error(`文件"${file.name}"大小超过10MB限制`)
        return
      }
    }
    
    loading.value = true
    let successCount = 0
    let failCount = 0
    
    // 逐个上传文件
    for (const file of files) {
      try {
        const formData = new FormData()
        formData.append('file', file)
        formData.append('document_config_id', row.config_id)
        formData.append('upload_source', 'pc')
        
        const response = await uploadEmployeeDocument(props.employeeId, formData)
        
        if (response.success) {
          successCount++
        } else {
          failCount++
          console.error(`上传${file.name}失败:`, response.message)
        }
      } catch (error) {
        failCount++
        console.error(`上传${file.name}失败:`, error)
      }
    }
    
    loading.value = false
    
    if (successCount > 0) {
      ElMessage.success(`成功上传 ${successCount} 个文件${failCount > 0 ? `，${failCount} 个失败` : ''}`)
      loadDocuments() // 刷新列表
    } else {
      ElMessage.error('上传失败')
    }
  }
  
  input.click()
}

// 获取文件类型accept
const getAcceptTypes = (documentType) => {
  const types = {
    image: 'image/jpeg,image/jpg,image/png,image/gif,image/webp',
    pdf: 'application/pdf',
    document: 'application/pdf,.doc,.docx,.xls,.xlsx',
    all: 'image/*,application/pdf,.doc,.docx,.xls,.xlsx'
  }
  return types[documentType] || ''
}

// 暴露刷新方法给父组件
defineExpose({
  loadDocuments
})
</script>

<style scoped>
.employee-document-manager {
  margin: 20px 0;
  padding: 20px;
  background-color: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.header {
  margin-bottom: 15px;
}

.header h4 {
  margin: 0 0 10px 0;
  font-size: 16px;
  color: #303133;
}

.files-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.file-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  background-color: #f5f7fa;
  border-radius: 4px;
  border: 1px solid #e4e7ed;
}

.file-info {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.file-info .el-icon {
  font-size: 16px;
  color: #409eff;
  flex-shrink: 0;
}

.filename {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 13px;
  color: #303133;
}

.file-meta {
  font-size: 12px;
  color: #909399;
  flex-shrink: 0;
}

.file-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  margin-left: 10px;
}

.no-upload {
  text-align: center;
  padding: 8px 0;
}

.preview-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 400px;
}
</style>
