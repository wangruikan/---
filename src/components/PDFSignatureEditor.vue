<template>
  <div class="pdf-signature-editor">
    <div class="editor-layout">
      <!-- 左侧：PDF预览区 -->
      <div class="pdf-preview-area">
        <div class="preview-header">
          <span>合同预览</span>
          <div class="page-controls">
            <el-button size="small" @click="prevPage" :disabled="currentPage <= 1">
              <el-icon><ArrowLeft /></el-icon>
            </el-button>
            <span class="page-info">{{ currentPage }} / {{ totalPages }}</span>
            <el-button size="small" @click="nextPage" :disabled="currentPage >= totalPages">
              <el-icon><ArrowRight /></el-icon>
            </el-button>
          </div>
        </div>
        
        <div class="pdf-canvas-container" ref="canvasContainer">
          <div class="canvas-wrapper" ref="canvasWrapper">
            <canvas 
              ref="pdfCanvas"
              @click="handleCanvasClick"
              :class="{ 'cursor-crosshair': selectedTool }"
            ></canvas>
            
            <!-- 已添加的签名/印章 -->
            <div
              v-for="stamp in getCurrentPageStamps()"
              :key="stamp.id"
              class="stamp-item"
              :class="{ 'active': selectedStamp?.id === stamp.id }"
              :style="getStampStyle(stamp)"
              @click.stop="selectExistingStamp(stamp)"
            >
              <img :src="stamp.image" :alt="stamp.type" />
              <el-button 
                class="stamp-delete" 
                size="small" 
                type="danger" 
                circle 
                @click.stop="removeStamp(stamp)"
              >
                <el-icon><Close /></el-icon>
              </el-button>
            </div>
          </div>
        </div>
        
        <!-- 工具提示 -->
        <div class="tool-hint" v-if="selectedTool">
          <el-alert type="success" :closable="false">
            <template #default>
              <div style="display: flex; align-items: center; gap: 10px;">
                <el-icon><InfoFilled /></el-icon>
                <span>已选中{{ selectedTool.type === 'signature' ? '签名' : selectedTool.name }}，点击文档添加到该位置</span>
                <el-button size="small" @click="cancelSelection">取消选择</el-button>
              </div>
            </template>
          </el-alert>
        </div>
      </div>
      
      <!-- 右侧：签名印章工具栏 -->
      <div class="tools-area">
        <div class="tools-header">
          <h3>✍️ 签名盖章工具</h3>
        </div>
        
        <div class="tools-body">
          <!-- 我的签名 -->
          <div class="tool-section">
            <h4>📝 我的签名</h4>
            <div 
              v-if="mySignature" 
              class="signature-card" 
              :class="{ 'selected': selectedTool?.type === 'signature' }"
              @click="selectTool('signature', mySignature)"
            >
              <img :src="mySignature.image_url" alt="签名" />
              <div class="card-label">点击选择</div>
            </div>
            <el-empty v-else description="未上传签名" :image-size="60">
              <el-button type="primary" size="small" @click="goToSignatureManagement">
                去上传
              </el-button>
            </el-empty>
          </div>
          
          <!-- 我的印章 -->
          <div class="tool-section">
            <h4>🔴 我的印章</h4>
            <div v-if="mySeals.length > 0" class="seals-grid">
              <div 
                v-for="seal in mySeals" 
                :key="seal.id" 
                class="seal-card"
                :class="{ 'selected': selectedTool?.id === seal.id }"
                @click="selectTool('seal', seal)"
              >
                <img :src="seal.image_url" :alt="seal.name" />
                <div class="card-label">{{ seal.name }}</div>
                <el-tag v-if="seal.is_default" type="success" size="small" class="default-tag">默认</el-tag>
              </div>
            </div>
            <el-empty v-else description="未添加印章" :image-size="60">
              <el-button type="primary" size="small" @click="goToSignatureManagement">
                去添加
              </el-button>
            </el-empty>
          </div>
          
          <!-- 操作提示 -->
          <div class="tool-tips">
            <el-alert type="info" :closable="false">
              <template #title>
                <div class="tips-content">
                  <div>💡 操作说明：</div>
                  <div>1. 点击右侧签名/印章选中</div>
                  <div>2. 点击左侧文档添加到该位置</div>
                  <div>3. 点击已添加项可删除</div>
                  <div>4. 可翻页在不同页添加</div>
                  <div>5. 完成后点击"确认盖章"</div>
                </div>
              </template>
            </el-alert>
          </div>
          
          <!-- 已添加统计 -->
          <div class="stamp-count">
            <el-tag type="success" effect="dark">已添加：{{ stamps.length }} 个</el-tag>
          </div>
        </div>
      </div>
    </div>
    
    <!-- 底部操作栏 -->
    <div class="editor-footer">
      <el-button @click="handleCancel">取消</el-button>
      <el-button 
        type="primary" 
        @click="handleConfirm" 
        :loading="loading" 
        :disabled="stamps.length === 0"
      >
        <el-icon><Check /></el-icon>
        确认盖章（{{ stamps.length }}）
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { ElMessage } from 'element-plus'
import { getMySignature, getMySeals } from '@/api/signatures'
import { PDFDocument } from 'pdf-lib'
import { useRouter } from 'vue-router'
import * as pdfjsLib from 'pdfjs-dist'

