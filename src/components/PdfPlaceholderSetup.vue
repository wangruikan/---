<template>
  <div class="pdf-placeholder-setup">
    <div class="setup-container">
      <!-- 左侧：PDF预览区域 -->
      <div class="pdf-preview-area">
        <div class="pdf-header">
          <h3>PDF模板预览</h3>
          <div class="pdf-controls">
            <span v-if="totalPages > 0" class="page-info">共 {{ totalPages }} 页</span>
            <el-button @click="resetPositions" size="small">重置位置</el-button>
          </div>
        </div>
        
        <div class="pdf-container" ref="pdfContainer">
          <!-- 垂直排列所有页面 -->
          <div v-if="pdfPages.length > 0" class="pdf-pages-wrapper">
            <div 
              v-for="(pageUrl, index) in pdfPages" 
              :key="index"
              class="pdf-page-item"
              :style="{ position: 'relative' }"
            >
              <div class="page-number">第 {{ index + 1 }} 页</div>
              <img 
                :src="pageUrl" 
                :alt="`PDF第${index + 1}页`"
                class="pdf-image"
              />
              
              <!-- 占位符位置标记（显示在对应页面） -->
              <div
                v-for="(placeholder, idx) in placeholderList"
                :key="placeholder.id"
                v-show="placeholder.page === index"
                class="placeholder-marker"
                :class="{ 
                  'selected': selectedPlaceholderIndex === idx,
                  'dragging': isDraggingPlaceholder && selectedPlaceholderIndex === idx,
                  'signature-placeholder': placeholder.type === 'employee_signature'
                }"
                :style="{
                  position: 'absolute',
                  left: placeholder.x + 'px',
                  top: (placeholder.y + 30) + 'px',
                  width: placeholder.width + 'px',
                  height: placeholder.height + 'px',
                  border: placeholder.type === 'employee_signature' ? '2px dashed #E6A23C' : '2px dashed #409EFF',
                  backgroundColor: placeholder.type === 'employee_signature' ? 'rgba(230, 162, 60, 0.15)' : 'rgba(64, 158, 255, 0.1)',
                  cursor: 'move',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontSize: placeholder.type === 'employee_signature' ? '12px' : '10px',
                  color: placeholder.type === 'employee_signature' ? '#E6A23C' : '#409EFF',
                  fontWeight: 'bold',
                  userSelect: 'none'
                }"
                @mousedown="startPlaceholderDrag($event, idx, index)"
                @dblclick="removePlaceholder(idx)"
              >
                {{ getPlaceholderLabel(placeholder.type) }}
                <span class="delete-btn" @click.stop="removePlaceholder(idx)">×</span>
              </div>
            </div>
          </div>
          
          <div v-else-if="props.pdfUrl" class="pdf-placeholder-area">
            <div class="placeholder-instructions">
              <h4>占位符位置设置</h4>
              <p>请将右侧的信息项拖拽到此区域设置位置</p>
              <p>系统会记录坐标，用于自动填充员工信息</p>
              <p><strong>建议设置位置：</strong></p>
              <ul>
                <li>姓名：通常在合同开头</li>
                <li>身份证：在姓名下方或旁边</li>
                <li>手机号：联系方式区域</li>
                <li>地址：住址信息区域</li>
              </ul>
            </div>
          </div>
          
          <div v-else class="no-pdf">
            <el-icon size="48" color="#ccc"><Document /></el-icon>
            <p>请先上传PDF模板</p>
          </div>
        </div>
      </div>
      
      <!-- 右侧：拖拽信息区域 -->
      <div class="drag-info-area">
        <h3>拖拽信息到PDF上</h3>
        <p class="drag-tip">将右侧信息拖拽到PDF上的合适位置</p>
        
        <div class="info-items">
          <div
            v-for="(item, key) in infoItems"
            :key="key"
            class="info-item"
            :class="{ 'selected': selectedPlaceholderKey === key }"
            draggable="true"
            @dragstart="startDrag($event, key)"
            @click="selectPlaceholder(key)"
          >
            <div class="item-label">{{ item.label }}</div>
            <div class="item-value">{{ item.value }}</div>
          </div>
        </div>
        
        <div class="actions">
          <el-button @click="savePositions" type="success">保存位置</el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, nextTick, watch, onMounted, onUnmounted } from 'vue'
