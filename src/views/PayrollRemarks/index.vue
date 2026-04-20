<template>
  <div class="payroll-remarks-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>工资表备注事项管理</span>
        </div>
      </template>

      <!-- 筛选表单 -->
      <el-form :inline="true" :model="searchForm" class="search-form">
        <el-form-item label="年份">
          <el-select v-model="searchForm.year" placeholder="请选择年份" clearable>
            <el-option
              v-for="year in years"
              :key="year"
              :label="year + '年'"
              :value="year"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="月份">
          <el-select v-model="searchForm.month" placeholder="请选择月份" clearable>
            <el-option
              v-for="month in 12"
              :key="month"
              :label="`${month}月`"
              :value="month"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
          <el-button type="success" @click="handleAdd">添加备注</el-button>
        </el-form-item>
      </el-form>

      <!-- 表格 -->
      <el-table
        :data="tableData"
        v-loading="loading"
        border
        style="width: 100%"
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="project_name" label="项目名称" width="200" />
        <el-table-column label="期间" width="120" align="center">
          <template #default="{ row }">
            {{ row.year }}-{{ String(row.month).padStart(2, '0') }}
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="工资表备注" min-width="300">
          <template #default="{ row }">
            <div class="remark-cell">
              {{ row.remark || '-' }}
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="creator.name" label="创建人" width="100" />
        <el-table-column prop="created_at" label="创建时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <el-pagination
        v-if="total > 0"
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadData"
        @current-change="loadData"
        style="margin-top: 20px; justify-content: flex-end"
      />
    </el-card>

    <!-- 添加/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="formRules"
        label-width="100px"
      >
        <el-form-item label="项目名称" prop="project_name">
          <el-select
            v-model="form.project_name"
            placeholder="请选择项目"
            filterable
            :disabled="isEdit"
            style="width: 100%"
          >
            <el-option
              v-for="project in projects"
              :key="project"
              :label="project"
              :value="project"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="年份" prop="year">
          <el-select
            v-model="form.year"
            placeholder="请选择年份"
            :disabled="isEdit"
            style="width: 100%"
          >
            <el-option
              v-for="year in years"
              :key="year"
              :label="year + '年'"
              :value="year"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="月份" prop="month">
          <el-select
            v-model="form.month"
            placeholder="请选择月份"
            :disabled="isEdit"
            style="width: 100%"
          >
            <el-option
              v-for="month in 12"
              :key="month"
              :label="`${month}月`"
              :value="month"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="工资表备注" prop="remark">
          <el-input
            v-model="form.remark"
            type="textarea"
            :rows="4"
            placeholder="请输入工资表备注"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitLoading">
          确定
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import request from '@/api/request'
import { useAccountSetStore } from '@/stores/accountSet'

const accountSetStore = useAccountSetStore()

// 年份选项
const currentYear = new Date().getFullYear()
const years = ref([])
for (let i = currentYear - 5; i <= currentYear + 1; i++) {
  years.value.push(i)
}

// 搜索表单
const searchForm = reactive({
  year: null,
  month: null
})

// 表格数据
const tableData = ref([])
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)

// 项目列表
const projects = ref([])

// 对话框
const dialogVisible = ref(false)
const dialogTitle = computed(() => isEdit.value ? '编辑备注' : '添加备注')
const isEdit = ref(false)
const submitLoading = ref(false)

// 表单
const formRef = ref(null)
const form = reactive({
  id: null,
  project_name: '',
  year: null,
  month: null,
  remark: ''
})

const formRules = {
  project_name: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  year: [
    { required: true, message: '请选择年份', trigger: 'change' }
  ],
  month: [
    { required: true, message: '请选择月份', trigger: 'change' }
  ]
}

// 加载数据
const loadData = async () => {
  loading.value = true
  try {
    const accountSetId = accountSetStore.currentAccountSetId
    const response = await request({
      url: '/payroll-remarks',
      method: 'get',
      params: {
        account_set_id: accountSetId,
        year: searchForm.year,
        month: searchForm.month
      }
    })

    if (response.success) {
      tableData.value = response.data.remarks || []
      projects.value = response.data.projects || []
      total.value = tableData.value.length
    }
  } catch (error) {
    console.error('加载数据失败', error)
    ElMessage.error(error.response?.data?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

// 搜索
const handleSearch = () => {
  currentPage.value = 1
  loadData()
}

// 重置
const handleReset = () => {
  searchForm.year = null
  searchForm.month = null
  handleSearch()
}

// 添加
const handleAdd = () => {
  isEdit.value = false
  dialogVisible.value = true
  // 默认当前年月
  const now = new Date()
  form.year = now.getFullYear()
  form.month = now.getMonth() + 1
}

// 编辑
const handleEdit = (row) => {
  isEdit.value = true
  form.id = row.id
  form.project_name = row.project_name
  form.year = row.year
  form.month = row.month
  form.remark = row.remark
  dialogVisible.value = true
}

// 提交
const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    submitLoading.value = true
    try {
      const accountSetId = accountSetStore.currentAccountSetId
      const response = await request({
        url: '/payroll-remarks',
        method: 'post',
        data: {
          account_set_id: accountSetId,
          project_name: form.project_name,
          year: form.year,
          month: form.month,
          remark: form.remark
        }
      })

      if (response.success) {
        ElMessage.success(response.message)
        dialogVisible.value = false
        loadData()
      }
    } catch (error) {
      console.error('保存失败', error)
      ElMessage.error(error.response?.data?.message || '保存失败')
    } finally {
      submitLoading.value = false
    }
  })
}

// 删除
const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(
      '确定要删除这条备注吗？',
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const response = await request({
      url: `/payroll-remarks/${row.id}`,
      method: 'delete'
    })

    if (response.success) {
      ElMessage.success('删除成功')
      loadData()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 关闭对话框
const handleDialogClose = () => {
  formRef.value?.resetFields()
  form.id = null
  form.project_name = ''
  form.year = null
  form.month = null
  form.remark = ''
}

// 格式化日期时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  const date = new Date(dateTime)
  return date.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  })
}

// 初始化
onMounted(() => {
  loadData()
})
</script>

<style scoped>
.payroll-remarks-container {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.search-form {
  margin-bottom: 20px;
}

.remark-cell {
  max-height: 60px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
</style>

