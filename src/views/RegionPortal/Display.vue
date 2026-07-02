<template>
  <div class="region-portal-display-container">
    <div class="card-header">
      <span class="title">地区网站导航</span>
    </div>

    <el-empty v-if="!loading && regionGroups.length === 0" description="暂无可用网站入口" />

    <div v-else class="region-list" v-loading="loading">
      <section v-for="group in regionGroups" :key="group.region_name" class="region-panel">
        <div class="region-panel-header">
          <div class="region-title-wrap">
            <span class="region-dot"></span>
            <h3 class="region-title">{{ group.region_name }}</h3>
          </div>
          <span class="region-count">{{ group.websites.length }} 个网站</span>
        </div>
        <div class="site-chip-list">
          <a
            v-for="site in group.websites"
            :key="site.uid"
            :href="site.portal_url"
            target="_blank"
            rel="noopener noreferrer"
            class="site-chip"
          >
            <span class="site-chip-text">{{ site.portal_name }}</span>
            <el-icon class="site-chip-icon"><ArrowRight /></el-icon>
          </a>
        </div>
      </section>
    </div>
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

const sortPortalList = (list) => {
  return [...list].sort((a, b) => {
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

const regionGroups = computed(() => {
  const groupedMap = new Map()

  sortPortalList(rawPortalList.value)
    .filter((item) => item.is_active)
    .forEach((item) => {
      const regionName = item.region_name || '未设置地区'

      if (!groupedMap.has(regionName)) {
        groupedMap.set(regionName, {
          region_name: regionName,
          websites: []
        })
      }

      const group = groupedMap.get(regionName)
      const duplicate = group.websites.some((site) => {
        return site.portal_name === item.portal_name && site.portal_url === item.portal_url
      })

      if (!duplicate) {
        group.websites.push({
          uid: item.id,
          portal_name: item.portal_name || '',
          portal_url: item.portal_url || ''
        })
      }
    })

  return Array.from(groupedMap.values())
})

watch(
  () => accountSetStore.currentAccountSetId,
  async () => {
    await loadPortalList()
  },
  { immediate: true }
)
</script>

<style scoped>
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

.region-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.region-panel {
  padding: 20px 24px 24px;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid #e8edf5;
  box-shadow: 0 10px 24px rgba(31, 42, 68, 0.06);
}

.region-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 16px;
  margin-bottom: 18px;
  border-bottom: 1px solid #edf2f8;
}

.region-title-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}

.region-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #3b82f6;
  box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.12);
}

.region-title {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
  color: #1f2a44;
}

.region-count {
  flex-shrink: 0;
  padding: 6px 12px;
  border-radius: 999px;
  background: #f1f6ff;
  color: #4a67a1;
  font-size: 13px;
  line-height: 20px;
}

.site-chip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.site-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 10px 14px;
  border-radius: 10px;
  background: #f7faff;
  border: 1px solid #dbe7ff;
  color: #2563eb;
  font-size: 14px;
  line-height: 20px;
  text-decoration: none;
  transition: all 0.2s ease;
}

.site-chip:hover {
  background: #edf4ff;
  border-color: #bfd3ff;
  color: #1d4ed8;
  transform: translateY(-1px);
}

.site-chip-text {
  word-break: break-all;
}

.site-chip-icon {
  font-size: 14px;
}

@media (max-width: 768px) {
  .region-panel {
    padding: 18px;
  }

  .region-panel-header {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
