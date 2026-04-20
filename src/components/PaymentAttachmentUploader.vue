<template>
  <div class="payment-attachment-uploader">
    <!-- 稍后上传选项 -->
    <el-form-item v-if="showUploadLater">
      <el-checkbox v-model="uploadLater" @change="handleUploadLaterChange">
        稍后上传发票或附件
      </el-checkbox>
      <div style="margin-top: 4px; color: #E6A23C; font-size: 12px;">
        勾选即可以稍后在付款申请列表上传，但必须发票当天出
      </div>
    </el-form-item>

    <!-- 发票上传（可选显示） -->
    <template v-if="showInvoiceUpload">
      <el-divider content-position="left">发票上传</el-divider>
      <InvoiceUploader
        ref="invoiceUploaderRef"
        v-model="invoiceFiles"
        label="选择发票"
        button-text="选择发票文件"
        tip-text="支持上传PDF、图片格式的发票文件，单个文件不超过50MB"
        :limit="invoiceLimit"
        accept=".pdf,.jpg,.jpeg,.png,.gif"
      />
    </template>

    <!-- 其他附件 -->
    <el-divider content-position="left">{{ showInvoiceUpload ? '其他附件' : '附件上传' }}</el-divider>
    <el-form-item label="附件上传">
      <div style="margin-bottom: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
        <!-- 生成PDF表格按钮 -->
        <el-button 
          v-if="showFormGenerator"
          type="success" 
          size="small"
          @click="showFormDialog = true"
        >
          <el-icon><DocumentAdd /></el-icon>
          {{ formButtonText }}
        </el-button>
        
        <!-- 签名盖章按钮 -->
        <el-button 
          v-if="showSignatureStamp"
          type="warning" 
          size="small"
          @click="openSignatureDialog"
          :disabled="!hasSignableFiles"
        >
          <el-icon><Edit /></el-icon>
          签名盖章
        </el-button>
      </div>
      
      <el-upload
        ref="uploadRef"
        :auto-upload="false"
        :on-change="handleFileChange"
        :on-remove="handleRemoveFile"
        :file-list="otherFiles"
        :limit="otherLimit"
        multiple
      >
        <el-button type="primary" size="small">选择其他附件</el-button>
        <template #tip>
          <div class="el-upload__tip">
            支持上传PDF、Word、Excel、图片等格式，单个文件不超过50MB（可选）
          </div>
        </template>
      </el-upload>
    </el-form-item>

    <!-- 表格填写生成PDF对话框 -->
    <FormToWordGenerator 
      v-model="showFormDialog" 
      :title="formTitle"
      :invoice-count="invoiceFiles.length"
      :attachment-count="otherFiles.length"
      @word-generated="handleFormGenerated"
    />

    <!-- 签名盖章选择文件对话框 -->
    <el-dialog
      v-if="showSignatureStamp"
      v-model="signatureDialogVisible"
      title="选择要签名盖章的附件"
      width="600px"
    >
      <div v-if="signableFiles.length > 0">
        <el-radio-group v-model="selectedFileForSignature" style="width: 100%;">
          <div v-for="file in signableFiles" :key="file.uid" style="margin-bottom: 10px;">
            <el-radio :label="file.uid" style="width: 100%; padding: 10px; border: 1px solid #dcdfe6; border-radius: 4px;">
              <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <el-icon size="20"><Document /></el-icon>
                  <span>{{ file.name }}</span>
                </div>
                <el-tag size="small" :type="file.isInvoice ? 'warning' : 'success'">
                  {{ file.isInvoice ? '发票' : 'PDF' }}
                </el-tag>
              </div>
            </el-radio>
          </div>
        </el-radio-group>
      </div>
      <el-empty v-else description="没有可签名的PDF文件" :image-size="100" />

      <template #footer>
        <el-button @click="signatureDialogVisible = false">取消</el-button>
        <el-button 
          type="primary" 
          @click="confirmFileForSignature"
          :disabled="!selectedFileForSignature"
        >
          确认并打开签名编辑器
        </el-button>
      </template>
    </el-dialog>

    <!-- PDF签名盖章编辑器 -->
    <el-dialog
      v-if="showSignatureStamp"
      v-model="pdfEditorVisible"
      title="PDF签名盖章"
      width="95%"
      top="5vh"
      :close-on-click-modal="false"
    >
      <PDFSignatureEditor
        v-if="pdfEditorVisible && currentPdfUrl"
        :pdf-url="currentPdfUrl"
        @confirm="handleSignedPdfSave"
        @cancel="pdfEditorVisible = false"
      />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { DocumentAdd, Edit, Document } from '@element-plus/icons-vue'