// 配置 pdf.js worker
pdfjsLib.GlobalWorkerOptions.workerSrc = `https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js`

const props = defineProps({
  pdfUrl: {
    type: String,
    required: true
  },
  previewMode: {
    type: Boolean,
    default: false
  },
  initialPosition: {
    type: Object,
    default: () => ({ x: 80, y: 85 })
  }
})

const emit = defineEmits(['confirm', 'cancel', 'position-change'])
const router = useRouter()

const canvasContainer = ref()
const canvasWrapper = ref()
const pdfCanvas = ref()
const currentPage = ref(1)
const totalPages = ref(1)
const loading = ref(false)
const mySignature = ref(null)
const mySeals = ref([])
const stamps = ref([]) // 已添加的签名/印章
const selectedTool = ref(null) // 当前选中的工具
const selectedStamp = ref(null) // 当前选中的已添加项

let pdfDoc = null // pdf.js文档对象
let nextStampId = 1
let currentScale = 1


const getAuthHeaders = () => {
  const token = localStorage.getItem('token')
  const accountSetId = localStorage.getItem('current_account_set_id')
  const headers = {}

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }
  if (accountSetId) {
    headers['X-Account-Set-Id'] = accountSetId
  }

  return headers
}

// 加载PDF
const loadPDF = async () => {
  try {
    console.log('📄 开始加载PDF:', props.pdfUrl)
    
    // 使用pdf.js加载（用于渲染显示）
    const loadingTask = pdfjsLib.getDocument({
      url: props.pdfUrl,
      httpHeaders: getAuthHeaders(),
      withCredentials: true
    })
    pdfDoc = await loadingTask.promise
    totalPages.value = pdfDoc.numPages
    
    console.log('✅ PDF加载成功，总页数:', totalPages.value)
    
    await nextTick()
    await renderPage(1)
  } catch (error) {
    console.error('❌ PDF加载失败:', error)
    ElMessage.error('PDF加载失败：' + error.message)
  }
}

// 渲染页面
const renderPage = async (pageNum) => {
  try {
    const page = await pdfDoc.getPage(pageNum)
    const viewport = page.getViewport({ scale: 1 })
    
    const canvas = pdfCanvas.value
    if (!canvas) return
    
    const container = canvasContainer.value
    const containerWidth = container.clientWidth - 40
    
    // 计算缩放比例
    currentScale = Math.min(containerWidth / viewport.width, 1.5)
    const scaledViewport = page.getViewport({ scale: currentScale })
    
    // 设置Canvas尺寸（考虑设备像素比，避免模糊）
    const outputScale = window.devicePixelRatio || 1
    canvas.width = Math.floor(scaledViewport.width * outputScale)
    canvas.height = Math.floor(scaledViewport.height * outputScale)
    canvas.style.width = Math.floor(scaledViewport.width) + 'px'
    canvas.style.height = Math.floor(scaledViewport.height) + 'px'
    
    const context = canvas.getContext('2d')
    context.scale(outputScale, outputScale)
    
    // 渲染PDF
    const renderContext = {
      canvasContext: context,
      viewport: scaledViewport
    }
    
    await page.render(renderContext).promise
    
    currentPage.value = pageNum
    
    console.log(`✅ 第${pageNum}页渲染完成: ${Math.floor(scaledViewport.width)}x${Math.floor(scaledViewport.height)}px, 缩放: ${currentScale.toFixed(2)}`)
    
  } catch (error) {
    console.error('❌ 渲染页面失败:', error)
    ElMessage.error('渲染页面失败')
  }
}

const prevPage = () => {
  if (currentPage.value > 1) {
    renderPage(currentPage.value - 1)
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    renderPage(currentPage.value + 1)
  }
}

