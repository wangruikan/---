<template>
  <div class="tax-declaration-configs">
    <div class="page-header">
      <h1>税费申报配置</h1>
    </div>

    <el-card>
      <el-tabs v-model="activeTab">
        <!-- Tab1: 税种类目管理 -->
        <el-tab-pane label="税种类目" name="categories">
          <div class="tab-header">
            <el-button type="primary" @click="handleCreateCategory">添加税种</el-button>
          </div>

          <el-table :data="categories" v-loading="categoriesLoading" border stripe>
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="name" label="税种名称" min-width="200" />
            <el-table-column label="创建人" width="120">
              <template #default="{ row }">
                {{ row.creator?.name || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="创建时间" width="180" />
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row }">
                <el-button type="primary" size="small" link @click="handleEditCategory(row)">
                  编辑
                </el-button>
                <el-button type="danger" size="small" link @click="handleDeleteCategory(row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab2: 申报配置管理 -->
        <el-tab-pane label="申报配置" name="configs">
          <div class="tab-header">
            <el-button type="primary" @click="handleCreateConfig">创建配置</el-button>
          </div>

          <el-table :data="configs" v-loading="configsLoading" border stripe>
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="company_name" label="公司名称" min-width="200" />
            <el-table-column label="税种" min-width="200">
              <template #default="{ row }">
                <el-tag
                  v-for="category in row.tax_categories_list"
                  :key="category.id"
                  size="small"
                  style="margin-right: 5px"
                >
                  {{ category.name }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="period_type" label="申报周期" width="100">
              <template #default="{ row }">
                <el-tag :type="getPeriodTypeTag(row.period_type)">
                  {{ getPeriodTypeText(row.period_type) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="declaration_date" label="申报日期" width="100" />
            <el-table-column label="创建人" width="120">
              <template #default="{ row }">
                {{ row.creator?.name || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="创建时间" width="180" />
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row }">
                <el-button type="primary" size="small" link @click="handleEditConfig(row)">
                  编辑
                </el-button>
                <el-button type="danger" size="small" link @click="handleDeleteConfig(row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 税种类目对话框 -->
    <el-dialog
      v-model="categoryDialogVisible"
      :title="categoryDialogMode === 'create' ? '添加税种' : '编辑税种'"
      width="500px"
    >
      <el-form :model="categoryForm" :rules="categoryRules" ref="categoryFormRef" label-width="100px">
        <el-form-item label="税种名称" prop="name">
          <el-input v-model="categoryForm.name" placeholder="请输入税种名称" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="categoryDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitCategory" :loading="categorySubmitting">
          确定
        </el-button>
      </template>
    </el-dialog>

    <!-- 申报配置对话框 -->
    <el-dialog
      v-model="configDialogVisible"
      :title="configDialogMode === 'create' ? '创建配置' : '编辑配置'"
      width="600px"
    >
      <el-form :model="configForm" :rules="configRules" ref="configFormRef" label-width="100px">
        <el-form-item label="公司名称" prop="company_name">
          <el-input v-model="configForm.company_name" placeholder="请输入公司名称" />
        </el-form-item>
        <el-form-item label="选择税种" prop="tax_category_ids">
          <el-select
            v-model="configForm.tax_category_ids"
            multiple
            placeholder="请选择税种（可多选）"
            style="width: 100%"
          >
            <el-option
              v-for="category in categories"
              :key="category.id"
              :label="category.name"
              :value="category.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="申报周期" prop="period_type">
          <el-select v-model="configForm.period_type" placeholder="请选择申报周期" style="width: 100%">
            <el-option label="月度" value="monthly" />
            <el-option label="季度" value="quarterly" />
            <el-option label="年度" value="yearly" />
          </el-select>
        </el-form-item>
        <el-form-item label="申报日期" prop="declaration_date">
          <el-date-picker
            v-model="configForm.declaration_date"
            type="date"
            placeholder="选择申报日期"
            format="MM-DD"
            value-format="MM-DD"
            style="width: 100%"
          />
          <div style="color: #909399; font-size: 12px; margin-top: 5px">
            选择月份和日期，例如：01-15 表示每年1月15日
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="configDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitConfig" :loading="configSubmitting">
          确定
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getCategories,
  createCategory,
  updateCategory,
  deleteCategory,
  getConfigs,
  createConfig,
  updateConfig,
  deleteConfig
} from '@/api/taxDeclaration'
import { useAccountSetStore } from '@/stores/accountSet'

const accountSetStore = useAccountSetStore()

const activeTab = ref('categories')

// 税种类目相关
const categories = ref([])
const categoriesLoading = ref(false)
const categoryDialogVisible = ref(false)
const categoryDialogMode = ref('create')
const categorySubmitting = ref(false)
const categoryFormRef = ref()

const categoryForm = reactive({
  name: ''
})

const categoryRules = {
  name: [{ required: true, message: '请输入税种名称', trigger: 'blur' }]
}

// 申报配置相关
const configs = ref([])
const configsLoading = ref(false)
const configDialogVisible = ref(false)
const configDialogMode = ref('create')
const configSubmitting = ref(false)
const configFormRef = ref()

const configForm = reactive({
  company_name: '',
  tax_category_ids: [],
  period_type: '',
  declaration_date: ''
})

const configRules = {
  company_name: [{ required: true, message: '请输入公司名称', trigger: 'blur' }],
  tax_category_ids: [{ required: true, message: '请选择税种', trigger: 'change' }],
  period_type: [{ required: true, message: '请选择申报周期', trigger: 'change' }],
  declaration_date: [
    { required: true, message: '请选择申报日期', trigger: 'change' }
  ]
}

// 加载税种类目
const loadCategories = async () => {
  categoriesLoading.value = true
  try {
    const response = await getCategories({
      account_set_id: accountSetStore.currentAccountSetId
    })
    categories.value = response.data
  } catch (error) {
    console.error('加载税种类目失败:', error)
    ElMessage.error('加载失败')
  } finally {
    categoriesLoading.value = false
  }
}

// 创建税种类目
const handleCreateCategory = () => {
  categoryDialogMode.value = 'create'
  Object.assign(categoryForm, {
    name: ''
  })
  categoryDialogVisible.value = true
}

// 编辑税种类目
const handleEditCategory = (row) => {
  categoryDialogMode.value = 'edit'
  Object.assign(categoryForm, {
    id: row.id,
    name: row.name
  })
  categoryDialogVisible.value = true
}

// 提交税种类目
const handleSubmitCategory = async () => {
  await categoryFormRef.value?.validate()
  
  categorySubmitting.value = true
  try {
    const data = {
      account_set_id: accountSetStore.currentAccountSetId,
      name: categoryForm.name
    }
    
    if (categoryDialogMode.value === 'create') {
      await createCategory(data)
      ElMessage.success('创建成功')
    } else {
      await updateCategory(categoryForm.id, data)
      ElMessage.success('更新成功')
    }
    
    categoryDialogVisible.value = false
    loadCategories()
  } catch (error) {
    console.error('提交失败:', error)
    ElMessage.error(error.response?.data?.message || '操作失败')
  } finally {
    categorySubmitting.value = false
  }
}

// 删除税种类目
const handleDeleteCategory = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要删除税种"${row.name}"吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await deleteCategory(row.id)
    ElMessage.success('删除成功')
    loadCategories()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 加载申报配置
const loadConfigs = async () => {
  configsLoading.value = true
  try {
    const response = await getConfigs({
      account_set_id: accountSetStore.currentAccountSetId
    })
    configs.value = response.data
  } catch (error) {
    console.error('加载配置失败:', error)
    ElMessage.error('加载失败')
  } finally {
    configsLoading.value = false
  }
}

// 创建配置
const handleCreateConfig = () => {
  configDialogMode.value = 'create'
  Object.assign(configForm, {
    company_name: '',
    tax_category_ids: [],
    period_type: '',
    declaration_date: ''
  })
  configDialogVisible.value = true
}

// 编辑配置
const handleEditConfig = (row) => {
  configDialogMode.value = 'edit'
  Object.assign(configForm, {
    id: row.id,
    company_name: row.company_name,
    tax_category_ids: row.tax_category_ids,
    period_type: row.period_type,
    declaration_date: row.declaration_date
  })
  configDialogVisible.value = true
}

// 提交配置
const handleSubmitConfig = async () => {
  await configFormRef.value?.validate()
  
  configSubmitting.value = true
  try {
    const data = {
      account_set_id: accountSetStore.currentAccountSetId,
      company_name: configForm.company_name,
      tax_category_ids: configForm.tax_category_ids,
      period_type: configForm.period_type,
      declaration_date: configForm.declaration_date
    }
    
    if (configDialogMode.value === 'create') {
      await createConfig(data)
      ElMessage.success('创建成功')
    } else {
      await updateConfig(configForm.id, data)
      ElMessage.success('更新成功')
    }
    
    configDialogVisible.value = false
    loadConfigs()
  } catch (error) {
    console.error('提交失败:', error)
    ElMessage.error(error.response?.data?.message || '操作失败')
  } finally {
    configSubmitting.value = false
  }
}

// 删除配置
const handleDeleteConfig = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要删除配置"${row.company_name}"吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await deleteConfig(row.id)
    ElMessage.success('删除成功')
    loadConfigs()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 工具函数
const getPeriodTypeText = (type) => {
  const types = {
    monthly: '月度',
    quarterly: '季度',
    yearly: '年度'
  }
  return types[type] || type
}

const getPeriodTypeTag = (type) => {
  const tags = {
    monthly: 'primary',
    quarterly: 'success',
    yearly: 'warning'
  }
  return tags[type] || 'info'
}

onMounted(() => {
  loadCategories()
  loadConfigs()
})
</script>

<style scoped>
.tax-declaration-configs {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h1 {
  font-size: 24px;
  font-weight: bold;
  color: #303133;
}

.tab-header {
  margin-bottom: 20px;
}
</style>
