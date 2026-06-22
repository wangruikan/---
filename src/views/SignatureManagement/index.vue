<template>
  <div class="signature-management-page">
    <div class="page-header">
      <h1>签名印章管理</h1>
    </div>

    <div class="section">
      <el-card>
        <template #header>
          <div class="section-header">
            <div>
              <span class="section-title">📝 我的签名</span>
              <span class="section-desc">审批签字时使用的个人签名</span>
            </div>
            <el-button type="primary" size="small" @click="showUploadSignature = true">
              <el-icon><Plus /></el-icon>
              {{ mySignature ? '重新上传' : '上传签名' }}
            </el-button>
          </div>
        </template>

        <div class="signature-content">
          <div v-if="mySignature" class="signature-card">
            <img :src="mySignature.image_url" alt="签名" class="signature-image" />
            <div class="signature-actions">
              <el-button type="primary" size="small" @click="showUploadSignature = true">
                重新上传
              </el-button>
              <el-button type="danger" size="small" @click="handleDeleteSignature">
                删除
              </el-button>
            </div>
          </div>
          <el-empty v-else description="还未上传签名">
            <el-button type="primary" @click="showUploadSignature = true">
              上传签名
            </el-button>
          </el-empty>
        </div>
      </el-card>
    </div>
    
    <!-- 我的印章 -->
    <div v-if="isAdmin" class="section">
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
                  type="warning" 
                  size="small" 
                  @click="handleEditSeal(seal)"
                >
                  修改
                </el-button>
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
    
    <div v-if="isAdmin" class="section">
      <el-card>
        <template #header>
          <div class="section-header">
            <div>
              <span class="section-title">公司印章</span>
              <span class="section-desc">按公司和固定类型管理审批用章</span>
            </div>
            <el-button type="primary" size="small" @click="openTypedStampUpload()">
              <el-icon><Plus /></el-icon>
              上传公司印章
            </el-button>
          </div>
        </template>

        <div class="company-stamp-content">
          <el-collapse v-if="companyStampGroups.length > 0" class="company-stamp-collapse">
            <el-collapse-item
              v-for="group in companyStampGroups"
              :key="group.company"
              :name="group.company"
            >
              <template #title>
                <div class="company-collapse-title">
                  <span>{{ group.company }}</span>
                  <el-tag size="small" type="info">{{ group.uploadedCount }}/{{ fixedStampTypes.length }}</el-tag>
                </div>
              </template>

              <el-table :data="group.rows" border size="small" class="company-stamp-table">
                <el-table-column prop="title" label="印章类型" min-width="150" />
                <el-table-column label="印章名称" min-width="160">
                  <template #default="{ row }">
                    <span>{{ row.stamp?.name || '-' }}</span>
                  </template>
                </el-table-column>
                <el-table-column label="状态" width="100" align="center">
                  <template #default="{ row }">
                    <el-tag v-if="row.stamp" type="success" size="small">已上传</el-tag>
                    <span v-else>-</span>
                  </template>
                </el-table-column>
                <el-table-column label="默认位置" min-width="160">
                  <template #default="{ row }">
                    <span v-if="row.stamp">X {{ row.stamp.position_x }}%, Y {{ row.stamp.position_y }}%</span>
                    <span v-else>-</span>
                  </template>
                </el-table-column>
                <el-table-column label="上传时间" min-width="170">
                  <template #default="{ row }">
                    <span>{{ row.stamp ? formatDateTime(row.stamp.created_at) : '-' }}</span>
                  </template>
                </el-table-column>
                <el-table-column label="操作" width="260" fixed="right">
                  <template #default="{ row }">
                    <el-button
                      v-if="row.stamp"
                      type="primary"
                      size="small"
                      @click="openTypedStampPreview(row.stamp)"
                    >
                      查看
                    </el-button>
                    <el-button
                      size="small"
                      @click="openTypedStampUpload(row.type, group.company, row.stamp)"
                    >
                      {{ row.stamp ? '更换' : '上传' }}
                    </el-button>
                    <el-button
                      v-if="row.stamp"
                      type="warning"
                      size="small"
                      @click="openTypedStampPosition(row.stamp)"
                    >
                      设置位置
                    </el-button>
                    <el-button
                      v-if="row.stamp"
                      type="danger"
                      size="small"
                      @click="handleDeleteTypedStamp(row.stamp)"
                    >
                      删除
                    </el-button>
                  </template>
                </el-table-column>
              </el-table>
            </el-collapse-item>
          </el-collapse>

          <el-empty v-else description="还未上传公司印章">
            <el-button type="primary" @click="openTypedStampUpload()">
              上传公司印章
            </el-button>
          </el-empty>
        </div>
      </el-card>
    </div>
    
    <!-- 上传印章对话框 -->
    <el-dialog
      v-model="showUploadSeal"
      :title="editingSealId ? '修改印章' : '添加印章'"
      width="500px"
    >
      <el-form :model="sealForm" label-width="100px">
        <el-form-item label="印章名称" required>
          <el-input v-model="sealForm.name" placeholder="例如：公司公章、合同专用章" />
        </el-form-item>
        
        <el-form-item :label="editingSealId ? '印章图片' : '印章图片'" :required="!editingSealId">
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
                {{ editingSealId ? '不上传则保留原图片，建议使用PNG透明背景图片，文件大小不超过2MB' : '建议使用PNG透明背景图片，文件大小不超过2MB' }}
              </div>
            </template>
          </el-upload>
        </el-form-item>
        
        <el-form-item label="设为默认">
          <el-switch v-model="sealForm.is_default" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="handleSealDialogClose">取消</el-button>
        <el-button type="primary" @click="handleSealSubmit" :loading="uploading">
          {{ editingSealId ? '确认修改' : '确认上传' }}
        </el-button>
      </template>
    </el-dialog>

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
        <el-button @click="handleSignatureDialogClose">取消</el-button>
        <el-button type="primary" @click="handleSignatureUpload" :loading="uploading">
          确认上传
        </el-button>
      </template>
    </el-dialog>
    
    <el-dialog
      v-model="showTypedStampUpload"
      :title="`${editingTypedStampId ? '更换' : '上传'}公司印章`"
      width="500px"
    >
      <el-form :model="typedStampForm" label-width="100px">
        <el-form-item label="公司" required>
          <el-input
            v-model.trim="typedStampForm.company"
            placeholder="请输入公司名称"
            :disabled="!!editingTypedStampId"
          />
        </el-form-item>
        <el-form-item label="印章类型" required>
          <el-select
            v-model="typedStampForm.type"
            placeholder="请选择印章类型"
            :disabled="!!editingTypedStampId"
            style="width: 100%"
          >
            <el-option
              v-for="stampType in fixedStampTypes"
              :key="stampType.type"
              :label="stampType.title"
              :value="stampType.type"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="名称">
          <el-input v-model="typedStampForm.name" placeholder="请输入印章名称" />
        </el-form-item>
      </el-form>
      <el-upload
        ref="typedStampUploadRef"
        :file-list="typedStampFileList"
        :auto-upload="false"
        :limit="1"
        :on-change="handleTypedStampFileChange"
        :on-exceed="handleTypedStampExceed"
        accept=".png,.jpg,.jpeg"
        drag
      >
        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
        <div class="el-upload__text">
          将印章图片拖到此处，或<em>点击上传</em>
        </div>
        <template #tip>
          <div class="el-upload__tip">
            更换印章需要重新上传图片，建议使用PNG透明背景图片，文件大小不超过2MB
          </div>
        </template>
      </el-upload>
      
      <template #footer>
        <el-button @click="handleTypedStampUploadClose">取消</el-button>
        <el-button type="primary" @click="handleTypedStampUpload" :loading="uploading">
          确认上传
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="showTypedStampPosition"
      :title="`设置${currentTypedStampConfig?.title || '印章'}位置`"
      width="400px"
    >
      <el-form :model="typedStampPositionForm" label-width="100px">
        <el-form-item label="X位置(%)">
          <el-slider v-model="typedStampPositionForm.position_x" :min="0" :max="100" show-input />
        </el-form-item>
        <el-form-item label="Y位置(%)">
          <el-slider v-model="typedStampPositionForm.position_y" :min="0" :max="100" show-input />
        </el-form-item>
        <el-form-item label="宽度(px)">
          <el-input-number v-model="typedStampPositionForm.width" :min="20" :max="300" />
        </el-form-item>
        <el-form-item label="高度(px)">
          <el-input-number v-model="typedStampPositionForm.height" :min="20" :max="300" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showTypedStampPosition = false">取消</el-button>
        <el-button type="primary" @click="handleUpdateTypedStampPosition" :loading="uploading">
          保存设置
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="showTypedStampPreview"
      :title="`${previewTypedStamp?.company || ''} - ${getTypedStampTitle(previewTypedStamp?.type)}`"
      width="420px"
    >
      <div v-if="previewTypedStamp" class="stamp-preview">
        <img :src="previewTypedStamp.image_url" :alt="previewTypedStamp.name" class="stamp-preview-image" />
        <div class="stamp-preview-info">
          <p>名称：{{ previewTypedStamp.name }}</p>
          <p>公司：{{ previewTypedStamp.company || '-' }}</p>
          <p>类型：{{ getTypedStampTitle(previewTypedStamp.type) }}</p>
          <p>默认位置：X {{ previewTypedStamp.position_x }}%, Y {{ previewTypedStamp.position_y }}%</p>
          <p>尺寸：{{ previewTypedStamp.width }} x {{ previewTypedStamp.height }}</p>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, UploadFilled } from '@element-plus/icons-vue'