// computed 已导入
import { ElMessage } from 'element-plus'
import { Document } from '@element-plus/icons-vue'
import * as pdfjsLib from 'pdfjs-dist'

// 配置PDF.js worker
pdfjsLib.GlobalWorkerOptions.workerSrc = `https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js`

const props = defineProps({
  pdfUrl: {
    type: String,
    default: null
  },
  templateId: {
    type: Number,
    required: true
  },
  savedPositions: {
    type: Array,
    default: () => []
  },
  placeholderFields: {
    type: Array,
    default: () => []
  }
})

// 默认字段配置
const defaultFields = [
  { key: 'name', label: '姓名', example: '王某某' },
  { key: 'id_number', label: '身份证号', example: '4302817226352635' },
  { key: 'phone', label: '手机号', example: '15304758473' },
  { key: 'address', label: '地址', example: 'XXXXXXXX地址' }
]

// 字段样例数据
const fieldExamples = {
  name: '王某某',
  id_number: '430281199001011234',
  phone: '13800138000',
  address: '北京市朝阳区建国路1号',
  gender: '男',
  birth_date: '1990-01-01',
  nationality: '汉族',
  education: '本科',
  position: '工程师',
  employee_number: 'EMP001',
  email: 'example@email.com',
  bank_name: '中国工商银行',
  bank_account: '6222021234567890123',
  bank_account_holder: '王某某',
  hire_date: '2024-01-01',
  contract_start_date: '2024-01-01',
  contract_end_date: '2027-01-01',
  contract_start_year: '2024',
  contract_start_month: '01',
  contract_start_day: '01',
  contract_end_year: '2027',
  contract_end_month: '01',
  contract_end_day: '01',
  emergency_contact: '张某',
  emergency_phone: '13900139000',
  household_address: '湖南省长沙市岳麓区',
  residence_address: '北京市朝阳区',
  contact_address: '北京市朝阳区XX路',
  employee_signature: '[签名]'
}

const emit = defineEmits(['save', 'cancel'])

// 响应式数据
const pdfContainer = ref(null)
const pdfImage = ref(null)
const pdfPages = ref([])  // 存储所有页面的图片URL
const totalPages = ref(0)
const selectedPlaceholderIndex = ref(null)
const isDragging = ref(false)
const draggedItem = ref(null)
const isDraggingPlaceholder = ref(false)
const dragStartPos = ref({ x: 0, y: 0 })
const placeholderStartPos = ref({ x: 0, y: 0 })
const currentDragPage = ref(0)
let placeholderId = 0  // 用于生成唯一ID

// 占位符列表 - 动态数组，可添加多个
const placeholderList = ref([])

// 监听 savedPositions 变化 - 只在模板切换时初始化
watch(() => props.templateId, () => {
  // 模板ID变化时，重新初始化占位符
  placeholderId = 0
  if (props.savedPositions && props.savedPositions.length > 0) {
    placeholderList.value = props.savedPositions.map((pos) => ({
      id: ++placeholderId,
      type: pos.type,
      x: pos.x,
      y: pos.y,
      width: pos.width,
      height: pos.height,
      page: pos.page || 0
    }))
  } else {
    placeholderList.value = []
  }
}, { immediate: true })

// 信息项数据 - 动态生成
const infoItems = computed(() => {
  // 使用项目配置的字段，如果没有则使用默认字段
  const fields = props.placeholderFields && props.placeholderFields.length > 0 
    ? props.placeholderFields 
    : defaultFields
  
  const items = {}
  fields.forEach(field => {
    const key = field.key || field
    const label = field.label || key
    items[key] = {
      label: label,
      value: field.example || fieldExamples[key] || 'XXX'
    }
  })
  return items
})

