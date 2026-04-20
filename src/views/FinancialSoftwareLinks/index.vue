<template>
  <div class="financial-software-links-container">
    <!-- 头部操作区 -->
    <el-card class="header-card">
      <el-row :gutter="20">
        <el-col :span="18">
          <el-form :model="filterForm" inline>
            <el-form-item label="软件名称">
              <el-input
                v-model="filterForm.name"
                placeholder="请输入软件名称"
                clearable
                style="width: 200px;"
              />
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filterForm.is_active" placeholder="全部" clearable style="width: 120px;">
                <el-option label="启用" :value="true" />
                <el-option label="禁用" :value="false" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
              <el-button :icon="Refresh" @click="handleReset">重置</el-button>
            </el-form-item>
          </el-form>
        </el-col>
        <el-col :span="6" style="text-align: right;">
          <el-button type="primary" :icon="Plus" @click="handleCreate">添加软件链接</el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 列表区 -->
    <el-card class="table-card">
      <el-table :data="linkList" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="name" label="软件名称" min-width="180" />
        <el-table-column label="软件地址" min-width="300">
          <template #default="{ row }">
            <el-link :href="row.url" target="_blank" type="primary">
              {{ row.url }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="80" align="center" />
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'">
              {{ row.is_active ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建人" width="100">
          <template #default="{ row }">
            {{ row.creator?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" :icon="Edit" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 新增/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="100px"
      >
        <el-form-item label="软件名称" prop="name">
          <el-input
            v-model="formData.name"
            placeholder="请输入软件名称"
            maxlength="100"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="软件地址" prop="url">
          <el-input
            v-model="formData.url"
            placeholder="请输入完整的软件地址，如：https://www.example.com"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="排序序号" prop="sort_order">
          <el-input-number
            v-model="formData.sort_order"
            :min="0"
            :step="1"
            controls-position="right"
            placeholder="数字越小越靠前"
          />
        </el-form-item>
        <el-form-item label="启用状态" prop="is_active">
          <el-switch v-model="formData.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  Search, Refresh, Plus, Edit, Delete 
} from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import {
  getFinancialSoftwareLinks,
  createFinancialSoftwareLink,
  updateFinancialSoftwareLink,
  deleteFinancialSoftwareLink
} from '@/api/financialSoftwareLinks'

const accountSetStore = useAccountSetStore()

// 筛选表单
const filterForm = reactive({
  name: '',
  is_active: null
})

// 列表数据
const loading = ref(false)
const linkList = ref([])

// 对话框
const dialogVisible = ref(false)
const dialogTitle = ref('')
const formRef = ref(null)
const submitting = ref(false)
const editingId = ref(null)

// 表单数据
const formData = reactive({
  name: '',
  url: '',
  sort_order: 0,
  is_active: true
})

// 表单验证规则
const formRules = {
  name: [
    { required: true, message: '请输入软件名称', trigger: 'blur' }
  ],
  url: [
    { required: true, message: '请输入软件地址', trigger: 'blur' }
  ]
}

// 加载列表
const loadLinkList = async () => {
  loading.value = true
  try {
    const res = await getFinancialSoftwareLinks({
      account_set_id: accountSetStore.currentAccountSetId,
      ...filterForm
    })
    
    if (res.success) {
      linkList.value = res.data
    } else {
      ElMessage.error(res.message || '获取列表失败')
    }
  } catch (error) {
    console.error('Load link list error:', error)
    ElMessage.error('获取列表失败')
  } finally {
    loading.value = false
  }
}

// 查询
const handleSearch = () => {
  loadLinkList()
}

// 重置
const handleReset = () => {
  filterForm.name = ''
  filterForm.is_active = null
  loadLinkList()
}

// 新增
const handleCreate = () => {
  dialogTitle.value = '添加软件链接'
  editingId.value = null
  resetForm()
  dialogVisible.value = true
}

// 编辑
const handleEdit = (row) => {
  dialogTitle.value = '编辑软件链接'
  editingId.value = row.id
  Object.assign(formData, {
    name: row.name,
    url: row.url,
    sort_order: row.sort_order,
    is_active: row.is_active
  })
  dialogVisible.value = true
}

// 提交
const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    
    submitting.value = true
    try {
      const data = {
        ...formData,
        account_set_id: accountSetStore.currentAccountSetId
      }
      
      let res
      if (editingId.value) {
        res = await updateFinancialSoftwareLink(editingId.value, data)
      } else {
        res = await createFinancialSoftwareLink(data)
      }
      
      if (res.success) {
        ElMessage.success(res.message || '操作成功')
        dialogVisible.value = false
        loadLinkList()
      } else {
        ElMessage.error(res.message || '操作失败')
      }
    } catch (error) {
      console.error('Submit error:', error)
      ElMessage.error('操作失败')
    } finally {
      submitting.value = false
    }
  })
}

// 删除
const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定要删除"${row.name}"吗？`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      const res = await deleteFinancialSoftwareLink(row.id)
      if (res.success) {
        ElMessage.success('删除成功')
        loadLinkList()
      } else {
        ElMessage.error(res.message || '删除失败')
      }
    } catch (error) {
      console.error('Delete error:', error)
      ElMessage.error('删除失败')
    }
  }).catch(() => {
    // 取消删除
  })
}

// 重置表单
const resetForm = () => {
  if (formRef.value) {
    formRef.value.resetFields()
  }
  Object.assign(formData, {
    name: '',
    url: '',
    sort_order: 0,
    is_active: true
  })
}

// 初始化
onMounted(() => {
  loadLinkList()
})
</script>

<style scoped>
.financial-software-links-container {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.table-card {
  margin-top: 20px;
}
</style>
