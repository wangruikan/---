<template>
  <div class="material-requests-page">
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    <div v-else>
      <div class="page-header">
        <h1>资料申请</h1>
        <div class="header-actions">
          <el-button type="primary" @click="openCreate">
            <el-icon><Plus /></el-icon>
            发起申请
          </el-button>
        </div>
      </div>

      <el-card shadow="never" style="margin-bottom: 16px;">
        <el-form :inline="true" :model="query">
          <el-form-item label="状态">
            <el-select v-model="query.status" clearable placeholder="全部">
              <el-option label="申请中" value="pending" />
              <el-option label="使用中" value="in_use" />
              <el-option label="已归档" value="archived" />
              <el-option label="已驳回" value="rejected" />
              <el-option label="已撤回" value="withdrawn" />
            </el-select>
          </el-form-item>
          <el-form-item label="关键词">
            <el-input v-model="query.keyword" clearable placeholder="事由关键字" @keyup.enter="loadData" />
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
          <el-table-column prop="applicant.name" label="申请人" width="120" />
          <el-table-column prop="reason" label="事由" min-width="200" show-overflow-tooltip />
          <el-table-column prop="expected_return_date" label="预计归还日期" width="140" />
          <el-table-column label="资料数量" width="120" align="center">
            <template #default="{ row }">
              <span>{{ row.returned_items || 0 }}/{{ row.total_items || 0 }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="110" align="center">
            <template #default="{ row }">
              <el-tag :type="getStatusTagType(row.status)">{{ getStatusText(row.status, row) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="申请时间" width="180" />
          <el-table-column label="操作" width="220" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="openDetail(row)">查看</el-button>
              <el-button
                v-if="row.status === 'in_use' && (row.returned_items || 0) < (row.total_items || 0)"
                link
                type="success"
                size="small"
                @click="openReturn(row)"
              >
                归还
              </el-button>
              <el-tag v-if="row.status === 'pending'" type="warning" size="small">审批中</el-tag>
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

      <!-- 发起申请 -->
      <el-dialog v-model="createDialogVisible" title="发起资料申请" width="900px" :close-on-click-modal="false">
        <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="120px">
          <el-form-item label="预计归还日期" prop="expected_return_date">
            <el-date-picker
              v-model="createForm.expected_return_date"
              type="date"
              value-format="YYYY-MM-DD"
              format="YYYY-MM-DD"
              placeholder="请选择日期"
              style="width: 100%;"
            />
          </el-form-item>
          <el-form-item label="事由" prop="reason">
            <el-input v-model="createForm.reason" type="textarea" :rows="3" placeholder="请输入使用事由" />
          </el-form-item>
          <el-form-item label="盖章方式" prop="stamp_method">
            <el-radio-group v-model="createForm.stamp_method">
              <el-radio label="online">线上</el-radio>
              <el-radio label="offline">线下</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="选择资料" prop="material_ids">
            <el-select
              v-model="createForm.material_ids"
              multiple
              filterable
              placeholder="可多选（仅显示已归档可申请的资料）"
              style="width: 100%;"
              :loading="loadingMaterials"
            >
              <el-option
                v-for="m in availableMaterials"
                :key="m.id"
                :label="`${m.name}${m.category ? '（' + m.category + '）' : ''}`"
                :value="m.id"
              />
            </el-select>
          </el-form-item>

          <!-- 复用“选择其他附件”组件：仅上传其他附件 -->
          <PaymentAttachmentUploader
            ref="attachmentUploaderRef"
            v-model:other-file-list="attachmentFileList"
            :show-invoice-upload="false"
            :other-limit="10"
            :show-form-generator="false"
            :show-signature-stamp="false"
          />
          <div style="color:#999; font-size:12px; margin-top:-8px;">
            说明：申请提交后，会创建审批流程；附件会上传到该审批实例中，审批管理里也能看到。
          </div>
        </el-form>
        <template #footer>
          <el-button @click="createDialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="submitting" @click="submitCreate">提交申请</el-button>
        </template>
      </el-dialog>

      <!-- 详情 -->
      <el-dialog v-model="detailDialogVisible" title="申请详情" width="900px">
        <div v-if="currentRow">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="申请人">{{ currentRow.applicant?.name }}</el-descriptions-item>
            <el-descriptions-item label="预计归还日期">{{ currentRow.expected_return_date }}</el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="getStatusTagType(currentRow.status)">{{ getStatusText(currentRow.status, currentRow) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="申请时间">{{ currentRow.created_at }}</el-descriptions-item>
            <el-descriptions-item label="事由" :span="2">{{ currentRow.reason }}</el-descriptions-item>
            <el-descriptions-item label="资料明细" :span="2">
              <el-table :data="currentRow.items || []" size="small" border>
                <el-table-column prop="material.name" label="资料名称" min-width="180" />
                <el-table-column prop="material.category" label="分类" width="140" />
                <el-table-column prop="status" label="状态" width="110" align="center">
                  <template #default="{ row }">
                    <el-tag size="small" :type="getItemStatusTagType(row.status)">{{ getItemStatusText(row.status) }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="returned_at" label="归还时间" width="180" />
              </el-table>
            </el-descriptions-item>
            <el-descriptions-item label="附件" :span="2">
              <div v-if="currentRow.approval_instance?.attachments?.length">
                <div v-for="att in currentRow.approval_instance.attachments" :key="att.id" style="margin-bottom: 6px;">
                  <el-link type="primary" @click="openAttachment(att)">{{ att.file_name }}</el-link>
                </div>
              </div>
              <div v-else style="color:#999;">暂无附件</div>
            </el-descriptions-item>
          </el-descriptions>
        </div>
        <template #footer>
          <el-button @click="detailDialogVisible = false">关闭</el-button>
        </template>
      </el-dialog>

      <!-- 归还 -->
      <el-dialog v-model="returnDialogVisible" title="归还资料" width="700px">
        <div v-if="returnTarget">
          <div style="margin-bottom: 10px; color:#606266;">
            可分开归还：选择要归还的资料（仅显示使用中的资料）
          </div>
          <el-checkbox-group v-model="returnSelectedIds">
            <div
              v-for="it in (returnTarget.items || []).filter(i => i.status === 'in_use')"
              :key="it.material_asset_id"
              style="padding: 8px 10px; border: 1px solid #ebeef5; border-radius: 6px; margin-bottom: 8px;"
            >
              <el-checkbox :label="it.material_asset_id">
                {{ it.material?.name }}{{ it.material?.category ? '（' + it.material.category + '）' : '' }}
              </el-checkbox>
            </div>
          </el-checkbox-group>
          <div v-if="!(returnTarget.items || []).some(i => i.status === 'in_use')" style="color:#999;">
            没有可归还的资料
          </div>
        </div>
        <template #footer>
          <el-button @click="returnDialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="returning" @click="confirmReturn">确认归还</el-button>
        </template>
      </el-dialog>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'
import PaymentAttachmentUploader from '@/components/PaymentAttachmentUploader.vue'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import { getMaterialAssets } from '@/api/materialAssets'
import { getMaterialRequests, createMaterialRequest, getMaterialRequestDetail, returnMaterialRequestMaterials } from '@/api/materialRequests'
import request from '@/api/request'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

const loading = ref(false)
const submitting = ref(false)
const returning = ref(false)

const rows = ref([])
const query = reactive({ status: '', keyword: '' })
const pagination = reactive({ page: 1, pageSize: 20, total: 0 })

const createDialogVisible = ref(false)
const createFormRef = ref()
const createForm = reactive({
  expected_return_date: '',
  reason: '',
  stamp_method: 'online',
  material_ids: []
})
const createRules = {
  expected_return_date: [{ required: true, message: '请选择预计归还日期', trigger: 'change' }],
  reason: [{ required: true, message: '请输入事由', trigger: 'blur' }],
  stamp_method: [{ required: true, message: '请选择盖章方式', trigger: 'change' }],
  material_ids: [{ type: 'array', required: true, message: '请选择资料', trigger: 'change' }]
}

const availableMaterials = ref([])
const loadingMaterials = ref(false)

const attachmentFileList = ref([])
const attachmentUploaderRef = ref(null)

const detailDialogVisible = ref(false)
const currentRow = ref(null)

const returnDialogVisible = ref(false)
const returnTarget = ref(null)
const returnSelectedIds = ref([])

const getStatusText = (status, row) => {
  const base = {
    pending: '申请中',
    in_use: '使用中',
    archived: '已归档',
    rejected: '已驳回',
    withdrawn: '已撤回'
  }[status] || status

  if (status === 'in_use' && row && (row.returned_items || 0) > 0 && (row.returned_items || 0) < (row.total_items || 0)) {
    return `${base}（已归还${row.returned_items}/${row.total_items}）`
  }
  return base
}

const getStatusTagType = (status) => {
  const map = { pending: 'warning', in_use: 'danger', archived: 'success', rejected: 'danger', withdrawn: 'info' }
  return map[status] || 'info'
}

const getItemStatusText = (status) => {
  const map = { pending: '申请中', in_use: '使用中', returned: '已归档', cancelled: '已取消' }
  return map[status] || status
}

const getItemStatusTagType = (status) => {
  const map = { pending: 'warning', in_use: 'danger', returned: 'success', cancelled: 'info' }
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
    const res = await getMaterialRequests({
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

const loadAvailableMaterials = async () => {
  loadingMaterials.value = true
  try {
    const res = await getMaterialAssets({ status: 'archived', per_page: 200 })
    availableMaterials.value = res.data || []
  } catch (e) {
    availableMaterials.value = []
  } finally {
    loadingMaterials.value = false
  }
}

const openCreate = async () => {
  createForm.expected_return_date = new Date().toISOString().split('T')[0]
  createForm.reason = ''
  createForm.stamp_method = 'online'
  createForm.material_ids = []
  attachmentFileList.value = []
  await loadAvailableMaterials()
  createDialogVisible.value = true
}

const uploadApprovalAttachments = async (instanceId) => {
  if (!instanceId) return
  if (!attachmentFileList.value || attachmentFileList.value.length === 0) return

  for (const fileItem of attachmentFileList.value) {
    try {
      const formData = new FormData()
      formData.append('file', fileItem.raw || fileItem)
      await request.post(`/approvals/${instanceId}/upload-attachment`, formData)
    } catch (e) {
      // 附件失败不影响主流程
      console.error('上传附件失败:', e)
    }
  }
}

const submitCreate = async () => {
  if (!createFormRef.value) return
  await createFormRef.value.validate(async (valid) => {
    if (!valid) return

    submitting.value = true
    try {
      const res = await createMaterialRequest({
        expected_return_date: createForm.expected_return_date,
        reason: createForm.reason,
        stamp_method: createForm.stamp_method,
        material_ids: createForm.material_ids
      })

      const instanceId = res.data?.approval_instance_id
      await uploadApprovalAttachments(instanceId)

      ElMessage.success('已发起申请')
      createDialogVisible.value = false
      loadData()
    } catch (e) {
      ElMessage.error(e.response?.data?.message || e.message || '提交失败')
    } finally {
      submitting.value = false
    }
  })
}

const openDetail = async (row) => {
  try {
    const res = await getMaterialRequestDetail(row.id)
    currentRow.value = res.data
    detailDialogVisible.value = true
  } catch (e) {
    ElMessage.error('加载详情失败')
  }
}

const openAttachment = (att) => {
  if (!att?.file_path) return
  const baseURL = import.meta.env.VITE_API_BASE_URL || ''
  const needsStoragePrefix = !att.file_path.includes('attendance-attachments')
    && !att.file_path.includes('process_approvals')
    && !att.file_path.includes('salary_approvals')
  const fileUrl = needsStoragePrefix ? `${baseURL}/storage/${att.file_path}` : `${baseURL}/${att.file_path}`
  window.open(fileUrl, '_blank')
}

const openReturn = async (row) => {
  try {
    const res = await getMaterialRequestDetail(row.id)
    returnTarget.value = res.data
    returnSelectedIds.value = (returnTarget.value.items || [])
      .filter(i => i.status === 'in_use')
      .map(i => i.material_asset_id)
    returnDialogVisible.value = true
  } catch (e) {
    ElMessage.error('加载失败')
  }
}

const confirmReturn = async () => {
  if (!returnTarget.value) return
  if (!returnSelectedIds.value || returnSelectedIds.value.length === 0) {
    ElMessage.warning('请选择要归还的资料')
    return
  }
  returning.value = true
  try {
    await returnMaterialRequestMaterials(returnTarget.value.id, {
      material_ids: returnSelectedIds.value
    })
    ElMessage.success('归还成功')
    returnDialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '归还失败')
  } finally {
    returning.value = false
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
.material-requests-page {
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