import {
  getMySignature,
  uploadSignature,
  deleteSignature,
  getMySeals,
  uploadSeal,
  updateSeal,
  setDefaultSeal,
  deleteSeal,
  getBankStamps,
  uploadTypedStamp,
  updateBankStampPosition,
  deleteTypedStamp
} from '@/api/signatures'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()
const isAdmin = computed(() => ['admin', 'super_admin'].includes(userStore.userInfo?.role))
const mySignature = ref(null)
const mySeals = ref([])
const typedStamps = ref([])
const uploading = ref(false)

const fixedStampTypes = [
  { type: 'bank', title: '银行付讫章' },
  { type: 'cash', title: '现金付讫章' },
  { type: 'official', title: '公章' },
  { type: 'finance', title: '财务专用章' },
  { type: 'contract', title: '合同专用章' },
  { type: 'legal_person', title: '法人章' },
  { type: 'business', title: '业务专用章' },
  { type: 'hr', title: '人事部专用章' }
]

const showUploadSignature = ref(false)
const signatureUploadRef = ref()
const signatureFileList = ref([])

// 印章上传
const showUploadSeal = ref(false)
const editingSealId = ref(null)
const sealUploadRef = ref()
const sealFileList = ref([])
const sealForm = reactive({
  name: '',
  seal_image: null,
  is_default: false
})

