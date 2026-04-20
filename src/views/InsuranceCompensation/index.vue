<template>
  <div class="insurance-compensation">
    <div class="page-header">
      <h1>理赔管理</h1>
    </div>

    <el-card>
      <el-tabs v-model="activeTab" @tab-click="handleTabClick">
        <!-- 工伤申报 Tab -->
        <el-tab-pane label="工伤申报" name="work_injury">
          <WorkInjuryTab />
        </el-tab-pane>

        <!-- 商业险申报 Tab -->
        <el-tab-pane label="商业险申报" name="commercial">
          <CommercialInsuranceTab />
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import WorkInjuryTab from './WorkInjuryTab.vue'
import CommercialInsuranceTab from './CommercialInsuranceTab.vue'

const activeTab = ref('work_injury')

const handleTabClick = (tab) => {
  console.log('切换到Tab:', tab.props.name)
}
</script>

<style scoped>
.insurance-compensation {
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
</style>
