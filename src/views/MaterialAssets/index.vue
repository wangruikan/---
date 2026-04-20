<template>
  <div class="material-assets-page">
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    <div v-else>
      <div class="page-header">
        <h1>资料管理</h1>
        <div class="header-actions">
          <el-button type="primary" @click="openCreate">
            <el-icon><Plus /></el-icon>
            上传资料
          </el-button>
        </div>
      </div>

      <el-card shadow="never" style="margin-bottom: 16px;">
        <el-form :inline="true" :model="query">
          <el-form-item label="状态">
            <el-select v-model="query.status" clearable placeholder="全部">
              <el-option label="已归档" value="archived" />
              <el-option label="申请中" value="applying" />
              <el-option label="使用中" value="in_use" />
            </el-select>
          </el-form-item>
          <el-form-item label="关键词">
            <el-input v-model="query.keyword" clearable placeholder="名称" @keyup.enter="loadData" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadData">查询</el-button>
            <el-button @click="resetQuery">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <el-card shadow="never">
        <el-table :data="rows" border stripe v-loading="loading">
          <el-table-column type="index" label="序号" width="60" align="center" />
          <el-table-column prop="name" label="资料名称" min-width="160" />
          <el-table-column prop="status" label="状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="getStatusTagType(row.status)">{{ getStatusText(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="文件" min-width="260" show-overflow-tooltip>
            <template #default="{ row }">
              <div style="display:flex; align-items:center; gap:8px;">
                <el-link v-if="row.file_url" type="primary" @click="openFile(row)">
                  {{ row.file_name || '查看' }}
                </el-link>
                <span v-else style="color:#999;">-</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="creator.name" label="上传人" width="120" />
          <el-table-column prop="created_at" label="上传时间" width="180" />
          <el-table-column label="操作" width="160" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="openEdit(row)">编辑</el-button>
              <el-button link type="danger" size="small" @click="handleDelete(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>

        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadData"
          @current-change="loadData"
          style="margin-top: 16px; justify-content: flex-end;"
        />
      </el-card>

      <!-- 新增/编辑 -->
      <el-dialog v-model="dialogVisible" :title="dialogMode === 'create' ? '上传资料' : '编辑资料'" width="600px">
        <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
          <el-form-item label="资料名称" prop="name">
            <el-input v-model="form.name" placeholder="例如：公章、营业执照" />
          </el-form-item>
          <el-form-item label="说明" prop="description">
            <el-input v-model="form.description" type="textarea" :rows="3" placeholder="可选" />
          </el-form-item>
          <el-form-item v-if="dialogMode === 'create'" label="文件" prop="file">
            <el-upload :auto-upload="false" :limit="1" :on-change="onFileChange" :on-remove="onFileRemove">
              <el-button type="primary">选择文件</el-button>
              <template #tip>
                <div class="el-upload__tip">单个文件不超过50MB</div>
              </template>
            </el-upload>
          </el-form-item>
          <el-form-item v-else label="附件管理">
            <div style="width: 100%;">
              <div style="margin-bottom: 10px; display:flex; align-items:center; justify-content: space-between;">
                <div style="color:#606266; font-size:12px;">
                  可预览/删除/新增附件（资料处于申请中/使用中时，后端会拒绝增删）
                </div>
                <el-upload
                  :auto-upload="false"
                  :show-file-list="false"
                  :on-change="handleAddAttachment"
                >
                  <el-button type="primary" size="small">新增附件</el-button>
                </el-upload>
              </div>

              <el-table :data="editFiles" size="small" border>
                <el-table-column prop="file_name" label="文件名" min-width="220" show-overflow-tooltip />
                <el-table-column prop="created_at" label="上传时间" width="160" />
                <el-table-column label="操作" width="160" align="center">
                  <template #default="{ row }">
                    <el-button link type="primary" size="small" @click="previewAttachment(row)">预览</el-button>
                    <el-button link type="danger" size="small" @click="deleteAttachment(row)">删除</el-button>
                  </template>
                </el-table-column>
              </el-table>
            </div>
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="saving" @click="submitForm">保存</el-button>
        </template>
      </el-dialog>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import {
  getMaterialAssets,
  getMaterialAssetDetail,
  createMaterialAsset,
  updateMaterialAsset,
  uploadMaterialAssetFile,
  deleteMaterialAssetFile,
  deleteMaterialAsset
} from '@/api/materialAssets'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

const loading = ref(false)
const saving = ref(false)
const rows = ref([])

