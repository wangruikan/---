<template>
  <div class="payment-center-page">
    <div class="title-row">
      <div class="page-title">付款申请</div>
      <el-button
        v-if="activeTab === 'reimbursement'"
        type="primary"
        class="top-create-btn"
        @click="handleCreateReimbursement"
      >
        发起报销
      </el-button>
    </div>

    <el-card class="tabs-card" shadow="never">
      <el-tabs v-model="activeTab" class="payment-center-tabs" @tab-change="handleTabChange">
        <el-tab-pane label="工资/保险" name="payment" />
        <el-tab-pane label="报销/差旅/采购/项目/其他" name="reimbursement" />
      </el-tabs>
    </el-card>

    <div class="tab-content" :class="`tab-${activeTab}`">
      <keep-alive>
        <component :is="currentView" ref="currentPageRef" />
      </keep-alive>
    </div>
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const tabs = ['payment', 'reimbursement']
const resolveActiveTab = () => {
  return tabs.includes(route.query.tab) ? route.query.tab : 'payment'
}

const activeTab = ref(resolveActiveTab())
const currentPageRef = ref(null)

const PaymentApplicationLegacy = defineAsyncComponent(() => import('./PaymentApplicationLegacy.vue'))
const ReimbursementPage = defineAsyncComponent(() => import('@/views/Reimbursement/index.vue'))

const currentView = computed(() => {
  return activeTab.value === 'reimbursement' ? ReimbursementPage : PaymentApplicationLegacy
})

const handleTabChange = (name) => {
  if (!tabs.includes(name)) return
  activeTab.value = name
}

const handleCreateReimbursement = () => {
  const pageInstance = currentPageRef.value
  const openCreateDialog = pageInstance?.openCreateDialog || pageInstance?.handleCreate
  if (typeof openCreateDialog === 'function') {
    openCreateDialog()
  }
}

watch(
  () => activeTab.value,
  (tab) => {
    const nextQuery = { ...route.query, tab }
    router.replace({ query: nextQuery })
  },
  { immediate: true }
)

watch(
  () => route.query.tab,
  (tab) => {
    if (tabs.includes(tab) && tab !== activeTab.value) {
      activeTab.value = tab
    }
  }
)

watch(
  () => route.path,
  () => {
    const nextTab = resolveActiveTab()
    if (nextTab !== activeTab.value) {
      activeTab.value = nextTab
    }
  }
)
</script>

<style scoped>
.payment-center-page {
  min-height: 100%;
}

.title-row {
  margin: 20px 20px 0 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  font-size: 22px;
  font-weight: 600;
  color: #303133;
}

.top-create-btn {
  flex-shrink: 0;
}

.tabs-card {
  margin: 12px 20px 0 20px;
}

:deep(.payment-center-tabs .el-tabs__header) {
  margin-bottom: 0;
}

.tab-content.tab-reimbursement :deep(.reimbursement-page) {
  padding-top: 0;
}

.tab-content.tab-reimbursement :deep(.reimbursement-page .page-header) {
  display: none;
}
</style>
