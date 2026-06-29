<template>
  <div class="invoice-configs-container">
    <div class="page-header">
      <h1>发票配置</h1>
      <p>统一维护发票项目和开票内容配置。</p>
    </div>

    <el-tabs v-model="activeTab" @tab-change="handleTabChange">
      <el-tab-pane label="发票项目配置" name="projects" lazy>
        <InvoiceProjectsView />
      </el-tab-pane>
      <el-tab-pane label="开票内容配置项目" name="content" lazy>
        <InvoiceContentConfigsView />
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import InvoiceProjectsView from '@/views/InvoiceProjects/index.vue'
import InvoiceContentConfigsView from '@/views/InvoiceContentConfigs/index.vue'

const route = useRoute()
const router = useRouter()

const validTabs = ['projects', 'content']

const activeTab = computed({
  get() {
    const tab = route.query.tab
    return validTabs.includes(tab) ? tab : 'projects'
  },
  set(value) {
    const nextTab = validTabs.includes(value) ? value : 'projects'
    if (route.query.tab === nextTab) {
      return
    }

    router.replace({
      name: 'InvoiceConfigs',
      query: {
        ...route.query,
        tab: nextTab
      }
    })
  }
})

const handleTabChange = (tab) => {
  activeTab.value = tab
}
</script>

<style scoped>
.invoice-configs-container {
  padding: 20px;
}

.page-header {
  margin-bottom: 16px;
}

.page-header h1 {
  margin: 0;
  font-size: 24px;
  color: #303133;
}

.page-header p {
  margin: 8px 0 0;
  color: #606266;
  font-size: 14px;
}
</style>
