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

      <!-- 搜索栏 -->
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

      <!-- 表格 -->
      <el-table
        :data="tableData"
        v-loading="loading"
        border
        style="width: 100%"
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="project_name" label="项目名称" min-width="200" />
        <el-table-column prop="remark" label="备注" min-width="250" show-overflow-tooltip />
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

      <!-- 分页 -->
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

    <!-- 新建/编辑对话框 -->
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
          <el-input
            v-model="form.project_name"
            placeholder="请输入项目名称"
            maxlength="255"
            show-word-limit
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
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getInvoiceProjects,
  createInvoiceProject,
  updateInvoiceProject,
  deleteInvoiceProject
} from '@/api/invoiceProject'
import { formatDate } from '@/utils/dateFormat'

// 搜索表单
const searchForm = reactive({
  keyword: ''
})

// 表格数据
const tableData = ref([])
const loading = ref(false)

// 分页
const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

// 对话框
const dialogVisible = ref(false)
const dialogTitle = ref('')
const isEdit = ref(false)
const formRef = ref(null)
const submitting = ref(false)

// 表单数据
const form = reactive({
  id: null,
  project_name: '',
  remark: ''
})

// 表单验证规则
const formRules = {
  project_name: [
    { required: true, message: '请输入项目名称', trigger: 'blur' },
    { max: 255, message: '项目名称不能超过255个字符', trigger: 'blur' }
  ]
}

// 加载数据
const loadData = async () => {
  loading.value = true
  try {
    const response = await getInvoiceProjects({
      keyword: searchForm.keyword,
      page: pagination.current,
      per_page: pagination.pageSize
    })

    console.log('API响应:', response)

    // 响应拦截器已经返回了 data，所以直接使用 response
    if (response.success) {
      const paginationData = response.data
      
      if (paginationData && paginationData.data) {
        // 分页数据格式
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

// 搜索
const handleSearch = () => {
  pagination.current = 1
  loadData()
}

// 重置
const handleReset = () => {
  searchForm.keyword = ''
  handleSearch()
}

// 新建
const handleCreate = () => {
  isEdit.value = false
  dialogTitle.value = '新建项目'
  resetForm()
  dialogVisible.value = true
}

// 编辑
const handleEdit = (row) => {
  isEdit.value = true
  dialogTitle.value = '编辑项目'
  form.id = row.id
  form.project_name = row.project_name
  form.remark = row.remark
  dialogVisible.value = true
}

// 删除
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
    console.log('删除响应:', response)
    
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

// 提交表单
const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    
    submitting.value = true
    const data = {
      project_name: form.project_name,
      remark: form.remark
    }

    let response
    if (isEdit.value) {
      response = await updateInvoiceProject(form.id, data)
    } else {
      response = await createInvoiceProject(data)
    }

    console.log('提交响应:', response)

    // 响应拦截器已经返回了 data，所以直接使用 response.success
    if (response.success) {
      ElMessage.success(response.message || (isEdit.value ? '更新成功' : '创建成功'))
      dialogVisible.value = false
      loadData()
    } else {
      ElMessage.error(response.message || '操作失败')
    }
  } catch (error) {
    if (error !== false) { // 验证失败会返回false
      console.error('提交失败', error)
      ElMessage.error(error.response?.data?.message || error.message || '操作失败')
    }
  } finally {
    submitting.value = false
  }
}

// 重置表单
const resetForm = () => {
  form.id = null
  form.project_name = ''
  form.remark = ''
  formRef.value?.clearValidate()
}

// 对话框关闭
const handleDialogClose = () => {
  resetForm()
}

// 初始化
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

