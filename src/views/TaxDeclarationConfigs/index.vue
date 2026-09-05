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
            <el-button type="primary" @click="handleCreateCategory">添加大类</el-button>
          </div>

          <el-table :data="topLevelCategories" v-loading="categoriesLoading" row-key="id" border stripe>
            <el-table-column type="expand" width="50">
              <template #default="{ row }">
                <div v-if="getChildCategories(row).length" class="child-category-table">
                  <el-table :data="getChildCategories(row)" border size="small">
                    <el-table-column prop="id" label="ID" width="80" />
                    <el-table-column prop="name" label="细分税种" min-width="180" />
                    <el-table-column label="创建人" width="120">
                      <template #default="{ row: child }">
                        {{ child.creator?.name || child.creator_name || '-' }}
                      </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="创建时间" width="180" />
                    <el-table-column label="操作" width="120" fixed="right">
                      <template #default="{ row: child }">
                        <el-button type="primary" size="small" link @click="handleEditCategory(child)">
                          编辑
                        </el-button>
                        <el-button type="danger" size="small" link @click="handleDeleteCategory(child)">
                          删除
                        </el-button>
                      </template>
                    </el-table-column>
                  </el-table>
                </div>
                <el-empty v-else description="暂无细分税种" :image-size="60" />
              </template>
            </el-table-column>
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="name" label="税种大类" min-width="180" />
            <el-table-column label="创建人" width="120">
              <template #default="{ row }">
                {{ row.creator?.name || row.creator_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="创建时间" width="180" />
            <el-table-column label="操作" width="220" fixed="right">
              <template #default="{ row }">
                <el-button type="success" size="small" link @click="handleCreateChildCategory(row)">
                  添加细分
                </el-button>
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
            <el-table-column label="申报周期" min-width="180">
              <template #default="{ row }">
                <el-tag
                  v-for="periodType in getConfigPeriodTypes(row)"
                  :key="periodType"
                  :type="getPeriodTypeTag(periodType)"
                  style="margin-right: 5px"
                >
                  {{ getPeriodTypeText(periodType) }}
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
      :title="categoryDialogMode === 'create' ? '添加大类' : categoryDialogMode === 'child' ? '添加细分税种' : '编辑税种'"
      width="500px"
    >
      <el-form :model="categoryForm" :rules="categoryRules" ref="categoryFormRef" label-width="100px">
        <template v-if="categoryDialogMode === 'child'">
          <el-form-item label="所属大类">
            <el-input :model-value="categoryForm.parent_name" disabled />
          </el-form-item>
          <el-form-item
            v-for="(_, index) in categoryForm.childNames"
            :key="index"
            :label="`细分${index + 1}`"
          >
            <div class="child-name-row">
              <el-input
                v-model="categoryForm.childNames[index]"
                placeholder="请输入细分税种名称"
              />
              <el-button
                v-if="categoryForm.childNames.length > 1"
                type="danger"
                link
                @click="removeChildName(index)"
              >
                删除
              </el-button>
            </div>
          </el-form-item>
          <el-button type="success" plain @click="addChildName">添加一行</el-button>
        </template>
        <template v-else>
          <el-form-item label="税种名称" prop="name">
            <el-input v-model="categoryForm.name" placeholder="请输入税种名称" />
          </el-form-item>
        </template>
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
        <el-form-item label="申报周期" prop="period_types">
          <el-select
            v-model="configForm.period_types"
            multiple
            collapse-tags
            collapse-tags-tooltip
            placeholder="请选择申报周期（可多选）"
            style="width: 100%"
            @change="handlePeriodTypesChange"
          >
            <el-option label="月度" value="monthly" />
            <el-option label="季度" value="quarterly" />
            <el-option label="年度" value="yearly" />
          </el-select>
        </el-form-item>
        <el-form-item
          v-if="configForm.period_types.includes('yearly')"
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
            年度任务按此月份生成，月度和季度任务不需要选择月份
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
  parent_id: null,
  parent_name: '',
  childNames: ['']
})

const topLevelCategories = computed(() => {
  return categories.value.filter(category => !category.parent_id)
})

const getChildCategories = (parent) => {
  return categories.value.filter(category => Number(category.parent_id) === Number(parent.id))
}

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
  period_types: [],
  declaration_date: ''
})

