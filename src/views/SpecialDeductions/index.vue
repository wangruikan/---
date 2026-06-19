<template>
  <div class="special-deductions-page">
    <div class="page-header">
      <h1>专项扣除管理</h1>
    </div>

    <el-card>
      <el-tabs v-model="activeTab" @tab-change="handleTabChange">
        <el-tab-pane label="扣除项目设置" name="items">
          <div class="tab-content">
            <div class="toolbar">
              <el-form :model="itemSearchForm" inline>
                <el-form-item label="名称">
                  <el-input
                    v-model="itemSearchForm.search"
                    placeholder="请输入扣除项目名称"
                    clearable
                    style="width: 200px"
                    @keyup.enter="loadDeductionItems"
                  />
                </el-form-item>

                <el-form-item>
                  <el-button type="primary" @click="loadDeductionItems">
                    <el-icon><Search /></el-icon>
                    搜索
                  </el-button>
                  <el-button type="primary" @click="handleCreateSpecialItem">
                    <el-icon><Plus /></el-icon>
                    新增扣除项目
                  </el-button>
                </el-form-item>
              </el-form>
            </div>

            <el-table
              :data="safeDeductionItems"
              v-loading="itemsLoading"
              stripe
              border
              style="margin-top: 20px"
            >
              <el-table-column label="序号" type="index" width="70" :index="getItemRowIndex" />
              <el-table-column prop="name" label="扣除项目名称" width="200" />
              <el-table-column prop="description" label="说明描述" show-overflow-tooltip />
              <el-table-column prop="sort_order" label="排序" width="80" />
              <el-table-column prop="creator_name" label="创建人" width="100" />
              <el-table-column prop="created_at" label="创建时间" width="160">
                <template #default="{ row }">
                  {{ formatDateTime(row.created_at) }}
                </template>
              </el-table-column>
              <el-table-column label="操作" width="150" fixed="right">
                <template #default="{ row }">
                  <el-button link type="primary" @click="handleEditItem(row)">编辑</el-button>
                  <el-button link type="danger" @click="handleDeleteItem(row)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>

            <div class="pagination">
              <el-pagination
                v-model:current-page="itemPagination.currentPage"
                v-model:page-size="itemPagination.pageSize"
                :page-sizes="[10, 20, 50, 100]"
                :total="itemPagination.total"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadDeductionItems"
                @current-change="loadDeductionItems"
              />
            </div>
          </div>
        </el-tab-pane>

        <el-tab-pane label="其他扣除" name="other">
          <div class="tab-content">
            <div class="toolbar">
              <el-form :model="otherItemSearchForm" inline>
                <el-form-item label="名称">
                  <el-input
                    v-model="otherItemSearchForm.search"
                    placeholder="请输入其他扣除项目名称"
                    clearable
                    style="width: 200px"
                    @keyup.enter="loadOtherDeductionItems"
                  />
                </el-form-item>

                <el-form-item>
                  <el-button type="primary" @click="loadOtherDeductionItems">
                    <el-icon><Search /></el-icon>
                    搜索
                  </el-button>
                  <el-button type="primary" @click="handleCreateOtherItem">
                    <el-icon><Plus /></el-icon>
                    新增其他扣除项目
                  </el-button>
                </el-form-item>
              </el-form>
            </div>

            <el-table
              :data="safeOtherDeductionItems"
              v-loading="otherItemsLoading"
              stripe
              border
              style="margin-top: 20px"
            >
              <el-table-column label="序号" type="index" width="70" :index="getOtherItemRowIndex" />
              <el-table-column prop="name" label="扣除项目名称" width="200" />
              <el-table-column prop="description" label="说明描述" show-overflow-tooltip />
              <el-table-column prop="sort_order" label="排序" width="80" />
              <el-table-column prop="creator_name" label="创建人" width="100" />
              <el-table-column prop="created_at" label="创建时间" width="160">
                <template #default="{ row }">
                  {{ formatDateTime(row.created_at) }}
                </template>
              </el-table-column>
              <el-table-column label="操作" width="150" fixed="right">
                <template #default="{ row }">
                  <el-button link type="primary" @click="handleEditItem(row, 'other')">编辑</el-button>
                  <el-button link type="danger" @click="handleDeleteItem(row, 'other')">删除</el-button>
                </template>
              </el-table-column>
            </el-table>

            <div class="pagination">
              <el-pagination
                v-model:current-page="otherItemPagination.currentPage"
                v-model:page-size="otherItemPagination.pageSize"
                :page-sizes="[10, 20, 50, 100]"
                :total="otherItemPagination.total"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadOtherDeductionItems"
                @current-change="loadOtherDeductionItems"
              />
            </div>
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <el-dialog
      v-model="showCreateItemDialog"
      :title="itemDialogTitle"
      width="600px"
      @close="handleItemDialogClose"
    >
      <el-form
        ref="itemFormRef"
        :model="itemForm"
        :rules="itemFormRules"
        label-width="120px"
      >
        <el-form-item label="扣除项目名称" prop="name">
          <el-input v-model="itemForm.name" placeholder="例如：子女教育、住房贷款利息等" />
        </el-form-item>

        <el-form-item label="说明描述" prop="description">
          <el-input
            v-model="itemForm.description"
            type="textarea"
            :rows="3"
            placeholder="请输入说明描述"
          />
        </el-form-item>

        <el-form-item label="排序" prop="sort_order">
          <el-input-number
            v-model="itemForm.sort_order"
            :min="0"
            placeholder="数字越小越靠前"
            style="width: 100%"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showCreateItemDialog = false">取消</el-button>
        <el-button type="primary" @click="handleItemSubmit" :loading="itemSubmitting">
          {{ isEditItem ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Plus } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import {
  getDeductionItems,
  createDeductionItem,
  updateDeductionItem,
  deleteDeductionItem
} from '@/api/specialDeductions'

const route = useRoute()
const accountSetStore = useAccountSetStore()
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)
const activeTab = ref('items')

