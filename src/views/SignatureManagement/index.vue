<template>
  <div class="signature-management-page">
    <div class="page-header">
      <h1>签名印章管理</h1>
    </div>
    
    <!-- 我的签名 -->
    <div class="section">
      <el-card>
        <template #header>
          <div class="section-header">
            <span class="section-title">✍️ 我的签名</span>
            <span class="section-desc">（用于审批流程）</span>
          </div>
        </template>
        
        <div class="signature-content">
          <div v-if="mySignature" class="signature-display">
            <img :src="mySignature.image_url" alt="我的签名" class="signature-image" />
            <div class="signature-info">
              <p>上传时间：{{ formatDateTime(mySignature.created_at) }}</p>
            </div>
            <div class="signature-actions">
              <el-button type="primary" @click="showUploadSignature = true">
                更换签名
              </el-button>
              <el-button type="danger" @click="handleDeleteSignature">
                删除签名
              </el-button>
            </div>
          </div>
          <div v-else class="signature-empty">
            <el-empty description="还未上传签名">
              <el-button type="primary" @click="showUploadSignature = true">
                上传签名
              </el-button>
            </el-empty>
          </div>
        </div>
      </el-card>
    </div>
    
    <!-- 我的印章 -->
    <div class="section">
      <el-card>
        <template #header>
          <div class="section-header">
            <span class="section-title">🔴 我的印章</span>
            <el-button type="primary" size="small" @click="showUploadSeal = true">
              <el-icon><Plus /></el-icon>
              添加印章
            </el-button>
          </div>
        </template>
        
        <div class="seals-content">
          <div v-if="mySeals.length > 0" class="seals-grid">
            <div 
              v-for="seal in mySeals" 
              :key="seal.id" 
              class="seal-item"
              :class="{ 'is-default': seal.is_default }"
            >
              <div class="seal-badge" v-if="seal.is_default">
                <el-tag type="success" size="small">默认</el-tag>
              </div>
              <img :src="seal.image_url" alt="印章" class="seal-image" />
              <div class="seal-name">{{ seal.name }}</div>
              <div class="seal-actions">
                <el-button 
                  v-if="!seal.is_default" 
                  type="primary" 
                  size="small" 
                  @click="handleSetDefaultSeal(seal)"
                >
                  设为默认
                </el-button>
                <el-button 
                  type="danger" 
                  size="small" 
                  @click="handleDeleteSeal(seal)"
                >
                  删除
                </el-button>
              </div>
            </div>
          </div>
          <el-empty v-else description="还未添加印章">
            <el-button type="primary" @click="showUploadSeal = true">
              添加印章
            </el-button>
          </el-empty>
        </div>
      </el-card>
    </div>
    
    <!-- 银行付讫章 -->
    <div class="section">
      <el-card>
        <template #header>
          <div class="section-header">
            <span class="section-title">🏦 银行付讫章</span>
            <span class="section-desc">（审批最后节点通过时自动盖章到付款申请单）</span>
          </div>
        </template>
        
        <div class="bank-stamp-content">
          <div v-if="myBankStamp" class="bank-stamp-display">
            <img :src="myBankStamp.image_url" alt="银行付讫章" class="bank-stamp-image" />
            <div class="bank-stamp-info">
              <p>名称：{{ myBankStamp.name }}</p>
              <p>默认位置：X {{ myBankStamp.position_x }}%, Y {{ myBankStamp.position_y }}%</p>
              <p>尺寸：{{ myBankStamp.width }} x {{ myBankStamp.height }}</p>
              <p>上传时间：{{ formatDateTime(myBankStamp.created_at) }}</p>
            </div>
            <div class="bank-stamp-actions">
              <el-button type="primary" @click="showUploadBankStamp = true">
                更换
              </el-button>
              <el-button type="warning" @click="showPositionSetting = true">
                设置位置
              </el-button>
              <el-button type="danger" @click="handleDeleteBankStamp">
                删除
              </el-button>
            </div>
          </div>
          <div v-else class="bank-stamp-empty">
            <el-empty description="还未上传银行付讫章">
              <el-button type="primary" @click="showUploadBankStamp = true">
                上传银行付讫章
              </el-button>
            </el-empty>
          </div>
        </div>
      </el-card>
    </div>
    
    <!-- 上传签名对话框 -->
    <el-dialog
      v-model="showUploadSignature"
      title="上传签名"
      width="500px"
    >
      <el-upload
        ref="signatureUploadRef"
        :file-list="signatureFileList"
        :auto-upload="false"
        :limit="1"
        :on-change="handleSignatureFileChange"
        :on-exceed="handleSignatureExceed"
        accept=".png,.jpg,.jpeg"
        drag
      >
        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
        <div class="el-upload__text">
          将签名图片拖到此处，或<em>点击上传</em>
        </div>
        <template #tip>
          <div class="el-upload__tip">
            建议使用PNG透明背景图片，文件大小不超过2MB
          </div>
        </template>
      </el-upload>
      
      <template #footer>
        <el-button @click="showUploadSignature = false">取消</el-button>
        <el-button type="primary" @click="handleSignatureUpload" :loading="uploading">
          确认上传
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 上传印章对话框 -->
    <el-dialog
      v-model="showUploadSeal"
      title="添加印章"
      width="500px"
    >
      <el-form :model="sealForm" label-width="100px">
        <el-form-item label="印章名称" required>
          <el-input v-model="sealForm.name" placeholder="例如：公司公章、合同专用章" />
        </el-form-item>
        
        <el-form-item label="印章图片" required>
          <el-upload
            ref="sealUploadRef"
            :file-list="sealFileList"
            :auto-upload="false"
            :limit="1"
            :on-change="handleSealFileChange"
            :on-exceed="handleSealExceed"
            accept=".png,.jpg,.jpeg"
            drag
          >
            <el-icon class="el-icon--upload"><upload-filled /></el-icon>
            <div class="el-upload__text">
              将印章图片拖到此处，或<em>点击上传</em>
            </div>
            <template #tip>
              <div class="el-upload__tip">
                建议使用PNG透明背景图片，文件大小不超过2MB
              </div>
            </template>
          </el-upload>
        </el-form-item>
        
        <el-form-item label="设为默认">
          <el-switch v-model="sealForm.is_default" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showUploadSeal = false">取消</el-button>
        <el-button type="primary" @click="handleSealUpload" :loading="uploading">
          确认上传
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 上传银行付讫章对话框 -->
    <el-dialog
      v-model="showUploadBankStamp"
      title="上传银行付讫章"
      width="500px"
    >
      <el-upload
        ref="bankStampUploadRef"
        :file-list="bankStampFileList"
        :auto-upload="false"
        :limit="1"
        :on-change="handleBankStampFileChange"
        :on-exceed="handleBankStampExceed"
        accept=".png,.jpg,.jpeg"
        drag
      >
        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
        <div class="el-upload__text">
          将银行付讫章图片拖到此处，或<em>点击上传</em>
        </div>
        <template #tip>
          <div class="el-upload__tip">
            建议使用PNG透明背景图片，文件大小不超过2MB
          </div>
        </template>
      </el-upload>
      
      <template #footer>
        <el-button @click="showUploadBankStamp = false">取消</el-button>
        <el-button type="primary" @click="handleBankStampUpload" :loading="uploading">
          确认上传
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 银行付讫章位置设置对话框 -->
    <el-dialog
      v-model="showPositionSetting"
      title="设置银行付讫章位置"
      width="400px"
    >
      <el-form :model="positionForm" label-width="100px">
        <el-form-item label="X位置(%)">
          <el-slider v-model="positionForm.position_x" :min="0" :max="100" show-input />
        </el-form-item>
        <el-form-item label="Y位置(%)">
          <el-slider v-model="positionForm.position_y" :min="0" :max="100" show-input />
        </el-form-item>
        <el-form-item label="宽度(px)">
          <el-input-number v-model="positionForm.width" :min="20" :max="300" />
        </el-form-item>
        <el-form-item label="高度(px)">
          <el-input-number v-model="positionForm.height" :min="20" :max="300" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showPositionSetting = false">取消</el-button>
        <el-button type="primary" @click="handleUpdatePosition" :loading="uploading">
          保存设置
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { UploadFilled, Plus } from '@element-plus/icons-vue'
import {
  getMySignature,
  uploadSignature,
  deleteSignature,
  getMySeals,
  uploadSeal,
  setDefaultSeal,
  deleteSeal,
  getMyBankStamp,
  uploadBankStamp,
  updateBankStampPosition,
  deleteBankStamp
} from '@/api/signatures'