// 选择工具
const selectTool = (type, item) => {
  selectedTool.value = {
    type: type,
    data: item,
    id: item.id,
    name: item.name || '签名'
  }
  selectedStamp.value = null
  console.log('🎯 已选择工具:', selectedTool.value.name)
  ElMessage.success(`已选择${selectedTool.value.name}，点击文档添加`)
}

// 取消选择
const cancelSelection = () => {
  selectedTool.value = null
  selectedStamp.value = null
}

// 处理Canvas点击
const handleCanvasClick = (e) => {
  if (!selectedTool.value) {
    ElMessage.warning('请先点击右侧的签名或印章')
    return
  }
  
  const canvas = pdfCanvas.value
  const wrapper = canvasWrapper.value
  
  // 获取Canvas在视口中的位置
  const rect = canvas.getBoundingClientRect()
  
  // 计算点击位置（相对于Canvas，考虑滚动）
  const x = e.clientX - rect.left
  const y = e.clientY - rect.top
  
  // 获取Canvas的实际显示尺寸
  const displayWidth = canvas.clientWidth
  const displayHeight = canvas.clientHeight
  
  // 计算百分比坐标（相对于Canvas实际尺寸）
  const xPercent = (x / displayWidth) * 100
  const yPercent = (y / displayHeight) * 100
  
  console.log('🖱️ 点击坐标:', {
    clientX: e.clientX,
    clientY: e.clientY,
    rectLeft: rect.left,
    rectTop: rect.top,
    x: x,
    y: y,
    xPercent: xPercent.toFixed(2),
    yPercent: yPercent.toFixed(2)
  })
  
  // 签名/印章尺寸
  const stampWidth = selectedTool.value.type === 'signature' ? 120 : 80
  const stampHeight = selectedTool.value.type === 'signature' ? 60 : 80
  
  // 创建签名/印章对象（坐标居中调整）
  const newStamp = {
    id: nextStampId++,
    type: selectedTool.value.type,
    image: selectedTool.value.data.image_url,
    name: selectedTool.value.name,
    xPercent: Math.max(0, Math.min(100 - (stampWidth / displayWidth * 100), xPercent - (stampWidth / displayWidth * 100 / 2))),
    yPercent: Math.max(0, Math.min(100 - (stampHeight / displayHeight * 100), yPercent - (stampHeight / displayHeight * 100 / 2))),
    width: stampWidth,
    height: stampHeight,
    page: currentPage.value,
    dataId: selectedTool.value.data.id
  }
  
  stamps.value.push(newStamp)
  
  console.log('➕ 添加签名/印章:', {
    id: newStamp.id,
    name: newStamp.name,
    xPercent: newStamp.xPercent.toFixed(2),
    yPercent: newStamp.yPercent.toFixed(2),
    page: newStamp.page
  })
  
  ElMessage.success(`已添加${newStamp.name}`)
}

// 获取当前页的签名/印章
const getCurrentPageStamps = () => {
  return stamps.value.filter(s => s.page === currentPage.value)
}

// 获取签名/印章的样式
const getStampStyle = (stamp) => {
  return {
    left: `${stamp.xPercent}%`,
    top: `${stamp.yPercent}%`,
    width: `${stamp.width}px`,
    height: `${stamp.height}px`,
  }
}

// 选中已添加的签名/印章
const selectExistingStamp = (stamp) => {
  selectedStamp.value = stamp
  selectedTool.value = null
  console.log('🎯 已选中:', stamp.name)
}

// 删除签名/印章
const removeStamp = (stamp) => {
  const index = stamps.value.findIndex(s => s.id === stamp.id)
  if (index > -1) {
    stamps.value.splice(index, 1)
    selectedStamp.value = null
    console.log('🗑️ 已删除:', stamp.name)
    ElMessage.success('已删除')
  }
}

// 确认盖章
const handleConfirm = async () => {
  if (stamps.value.length === 0) {
    ElMessage.warning('请先添加签名或印章')
    return
  }
  
  loading.value = true
  try {
    console.log('🔄 开始合成PDF...')
    
    const mergedPdfBytes = await mergePDFWithStamps()
    const pdfBlob = new Blob([mergedPdfBytes], { type: 'application/pdf' })
    
    console.log('✅ PDF合成成功，大小:', (pdfBlob.size / 1024).toFixed(2), 'KB')
    
    emit('confirm', {
      pdfBlob: pdfBlob,
      stamps: stamps.value,
      hasSignature: stamps.value.some(s => s.type === 'signature'),
      hasSeal: stamps.value.some(s => s.type === 'seal'),
      signatureId: stamps.value.find(s => s.type === 'signature')?.dataId,
      sealId: stamps.value.find(s => s.type === 'seal')?.dataId
    })
  } catch (error) {
    console.error('❌ 合成PDF失败:', error)
    ElMessage.error('合成PDF失败：' + error.message)
    loading.value = false
  }
}