const deductionItems = ref([])
const safeDeductionItems = computed(() => Array.isArray(deductionItems.value) ? deductionItems.value : [])
const itemsLoading = ref(false)
const itemSearchForm = reactive({
  search: ''
})
const itemPagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const otherDeductionItems = ref([])
const safeOtherDeductionItems = computed(() => Array.isArray(otherDeductionItems.value) ? otherDeductionItems.value : [])
const otherItemsLoading = ref(false)
const otherItemSearchForm = reactive({
  search: ''
})
const otherItemPagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const showCreateItemDialog = ref(false)
const isEditItem = ref(false)
const itemSubmitting = ref(false)
const itemFormRef = ref(null)
const currentItemId = ref(null)
const currentItemType = ref('special')
const itemForm = reactive({
  name: '',
  description: '',
  sort_order: 0,
  is_active: true
})
const itemFormRules = {
  name: [{ required: true, message: '请输入扣除项目名称', trigger: 'blur' }]
}

const itemDialogTitle = computed(() => {
  const typeName = currentItemType.value === 'other' ? '其他扣除项目' : '扣除项目'
  return isEditItem.value ? `编辑${typeName}` : `新增${typeName}`
})

const getItemRowIndex = (index) => {
  return (itemPagination.currentPage - 1) * itemPagination.pageSize + index + 1
}

const getOtherItemRowIndex = (index) => {
  return (otherItemPagination.currentPage - 1) * otherItemPagination.pageSize + index + 1
}

