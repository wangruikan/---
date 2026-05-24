<template>
  <div class="signature-management-page">
    <div class="page-header">
      <h1>签名印章管理</h1>
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
    
    <!-- 现金付讫章 -->
    <div class="section">
      <el-card>
        <template #header>
          <div class="section-header">
            <span class="section-title">💵 现金付讫章</span>
            <span class="section-desc">（付款方式为现金时自动盖章到付款申请单）</span>
          </div>
        </template>
        
        <div class="bank-stamp-content">
          <div v-if="myCashStamp" class="bank-stamp-display">
            <img :src="myCashStamp.image_url" alt="现金付讫章" class="bank-stamp-image" />
            <div class="bank-stamp-info">
              <p>名称：{{ myCashStamp.name }}</p>
              <p>默认位置：X {{ myCashStamp.position_x }}%, Y {{ myCashStamp.position_y }}%</p>
              <p>尺寸：{{ myCashStamp.width }} x {{ myCashStamp.height }}</p>
              <p>上传时间：{{ formatDateTime(myCashStamp.created_at) }}</p>
            </div>
            <div class="bank-stamp-actions">
              <el-button type="primary" @click="showUploadCashStamp = true">
                更换
              </el-button>
              <el-button type="warning" @click="showCashPositionSetting = true">
                设置位置
              </el-button>
              <el-button type="danger" @click="handleDeleteCashStamp">
                删除
              </el-button>
            </div>
          </div>
          <div v-else class="bank-stamp-empty">
            <el-empty description="还未上传现金付讫章">
              <el-button type="primary" @click="showUploadCashStamp = true">
                上传现金付讫章
              </el-button>
            </el-empty>
          </div>
        </div>
      </el-card>
    </div>

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
    
    <!-- 上传现金付讫章对话框 -->
    <el-dialog
      v-model="showUploadCashStamp"
      title="上传现金付讫章"
      width="500px"
    >
      <el-upload
        ref="cashStampUploadRef"
        :file-list="cashStampFileList"
        :auto-upload="false"
        :limit="1"
        :on-change="handleCashStampFileChange"
        :on-exceed="handleCashStampExceed"
        accept=".png,.jpg,.jpeg"
        drag
      >
        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
        <div class="el-upload__text">
          将现金付讫章图片拖到此处，或<em>点击上传</em>
        </div>
        <template #tip>
          <div class="el-upload__tip">
            建议使用PNG透明背景图片，文件大小不超过2MB
          </div>
        </template>
      </el-upload>
      
      <template #footer>
        <el-button @click="showUploadCashStamp = false">取消</el-button>
        <el-button type="primary" @click="handleCashStampUpload" :loading="uploading">
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

    <!-- 现金付讫章位置设置对话框 -->
    <el-dialog
      v-model="showCashPositionSetting"
      title="设置现金付讫章位置"
      width="400px"
    >
      <el-form :model="cashPositionForm" label-width="100px">
        <el-form-item label="X位置(%)">
          <el-slider v-model="cashPositionForm.position_x" :min="0" :max="100" show-input />
        </el-form-item>
        <el-form-item label="Y位置(%)">
          <el-slider v-model="cashPositionForm.position_y" :min="0" :max="100" show-input />
        </el-form-item>
        <el-form-item label="宽度(px)">
          <el-input-number v-model="cashPositionForm.width" :min="20" :max="300" />
        </el-form-item>
        <el-form-item label="高度(px)">
          <el-input-number v-model="cashPositionForm.height" :min="20" :max="300" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCashPositionSetting = false">取消</el-button>
        <el-button type="primary" @click="handleUpdateCashPosition" :loading="uploading">
          保存设置
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getMySeals,
  uploadSeal,
  setDefaultSeal,
  deleteSeal,
  getMyBankStamp,
  uploadBankStamp,
  updateBankStampPosition,
  deleteBankStamp
} from '@/api/signatures'

const mySeals = ref([])
const myBankStamp = ref(null)
const myCashStamp = ref(null)
const uploading = ref(false)

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

// 现金付讫章上传
const showUploadCashStamp = ref(false)
const cashStampUploadRef = ref()
const cashStampFileList = ref([])
const showCashPositionSetting = ref(false)
const cashPositionForm = reactive({
  position_x: 70,
  position_y: 80,
  width: 100,
  height: 50
})

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

// ==================== 现金付讫章相关 ====================

// 加载我的现金付讫章
const loadMyCashStamp = async () => {
  try {
    const response = await getMyBankStamp('cash')
    if (response.success) {
      myCashStamp.value = response.data
      if (myCashStamp.value) {
        cashPositionForm.position_x = myCashStamp.value.position_x
        cashPositionForm.position_y = myCashStamp.value.position_y
        cashPositionForm.width = myCashStamp.value.width
        cashPositionForm.height = myCashStamp.value.height
      }
    }
  } catch (error) {
    console.error('加载现金付讫章失败:', error)
  }
}

// 现金付讫章文件选择
const handleCashStampFileChange = (file, fileList) => {
  cashStampFileList.value = fileList
}

const handleCashStampExceed = () => {
  ElMessage.warning('只能上传一个现金付讫章图片')
}

// 上传现金付讫章
const handleCashStampUpload = async () => {
  if (cashStampFileList.value.length === 0) {
    ElMessage.warning('请选择现金付讫章图片')
    return
  }

  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('bank_stamp_image', cashStampFileList.value[0].raw)

    const response = await uploadBankStamp(formData, 'cash')
    if (response.success) {
      ElMessage.success('现金付讫章上传成功')
      showUploadCashStamp.value = false
      cashStampFileList.value = []
      await loadMyCashStamp()
    }
  } catch (error) {
    console.error('上传现金付讫章失败:', error)
    ElMessage.error(error.response?.data?.message || '上传失败')
  } finally {
    uploading.value = false
  }
}

// 更新现金付讫章位置
const handleUpdateCashPosition = async () => {
  uploading.value = true
  try {
    const response = await updateBankStampPosition({ ...cashPositionForm, type: 'cash' })
    if (response.success) {
      ElMessage.success('位置设置已保存')
      showCashPositionSetting.value = false
      await loadMyCashStamp()
    }
  } catch (error) {
    console.error('更新位置失败:', error)
    ElMessage.error(error.response?.data?.message || '更新失败')
  } finally {
    uploading.value = false
  }
}

// 删除现金付讫章
const handleDeleteCashStamp = async () => {
  try {
    await ElMessageBox.confirm('确定要删除现金付讫章吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const response = await deleteBankStamp('cash')
    if (response.success) {
      ElMessage.success('现金付讫章删除成功')
      myCashStamp.value = null
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除现金付讫章失败:', error)
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
  loadMySeals()
  loadMyBankStamp()
  loadMyCashStamp()
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