const showTypedStampUpload = ref(false)
const typedStampUploadRef = ref()
const typedStampFileList = ref([])
const currentTypedStampType = ref('bank')
const currentTypedStampId = ref(null)
const editingTypedStampId = ref(null)
const showTypedStampPosition = ref(false)
const showTypedStampPreview = ref(false)
const previewTypedStamp = ref(null)
const typedStampForm = reactive({
  name: '',
  company: '',
  type: 'bank'
})
const typedStampPositionForm = reactive({
  position_x: 70,
  position_y: 80,
  width: 100,
  height: 50
})

const loadMySignature = async () => {
  try {
    const response = await getMySignature()
    if (response.success) {
      mySignature.value = response.data || null
    }
  } catch (error) {
    console.error('加载签名失败:', error)
  }
}

const handleSignatureFileChange = (file, fileList) => {
  signatureFileList.value = fileList
}

const handleSignatureExceed = () => {
  ElMessage.warning('只能上传一个签名图片')
}

const resetSignatureForm = () => {
  signatureFileList.value = []
  signatureUploadRef.value?.clearFiles?.()
}

const handleSignatureDialogClose = () => {
  showUploadSignature.value = false
  resetSignatureForm()
}

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
      resetSignatureForm()
      await loadMySignature()
    }
  } catch (error) {
    console.error('上传签名失败:', error)
    ElMessage.error(error.response?.data?.message || '上传失败')
  } finally {
    uploading.value = false
  }
}