import InvoiceUploader from './InvoiceUploader.vue'
import FormToWordGenerator from './FormToWordGenerator.vue'
import PDFSignatureEditor from './PDFSignatureEditor.vue'

const props = defineProps({
  // 发票文件列表（v-model）
  invoiceFileList: {
    type: Array,
    default: () => []
  },
  // 其他附件列表（v-model）
  otherFileList: {
    type: Array,
    default: () => []
  },
  // 是否显示发票上传部分
  showInvoiceUpload: {
    type: Boolean,
    default: true
  },
  // 发票上传限制数量
  invoiceLimit: {
    type: Number,
    default: 10
  },
  // 其他附件限制数量
  otherLimit: {
    type: Number,
    default: 5
  },
  // 是否显示表格生成器按钮
  showFormGenerator: {
    type: Boolean,
    default: true
  },
  // 表格生成器按钮文本
  formButtonText: {
    type: String,
    default: '填写表格生成PDF'
  },
  // 表格生成器标题
  formTitle: {
    type: String,
    default: '付款申请表'
  },
  // 是否显示"签名盖章"
  showSignatureStamp: {
    type: Boolean,
    default: true
  },
  // 是否显示"稍后上传"选项
  showUploadLater: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits([
  'update:invoiceFileList',
  'update:otherFileList',
  'files-changed',
  'upload-later-changed'
])

// 内部文件列表
const invoiceFiles = ref([...props.invoiceFileList])
const otherFiles = ref([...props.otherFileList])

// 稍后上传标识
const uploadLater = ref(false)

// 组件引用
const invoiceUploaderRef = ref(null)
const uploadRef = ref(null)

// 表格生成对话框
const showFormDialog = ref(false)

// 签名盖章相关
const signatureDialogVisible = ref(false)
const pdfEditorVisible = ref(false)
const selectedFileForSignature = ref(null)
const currentPdfUrl = ref(null)
const currentFileInfo = ref(null)

// 计算属性：是否有可签名的文件
const hasSignableFiles = computed(() => {
  return signableFiles.value.length > 0
})

// 计算属性：可签名的文件列表（只包含PDF）
const signableFiles = computed(() => {
  const allFiles = []
  
  // 添加发票文件
  invoiceFiles.value.forEach(file => {
    if (file.name && file.name.toLowerCase().endsWith('.pdf')) {
      allFiles.push({
        ...file,
        isInvoice: true
      })
    }
  })
  
  // 添加其他附件
  otherFiles.value.forEach(file => {
    if (file.name && file.name.toLowerCase().endsWith('.pdf')) {
      allFiles.push({
        ...file,
        isInvoice: false
      })
    }
  })
  
  return allFiles
})

// 监听发票文件变化
watch(invoiceFiles, (newVal) => {
  emit('update:invoiceFileList', [...newVal])
  emitFilesChanged()
})

// 监听其他附件变化
watch(otherFiles, (newVal) => {
  emit('update:otherFileList', [...newVal])
  emitFilesChanged()
})

// 监听外部传入的文件列表变化
watch(() => props.invoiceFileList, (newVal) => {
  if (JSON.stringify(newVal) !== JSON.stringify(invoiceFiles.value)) {
    invoiceFiles.value = [...newVal]
  }
})

watch(() => props.otherFileList, (newVal) => {
  if (JSON.stringify(newVal) !== JSON.stringify(otherFiles.value)) {
    otherFiles.value = [...newVal]
  }
})

// 发出文件变化事件
const emitFilesChanged = () => {
  emit('files-changed', {
    invoiceFiles: invoiceFiles.value,
    otherFiles: otherFiles.value,
    allFiles: [...invoiceFiles.value, ...otherFiles.value],
    uploadLater: uploadLater.value
  })
}

// 处理稍后上传选项变化
const handleUploadLaterChange = (value) => {
  emit('upload-later-changed', value)
  emitFilesChanged()
}

// 处理其他附件文件变化
const handleFileChange = (file, fileList) => {
  otherFiles.value = fileList
}

// 移除其他附件文件
const handleRemoveFile = (file, fileList) => {
  otherFiles.value = fileList
}

// 处理表格生成
const handleFormGenerated = ({ file, fileName }) => {
  console.log('PDF文档已生成:', fileName)
  
  // 创建符合 el-upload 格式的文件对象
  const fileObj = {
    name: fileName,
    raw: file,
    size: file.size,
    status: 'ready',
    uid: Date.now(),
    // 添加 URL 用于预览
    url: URL.createObjectURL(file)
  }
  
  // 添加到其他附件列表
  otherFiles.value = [...otherFiles.value, fileObj]
  
  console.log('当前附件列表:', otherFiles.value)
  ElMessage.success('PDF文档已添加到附件列表')
}

// 打开签名盖章对话框
const openSignatureDialog = () => {
  if (signableFiles.value.length === 0) {
    ElMessage.warning('没有可签名的PDF文件')
    return
  }
  selectedFileForSignature.value = null
  signatureDialogVisible.value = true
}

// 确认选择文件并打开编辑器
const confirmFileForSignature = () => {
  if (!selectedFileForSignature.value) {
    ElMessage.warning('请选择要签名的文件')
    return
  }

  // 找到选中的文件
  const selectedFile = signableFiles.value.find(f => f.uid === selectedFileForSignature.value)

  if (!selectedFile) {
    ElMessage.error('未找到选中的文件')
    return
  }

  // 保存当前文件信息
  currentFileInfo.value = selectedFile
  
  // 将File对象转换为URL
  const file = selectedFile.raw || selectedFile
  if (file instanceof File || file instanceof Blob) {
    currentPdfUrl.value = URL.createObjectURL(file)
  } else {
    ElMessage.error('文件格式不正确')
    return
  }

  // 关闭选择对话框，打开编辑器
  signatureDialogVisible.value = false
  pdfEditorVisible.value = true
}

// 处理签名后的PDF保存
const handleSignedPdfSave = async (result) => {
  try {
    const signedPdfBlob = result.pdfBlob
    const originalFile = currentFileInfo.value
    
    if (!originalFile) {
      ElMessage.error('未找到原文件')
      return
    }

    // 创建新的文件对象（带签名的）
    const timestamp = new Date().getTime()
    const originalName = originalFile.name.replace('.pdf', '')
    const signedFileName = `${originalName}_已签名_${timestamp}.pdf`
    
    const signedFile = new File([signedPdfBlob], signedFileName, { type: 'application/pdf' })
    
    const newFileObj = {
      name: signedFileName,
      raw: signedFile,
      size: signedFile.size,
      status: 'ready',
      uid: Date.now()
    }

    // 判断原文件是发票还是其他附件
    if (originalFile.isInvoice) {
      // 替换发票列表中的文件
      const index = invoiceFiles.value.findIndex(f => f.uid === originalFile.uid)
      if (index !== -1) {
        invoiceFiles.value.splice(index, 1, newFileObj)
      }
    } else {
      // 替换其他附件列表中的文件
      const index = otherFiles.value.findIndex(f => f.uid === originalFile.uid)
      if (index !== -1) {
        otherFiles.value.splice(index, 1, newFileObj)
      }
    }

    ElMessage.success('签名盖章完成，已更新附件')
    pdfEditorVisible.value = false
    
    // 清理状态和释放URL
    if (currentPdfUrl.value && currentPdfUrl.value.startsWith('blob:')) {
      URL.revokeObjectURL(currentPdfUrl.value)
    }
    currentPdfUrl.value = null
    currentFileInfo.value = null
    selectedFileForSignature.value = null
  } catch (error) {
    console.error('保存签名PDF失败:', error)
    ElMessage.error('保存签名PDF失败')
  }
}

// 暴露方法给父组件
defineExpose({
  // 清空所有文件
  clearAll: () => {
    invoiceFiles.value = []
    otherFiles.value = []
    uploadLater.value = false
  },
  // 清空发票
  clearInvoices: () => {
    invoiceFiles.value = []
  },
  // 清空其他附件
  clearOthers: () => {
    otherFiles.value = []
  },
  // 获取所有文件
  getAllFiles: () => {
    return [...invoiceFiles.value, ...otherFiles.value]
  },
  // 获取发票文件
  getInvoiceFiles: () => {
    return invoiceFiles.value
  },
  // 获取其他附件
  getOtherFiles: () => {
    return otherFiles.value
  },
  // 获取稍后上传标识
  getUploadLater: () => {
    return uploadLater.value
  },
  // 设置稍后上传标识
  setUploadLater: (value) => {
    uploadLater.value = value
  }
})
</script>

<style scoped>
.payment-attachment-uploader {
  width: 100%;
}

:deep(.el-divider__text) {
  font-weight: 600;
  color: #303133;
}
</style>