// 加载PDF并转换为图片（支持多页）
const loadPdfAsImage = async () => {
  if (!props.pdfUrl) return
  
  try {
    console.log('开始加载PDF:', props.pdfUrl)
    
    // 使用PDF.js加载PDF
    const loadingTask = pdfjsLib.getDocument(props.pdfUrl)
    const pdf = await loadingTask.promise
    totalPages.value = pdf.numPages
    console.log('PDF加载成功，页数:', pdf.numPages)
    
    // 加载所有页面
    const pages = []
    for (let i = 1; i <= pdf.numPages; i++) {
      const page = await pdf.getPage(i)
      const viewport = page.getViewport({ scale: 1.5 })
      
      // 创建Canvas
      const canvas = document.createElement('canvas')
      const context = canvas.getContext('2d')
      canvas.height = viewport.height
      canvas.width = viewport.width
      
      // 渲染PDF页面到Canvas
      const renderContext = {
        canvasContext: context,
        viewport: viewport
      }
      
      await page.render(renderContext).promise
      pages.push(canvas.toDataURL('image/png'))
      console.log(`第${i}页渲染完成`)
    }
    
    pdfPages.value = pages
    
    ElMessage.success(`PDF预览加载成功，共${pages.length}页`)
    
  } catch (error) {
    console.error('PDF加载失败:', error)
    ElMessage.error('PDF预览加载失败: ' + error.message)
  }
}

// 监听PDF URL变化
watch(() => props.pdfUrl, (newUrl) => {
  if (newUrl) {
    // 只重置PDF相关状态，不重置占位符（由templateId的watch处理）
    pdfPages.value = []
    totalPages.value = 0
    selectedPlaceholderIndex.value = null
    loadPdfAsImage()
  }
}, { immediate: true })

// 拖拽开始
const startDrag = (event, key) => {
  isDragging.value = true
  draggedItem.value = key
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', key)
}

// 拖拽放置 - 添加新占位符
const onDrop = (event) => {
  event.preventDefault()
  
  if (!isDragging.value || !draggedItem.value) return
  
  // 找到放置在哪一页
  const pageElements = document.querySelectorAll('.pdf-page-item')
  let targetPage = 0
  let targetRect = null
  
  for (let i = 0; i < pageElements.length; i++) {
    const rect = pageElements[i].getBoundingClientRect()
    if (event.clientY >= rect.top && event.clientY <= rect.bottom &&
        event.clientX >= rect.left && event.clientX <= rect.right) {
      targetPage = i
      targetRect = rect
      break
    }
  }
  
  if (targetRect) {
    const x = event.clientX - targetRect.left
    const y = event.clientY - targetRect.top - 30  // 减去页码标签高度
    
    // 获取占位符宽度和高度（签名需要更大区域）
    const isSignature = draggedItem.value === 'employee_signature'
    const widthMap = { 
      name: 100, id_card: 150, phone: 120, address: 200,
      employee_signature: 150  // 签名区域宽度
    }
    const heightMap = {
      employee_signature: 50  // 签名区域高度
    }
    
    // 添加新占位符
    placeholderList.value.push({
      id: ++placeholderId,
      type: draggedItem.value,
      x: Math.max(0, x - 50),
      y: Math.max(0, y - (isSignature ? 25 : 15)),
      width: widthMap[draggedItem.value] || 100,
      height: heightMap[draggedItem.value] || 15,
      page: targetPage
    })
    
    ElMessage.success(`已在第${targetPage + 1}页添加${getPlaceholderLabel(draggedItem.value)}`)
  }
  
  isDragging.value = false
  draggedItem.value = null
}

// 删除占位符
const removePlaceholder = (index) => {
  placeholderList.value.splice(index, 1)
  selectedPlaceholderIndex.value = null
  ElMessage.info('已删除占位符')
}

// 拖拽悬停
const onDragOver = (event) => {
  event.preventDefault()
  event.dataTransfer.dropEffect = 'move'
}

// 占位符拖拽开始
const startPlaceholderDrag = (event, index, pageIndex) => {
  event.preventDefault()
  event.stopPropagation()
  
  isDraggingPlaceholder.value = true
  selectedPlaceholderIndex.value = index
  currentDragPage.value = pageIndex
  
  // 获取当前页面元素
  const pageElement = event.target.closest('.pdf-page-item')
  if (pageElement) {
    const rect = pageElement.getBoundingClientRect()
    dragStartPos.value = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top
    }
  }
  
  placeholderStartPos.value = {
    x: placeholderList.value[index].x,
    y: placeholderList.value[index].y
  }
  
  document.addEventListener('mousemove', handlePlaceholderDrag)
  document.addEventListener('mouseup', stopPlaceholderDrag)
}