// 合成PDF
const mergePDFWithStamps = async () => {
  // 重新下载PDF用于合成（避免ArrayBuffer detached问题）
  console.log('📥 重新下载PDF用于合成...')
  const response = await fetch(props.pdfUrl, {
    headers: getAuthHeaders()
  })
  if (!response.ok) {
    throw new Error(`下载PDF失败: HTTP ${response.status}`)
  }
  const pdfBytes = await response.arrayBuffer()
  
  // 使用pdf-lib加载
  const newPdfDoc = await PDFDocument.load(pdfBytes)
  
  for (let pageIndex = 0; pageIndex < totalPages.value; pageIndex++) {
    const page = newPdfDoc.getPage(pageIndex)
    const { width: pdfWidth, height: pdfHeight } = page.getSize()
    const pageStamps = stamps.value.filter(s => s.page === pageIndex + 1)
    
    console.log(`📄 处理第${pageIndex + 1}页，签名/印章数量: ${pageStamps.length}`)
    
    for (const stamp of pageStamps) {
      try {
        // 使用代理URL避免CORS问题
        let imgUrl = stamp.image
        // 如果是完整URL，转换为相对路径使用Vite代理
        if (imgUrl.includes('localhost:8000')) {
          imgUrl = imgUrl.replace('http://localhost:8000', '')
        }
        
        console.log(`  📥 下载图片: ${imgUrl}`)
        const imgResponse = await fetch(imgUrl, {
          credentials: 'include',
          mode: 'cors'
        })
        
        if (!imgResponse.ok) {
          throw new Error(`下载失败: ${imgResponse.status}`)
        }
        
        const imgBytes = await imgResponse.arrayBuffer()
        
        let image
        if (stamp.image.toLowerCase().includes('.png')) {
          image = await newPdfDoc.embedPng(imgBytes)
        } else {
          image = await newPdfDoc.embedJpg(imgBytes)
        }
        
        // 获取图片原始尺寸
        const imgWidth = image.width
        const imgHeight = image.height
        const imgAspectRatio = imgWidth / imgHeight
        
        // 根据预设尺寸和原始宽高比计算实际绘制尺寸
        let drawWidth = stamp.width
        let drawHeight = stamp.height
        
        // 保持宽高比：以较小的缩放比例为准
        const scaleByWidth = stamp.width / imgWidth
        const scaleByHeight = stamp.height / imgHeight
        const scale = Math.min(scaleByWidth, scaleByHeight)
        
        drawWidth = imgWidth * scale
        drawHeight = imgHeight * scale
        
        console.log(`  📐 图片原始尺寸: ${imgWidth}x${imgHeight}, 宽高比: ${imgAspectRatio.toFixed(2)}`)
        console.log(`  📐 绘制尺寸: ${drawWidth.toFixed(0)}x${drawHeight.toFixed(0)}`)
        
        // 百分比坐标 → PDF实际坐标
        const x = (stamp.xPercent / 100) * pdfWidth
        const y = pdfHeight - ((stamp.yPercent / 100) * pdfHeight) - drawHeight
        
        console.log(`  ➕ 添加 ${stamp.name} 到 (${x.toFixed(0)}, ${y.toFixed(0)})`)
        
        page.drawImage(image, {
          x: x,
          y: y,
          width: drawWidth,
          height: drawHeight,
        })
      } catch (error) {
        console.error(`  ❌ 添加 ${stamp.name} 失败:`, error)
        throw new Error(`添加${stamp.name}失败`)
      }
    }
  }
  
  return await newPdfDoc.save()
}

const handleCancel = () => {
  emit('cancel')
}

const goToSignatureManagement = () => {
  router.push('/signature-management')
}

// 加载签名和印章
const loadSignatureAndSeals = async () => {
  try {
    const sigResponse = await getMySignature()
    if (sigResponse.success) {
      mySignature.value = sigResponse.data
    }
    
    const sealsResponse = await getMySeals()
    if (sealsResponse.success) {
      mySeals.value = sealsResponse.data
    }
    
    console.log('✅ 签名印章加载完成:', {
      signature: !!mySignature.value,
      seals: mySeals.value.length
    })
  } catch (error) {
    console.error('❌ 加载签名印章失败:', error)
  }
}

