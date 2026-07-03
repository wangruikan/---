<template>
  <div class="region-portal-display-container">
    <div class="card-header">
      <span class="title">地区网站导航</span>
    </div>

    <el-empty v-if="!loading && businessTypeGroups.length === 0" description="暂无可用网站入口" />

    <div v-else class="business-type-grid" v-loading="loading">
      <button
        v-for="group in businessTypeGroups"
        :key="group.business_type"
        type="button"
        class="business-type-card"
        @click="handleOpenGroup(group)"
      >
        <div class="business-type-card-header">
          <div class="business-type-title-wrap">
            <span class="business-type-dot"></span>
            <h3 class="business-type-title">{{ group.business_type }}</h3>
          </div>
          <span class="business-type-count">{{ group.websites.length }} 个网站</span>
        </div>
        <div class="business-type-preview">
          <span
            v-for="site in group.previewWebsites"
            :key="site.uid"
            class="preview-chip"
          >
            {{ site.portal_name }}
          </span>
          <span v-if="group.remainingCount > 0" class="preview-chip more-chip">
            +{{ group.remainingCount }}
          </span>
        </div>
      </button>
    </div>

    <el-dialog
      v-model="dialogVisible"
      :title="currentGroup?.business_type || '网站列表'"
      width="720px"
      :close-on-click-modal="false"
      destroy-on-close
    >
      <div v-if="currentGroup" class="website-dialog-content">
        <div class="dialog-summary">
          共 {{ currentGroup.websites.length }} 个网站
        </div>

        <div class="website-list">
          <a
            v-for="site in currentGroup.websites"
            :key="site.uid"
            :href="site.portal_url"
            target="_blank"
            rel="noopener noreferrer"
            class="website-item"
          >
            <div class="website-item-main">
              <span class="website-name">{{ site.portal_name }}</span>
              <el-tag size="small" effect="plain">{{ site.region_name }}</el-tag>
            </div>
            <el-icon class="website-item-icon"><ArrowRight /></el-icon>
          </a>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { ArrowRight } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useAccountSetStore } from '@/stores/accountSet'
import { getRegionPortals } from '@/api/regionPortal'

const accountSetStore = useAccountSetStore()

const loading = ref(false)
const rawPortalList = ref([])
const dialogVisible = ref(false)
const currentGroup = ref(null)

const sortPortalList = (list) => {
  return [...list].sort((a, b) => {
    const businessTypeDiff = String(a.business_type || '').localeCompare(String(b.business_type || ''), 'zh-CN')
    if (businessTypeDiff !== 0) {
      return businessTypeDiff
    }

    const sortDiff = Number(a.sort_order || 0) - Number(b.sort_order || 0)
    if (sortDiff !== 0) {
      return sortDiff
    }

    const regionDiff = String(a.region_name || '').localeCompare(String(b.region_name || ''), 'zh-CN')
    if (regionDiff !== 0) {
      return regionDiff
    }

    return String(a.portal_name || '').localeCompare(String(b.portal_name || ''), 'zh-CN')
  })
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

    rawPortalList.value = sortPortalList(rows)
  } catch (error) {
    console.error('Load region portal display list error:', error)
    ElMessage.error(getErrorMessage(error, '获取列表失败'))
  } finally {
    loading.value = false
  }
}

const businessTypeGroups = computed(() => {
  const groupedMap = new Map()

  sortPortalList(rawPortalList.value)
    .filter((item) => item.is_active)
    .forEach((item) => {
      const businessType = item.business_type || '未设置业务类型'

      if (!groupedMap.has(businessType)) {
        groupedMap.set(businessType, {
          business_type: businessType,
          websites: []
        })
      }

      const group = groupedMap.get(businessType)
      const duplicate = group.websites.some((site) => {
        return site.region_name === (item.region_name || '未设置地区') &&
          site.portal_name === item.portal_name &&
          site.portal_url === item.portal_url
      })

      if (!duplicate) {
        group.websites.push({
          uid: item.id,
          region_name: item.region_name || '未设置地区',
          portal_name: item.portal_name || '',
          portal_url: item.portal_url || ''
        })
      }
    })

  return Array.from(groupedMap.values()).map((group) => ({
    ...group,
    previewWebsites: group.websites.slice(0, 3),
    remainingCount: Math.max(0, group.websites.length - 3)
  }))
})

const handleOpenGroup = (group) => {
  currentGroup.value = group
  dialogVisible.value = true
}

watch(dialogVisible, (visible) => {
  if (!visible) {
    currentGroup.value = null
  }
})

watch(
  () => accountSetStore.currentAccountSetId,
  async () => {
    await loadPortalList()
  },
  { immediate: true }
)
</script>

<style>
.region-portal-display-container {
  padding: 20px;
}

.card-header {
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.title {
  font-size: 16px;
  font-weight: 600;
  color: var(--el-text-color-primary);
}

.business-type-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 16px;
}

.business-type-card {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  width: 100%;
  min-height: 136px;
  padding: 20px;
  border: 1px solid #e8edf5;
  border-radius: 10px;
  background: #ffffff;
  box-shadow: 0 10px 24px rgba(31, 42, 68, 0.06);
  text-align: left;
  cursor: pointer;
  transition: all 0.2s ease;
  appearance: none;
}

.business-type-card:hover {
  border-color: #bfd3ff;
  box-shadow: 0 14px 28px rgba(31, 42, 68, 0.1);
  transform: translateY(-1px);
}

.business-type-card:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}

.business-type-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.business-type-title-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.business-type-dot {
  width: 10px;
  height: 10px;
  flex-shrink: 0;
  border-radius: 50%;
  background: #3b82f6;
  box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.12);
}

.business-type-title {
  margin: 0;
  color: #1f2a44;
  font-size: 18px;
  font-weight: 600;
  line-height: 26px;
  word-break: break-all;
}

.business-type-count {
  flex-shrink: 0;
  padding: 6px 12px;
  border-radius: 999px;
  background: #f1f6ff;
  color: #4a67a1;
  font-size: 13px;
  line-height: 20px;
}

.business-type-preview {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.preview-chip {
  display: inline-flex;
  align-items: center;
  min-height: 34px;
  padding: 6px 12px;
  border-radius: 999px;
  background: #f7faff;
  border: 1px solid #dbe7ff;
  color: #2563eb;
  font-size: 13px;
  line-height: 18px;
  word-break: break-all;
}

.more-chip {
  color: #4a67a1;
}

.website-dialog-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.dialog-summary {
  color: var(--el-text-color-secondary);
  font-size: 14px;
  line-height: 22px;
}

.website-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 480px;
  overflow-y: auto;
  padding-right: 4px;
}

.website-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 56px;
  padding: 14px 16px;
  border: 1px solid #e8edf5;
  border-radius: 10px;
  background: #ffffff;
  color: inherit;
  text-decoration: none;
  transition: all 0.2s ease;
}

.website-item:hover {
  border-color: #bfd3ff;
  background: #f8fbff;
}

.website-item-main {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  min-width: 0;
}

.website-name {
  color: #1f2a44;
  font-size: 14px;
  line-height: 22px;
  word-break: break-all;
}

.website-item-icon {
  flex-shrink: 0;
  color: #2563eb;
  font-size: 16px;
}

@media (max-width: 768px) {
  .business-type-grid {
    grid-template-columns: 1fr;
  }

  .business-type-card {
    padding: 18px;
  }

  .business-type-card-header,
  .website-item,
  .website-item-main {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