// 占位符拖抽中
const handlePlaceholderDrag = (event) => {
  if (!isDraggingPlaceholder.value || selectedPlaceholderIndex.value === null) return
  
  // 获取当前页面元素
  const pageElements = document.querySelectorAll('.pdf-page-item')
  const pageElement = pageElements[currentDragPage.value]
  if (!pageElement) return
  
  const rect = pageElement.getBoundingClientRect()
  const currentPos = {
    x: event.clientX - rect.left,
    y: event.clientY - rect.top
  }
  
  const deltaX = currentPos.x - dragStartPos.value.x
  const deltaY = currentPos.y - dragStartPos.value.y
  
  const newX = Math.max(0, placeholderStartPos.value.x + deltaX)
  const newY = Math.max(0, placeholderStartPos.value.y + deltaY)
  
  placeholderList.value[selectedPlaceholderIndex.value].x = newX
  placeholderList.value[selectedPlaceholderIndex.value].y = newY
}

// 占位符拖拽结束
const stopPlaceholderDrag = () => {
  isDraggingPlaceholder.value = false
  document.removeEventListener('mousemove', handlePlaceholderDrag)
  document.removeEventListener('mouseup', stopPlaceholderDrag)
}

// 清空所有占位符
const resetPositions = () => {
  placeholderList.value = []
  selectedPlaceholderIndex.value = null
  ElMessage.info('已清空所有占位符')
}

// 预览位置
const previewPositions = () => {
  console.log('当前占位符位置:', placeholderPositions)
  ElMessage.info('请查看左侧PDF上的占位符位置')
}

// 保存位置
const savePositions = () => {
  emit('save', {
    templateId: props.templateId,
    positions: placeholderList.value
  })
}

// 获取占位符标签
const getPlaceholderLabel = (type) => {
  // 优先从当前配置的字段中获取标签
  if (infoItems.value && infoItems.value[type]) {
    return infoItems.value[type].label
  }
  // 回退到 fieldExamples 的键对应的中文名
  const labels = {
    name: '姓名',
    id_number: '身份证号',
    id_card: '身份证号',
    phone: '手机号',
    address: '地址',
    gender: '性别',
    birth_date: '出生日期',
    nationality: '民族',
    education: '学历',
    position: '岗位',
    employee_number: '工号',
    email: '邮箱',
    bank_name: '开户银行',
    bank_account: '银行卡号',
    bank_account_holder: '开户名',
    hire_date: '入职日期',
    contract_start_date: '合同开始日期',
    contract_end_date: '合同结束日期',
    contract_start_year: '合同开始年',
    contract_start_month: '合同开始月',
    contract_start_day: '合同开始日',
    contract_end_year: '合同结束年',
    contract_end_month: '合同结束月',
    contract_end_day: '合同结束日',
    emergency_contact: '紧急联系人',
    emergency_phone: '紧急联系电话',
    household_address: '户籍地址',
    residence_address: '居住地址',
    contact_address: '通讯地址',
    employee_signature: '员工签字'
  }
  return labels[type] || type
}

// PDF图片加载完成
const onPdfImageLoad = () => {
  // 图片加载完成后不再修改容器尺寸，让CSS控制布局
  console.log('PDF图片加载完成')
}

// 添加拖拽事件监听
onMounted(() => {
  const container = pdfContainer.value
  if (container) {
    container.addEventListener('dragover', onDragOver)
    container.addEventListener('drop', onDrop)
  }
})

onUnmounted(() => {
  const container = pdfContainer.value
  if (container) {
    container.removeEventListener('dragover', onDragOver)
    container.removeEventListener('drop', onDrop)
  }
  
  // 清理占位符拖拽事件监听器
  document.removeEventListener('mousemove', handlePlaceholderDrag)
  document.removeEventListener('mouseup', stopPlaceholderDrag)
})
</script>

<style scoped>
.pdf-placeholder-setup {
  height: 70vh;
  max-height: 700px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.setup-container {
  display: flex;
  height: 100%;
  gap: 20px;
  overflow: hidden;
}

.pdf-preview-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  overflow: hidden;
  min-height: 0;
}

.pdf-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background-color: #f5f7fa;
  border-bottom: 1px solid #e4e7ed;
}

