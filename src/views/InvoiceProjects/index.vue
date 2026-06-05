<template>
  <div class="invoice-projects-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span class="title">发票项目配置</span>
          <el-button type="primary" @click="handleCreate">
            <el-icon><Plus /></el-icon>
            新建项目
          </el-button>
        </div>
      </template>

      <el-form :inline="true" class="search-form">
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            placeholder="项目名称/备注"
            clearable
            @clear="handleSearch"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table
        :data="tableData"
        v-loading="loading"
        border
        style="width: 100%"
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="project_name" label="项目名称" min-width="180" />
        <el-table-column prop="spec_model" label="规格型号" min-width="140" show-overflow-tooltip />
        <el-table-column prop="unit" label="单位" width="90" />
        <el-table-column prop="quantity" label="数量" width="110" align="right" />
        <el-table-column prop="unit_price" label="单价(不含税)" width="130" align="right" />
        <el-table-column prop="amount" label="金额(不含税)" width="130" align="right" />
        <el-table-column label="税率/征收率" width="120" align="center">
          <template #default="{ row }">
            {{ formatRate(row.tax_rate) }}
          </template>
        </el-table-column>
        <el-table-column prop="tax_amount" label="税额" width="120" align="right" />
        <el-table-column prop="remark" label="备注" min-width="220" show-overflow-tooltip />
        <el-table-column prop="creator.name" label="创建人" width="120" />
        <el-table-column prop="created_at" label="创建时间" width="180">
          <template #default="{ row }">
            {{ formatDate(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.current"
        v-model:page-size="pagination.pageSize"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSearch"
        @current-change="handleSearch"
        style="margin-top: 20px; justify-content: flex-end"
      />
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="680px"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="formRules"
        label-width="120px"
      >
        <el-form-item label="项目名称" prop="project_name">
          <el-input
            v-model="form.project_name"
            placeholder="请输入项目名称"
            maxlength="255"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="规格型号" prop="spec_model">
          <el-input v-model="form.spec_model" placeholder="请输入规格型号" maxlength="255" />
        </el-form-item>
        <el-form-item label="单位" prop="unit">
          <el-input v-model="form.unit" placeholder="请输入单位" maxlength="50" />
        </el-form-item>
        <el-form-item label="数量" prop="quantity">
          <el-input-number
            v-model="form.quantity"
            :min="0"
            :precision="4"
            :controls="false"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="单价(不含税)" prop="unit_price">
          <el-input-number
            v-model="form.unit_price"
            :min="0"
            :precision="2"
            :controls="false"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="金额(不含税)" prop="amount">
          <el-input-number
            v-model="form.amount"
            :min="0"
            :precision="2"
            :controls="false"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="税率/征收率" prop="tax_rate">
          <el-select v-model="form.tax_rate" placeholder="请选择税率/征收率" style="width: 100%">
            <el-option
              v-for="option in taxRateOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="税额" prop="tax_amount">
          <el-input-number
            v-model="form.tax_amount"
            :min="0"
            :precision="2"
            :controls="false"
            disabled
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input
            v-model="form.remark"
            type="textarea"
            :rows="4"
            placeholder="请输入备注"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">
          确定
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getInvoiceProjects,
  createInvoiceProject,
  updateInvoiceProject,
  deleteInvoiceProject
} from '@/api/invoiceProject'
import { formatDate } from '@/utils/dateFormat'

const searchForm = reactive({
  keyword: ''
})

const tableData = ref([])
const loading = ref(false)

const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

const dialogVisible = ref(false)
const dialogTitle = ref('')
const isEdit = ref(false)
const formRef = ref(null)
const submitting = ref(false)

const taxRateOptions = [
  { label: '0%', value: 0 },
  { label: '1%', value: 0.01 },
  { label: '2%', value: 0.02 },
  { label: '3%', value: 0.03 },
  { label: '4%', value: 0.04 },
  { label: '5%', value: 0.05 },
  { label: '6%', value: 0.06 },
  { label: '9%', value: 0.09 },
  { label: '13%', value: 0.13 }
]

const form = reactive({
  id: null,
  project_name: '',
  spec_model: '',
  unit: '',
  quantity: null,
  unit_price: null,
  amount: 0,
  tax_rate: 0,
  tax_amount: 0,
  remark: ''
})

const formRules = {
  project_name: [
    { required: true, message: '请输入项目名称', trigger: 'blur' },
    { max: 255, message: '项目名称不能超过255个字符', trigger: 'blur' }
  ],
  tax_rate: [
    { required: true, message: '请选择税率/征收率', trigger: 'change' }
  ]
}

const roundAmount = (value) => {
  return Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100
}

const syncTaxAmount = () => {
  form.tax_amount = roundAmount(Number(form.amount || 0) * Number(form.tax_rate || 0))
}

const formatRate = (value) => {
  return `${roundAmount(Number(value || 0) * 100)}%`
}

const loadData = async () => {
  loading.value = true
  try {
    const response = await getInvoiceProjects({
      keyword: searchForm.keyword,
      page: pagination.current,
      per_page: pagination.pageSize
    })

    if (response.success) {
      const paginationData = response.data
      if (paginationData && paginationData.data) {
        tableData.value = paginationData.data
        pagination.total = paginationData.total
        pagination.current = paginationData.current_page
      } else {
        ElMessage.error('数据格式错误')
      }
    } else {
      ElMessage.error(response.message || '加载失败')
    }
  } catch (error) {
    console.error('加载数据失败', error)
    ElMessage.error(error.message || '加载数据失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.current = 1
  loadData()
}

const handleReset = () => {
  searchForm.keyword = ''
  handleSearch()
}

const handleCreate = () => {
  isEdit.value = false
  dialogTitle.value = '新建项目'
  resetForm()
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  dialogTitle.value = '编辑项目'
  form.id = row.id
  form.project_name = row.project_name || ''
  form.spec_model = row.spec_model || ''
  form.unit = row.unit || ''
  form.quantity = row.quantity === null || row.quantity === undefined ? null : Number(row.quantity)
  form.unit_price = row.unit_price === null || row.unit_price === undefined ? null : Number(row.unit_price)
  form.amount = Number(row.amount || 0)
  form.tax_rate = Number(row.tax_rate || 0)
  form.tax_amount = Number(row.tax_amount || 0)
  form.remark = row.remark || ''
  dialogVisible.value = true
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除项目"${row.project_name}"吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const response = await deleteInvoiceProject(row.id)
    if (response.success) {
      ElMessage.success(response.message || '删除成功')
      loadData()
    } else {
      ElMessage.error(response.message || '删除失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败', error)
      ElMessage.error(error.response?.data?.message || error.message || '删除失败')
    }
  }
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()

    submitting.value = true
    const data = {
      project_name: form.project_name,
      spec_model: form.spec_model,
      unit: form.unit,
      quantity: form.quantity,
      unit_price: form.unit_price,
      amount: form.amount,
      tax_rate: form.tax_rate,
      tax_amount: form.tax_amount,
      remark: form.remark
    }

    let response
    if (isEdit.value) {
      response = await updateInvoiceProject(form.id, data)
    } else {
      response = await createInvoiceProject(data)
    }

    if (response.success) {
      ElMessage.success(response.message || (isEdit.value ? '更新成功' : '创建成功'))
      dialogVisible.value = false
      loadData()
    } else {
      ElMessage.error(response.message || '操作失败')
    }
  } catch (error) {
    if (error !== false) {
      console.error('提交失败', error)
      ElMessage.error(error.response?.data?.message || error.message || '操作失败')
    }
  } finally {
    submitting.value = false
  }
}

const resetForm = () => {
  form.id = null
  form.project_name = ''
  form.spec_model = ''
  form.unit = ''
  form.quantity = null
  form.unit_price = null
  form.amount = 0
  form.tax_rate = 0
  form.tax_amount = 0
  form.remark = ''
  formRef.value?.clearValidate()
}

const handleDialogClose = () => {
  resetForm()
}

watch(
  () => [form.amount, form.tax_rate],
  () => {
    syncTaxAmount()
  }
)

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.invoice-projects-container {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header .title {
  font-size: 18px;
  font-weight: bold;
}

.search-form {
  margin-bottom: 20px;
}
</style>