onMounted(async () => {
  await loadSignatureAndSeals()
  await nextTick()
  await loadPDF()
})

// 获取当前盖章位置（供父组件调用）
const getCurrentPosition = () => {
  if (stamps.value.length === 0) {
    return null
  }
  
  const lastStamp = stamps.value[stamps.value.length - 1]
  return {
    x: lastStamp.xPercent,
    y: lastStamp.yPercent,
    page: lastStamp.page,
    sealId: lastStamp.dataId,
    sealUrl: lastStamp.image,
    width: lastStamp.width,
    height: lastStamp.height
  }
}

// 暴露方法给父组件
defineExpose({
  getCurrentPosition
})
</script>

<style scoped>
.pdf-signature-editor {
  display: flex;
  flex-direction: column;
  height: 75vh;
  max-height: 800px;
}

.editor-layout {
  flex: 1;
  display: flex;
  gap: 20px;
  overflow: hidden;
}

.pdf-preview-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #f5f7fa;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid #e4e7ed;
}

.preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  font-weight: 600;
}

.page-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-info {
  padding: 0 10px;
  font-size: 14px;
}

.pdf-canvas-container {
  flex: 1;
  overflow: auto;
  padding: 20px;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  background: #e8eaf0;
}

.canvas-wrapper {
  position: relative;
  display: inline-block;
}

canvas {
  display: block;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  background: #fff;
  cursor: default;
}

canvas.cursor-crosshair {
  cursor: crosshair;
}

.tool-hint {
  padding: 12px 16px;
  background: #fff;
  border-top: 2px solid #e4e7ed;
}

.stamp-item {
  position: absolute;
  border: 2px dashed transparent;
  transition: all 0.2s;
  user-select: none;
  cursor: pointer;
}

.stamp-item:hover,
.stamp-item.active {
  border-color: #409eff;
  box-shadow: 0 0 0 3px rgba(64, 158, 255, 0.1);
}

.stamp-item img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  pointer-events: none;
}

.stamp-delete {
  position: absolute;
  top: -12px;
  right: -12px;
  opacity: 0;
  transition: opacity 0.2s;
}

.stamp-item:hover .stamp-delete,
.stamp-item.active .stamp-delete {
  opacity: 1;
}

.tools-area {
  width: 320px;
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 8px;
  border: 2px solid #e4e7ed;
  overflow: hidden;
}

.tools-header {
  padding: 16px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}

.tools-header h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
}

.tools-body {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

.tool-section {
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e4e7ed;
}

.tool-section:last-child {
  border-bottom: none;
}

.tool-section h4 {
  margin: 0 0 12px 0;
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}

.signature-card {
  padding: 16px;
  background: #f5f7fa;
  border: 2px dashed #dcdfe6;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;
  text-align: center;
}

.signature-card:hover {
  border-color: #409eff;
  background: #ecf5ff;
  transform: translateY(-2px);
}

.signature-card.selected {
  border-color: #409eff;
  background: #ecf5ff;
  box-shadow: 0 0 0 3px rgba(64, 158, 255, 0.1);
}

.signature-card img {
  max-width: 100%;
  height: 70px;
  object-fit: contain;
  display: block;
  margin: 0 auto;
}

.card-label {
  margin-top: 10px;
  font-size: 12px;
  color: #606266;
  font-weight: 500;
}

.seals-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.seal-card {
  position: relative;
  padding: 12px;
  background: #f5f7fa;
  border: 2px dashed #dcdfe6;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;
  text-align: center;
}

.seal-card:hover {
  border-color: #409eff;
  background: #ecf5ff;
  transform: translateY(-2px);
}

.seal-card.selected {
  border-color: #409eff;
  background: #ecf5ff;
  box-shadow: 0 0 0 3px rgba(64, 158, 255, 0.1);
}

.seal-card img {
  width: 70px;
  height: 70px;
  object-fit: contain;
  margin: 0 auto 8px;
  display: block;
}

.default-tag {
  position: absolute;
  top: 4px;
  right: 4px;
}

.tool-tips {
  margin-top: 16px;
}

.tips-content {
  font-size: 12px;
  line-height: 1.8;
  color: #606266;
}

.tips-content > div:first-child {
  font-weight: 600;
  margin-bottom: 6px;
}

.stamp-count {
  text-align: center;
  padding: 12px 0;
}

.editor-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 0 0 0;
  border-top: 2px solid #e4e7ed;
  margin-top: 16px;
}
</style>