const handleDeleteSignature = async () => {
  try {
    await ElMessageBox.confirm('确定要删除当前签名吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const response = await deleteSignature()
    if (response.success) {
      ElMessage.success('签名删除成功')
      mySignature.value = null
      resetSignatureForm()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除签名失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
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

// 印章文件选择
const handleSealFileChange = (file, fileList) => {
  sealFileList.value = fileList
  sealForm.seal_image = file.raw
}

const handleSealExceed = () => {
  ElMessage.warning('只能上传一个印章图片')
}

const resetSealForm = () => {
  editingSealId.value = null
  sealFileList.value = []
  sealForm.name = ''
  sealForm.seal_image = null
  sealForm.is_default = false
  sealUploadRef.value?.clearFiles?.()
}

const handleSealDialogClose = () => {
  showUploadSeal.value = false
  resetSealForm()
}

const handleEditSeal = (seal) => {
  editingSealId.value = seal.id
  sealForm.name = seal.name || ''
  sealForm.seal_image = null
  sealForm.is_default = !!seal.is_default
  sealFileList.value = []
  sealUploadRef.value?.clearFiles?.()
  showUploadSeal.value = true
}

// 新增/修改印章
const handleSealSubmit = async () => {
  if (!sealForm.name) {
    ElMessage.warning('请输入印章名称')
    return
  }

  if (!editingSealId.value && sealFileList.value.length === 0) {
    ElMessage.warning('请选择印章图片')
    return
  }

  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('name', sealForm.name)
    formData.append('is_default', sealForm.is_default ? '1' : '0')

    if (sealFileList.value.length > 0) {
      formData.append('seal_image', sealFileList.value[0].raw)
    }

    const response = editingSealId.value
      ? await updateSeal(editingSealId.value, formData)
      : await uploadSeal(formData)

    if (response.success) {
      ElMessage.success(editingSealId.value ? '印章修改成功' : '印章添加成功')
      showUploadSeal.value = false
      resetSealForm()
      await loadMySeals()
    }
  } catch (error) {
    console.error('提交印章失败:', error)
    ElMessage.error(error.response?.data?.message || '提交失败')
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

const currentTypedStampConfig = computed(() => {
  return fixedStampTypes.find(item => item.type === currentTypedStampType.value) || null
})

const normalizeCompanyName = (company) => {
  return (company || '').trim() || '未填写公司'
}

const companyStampGroups = computed(() => {
  const companyNames = Array.from(new Set(
    typedStamps.value
      .map(stamp => normalizeCompanyName(stamp.company))
  )).sort((a, b) => a.localeCompare(b, 'zh-CN'))

  return companyNames.map(company => {
    const rows = fixedStampTypes.map(typeConfig => {
      const stamp = typedStamps.value.find(item => normalizeCompanyName(item.company) === company && item.type === typeConfig.type) || null
      return {
        ...typeConfig,
        stamp
      }
    })

    return {
      company,
      rows,
      uploadedCount: rows.filter(row => row.stamp).length
    }
  })
})

const resetTypedStampUploadForm = () => {
  editingTypedStampId.value = null
  typedStampForm.name = ''
  typedStampForm.company = ''
  typedStampForm.type = 'bank'
  typedStampFileList.value = []
  typedStampUploadRef.value?.clearFiles?.()
}

const fillTypedStampPositionForm = (stamp) => {
  typedStampPositionForm.position_x = stamp?.position_x ?? 70
  typedStampPositionForm.position_y = stamp?.position_y ?? 80
  typedStampPositionForm.width = stamp?.width ?? 100
  typedStampPositionForm.height = stamp?.height ?? 50
}

const loadAllTypedStamps = async () => {
  try {
    const response = await getBankStamps()
    if (response.success) {
      typedStamps.value = Array.isArray(response.data) ? response.data : []
    }
  } catch (error) {
    console.error('加载公司印章失败:', error)
  }
}

const openTypedStampUpload = (type = 'bank', company = '', stamp = null) => {
  resetTypedStampUploadForm()
  currentTypedStampType.value = type
  editingTypedStampId.value = stamp?.id || null
  typedStampForm.type = type
  typedStampForm.company = company || stamp?.company || ''
  typedStampForm.name = stamp?.name || getTypedStampDefaultName(type)
  showTypedStampUpload.value = true
}

const handleTypedStampUploadClose = () => {
  showTypedStampUpload.value = false
  resetTypedStampUploadForm()
}

const openTypedStampPosition = (stamp) => {
  currentTypedStampId.value = stamp.id
  currentTypedStampType.value = stamp.type
  fillTypedStampPositionForm(stamp)
  showTypedStampPosition.value = true
}

const openTypedStampPreview = (stamp) => {
  previewTypedStamp.value = stamp
  showTypedStampPreview.value = true
}

const handleTypedStampFileChange = (file, fileList) => {
  typedStampFileList.value = fileList
}

const handleTypedStampExceed = () => {
  ElMessage.warning('只能上传一个印章图片')
}

const getTypedStampDefaultName = (type) => {
  return getTypedStampTitle(type)
}

const getTypedStampTitle = (type) => {
  return fixedStampTypes.find(item => item.type === type)?.title || '印章'
}

const handleTypedStampUpload = async () => {
  if (!typedStampForm.company) {
    ElMessage.warning('请输入公司名称')
    return
  }

  if (!typedStampForm.type) {
    ElMessage.warning('请选择印章类型')
    return
  }

  if (typedStampFileList.value.length === 0) {
    ElMessage.warning(`请选择${getTypedStampTitle(typedStampForm.type)}图片`)
    return
  }

  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('bank_stamp_image', typedStampFileList.value[0].raw)
    formData.append('type', typedStampForm.type)
    formData.append('name', typedStampForm.name || getTypedStampDefaultName(typedStampForm.type))
    formData.append('company', typedStampForm.company)
    if (editingTypedStampId.value) {
      formData.append('id', editingTypedStampId.value)
    }

    const response = await uploadTypedStamp(formData)
    if (response.success) {
      ElMessage.success(`${getTypedStampTitle(typedStampForm.type)}上传成功`)
      showTypedStampUpload.value = false
      resetTypedStampUploadForm()
      await loadAllTypedStamps()
    }
  } catch (error) {
    console.error('上传固定类型印章失败:', error)
    ElMessage.error(error.response?.data?.message || '上传失败')
  } finally {
    uploading.value = false
  }
}

const handleUpdateTypedStampPosition = async () => {
  uploading.value = true
  try {
    const response = await updateBankStampPosition({
      ...typedStampPositionForm,
      id: currentTypedStampId.value
    })
    if (response.success) {
      ElMessage.success('位置设置已保存')
      showTypedStampPosition.value = false
      await loadAllTypedStamps()
    }
  } catch (error) {
    console.error('更新位置失败:', error)
    ElMessage.error(error.response?.data?.message || '更新失败')
  } finally {
    uploading.value = false
  }
}

const handleDeleteTypedStamp = async (stamp) => {
  try {
    await ElMessageBox.confirm(`确定要删除"${stamp.company}"的${getTypedStampTitle(stamp.type)}吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const response = await deleteTypedStamp({ id: stamp.id })
    if (response.success) {
      ElMessage.success(`${getTypedStampTitle(stamp.type)}删除成功`)
      await loadAllTypedStamps()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除固定类型印章失败:', error)
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
})

watch(isAdmin, (value) => {
  if (value) {
    loadMySeals()
    loadAllTypedStamps()
    return
  }

  mySeals.value = []
  typedStamps.value = []
}, { immediate: true })
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
  min-height: 180px;
}

.signature-card {
  max-width: 360px;
  margin: 0 auto;
  text-align: center;
}

.signature-image {
  max-width: 100%;
  max-height: 160px;
  object-fit: contain;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  padding: 16px;
  background: #fafafa;
}

.signature-actions {
  margin-top: 16px;
  display: flex;
  gap: 12px;
  justify-content: center;
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

.company-stamp-content {
  min-height: 180px;
}

.company-stamp-collapse {
  border-top: none;
}

.company-collapse-title {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-right: 16px;
  font-weight: 600;
}

.company-stamp-table {
  margin-bottom: 12px;
}

.stamp-preview {
  text-align: center;
}

.stamp-preview-image {
  max-width: 260px;
  max-height: 160px;
  object-fit: contain;
  border: 1px solid #e4e7ed;
  border-radius: 6px;
  padding: 12px;
  background: #fafafa;
}

.stamp-preview-info {
  margin-top: 16px;
  text-align: left;
  color: #606266;
  font-size: 14px;
}

.stamp-preview-info p {
  margin: 6px 0;
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

