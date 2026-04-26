<template>
  <div class="assessment-container">
    <div class="page-header">
      <h2>考核记录</h2>
    </div>

    <el-card class="filter-card">
      <el-form :model="filterForm" inline>
        <el-form-item label="业务类型">
          <el-select v-model="filterForm.business_type" placeholder="请选择业务类型" clearable style="width: 150px">
            <el-option label="全部" value="" />
            <el-option label="参保入职" value="insurance_enrollment" />
            <el-option label="合同签署" value="contract_signing" />
            <el-option label="工资发放" value="salary_payment" />
            <el-option label="发票处理" value="invoice_processing" />
            <el-option label="资料收集" value="document_upload" />
            <el-option label="合同管理" value="contract_management" />
          </el-select>
        </el-form-item>
        <el-form-item label="时间范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            style="width: 240px"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadRecords">查询</el-button>
          <el-button @click="resetFilter">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <el-table :data="records" v-loading="loading" border stripe>
        <el-table-column prop="business_type_text" label="业务类型" width="120" />
        <el-table-column prop="business_name" label="业务描述" min-width="180" />
        <el-table-column prop="remark" label="备注" min-width="220" show-overflow-tooltip />
        <el-table-column label="申诉状态" width="120">
          <template #default="{ row }">
            <el-tag v-if="row.latest_appeal" :type="getAppealStatusType(row.latest_appeal.status)">
              {{ row.latest_appeal.status_text }}
            </el-tag>
            <span v-else>未申诉</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="showAppealButton(row)"
              link
              type="primary"
              @click="openAppealDialog(row)"
            >
              申诉
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :page-sizes="[10, 20, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadRecords"
          @current-change="loadRecords"
        />
      </div>
    </el-card>

    <el-dialog v-model="appealDialogVisible" title="发起申诉" width="600px">
      <el-form :model="appealForm" label-width="90px">
        <el-form-item label="申诉说明">
          <el-input
            v-model="appealForm.description"
            type="textarea"
            :rows="4"
            maxlength="1000"
            show-word-limit
            placeholder="请输入申诉说明"
          />
        </el-form-item>
        <el-form-item label="申诉图片">
          <el-upload
            list-type="picture-card"
            :file-list="appealFileList"
            :http-request="handleAppealImageUpload"
            :on-remove="handleAppealImageRemove"
            :limit="9"
            accept="image/png,image/jpeg,image/jpg"
          >
            <el-icon><Plus /></el-icon>
          </el-upload>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="closeAppealDialog">取消</el-button>
        <el-button type="primary" :loading="appealSubmitting" @click="submitAppeal">提交申诉</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import {
  getAssessmentRecords,
  submitAssessmentAppeal,
  uploadAssessmentAppealImage
} from '@/api/assessment'

const accountSetStore = useAccountSetStore()
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

const loading = ref(false)
const records = ref([])

const filterForm = ref({
  business_type: '',
  status: '',
  handler_id: '',
  start_date: '',
  end_date: ''
})

const dateRange = ref([])

const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})

const appealDialogVisible = ref(false)
const appealSubmitting = ref(false)
const currentAppealRecordId = ref(null)
const appealFileList = ref([])
const appealForm = ref({
  description: '',
  images: []
})

const loadRecords = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  loading.value = true
  try {
    const response = await getAssessmentRecords({
      account_set_id: currentAccountSetId.value,
      ...filterForm.value,
      page: pagination.value.page,
      per_page: pagination.value.per_page
    })

    if (response.success) {
      records.value = response.data
      pagination.value.total = response.total
    }
  } catch (error) {
    ElMessage.error('加载考核记录失败')
  } finally {
    loading.value = false
  }
}

const handleDateChange = (val) => {
  if (val && val.length === 2) {
    filterForm.value.start_date = formatDate(val[0])
    filterForm.value.end_date = formatDate(val[1])
  } else {
    filterForm.value.start_date = ''
    filterForm.value.end_date = ''
  }
}

const resetFilter = () => {
  filterForm.value = {
    business_type: '',
    status: '',
    handler_id: '',
    start_date: '',
    end_date: ''
  }
  dateRange.value = []
  pagination.value.page = 1
  loadRecords()
}

const openAppealDialog = (row) => {
  currentAppealRecordId.value = row.id
  appealForm.value.description = ''
  appealForm.value.images = []
  appealFileList.value = []
  appealDialogVisible.value = true
}

const closeAppealDialog = () => {
  appealDialogVisible.value = false
  currentAppealRecordId.value = null
  appealForm.value.description = ''
  appealForm.value.images = []
  appealFileList.value = []
}

const handleAppealImageUpload = async (option) => {
  try {
    const formData = new FormData()
    formData.append('file', option.file)
    const response = await uploadAssessmentAppealImage(formData)

    if (response.success) {
      appealForm.value.images.push(response.data.file_path)
      appealFileList.value.push({
        name: response.data.file_name,
        url: response.data.url,
        path: response.data.file_path
      })
      option.onSuccess(response)
      return
    }

    option.onError(new Error('上传失败'))
  } catch (error) {
    option.onError(error)
    ElMessage.error('图片上传失败')
  }
}

const handleAppealImageRemove = (file) => {
  if (!file.path) {
    return
  }

  appealForm.value.images = appealForm.value.images.filter(path => path !== file.path)
}

const submitAppeal = async () => {
  if (!currentAppealRecordId.value) {
    return
  }

  if (!appealForm.value.description.trim()) {
    ElMessage.warning('请填写申诉说明')
    return
  }

  if (appealForm.value.images.length === 0) {
    ElMessage.warning('请上传至少1张申诉图片')
    return
  }

  appealSubmitting.value = true
  try {
    const response = await submitAssessmentAppeal(currentAppealRecordId.value, {
      description: appealForm.value.description,
      images: appealForm.value.images
    })

    if (response.success) {
      ElMessage.success('申诉提交成功')
      closeAppealDialog()
      loadRecords()
    }
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '申诉提交失败')
  } finally {
    appealSubmitting.value = false
  }
}

const getAppealStatusType = (status) => {
  if (status === 'approved') return 'success'
  if (status === 'rejected') return 'danger'
  return 'warning'
}

const showAppealButton = (row) => {
  if (!row || row.latest_appeal) {
    return false
  }

  if (!row.deadline_date) {
    return true
  }

  const deadline = new Date(row.deadline_date)
  if (Number.isNaN(deadline.getTime())) {
    return false
  }

  return Date.now() <= deadline.getTime()
}

const formatDate = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  return d.toLocaleDateString('zh-CN')
}

const formatDateTime = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  return d.toLocaleString('zh-CN')
}

onMounted(() => {
  if (currentAccountSetId.value) {
    loadRecords()
  }
})
</script>

<style scoped>
.assessment-container {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 24px;
  color: #303133;
}

.filter-card {
  margin-bottom: 20px;
}

.table-card {
  margin-bottom: 20px;
}

.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