.pdf-header h3 {
  margin: 0;
  font-size: 16px;
  color: #303133;
}

.pdf-controls {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-info {
  font-size: 13px;
  color: #909399;
}

.pdf-container {
  flex: 1;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 20px;
  background-color: #fafafa;
  overflow-y: auto;
  overflow-x: auto;
  min-height: 0;
}

.pdf-pages-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  padding-bottom: 20px;
}

.pdf-page-item {
  position: relative;
  display: inline-block;
}

.page-number {
  text-align: center;
  font-size: 12px;
  color: #909399;
  margin-bottom: 8px;
  padding: 4px 12px;
  background: #f0f0f0;
  border-radius: 4px;
}

.pdf-image {
  width: auto;
  height: auto;
  max-width: none;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
  display: block;
}

.placeholder-marker {
  position: absolute;
  border: 2px dashed #409EFF;
  background-color: rgba(64, 158, 255, 0.1);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: #409EFF;
  font-weight: bold;
  transition: all 0.3s;
}

.placeholder-marker {
  position: relative;
}

.placeholder-marker:hover {
  background-color: rgba(64, 158, 255, 0.2);
  border-color: #66b1ff;
}

.placeholder-marker:hover .delete-btn {
  display: flex;
}

.delete-btn {
  display: none;
  position: absolute;
  top: -8px;
  right: -8px;
  width: 16px;
  height: 16px;
  background: #f56c6c;
  color: white;
  border-radius: 50%;
  font-size: 12px;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  line-height: 1;
}

.delete-btn:hover {
  background: #e04848;
}

.placeholder-marker.selected {
  background-color: rgba(64, 158, 255, 0.3);
  border-color: #409EFF;
  border-style: solid;
}

.placeholder-marker.dragging {
  background-color: rgba(64, 158, 255, 0.4);
  border-color: #66b1ff;
  border-style: solid;
  cursor: grabbing;
  z-index: 1000;
}

.pdf-placeholder-area {
  text-align: center;
  padding: 40px;
  background-color: #f9f9f9;
  border: 2px dashed #d9d9d9;
  border-radius: 4px;
  min-height: 300px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.placeholder-instructions {
  max-width: 400px;
}

.placeholder-instructions h4 {
  margin: 0 0 16px 0;
  color: #303133;
}

.placeholder-instructions p {
  margin: 8px 0;
  color: #606266;
  line-height: 1.6;
}

.placeholder-instructions ul {
  text-align: left;
  margin: 16px 0;
  padding-left: 20px;
}

.placeholder-instructions li {
  margin: 8px 0;
  color: #606266;
}

.no-pdf {
  text-align: center;
  color: #909399;
}

.no-pdf p {
  margin: 16px 0 0 0;
  font-size: 14px;
}

.drag-info-area {
  width: 300px;
  display: flex;
  flex-direction: column;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  overflow: hidden;
  flex-shrink: 0;
}

.drag-info-area h3 {
  margin: 0;
  padding: 12px 16px;
  background-color: #f5f7fa;
  border-bottom: 1px solid #e4e7ed;
  font-size: 16px;
  color: #303133;
}

.drag-tip {
  margin: 12px 16px;
  padding: 8px 12px;
  background-color: #e1f3d8;
  border: 1px solid #b3e19d;
  border-radius: 4px;
  font-size: 12px;
  color: #529b2e;
}

.info-items {
  flex: 1;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow-y: auto;
  max-height: calc(100vh - 350px);
}

.info-item {
  padding: 12px;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  cursor: grab;
  transition: all 0.3s;
  background-color: #fff;
}

.info-item:hover {
  border-color: #409EFF;
  box-shadow: 0 2px 8px rgba(64, 158, 255, 0.2);
}

.info-item.selected {
  border-color: #409EFF;
  background-color: #ecf5ff;
}

.info-item:active {
  cursor: grabbing;
}

.item-label {
  font-size: 12px;
  color: #909399;
  margin-bottom: 4px;
}

.item-value {
  font-size: 14px;
  color: #303133;
  font-weight: 500;
}

.actions {
  padding: 16px;
  border-top: 1px solid #e4e7ed;
  display: flex;
  gap: 8px;
}

.actions .el-button {
  flex: 1;
}
</style>
