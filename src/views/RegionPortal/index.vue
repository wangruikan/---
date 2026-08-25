<template>
  <div class="region-portal-container">
    <el-card class="header-card">
      <el-row :gutter="20">
        <el-col :span="16">
          <el-form :model="filterForm" inline>
            <el-form-item label="地区名称">
              <el-select
                v-model="filterForm.region_name"
                placeholder="请选择地区名称"
                clearable
                filterable
                style="width: 200px;"
              >
                <el-option
                  v-for="item in regionOptions"
                  :key="item"
                  :label="item"
                  :value="item"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="业务类型">
              <el-select
                v-model="filterForm.business_type"
                placeholder="请选择业务类型"
                clearable
                filterable
                style="width: 200px;"
              >
                <el-option
                  v-for="item in businessTypeOptions"
                  :key="item"
                  :label="item"
                  :value="item"
                />
              </el-select>
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
        <el-col :span="8" class="header-actions">
          <el-button :icon="View" @click="handleOpenDisplayPage">网站展示页</el-button>
          <el-button type="primary" :icon="Plus" @click="handleCreate">添加网页入口</el-button>
        </el-col>
      </el-row>
    </el-card>

    <el-card class="table-card">
      <el-table
        :data="pagedPortalList"
        v-loading="loading"
        border
        stripe
        :span-method="regionSpanMethod"
      >
        <el-table-column prop="region_sequence" label="序号" width="70" align="center">
          <template #default="{ row }">
            {{ row.region_sequence }}
          </template>
        </el-table-column>
        <el-table-column prop="region_name" label="地区名称" width="140" />
        <el-table-column prop="business_type" label="业务类型" width="140" />
        <el-table-column
          prop="remarks"
          label="备注"
          min-width="180"
          show-overflow-tooltip
        >
          <template #default="{ row }">
            {{ row.remarks || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="网站名称" min-width="220">
          <template #default="{ row }">
            <div class="portal-cell-list">
              <div v-for="site in row.websites" :key="site.uid" class="portal-cell-item">
                <span>{{ site.portal_name }}</span>
                <el-tag v-if="!site.is_active" size="small" type="info">停用</el-tag>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="网站地址" min-width="320">
          <template #default="{ row }">
            <div class="portal-cell-list">
              <div v-for="site in row.websites" :key="`${site.uid}-url`" class="portal-cell-item">
                <el-link :href="site.portal_url" target="_blank" type="primary">
                  {{ site.portal_url }}
                </el-link>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="80" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row)">
              {{ getStatusText(row) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建人" width="100">
          <template #default="{ row }">
            {{ row.creatorName }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" :icon="Edit" @click="handleEdit(row)">编辑</el-button>
            <el-button
              link
              :type="row.allActive ? 'warning' : 'success'"
              @click="handleToggleStatus(row)"
            >
              {{ row.allActive ? '禁用' : '启用' }}
            </el-button>
            <el-button link type="danger" :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.current"
          v-model:page-size="pagination.pageSize"
          :total="totalGroups"
          :page-sizes="[15, 30, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handlePageSizeChange"
        />
      </div>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="760px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="100px"
      >
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="地区名称" prop="region_name">
              <el-input
                v-model="formData.region_name"
                placeholder="请输入地区名称，如：北京、上海"
                maxlength="100"
                show-word-limit
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="业务类型" prop="business_type">
              <el-input
                v-model="formData.business_type"
                placeholder="请输入业务类型，如：社保、公积金、税务"
                maxlength="100"
                show-word-limit
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="排序序号" prop="sort_order">
              <el-input-number
                v-model="formData.sort_order"
                :min="0"
                :step="1"
                controls-position="right"
                placeholder="数字越小越靠前"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="备注说明" prop="remarks">
              <el-input
                v-model="formData.remarks"
                placeholder="请输入备注说明（可选）"
                maxlength="500"
                show-word-limit
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="网站列表" required>
          <div class="website-editor-list">
            <div
              v-for="(item, index) in formData.websites"
              :key="item.uid"
              class="website-editor-item"
            >
              <div class="website-editor-header">
                <span>网站 {{ index + 1 }}</span>
                <el-button link type="danger" @click="handleRemoveWebsite(index)">删除</el-button>
              </div>
              <el-row :gutter="12">
                <el-col :span="10">
                  <el-form-item
                    :prop="`websites.${index}.portal_name`"
                    :rules="websiteNameRules"
                    label-width="0"
                  >
                    <el-input
                      v-model="item.portal_name"
                      placeholder="请输入网站名称"
                      maxlength="200"
                      show-word-limit
                    />
                  </el-form-item>
                </el-col>
                <el-col :span="14">
                  <el-form-item
                    :prop="`websites.${index}.portal_url`"
                    :rules="websiteUrlRules"
                    label-width="0"
                  >
                    <el-input
                      v-model="item.portal_url"
                      placeholder="请输入完整的网站地址，如：https://www.example.com"
                      maxlength="500"
                      show-word-limit
                    />
                  </el-form-item>
                </el-col>
              </el-row>
            </div>
            <el-button type="primary" link @click="handleAddWebsite">+ 添加网站</el-button>
          </div>
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
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Search,
  Refresh,
  Plus,
  Edit,
  Delete,
  View
} from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import {
  getRegionPortals,
  createRegionPortal,
  updateRegionPortal,
  deleteRegionPortal,
  togglePortalStatus
} from '@/api/regionPortal'

const router = useRouter()
const accountSetStore = useAccountSetStore()

const filterForm = reactive({
  region_name: '',
  business_type: '',
  is_active: null
})

const appliedFilters = reactive({
  region_name: '',
  business_type: '',
  is_active: null
})

const loading = ref(false)
const rawPortalList = ref([])
const pagination = reactive({
  current: 1,
  pageSize: 15
})

const dialogVisible = ref(false)
const dialogTitle = ref('')
const formRef = ref(null)
const submitting = ref(false)
const editingPortalIds = ref([])

let websiteSeed = 0

const createWebsiteFormItem = (item = {}) => ({
  uid: item.uid || item.id || `website-${websiteSeed++}`,
  id: item.id || null,
  portal_name: item.portal_name || '',
  portal_url: item.portal_url || '',
  is_active: item.is_active ?? true
})

const formData = reactive({
  region_name: '',
  business_type: '',
  sort_order: 0,
  remarks: '',
  websites: [createWebsiteFormItem()]
})

const formRules = {
  region_name: [
    { required: true, message: '请输入地区名称', trigger: 'blur' }
  ],
  business_type: [
    { required: true, message: '请输入业务类型', trigger: 'blur' }
  ]
}

const websiteNameRules = [
  { required: true, message: '请输入网站名称', trigger: 'blur' }
]

const websiteUrlRules = [
  { required: true, message: '请输入网站地址', trigger: 'blur' },
  { type: 'url', message: '请输入正确的网址格式', trigger: 'blur' }
]

const buildOptionList = (field) => {
  const seen = new Set()

  return rawPortalList.value.reduce((result, item) => {
    const value = (item[field] || '').trim()

    if (value && !seen.has(value)) {
      seen.add(value)
      result.push(value)
    }

    return result
  }, [])
}

const regionOptions = computed(() => buildOptionList('region_name'))
const businessTypeOptions = computed(() => buildOptionList('business_type'))

const sortFlatPortalList = (list) => {
  return [...list].sort((a, b) => {
    const sortDiff = Number(a.sort_order || 0) - Number(b.sort_order || 0)
    if (sortDiff !== 0) {
      return sortDiff
    }

    const regionDiff = String(a.region_name || '').localeCompare(String(b.region_name || ''), 'zh-CN')
    if (regionDiff !== 0) {
      return regionDiff
    }

    return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime()
  })
}

const groupedPortalList = computed(() => {
  const groupedMap = new Map()

  sortFlatPortalList(rawPortalList.value).forEach((item) => {
    const groupKey = `${item.region_name || ''}__${item.business_type || ''}`

    if (!groupedMap.has(groupKey)) {
      groupedMap.set(groupKey, {
        groupKey,
        region_name: item.region_name || '',
        business_type: item.business_type || '',
        sort_order: Number(item.sort_order || 0),
        remarks: item.remarks || '',
        creatorName: item.creator?.name || '-',
        created_at: item.created_at,
        websites: []
      })
    }

    const group = groupedMap.get(groupKey)
    group.websites.push({
      uid: item.id,
      id: item.id,
      portal_name: item.portal_name || '',
      portal_url: item.portal_url || '',
      is_active: Boolean(item.is_active),
      sort_order: Number(item.sort_order || 0)
    })
  })

  let groups = Array.from(groupedMap.values()).map((group) => {
    const websites = [...group.websites].sort((a, b) => {
      const sortDiff = a.sort_order - b.sort_order
      if (sortDiff !== 0) {
        return sortDiff
      }

      return String(a.portal_name || '').localeCompare(String(b.portal_name || ''), 'zh-CN')
    })

    const allActive = websites.every((item) => item.is_active)
    const anyActive = websites.some((item) => item.is_active)

    return {
      ...group,
      websites,
      allActive,
      anyActive
    }
  })

  if (appliedFilters.region_name) {
    groups = groups.filter((item) => item.region_name === appliedFilters.region_name)
  }

  if (appliedFilters.business_type) {
    groups = groups.filter((item) => item.business_type === appliedFilters.business_type)
  }

  if (appliedFilters.is_active === true) {
    groups = groups.filter((item) => item.anyActive)
  }

  if (appliedFilters.is_active === false) {
    groups = groups.filter((item) => !item.anyActive)
  }

  const regionSequenceMap = new Map()
  let nextRegionSequence = 1

  return groups.map((group) => {
    const regionName = group.region_name || ''

    if (!regionSequenceMap.has(regionName)) {
      regionSequenceMap.set(regionName, nextRegionSequence)
      nextRegionSequence += 1
    }

    return {
      ...group,
      region_sequence: regionSequenceMap.get(regionName)
    }
  })
})

const totalGroups = computed(() => groupedPortalList.value.length)

const pagedPortalList = computed(() => {
  const start = (pagination.current - 1) * pagination.pageSize
  const end = start + pagination.pageSize
  return groupedPortalList.value.slice(start, end)
})

const getStatusText = (row) => {
  if (row.allActive) {
    return '启用'
  }

  if (row.anyActive) {
    return '部分启用'
  }

  return '禁用'
}

const getStatusType = (row) => {
  if (row.allActive) {
    return 'success'
  }

  if (row.anyActive) {
    return 'warning'
  }

  return 'info'
}

const regionSpanMethod = ({ row, column, rowIndex }) => {
  if (!['region_sequence', 'region_name'].includes(column.property)) {
    return { rowspan: 1, colspan: 1 }
  }

  const rows = pagedPortalList.value
  const regionName = row.region_name || ''

  if (rowIndex > 0 && (rows[rowIndex - 1].region_name || '') === regionName) {
    return { rowspan: 0, colspan: 0 }
  }

  let rowspan = 1
  for (let index = rowIndex + 1; index < rows.length; index += 1) {
    if ((rows[index].region_name || '') !== regionName) {
      break
    }
    rowspan += 1
  }

  return { rowspan, colspan: 1 }
}

const getErrorMessage = (error, fallback) => {
  return error?.response?.data?.message || error?.message || fallback
}

const loadPortalList = async () => {
  const accountSetId = accountSetStore.currentAccountSetId

  if (!accountSetId) {
    rawPortalList.value = []
    return
  }

  loading.value = true

  try {
    let perPage = 1000
    let res = await getRegionPortals({
      current_account_set_id: accountSetId,
      page: 1,
      per_page: perPage
    })

    if (!res.success) {
      ElMessage.error(res.message || '获取列表失败')
      return
    }

    let rows = Array.isArray(res.data?.data) ? res.data.data : []
    const total = Number(res.data?.total || rows.length)

    if (total > perPage) {
      perPage = total
      res = await getRegionPortals({
        current_account_set_id: accountSetId,
        page: 1,
        per_page: perPage
      })

      if (!res.success) {
        ElMessage.error(res.message || '获取列表失败')
        return
      }

      rows = Array.isArray(res.data?.data) ? res.data.data : []
    }

    rawPortalList.value = sortFlatPortalList(rows)
  } catch (error) {
    console.error('Load portal list error:', error)
    ElMessage.error(getErrorMessage(error, '获取列表失败'))
  } finally {
    loading.value = false
  }
}

const handleSearch = async () => {
  Object.assign(appliedFilters, filterForm)
  pagination.current = 1
  await loadPortalList()
}

const handleReset = async () => {
  Object.assign(filterForm, {
    region_name: '',
    business_type: '',
    is_active: null
  })

  Object.assign(appliedFilters, {
    region_name: '',
    business_type: '',
    is_active: null
  })

  pagination.current = 1
  await loadPortalList()
}

const handlePageSizeChange = () => {
  pagination.current = 1
}

const resetForm = () => {
  Object.assign(formData, {
    region_name: '',
    business_type: '',
    sort_order: 0,
    remarks: '',
    websites: [createWebsiteFormItem()]
  })
  editingPortalIds.value = []
}

const handleCreate = async () => {
  dialogTitle.value = '添加网页入口'
  resetForm()
  dialogVisible.value = true
  await nextTick()
  formRef.value?.clearValidate()
}

const handleEdit = async (row) => {
  dialogTitle.value = '编辑网页入口'
  editingPortalIds.value = row.websites.map((item) => item.id)

  Object.assign(formData, {
    region_name: row.region_name,
    business_type: row.business_type,
    sort_order: Number(row.sort_order || 0),
    remarks: row.remarks || '',
    websites: row.websites.map((item) => createWebsiteFormItem(item))
  })

  dialogVisible.value = true
  await nextTick()
  formRef.value?.clearValidate()
}

const handleAddWebsite = () => {
  formData.websites.push(createWebsiteFormItem())
}

const handleRemoveWebsite = (index) => {
  if (formData.websites.length === 1) {
    ElMessage.warning('至少保留一个网站')
    return
  }

  formData.websites.splice(index, 1)
}

const handleSubmit = async () => {
  if (!formRef.value) {
    return
  }

  let valid = false

  try {
    valid = await formRef.value.validate()
  } catch (error) {
    valid = false
  }

  if (!valid) {
    return
  }

  if (!accountSetStore.currentAccountSetId) {
    ElMessage.error('请先选择账套')
    return
  }

  const trimmedWebsites = formData.websites.map((item) => ({
    ...item,
    portal_name: item.portal_name.trim(),
    portal_url: item.portal_url.trim()
  }))

  if (trimmedWebsites.some((item) => !item.portal_name || !item.portal_url)) {
    ElMessage.error('请完整填写网站名称和网站地址')
    return
  }

  submitting.value = true

  try {
    const commonData = {
      current_account_set_id: accountSetStore.currentAccountSetId,
      region_name: formData.region_name.trim(),
      business_type: formData.business_type.trim(),
      remarks: formData.remarks?.trim() || '',
      sort_order: Number(formData.sort_order || 0)
    }

    const retainedIds = new Set()

    for (const item of trimmedWebsites) {
      const payload = {
        ...commonData,
        portal_name: item.portal_name,
        portal_url: item.portal_url,
        is_active: item.is_active
      }

      let res

      if (item.id) {
        retainedIds.add(item.id)
        res = await updateRegionPortal(item.id, payload)
      } else {
        res = await createRegionPortal(payload)
      }

      if (!res.success) {
        throw new Error(res.message || '保存失败')
      }
    }

    for (const id of editingPortalIds.value) {
      if (retainedIds.has(id)) {
        continue
      }

      const res = await deleteRegionPortal(id)

      if (!res.success) {
        throw new Error(res.message || '删除旧网站失败')
      }
    }

    ElMessage.success(editingPortalIds.value.length ? '网页入口更新成功' : '网页入口创建成功')
    dialogVisible.value = false
    await loadPortalList()
  } catch (error) {
    console.error('Submit portal group error:', error)
    ElMessage.error(getErrorMessage(error, '操作失败'))
  } finally {
    submitting.value = false
  }
}

const handleToggleStatus = (row) => {
  const enableTarget = !row.allActive
  const actionText = enableTarget ? '启用' : '禁用'

  ElMessageBox.confirm(
    `确定要${actionText}"${row.region_name} / ${row.business_type}"下的全部网站吗？`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      for (const site of row.websites) {
        if (site.is_active === enableTarget) {
          continue
        }

        const res = await togglePortalStatus(site.id)

        if (!res.success) {
          throw new Error(res.message || `${actionText}失败`)
        }
      }

      ElMessage.success(`${actionText}成功`)
      await loadPortalList()
    } catch (error) {
      console.error('Toggle status error:', error)
      ElMessage.error(getErrorMessage(error, `${actionText}失败`))
    }
  }).catch(() => {})
}

const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定要删除"${row.region_name} / ${row.business_type}"下的全部网站吗？`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      for (const site of row.websites) {
        const res = await deleteRegionPortal(site.id)

        if (!res.success) {
          throw new Error(res.message || '删除失败')
        }
      }

      ElMessage.success('删除成功')
      await loadPortalList()
    } catch (error) {
      console.error('Delete portal group error:', error)
      ElMessage.error(getErrorMessage(error, '删除失败'))
    }
  }).catch(() => {})
}

const handleOpenDisplayPage = () => {
  router.push('/region-portals/display')
}

watch(
  totalGroups,
  (total) => {
    const maxPage = Math.max(1, Math.ceil(total / pagination.pageSize))

    if (pagination.current > maxPage) {
      pagination.current = maxPage
    }
  }
)

watch(
  () => accountSetStore.currentAccountSetId,
  async () => {
    await handleReset()
  },
  { immediate: true }
)
</script>

<style scoped>
.region-portal-container {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.header-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.table-card {
  margin-top: 20px;
}

.portal-cell-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 6px 0;
}

.portal-cell-item {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 24px;
}

.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.website-editor-list {
  width: 100%;
}

.website-editor-item {
  width: 100%;
  padding: 12px 12px 0;
  margin-bottom: 12px;
  border: 1px solid var(--el-border-color-light);
  border-radius: 6px;
  background: var(--el-fill-color-blank);
}

.website-editor-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  font-weight: 500;
  color: var(--el-text-color-primary);
}
</style>
