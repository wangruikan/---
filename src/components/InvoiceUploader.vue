<template>
  <div class="invoice-uploader">
    <el-form-item :label="label" :required="required">
      <el-upload
        ref="uploadRef"
        :file-list="fileList"
        :on-change="handleFileChange"
        :on-remove="handleRemove"
        :before-upload="beforeUpload"
        :auto-upload="false"
        :limit="limit"
        :accept="accept"
        multiple
        list-type="text"
      >
        <el-button type="primary" :icon="Upload">
          {{ buttonText }}
        </el-button>
        <template #tip>
          <div class="el-upload__tip">
            {{ tipText }}
          </div>
        </template>
      </el-upload>
    </el-form-item>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Upload } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'

const props = defineProps({
  // 标签文本
  label: {
    type: String,
    default: '选择发票'
  },
  // 是否必填
  required: {
    type: Boolean,
    default: false
  },
  // 按钮文本
  buttonText: {
    type: String,
    default: '选择发票文件'
  },
  // 提示文本
  tipText: {
    type: String,
    default: '支持上传PDF、图片格式的发票文件，单个文件不超过50MB'
  },
  // 文件数量限制
  limit: {
    type: Number,
    default: 10
  },
  // 接受的文件类型
  accept: {
    type: String,
    default: '.pdf,.jpg,.jpeg,.png,.gif'
  },
  // 单个文件大小限制（MB）
  maxSize: {
    type: Number,
    default: 50
  },
  // 初始文件列表
  modelValue: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

const uploadRef = ref(null)
const fileList = ref([...props.modelValue])

// 监听外部传入的文件列表变化
watch(() => props.modelValue, (newVal) => {
  fileList.value = [...newVal]
}, { deep: true })

// 文件选择后的处理
const handleFileChange = (file, uploadFiles) => {
  // 检查文件大小
  const maxSize = props.maxSize * 1024 * 1024
  if (file.size > maxSize) {
    ElMessage.error(`文件 ${file.name} 超过${props.maxSize}MB限制`)
    uploadFiles.splice(uploadFiles.indexOf(file), 1)
    return
  }
  
  fileList.value = uploadFiles
  emit('update:modelValue', uploadFiles)
  emit('change', uploadFiles)
}

// 移除文件
const handleRemove = (file, uploadFiles) => {
  fileList.value = uploadFiles
  emit('update:modelValue', uploadFiles)
  emit('change', uploadFiles)
}

// 上传前的校验
const beforeUpload = (file) => {
  const maxSize = props.maxSize * 1024 * 1024
  if (file.size > maxSize) {
    ElMessage.error(`文件 ${file.name} 超过${props.maxSize}MB限制`)
    return false
  }
  return true
}

// 清空文件列表
const clearFiles = () => {
  fileList.value = []
  emit('update:modelValue', [])
  emit('change', [])
}

// 添加文件（用于外部添加生成的文件）
const addFile = (file) => {
  const fileObj = {
    name: file.name,
    raw: file,
    size: file.size,
    status: 'ready',
    uid: Date.now() + Math.random()
  }
  
  fileList.value.push(fileObj)
  emit('update:modelValue', fileList.value)
  emit('change', fileList.value)
}

// 暴露方法给父组件
defineExpose({
  clearFiles,
  addFile,
  fileList
})
</script>

<style scoped>
.invoice-uploader {
  width: 100%;
}

.el-upload__tip {
  color: #909399;
  font-size: 12px;
  margin-top: 7px;
}
</style>