const mySignature = ref(null)
const mySeals = ref([])
const myBankStamp = ref(null)
const uploading = ref(false)

// 签名上传
const showUploadSignature = ref(false)
const signatureUploadRef = ref()
const signatureFileList = ref([])

// 印章上传
const showUploadSeal = ref(false)
const sealUploadRef = ref()
const sealFileList = ref([])
const sealForm = reactive({
  name: '',
  seal_image: null,
  is_default: false
})

// 银行付讫章上传
const showUploadBankStamp = ref(false)
const bankStampUploadRef = ref()
const bankStampFileList = ref([])
const showPositionSetting = ref(false)
const positionForm = reactive({
  position_x: 70,
  position_y: 80,
  width: 100,
  height: 50
})

// 加载我的签名
const loadMySignature = async () => {
  try {
    const response = await getMySignature()
    if (response.success) {
      mySignature.value = response.data
    }
  } catch (error) {
    console.error('加载签名失败:', error)
  }
}

// 加载我的印章
const loadMySeals = async () => {
  try {
    const response = await getMySeals()
    if (response.success) {
      mySeals.value = response.data
    }
  } catch (error) {
    console.error('加载印章失败:', error)
  }
}

// 签名文件选择
const handleSignatureFileChange = (file, fileList) => {
  signatureFileList.value = fileList
}