const query = reactive({
  status: '',
  keyword: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

const dialogVisible = ref(false)
const dialogMode = ref('create') // create | edit
const formRef = ref()
const selectedFile = ref(null)
const editingId = ref(null)
const editFiles = ref([])

const form = reactive({
  name: '',
  description: ''
})

const rules = {
  name: [{ required: true, message: '请输入资料名称', trigger: 'blur' }]
}

const getStatusText = (status) => {
  const map = { archived: '已归档', applying: '申请中', in_use: '使用中' }
  return map[status] || status
}

const getStatusTagType = (status) => {
  const map = { archived: 'success', applying: 'warning', in_use: 'danger' }
  return map[status] || 'info'
}

const resetQuery = () => {
  query.status = ''
  query.keyword = ''
  pagination.page = 1
  loadData()
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await getMaterialAssets({
      status: query.status || undefined,
      keyword: query.keyword || undefined,
      page: pagination.page,
      per_page: pagination.pageSize
    })
    rows.value = res.data || []
    pagination.total = res.pagination?.total || 0
  } catch (e) {
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

const onFileChange = (file) => {
  selectedFile.value = file.raw
}

const onFileRemove = () => {
  selectedFile.value = null
}

const resetForm = () => {
  form.name = ''
  form.description = ''
  selectedFile.value = null
  editingId.value = null
  editFiles.value = []
  formRef.value?.clearValidate?.()
}

const openCreate = () => {
  dialogMode.value = 'create'
  resetForm()
  dialogVisible.value = true
}

const openEdit = async (row) => {
  dialogMode.value = 'edit'
  resetForm()
  editingId.value = row.id
  try {
    const res = await getMaterialAssetDetail(row.id)
    const data = res.data || {}
    form.name = data.name || ''
    form.description = data.description || ''
    editFiles.value = data.files || []
  } catch (e) {
    ElMessage.error('加载详情失败')
    return
  }
  dialogVisible.value = true
}

const previewAttachment = (fileRow) => {
  if (fileRow?.file_url) {
    window.open(fileRow.file_url, '_blank')
  }
}

const handleAddAttachment = async (file) => {
  if (!editingId.value) return
  const raw = file?.raw
  if (!raw) return
  try {
    const fd = new FormData()
    fd.append('file', raw)
    await uploadMaterialAssetFile(editingId.value, fd)
    ElMessage.success('附件上传成功')
    const detail = await getMaterialAssetDetail(editingId.value)
    editFiles.value = detail.data?.files || []
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '附件上传失败')
  }
}

const deleteAttachment = async (fileRow) => {
  if (!editingId.value || !fileRow?.id) return
  try {
    await ElMessageBox.confirm(`确定删除附件【${fileRow.file_name}】吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await deleteMaterialAssetFile(editingId.value, fileRow.id)
    ElMessage.success('删除成功')
    const detail = await getMaterialAssetDetail(editingId.value)
    editFiles.value = detail.data?.files || []
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.response?.data?.message || '删除失败')
    }
  }
}

const submitForm = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return

    // 创建模式必须选择文件；编辑模式可选
    if (dialogMode.value === 'create' && !selectedFile.value) {
      ElMessage.error('请选择文件')
      return
    }

    saving.value = true
    try {
      if (dialogMode.value === 'create') {
        const fd = new FormData()
        fd.append('name', form.name)
        if (form.description) fd.append('description', form.description)
        fd.append('file', selectedFile.value)
        await createMaterialAsset(fd)
        ElMessage.success('上传成功')
      } else {
        const fd = new FormData()
        if (form.name) fd.append('name', form.name)
        if (form.description) fd.append('description', form.description)
        await updateMaterialAsset(editingId.value, fd)
        ElMessage.success('更新成功')
      }

      dialogVisible.value = false
      loadData()
    } catch (e) {
      ElMessage.error(e.response?.data?.message || e.message || '保存失败')
    } finally {
      saving.value = false
    }
  })
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除资料【${row.name}】吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await deleteMaterialAsset(row.id)
    ElMessage.success('删除成功')
    loadData()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.response?.data?.message || '删除失败')
    }
  }
}

const openFile = (row) => {
  if (row.file_url) {
    window.open(row.file_url, '_blank')
  }
}

watch(() => accountSetStore.currentAccountSetId, () => {
  pagination.page = 1
  loadData()
})

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.material-assets-page {
  padding: 20px;
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.page-header h1 {
  font-size: 24px;
  margin: 0;
}
.header-actions {
  display: flex;
  gap: 10px;
}
</style>