const formatDateTime = (datetime) => {
  if (!datetime) return ''
  const date = new Date(datetime)
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

const loadDeductionItems = async () => {
  itemsLoading.value = true
  try {
    const res = await getDeductionItems({
      ...itemSearchForm,
      page: itemPagination.currentPage,
      per_page: itemPagination.pageSize,
      item_type: 'special',
      current_account_set_id: currentAccountSetId.value
    })
    if (res.success) {
      deductionItems.value = (res.data || []).filter(item => item !== null)
      itemPagination.total = res.total || 0
    }
  } catch (error) {
    ElMessage.error('加载扣除项目失败')
    console.error(error)
    deductionItems.value = []
  } finally {
    itemsLoading.value = false
  }
}

const loadOtherDeductionItems = async () => {
  otherItemsLoading.value = true
  try {
    const res = await getDeductionItems({
      ...otherItemSearchForm,
      page: otherItemPagination.currentPage,
      per_page: otherItemPagination.pageSize,
      item_type: 'other',
      current_account_set_id: currentAccountSetId.value
    })
    if (res.success) {
      otherDeductionItems.value = (res.data || []).filter(item => item !== null)
      otherItemPagination.total = res.total || 0
    }
  } catch (error) {
    ElMessage.error('加载其他扣除项目失败')
    console.error(error)
    otherDeductionItems.value = []
  } finally {
    otherItemsLoading.value = false
  }
}

const loadCurrentTab = () => {
  if (activeTab.value === 'other') {
    loadOtherDeductionItems()
  } else {
    loadDeductionItems()
  }
}

const handleCreateSpecialItem = () => {
  currentItemType.value = 'special'
  isEditItem.value = false
  showCreateItemDialog.value = true
}

const handleCreateOtherItem = () => {
  currentItemType.value = 'other'
  isEditItem.value = false
  showCreateItemDialog.value = true
}

const handleEditItem = (row, itemType = 'special') => {
  currentItemType.value = itemType
  isEditItem.value = true
  currentItemId.value = row.id
  Object.assign(itemForm, {
    name: row.name,
    description: row.description,
    sort_order: row.sort_order,
    is_active: true
  })
  showCreateItemDialog.value = true
}

const handleDeleteItem = async (row, itemType = 'special') => {
  try {
    await ElMessageBox.confirm('确定要删除该扣除项目吗？', '提示', {
      type: 'warning'
    })
    const res = await deleteDeductionItem(row.id)
    if (res.success) {
      ElMessage.success('删除成功')
      if (itemType === 'other') {
        loadOtherDeductionItems()
      } else {
        loadDeductionItems()
      }
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.message || '删除失败')
    }
  }
}

const handleItemSubmit = async () => {
  try {
    await itemFormRef.value.validate()
    itemSubmitting.value = true

    const data = {
      ...itemForm,
      is_active: true,
      item_type: currentItemType.value,
      current_account_set_id: currentAccountSetId.value
    }
    const res = isEditItem.value
      ? await updateDeductionItem(currentItemId.value, data)
      : await createDeductionItem(data)

    if (res.success) {
      ElMessage.success(isEditItem.value ? '更新成功' : '创建成功')
      showCreateItemDialog.value = false
      if (currentItemType.value === 'other') {
        loadOtherDeductionItems()
      } else {
        loadDeductionItems()
      }
    }
  } catch (error) {
    if (error !== false) {
      ElMessage.error(error.message || '操作失败')
    }
  } finally {
    itemSubmitting.value = false
  }
}

const handleItemDialogClose = () => {
  isEditItem.value = false
  currentItemId.value = null
  currentItemType.value = 'special'
  Object.assign(itemForm, {
    name: '',
    description: '',
    sort_order: 0,
    is_active: true
  })
  itemFormRef.value?.clearValidate()
}

const handleTabChange = () => {
  loadCurrentTab()
}

watch(currentAccountSetId, (newId, oldId) => {
  if (newId && newId !== oldId) {
    loadCurrentTab()
  }
})

onMounted(async () => {
  if (route.query.tab === 'other') {
    activeTab.value = 'other'
  }

  if (!currentAccountSetId.value) {
    await accountSetStore.loadMyAccountSets()
  }

  loadCurrentTab()
})
</script>

<style scoped>
.special-deductions-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0;
  font-size: 24px;
  font-weight: 500;
}

.tab-content {
  padding: 20px 0;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
