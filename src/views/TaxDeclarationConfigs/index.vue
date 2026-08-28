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
            <el-table-column label="税种大类" min-width="180">
              <template #default="{ row }">
                {{ row.parent?.name || row.name }}
              </template>
            </el-table-column>
            <el-table-column label="细分税种" min-width="180">
              <template #default="{ row }">
                {{ row.parent ? row.name : '-' }}
              </template>
            </el-table-column>
            <el-table-column label="创建人" width="120">
              <template #default="{ row }">
                {{ row.creator?.name || row.creator_name || '-' }}
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
                  {{ formatCategoryLabel(category) }}
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
            <el-table-column label="类型" width="100">
              <template #default="{ row }">
                <el-tag :type="getPeriodTypeTag(row.declaration_type || row.period_type)">
                  {{ getPeriodTypeText(row.declaration_type || row.period_type) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="申报月份" width="150">
              <template #default="{ row }">
                {{ formatDeclarationRule(row) }}
              </template>
            </el-table-column>
            <el-table-column label="创建人" width="120">
              <template #default="{ row }">
                {{ row.creator?.name || row.creator_name || '-' }}
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
        <el-form-item label="所属大类">
          <el-select
            v-model="categoryForm.parent_id"
            placeholder="不选择则创建大类"
            clearable
            style="width: 100%"
          >
            <el-option
              v-for="category in topLevelCategories"
              :key="category.id"
              :label="category.name"
              :value="category.id"
            />
          </el-select>
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
          <el-tree-select
            v-model="configForm.tax_category_ids"
            multiple
            show-checkbox
            :check-strictly="false"
            node-key="id"
            :data="categoryTree"
            :render-after-expand="false"
            collapse-tags
            collapse-tags-tooltip
            placeholder="请选择大类或细分税种（可多选）"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="申报周期" prop="period_type">
          <el-select
            v-model="configForm.period_type"
            placeholder="请选择申报周期"
            style="width: 100%"
            @change="handlePeriodTypeChange"
          >
            <el-option label="月度" value="monthly" />
            <el-option label="季度" value="quarterly" />
            <el-option label="年度" value="yearly" />
          </el-select>
        </el-form-item>
        <el-form-item label="类型" prop="declaration_type">
          <el-select
            v-model="configForm.declaration_type"
            placeholder="请选择类型"
            style="width: 100%"
          >
            <el-option label="月度" value="monthly" />
            <el-option label="季度" value="quarterly" />
            <el-option label="年度" value="yearly" />
          </el-select>
        </el-form-item>
        <el-form-item
          v-if="configForm.period_type === 'quarterly'"
          label="申报月份"
        >
          <div class="form-tip">季度固定按第1个月生成任务，不需要选择。</div>
        </el-form-item>
        <el-form-item
          v-else-if="configForm.period_type === 'yearly'"
          label="申报月份"
          prop="declaration_date"
        >
          <el-select v-model="configForm.declaration_date" placeholder="请选择月份" style="width: 100%">
            <el-option
              v-for="month in monthOptions"
              :key="month.value"
              :label="month.label"
              :value="month.value"
            />
          </el-select>
          <div style="color: #909399; font-size: 12px; margin-top: 5px">
            只按月份生成任务，例如选择6月表示每年6月生成
          </div>
        </el-form-item>
        <el-form-item v-else-if="configForm.period_type === 'monthly'" label="申报月份">
          <div class="form-tip">月度会每个月生成一条任务，不需要再选择具体日期。</div>
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
import { ref, reactive, computed, onMounted } from 'vue'
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
  name: '',
  parent_id: null
})

const topLevelCategories = computed(() => {
  return categories.value.filter(category => !category.parent_id)
})

const categoryTree = computed(() => {
  return topLevelCategories.value.map(parent => {
    const children = categories.value
      .filter(category => Number(category.parent_id) === Number(parent.id))
      .map(category => ({
        id: category.id,
        label: category.name
      }))

    return {
      id: parent.id,
      label: parent.name,
      ...(children.length ? { children } : {})
    }
  })
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
  declaration_type: '',
  declaration_date: ''
})

const monthOptions = Array.from({ length: 12 }, (_, index) => {
  const month = String(index + 1).padStart(2, '0')
  return { label: `${index + 1}月`, value: month }
})

const configRules = {
  company_name: [{ required: true, message: '请输入公司名称', trigger: 'blur' }],
  tax_category_ids: [{ required: true, message: '请选择税种', trigger: 'change' }],
  period_type: [{ required: true, message: '请选择申报周期', trigger: 'change' }],
  declaration_type: [{ required: true, message: '请选择类型', trigger: 'change' }],
  declaration_date: [
    { required: true, message: '请选择申报月份', trigger: 'change' }
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
    name: '',
    parent_id: null
  })
  categoryDialogVisible.value = true
}

// 编辑税种类目
const handleEditCategory = (row) => {
  categoryDialogMode.value = 'edit'
  Object.assign(categoryForm, {
    id: row.id,
    name: row.name,
    parent_id: row.parent_id || null
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
      name: categoryForm.name,
      parent_id: categoryForm.parent_id || null
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
    declaration_type: '',
    declaration_date: ''
  })
  configDialogVisible.value = true
}

const handlePeriodTypeChange = () => {
  if (configForm.period_type === 'monthly') {
    configForm.declaration_date = ''
    return
  }

  if (configForm.period_type === 'quarterly') {
    configForm.declaration_date = '01'
    return
  }

  const month = normalizeConfigMonth(configForm.declaration_date)
  configForm.declaration_date = month || '01'
}

// 编辑配置
const handleEditConfig = (row) => {
  configDialogMode.value = 'edit'
  Object.assign(configForm, {
    id: row.id,
    company_name: row.company_name,
    tax_category_ids: (row.tax_category_ids || []).map(Number),
    period_type: row.period_type,
    declaration_type: row.declaration_type || row.period_type,
    declaration_date: row.period_type === 'monthly'
      ? ''
      : row.period_type === 'quarterly'
        ? '01'
        : normalizeConfigMonth(row.declaration_date)
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
      declaration_type: configForm.declaration_type || configForm.period_type,
      declaration_date: configForm.period_type === 'monthly'
        ? ''
        : configForm.period_type === 'quarterly'
          ? '01'
          : configForm.declaration_date
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

const normalizeConfigMonth = (value) => {
  if (!value) return ''
  const month = String(value).slice(0, 2)
  return /^\d{2}$/.test(month) ? month : ''
}

const formatDeclarationRule = (row) => {
  if (row.period_type === 'monthly') {
    return '每月'
  }

  const month = Number(normalizeConfigMonth(row.declaration_date))
  if (!month) return '-'

  if (row.period_type === 'quarterly') {
    return '每季度第1个月'
  }

  if (row.period_type === 'yearly') {
    return `每年${month}月`
  }

  return row.declaration_date || '-'
}

const formatCategoryLabel = (category) => {
  return category.parent?.name
    ? `${category.parent.name} / ${category.name}`
    : category.name
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

.form-tip {
  color: #909399;
  font-size: 12px;
}
</style>