const monthOptions = Array.from({ length: 12 }, (_, index) => {
  const month = String(index + 1).padStart(2, '0')
  return { label: `${index + 1}月`, value: month }
})

const configRules = {
  company_name: [{ required: true, message: '请输入公司名称', trigger: 'blur' }],
  tax_category_ids: [{ required: true, message: '请选择税种', trigger: 'change' }],
  period_types: [{ required: true, message: '请选择申报周期', trigger: 'change' }],
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
    parent_id: null,
    parent_name: '',
    childNames: ['']
  })
  categoryDialogVisible.value = true
}

// 在指定大类下创建细分税种
const handleCreateChildCategory = (parent) => {
  categoryDialogMode.value = 'child'
  Object.assign(categoryForm, {
    name: '',
    parent_id: parent.id,
    parent_name: parent.name,
    childNames: ['']
  })
  categoryDialogVisible.value = true
}

// 编辑税种类目
const handleEditCategory = (row) => {
  categoryDialogMode.value = 'edit'
  Object.assign(categoryForm, {
    id: row.id,
    name: row.name,
    parent_id: row.parent_id || null,
    parent_name: row.parent?.name || '',
    childNames: ['']
  })
  categoryDialogVisible.value = true
}

const addChildName = () => {
  categoryForm.childNames.push('')
}

const removeChildName = (index) => {
  categoryForm.childNames.splice(index, 1)
}

// 提交税种类目
const handleSubmitCategory = async () => {
  if (categoryDialogMode.value === 'child') {
    const childNames = categoryForm.childNames
      .map(name => name.trim())
      .filter(Boolean)

    if (!childNames.length) {
      ElMessage.warning('请至少填写一个细分税种')
      return
    }

    categorySubmitting.value = true
    try {
      for (const name of childNames) {
        await createCategory({
          account_set_id: accountSetStore.currentAccountSetId,
          name,
          parent_id: categoryForm.parent_id
        })
      }

      ElMessage.success(`成功创建${childNames.length}个细分税种`)
      categoryDialogVisible.value = false
      loadCategories()
    } catch (error) {
      console.error('提交失败:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    } finally {
      categorySubmitting.value = false
    }
    return
  }

  await categoryFormRef.value?.validate()
  
  categorySubmitting.value = true
  try {
    const data = {
      account_set_id: accountSetStore.currentAccountSetId,
      name: categoryForm.name,
      parent_id: categoryForm.parent_id || null
    }
    
    if (categoryDialogMode.value !== 'edit') {
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
    period_types: [],
    declaration_date: ''
  })
  configDialogVisible.value = true
}

const handlePeriodTypesChange = () => {
  if (!configForm.period_types.includes('yearly')) {
    configForm.declaration_date = ''
    return
  }

  const month = normalizeConfigMonth(configForm.declaration_date)
  configForm.declaration_date = month || '01'
}

// 编辑配置
const handleEditConfig = (row) => {
  const periodTypes = getConfigPeriodTypes(row)
  configDialogMode.value = 'edit'
  Object.assign(configForm, {
    id: row.id,
    company_name: row.company_name,
    tax_category_ids: (row.tax_category_ids || []).map(Number),
    period_types: periodTypes,
    declaration_date: periodTypes.includes('yearly')
      ? normalizeConfigMonth(row.declaration_date)
      : ''
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
      period_types: configForm.period_types,
      declaration_date: configForm.period_types.includes('yearly')
        ? configForm.declaration_date
        : ''
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

const getConfigPeriodTypes = (row) => {
  return Array.isArray(row?.period_types)
    ? row.period_types.filter(type => ['monthly', 'quarterly', 'yearly'].includes(type))
    : []
}

const normalizeConfigMonth = (value) => {
  if (!value) return ''
  const month = String(value).slice(0, 2)
  return /^\d{2}$/.test(month) ? month : ''
}

const formatDeclarationRule = (row) => {
  const periodTypes = getConfigPeriodTypes(row)
  const month = Number(normalizeConfigMonth(row.declaration_date))
  return periodTypes.map(periodType => {
    if (periodType === 'monthly') return '每月'
    if (periodType === 'quarterly') return '每季度第1个月'
    if (periodType === 'yearly') return month ? `每年${month}月` : '每年-'
    return '-'
  }).join('、') || '-'
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

.child-category-table {
  padding: 8px 48px 8px 48px;
}

.child-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.child-name-row .el-input {
  flex: 1;
}
</style>
