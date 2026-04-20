<template>
  <div class="role-permissions-page">
    <div class="page-header">
      <h1>角色权限管理</h1>
      <p class="description">为每个角色配置权限，用户将自动继承其角色的权限</p>
    </div>

    <el-row :gutter="20">
      <!-- 角色列表 -->
      <el-col :span="6">
        <el-card class="role-list-card">
          <template #header>
            <span>角色列表</span>
          </template>
          <el-menu :default-active="selectedRoleId?.toString()" @select="handleSelectRole">
            <el-menu-item v-for="role in roles" :key="role.id" :index="role.id.toString()">
              <span>{{ role.display_name }}</span>
              <el-tag size="small" type="info" style="margin-left: 10px">
                {{ role.permission_count }}个权限
              </el-tag>
            </el-menu-item>
          </el-menu>
        </el-card>
      </el-col>

      <!-- 权限配置 -->
      <el-col :span="18">
        <el-card v-loading="loading">
          <template #header>
            <div class="permission-header">
              <span>{{ selectedRole?.display_name || '请选择角色' }} - 权限配置</span>
              <div v-if="selectedRole && selectedRole.name !== 'super_admin'" class="header-actions">
                <el-button @click="handleSelectAll">全选</el-button>
                <el-button @click="handleSelectNone">全不选</el-button>
                <el-button @click="handleSelectViewOnly">仅查看</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存权限</el-button>
              </div>
            </div>
          </template>

          <div v-if="!selectedRole" class="no-selection">
            <el-empty description="请从左侧选择一个角色" />
          </div>

          <div v-else-if="selectedRole.name === 'super_admin'" class="super-admin-notice">
            <el-alert title="超级管理员拥有所有权限" type="warning" description="超级管理员角色自动拥有系统所有权限，无需单独配置" show-icon :closable="false" />
          </div>

          <div v-else class="permission-modules">
            <el-collapse v-model="activeModules">
              <el-collapse-item v-for="group in permissionGroups" :key="group.module" :name="group.module">
                <template #title>
                  <div class="module-title">
                    <el-checkbox :model-value="isModuleAllChecked(group)" :indeterminate="isModuleIndeterminate(group)" @change="handleModuleCheckAll(group, $event)" @click.stop />
                    <span class="module-name">{{ group.module_name }}</span>
                    <el-tag size="small" type="info">{{ getModuleCheckedCount(group) }}/{{ group.permissions.length }}</el-tag>
                  </div>
                </template>
                <el-checkbox-group v-model="selectedPermissions">
                  <div class="permission-list">
                    <div v-for="perm in group.permissions" :key="perm.id" class="permission-item">
                      <el-checkbox :label="perm.id">{{ perm.name }}</el-checkbox>
                    </div>
                  </div>
                </el-checkbox-group>
              </el-collapse-item>
            </el-collapse>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/api/request'

const roles = ref([])
const permissionGroups = ref([])
const selectedRoleId = ref(null)
const selectedPermissions = ref([])
const activeModules = ref([])
const loading = ref(false)
const saving = ref(false)

const selectedRole = computed(() => roles.value.find(r => r.id === selectedRoleId.value))

onMounted(() => {
  fetchRoles()
  fetchPermissions()
})

const fetchRoles = async () => {
  try {
    const res = await request.get('/roles')
    if (res.success) {
      roles.value = res.data
      if (roles.value.length > 0) handleSelectRole(roles.value[0].id.toString())
    }
  } catch (error) {
    ElMessage.error('获取角色列表失败')
  }
}

const fetchPermissions = async () => {
  try {
    const res = await request.get('/roles/permissions/all')
    if (res.success) {
      permissionGroups.value = res.data
      activeModules.value = res.data.map(g => g.module)
    }
  } catch (error) {
    ElMessage.error('获取权限列表失败')
  }
}

const handleSelectRole = async (roleId) => {
  selectedRoleId.value = parseInt(roleId)
  loading.value = true
  try {
    const res = await request.get(`/roles/${roleId}`)
    if (res.success) selectedPermissions.value = res.data.permission_ids || []
  } catch (error) {
    ElMessage.error('获取角色权限失败')
  } finally {
    loading.value = false
  }
}

const handleSave = async () => {
  saving.value = true
  try {
    const res = await request.put(`/roles/${selectedRoleId.value}/permissions`, { permission_ids: selectedPermissions.value })
    if (res.success) {
      ElMessage.success('权限保存成功')
      fetchRoles()
    }
  } catch (error) {
    ElMessage.error('保存权限失败')
  } finally {
    saving.value = false
  }
}

const isModuleAllChecked = (group) => group.permissions.every(p => selectedPermissions.value.includes(p.id))
const isModuleIndeterminate = (group) => {
  const count = group.permissions.filter(p => selectedPermissions.value.includes(p.id)).length
  return count > 0 && count < group.permissions.length
}
const getModuleCheckedCount = (group) => group.permissions.filter(p => selectedPermissions.value.includes(p.id)).length
const handleModuleCheckAll = (group, checked) => {
  const permIds = group.permissions.map(p => p.id)
  if (checked) selectedPermissions.value = [...new Set([...selectedPermissions.value, ...permIds])]
  else selectedPermissions.value = selectedPermissions.value.filter(id => !permIds.includes(id))
}

// 全选所有权限
const handleSelectAll = () => {
  const allPermIds = permissionGroups.value.flatMap(g => g.permissions.map(p => p.id))
  selectedPermissions.value = allPermIds
}

// 全不选
const handleSelectNone = () => {
  selectedPermissions.value = []
}

// 仅选择查看权限（action包含view、list、index、show、get的权限）
const handleSelectViewOnly = () => {
  const viewPermIds = permissionGroups.value.flatMap(g => 
    g.permissions.filter(p => /view|list|index|show|get|查看|列表/i.test(p.action || p.name)).map(p => p.id)
  )
  selectedPermissions.value = viewPermIds
}

</script>

<style scoped>
.role-permissions-page { padding: 20px; }
.page-header { margin-bottom: 20px; }
.page-header h1 { margin: 0 0 8px 0; font-size: 20px; }
.page-header .description { color: #909399; margin: 0; }
.role-list-card { height: calc(100vh - 180px); overflow-y: auto; }
.permission-header { display: flex; justify-content: space-between; align-items: center; }
.header-actions { display: flex; gap: 10px; }
.no-selection, .super-admin-notice { padding: 40px; }
.module-title { display: flex; align-items: center; gap: 10px; }
.module-name { font-weight: 500; }
.permission-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; padding: 10px 0; }
.permission-item { padding: 8px 12px; background: #f5f7fa; border-radius: 4px; }

</style>