const handleSignatureExceed = () => {
  ElMessage.warning('只能上传一个签名图片')
}

// 上传签名
const handleSignatureUpload = async () => {
  if (signatureFileList.value.length === 0) {
    ElMessage.warning('请选择签名图片')
    return
  }

  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('signature_image', signatureFileList.value[0].raw)

    const response = await uploadSignature(formData)
    if (response.success) {
      ElMessage.success('签名上传成功')
      showUploadSignature.value = false
      signatureFileList.value = []
      await loadMySignature()
    }
  } catch (error) {
    console.error('上传签名失败:', error)
    ElMessage.error(error.response?.data?.message || '上传失败')
  } finally {
    uploading.value = false
  }
}

// 删除签名
const handleDeleteSignature = async () => {
  try {
    await ElMessageBox.confirm('确定要删除签名吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const response = await deleteSignature()
    if (response.success) {
      ElMessage.success('签名删除成功')
      mySignature.value = null
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除签名失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 印章文件选择
const handleSealFileChange = (file, fileList) => {
  sealFileList.value = fileList
  sealForm.seal_image = file.raw
}

const handleSealExceed = () => {
  ElMessage.warning('只能上传一个印章图片')
}

// 上传印章
const handleSealUpload = async () => {
  if (!sealForm.name) {
    ElMessage.warning('请输入印章名称')
    return
  }

  if (sealFileList.value.length === 0) {
    ElMessage.warning('请选择印章图片')
    return
  }

  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('name', sealForm.name)
    formData.append('seal_image', sealFileList.value[0].raw)
    formData.append('is_default', sealForm.is_default ? '1' : '0')

    const response = await uploadSeal(formData)
    if (response.success) {
      ElMessage.success('印章添加成功')
      showUploadSeal.value = false
      sealFileList.value = []
      sealForm.name = ''
      sealForm.is_default = false
      await loadMySeals()
    }
  } catch (error) {
    console.error('上传印章失败:', error)
    ElMessage.error(error.response?.data?.message || '上传失败')
  } finally {
    uploading.value = false
  }
}

// 设置默认印章
const handleSetDefaultSeal = async (seal) => {
  try {
    const response = await setDefaultSeal(seal.id)
    if (response.success) {
      ElMessage.success('已设置为默认印章')
      await loadMySeals()
    }
  } catch (error) {
    console.error('设置默认印章失败:', error)
    ElMessage.error(error.response?.data?.message || '操作失败')
  }
}

// 删除印章
const handleDeleteSeal = async (seal) => {
  try {
    await ElMessageBox.confirm(`确定要删除印章"${seal.name}"吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const response = await deleteSeal(seal.id)
    if (response.success) {
      ElMessage.success('印章删除成功')
      await loadMySeals()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除印章失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// ==================== 银行付讫章相关 ====================

// 加载我的银行付讫章
const loadMyBankStamp = async () => {
  try {
    const response = await getMyBankStamp()
    if (response.success) {
      myBankStamp.value = response.data
      if (myBankStamp.value) {
        positionForm.position_x = myBankStamp.value.position_x
        positionForm.position_y = myBankStamp.value.position_y
        positionForm.width = myBankStamp.value.width
        positionForm.height = myBankStamp.value.height
      }
    }
  } catch (error) {
    console.error('加载银行付讫章失败:', error)
  }
}

// 银行付讫章文件选择
const handleBankStampFileChange = (file, fileList) => {
  bankStampFileList.value = fileList
}

const handleBankStampExceed = () => {
  ElMessage.warning('只能上传一个银行付讫章图片')
}

// 上传银行付讫章
const handleBankStampUpload = async () => {
  if (bankStampFileList.value.length === 0) {
    ElMessage.warning('请选择银行付讫章图片')
    return
  }

  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('bank_stamp_image', bankStampFileList.value[0].raw)

    const response = await uploadBankStamp(formData)
    if (response.success) {
      ElMessage.success('银行付讫章上传成功')
      showUploadBankStamp.value = false
      bankStampFileList.value = []
      await loadMyBankStamp()
    }
  } catch (error) {
    console.error('上传银行付讫章失败:', error)
    ElMessage.error(error.response?.data?.message || '上传失败')
  } finally {
    uploading.value = false
  }
}

// 更新银行付讫章位置
const handleUpdatePosition = async () => {
  uploading.value = true
  try {
    const response = await updateBankStampPosition(positionForm)
    if (response.success) {
      ElMessage.success('位置设置已保存')
      showPositionSetting.value = false
      await loadMyBankStamp()
    }
  } catch (error) {
    console.error('更新位置失败:', error)
    ElMessage.error(error.response?.data?.message || '更新失败')
  } finally {
    uploading.value = false
  }
}

// 删除银行付讫章
const handleDeleteBankStamp = async () => {
  try {
    await ElMessageBox.confirm('确定要删除银行付讫章吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const response = await deleteBankStamp()
    if (response.success) {
      ElMessage.success('银行付讫章删除成功')
      myBankStamp.value = null
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除银行付讫章失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 格式化时间
const formatDateTime = (dateTimeStr) => {
  if (!dateTimeStr) return '-'
  const date = new Date(dateTimeStr)
  return date.toLocaleString('zh-CN')
}

onMounted(() => {
  loadMySignature()
  loadMySeals()
  loadMyBankStamp()
})
</script>

<style scoped>
.signature-management-page {
  padding: 0;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h1 {
  font-size: 24px;
  color: #303133;
  margin: 0;
}

.section {
  margin-bottom: 20px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.section-title {
  font-size: 16px;
  font-weight: bold;
}

.section-desc {
  font-size: 12px;
  color: #909399;
  margin-left: 10px;
}

.signature-content {
  min-height: 200px;
}

.signature-display {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}

.signature-image {
  max-width: 300px;
  max-height: 150px;
  border: 2px solid #e4e7ed;
  border-radius: 8px;
  padding: 10px;
  background: #fafafa;
}

.signature-info {
  text-align: center;
  color: #606266;
  font-size: 14px;
}

.signature-actions {
  display: flex;
  gap: 10px;
}

.signature-empty {
  padding: 40px 0;
}

.seals-content {
  min-height: 200px;
}

.seals-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 20px;
}

.seal-item {
  position: relative;
  border: 2px solid #e4e7ed;
  border-radius: 8px;
  padding: 15px;
  text-align: center;
  transition: all 0.3s;
}

.seal-item:hover {
  border-color: #409eff;
  box-shadow: 0 2px 12px rgba(64, 158, 255, 0.2);
}

.seal-item.is-default {
  border-color: #67c23a;
  background-color: #f0f9ff;
}

.seal-badge {
  position: absolute;
  top: 5px;
  right: 5px;
}

.seal-image {
  width: 120px;
  height: 120px;
  object-fit: contain;
  margin: 0 auto 10px;
  display: block;
}

.seal-name {
  font-size: 14px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 10px;
}

.seal-actions {
  display: flex;
  gap: 8px;
  justify-content: center;
}

/* 银行付讫章样式 */
.bank-stamp-content {
  min-height: 200px;
}

.bank-stamp-display {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}

.bank-stamp-image {
  max-width: 200px;
  max-height: 100px;
  border: 2px solid #e4e7ed;
  border-radius: 8px;
  padding: 10px;
  background: #fafafa;
}

.bank-stamp-info {
  text-align: center;
  color: #606266;
  font-size: 14px;
}

.bank-stamp-info p {
  margin: 5px 0;
}

.bank-stamp-actions {
  display: flex;
  gap: 10px;
}

.bank-stamp-empty {
  padding: 40px 0;
}

:deep(.el-upload-dragger) {
  padding: 30px;
}

:deep(.el-icon--upload) {
  font-size: 50px;
  color: #409eff;
  margin-bottom: 10px;
}
</style>

