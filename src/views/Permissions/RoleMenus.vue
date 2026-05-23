<template>
  <div class="role-menus-page">
    <div class="page-header">
      <h1>菜单配置</h1>
      <p class="description">支持主菜单自定义和子菜单自由分配，角色菜单权限按稳定子菜单键保存。</p>
    </div>

    <el-tabs v-model="activeTab">
      <el-tab-pane label="角色菜单分配" name="role-visibility">
        <el-row :gutter="20">
          <el-col :span="6">
            <el-card class="role-list-card">
              <template #header>
                <span>角色列表</span>
              </template>
              <el-menu :default-active="selectedRoleId?.toString()" @select="handleSelectRole">
                <el-menu-item v-for="role in roles" :key="role.id" :index="role.id.toString()">
                  <span>{{ role.display_name }}</span>
                  <el-tag size="small" type="info" style="margin-left: 10px">
                    {{ role.visible_menus === null ? '全部' : `${role.visible_menus?.length || 0}项` }}
                  </el-tag>
                </el-menu-item>
              </el-menu>
            </el-card>
          </el-col>

          <el-col :span="18">
            <el-card v-loading="loading">
              <template #header>
                <div class="card-header">
                  <span>{{ selectedRole?.display_name || '请选择角色' }} - 菜单权限</span>
                  <div v-if="selectedRole && !isBuiltInAllVisibleRole" class="header-actions">
                    <el-button @click="handleSelectAllMenus">全选</el-button>
                    <el-button @click="handleClearMenus">清空</el-button>
                    <el-button type="primary" @click="handleSaveVisibleMenus" :loading="saving">保存</el-button>
                  </div>
                </div>
              </template>

              <div v-if="!selectedRole" class="empty-box">
                <el-empty description="请选择角色" />
              </div>

              <div v-else-if="isBuiltInAllVisibleRole" class="empty-box">
                <el-alert
                  type="info"
                  :closable="false"
                  title="该角色默认可见全部菜单，无需单独配置"
                  show-icon
                />
              </div>

              <div v-else class="visibility-groups">
                <el-collapse v-model="expandedGroups">
                  <el-collapse-item
                    v-for="group in groupedSubmenuOptions"
                    :key="group.id"
                    :name="group.id"
                  >
                    <template #title>
                      <div class="group-title">
                        <el-checkbox
                          :model-value="isGroupAllChecked(group)"
                          :indeterminate="isGroupIndeterminate(group)"
                          @change="handleGroupCheckAll(group, $event)"
                          @click.stop
                        />
                        <span>{{ group.title }}</span>
                        <el-tag size="small" type="info">
                          {{ getGroupCheckedCount(group) }}/{{ group.children.length }}
                        </el-tag>
                      </div>
                    </template>

                    <el-checkbox-group v-model="selectedMenus">
                      <div class="permission-grid">
                        <el-checkbox
                          v-for="child in group.children"
                          :key="child.key"
                          :label="child.key"
                        >
                          {{ child.title }}
                        </el-checkbox>
                      </div>
                    </el-checkbox-group>
                  </el-collapse-item>
                </el-collapse>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <el-tab-pane label="主菜单布局" name="menu-layout">
        <el-card v-loading="layoutLoading">
          <template #header>
            <div class="card-header">
              <span>主菜单布局</span>
              <div class="header-actions">
                <el-button @click="resetLayoutToDefault">恢复默认</el-button>
                <el-button type="primary" @click="handleSaveLayout" :loading="layoutSaving">保存布局</el-button>
              </div>
            </div>
          </template>

          <div class="add-main-menu">
            <el-input v-model="newMainMenu.title" placeholder="主菜单名称" style="width: 220px" />
            <el-select v-model="newMainMenu.icon" placeholder="图标" style="width: 180px">
              <el-option v-for="item in iconOptions" :key="item" :label="item" :value="item" />
            </el-select>
            <el-button type="primary" @click="handleAddMainMenu">新增主菜单</el-button>
          </div>

          <div class="layout-list">
            <div v-for="(menu, index) in menuLayoutDraft" :key="menu.id" class="layout-item">
              <div class="layout-item-header">
                <el-input v-model="menu.title" placeholder="主菜单名称" style="width: 220px" />
                <el-select v-model="menu.icon" placeholder="图标" style="width: 180px">
                  <el-option v-for="item in iconOptions" :key="item" :label="item" :value="item" />
                </el-select>
                <span class="menu-id">ID: {{ menu.id }}</span>
                <el-button @click="moveMainMenuUp(index)" :disabled="index === 0">上移</el-button>
                <el-button @click="moveMainMenuDown(index)" :disabled="index === menuLayoutDraft.length - 1">下移</el-button>
                <el-button type="danger" @click="removeMainMenu(index)">删除</el-button>
              </div>

              <el-select
                v-model="menu.children"
                multiple
                filterable
                collapse-tags
                collapse-tags-tooltip
                placeholder="给该主菜单分配子菜单"
                style="width: 100%"
              >
                <el-option
                  v-for="child in allSubmenuOptions"
                  :key="child.key"
                  :label="`${child.title} (${child.path})`"
                  :value="child.key"
                  :disabled="isChildUsedByOtherMenu(child.key, menu.id)"
                />
              </el-select>
            </div>
          </div>
        </el-card>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/api/request'
import { menuConfig } from '@/config/menuConfig'
import { getMenuLayout, updateMenuLayout } from '@/api/permission'
import {
  buildMenuLibrary,
  normalizeMenuLayout,
  normalizeVisibleMenuKeys
} from '@/utils/menuLayout'

const activeTab = ref('role-visibility')

const roles = ref([])
const selectedRoleId = ref(null)
const selectedMenus = ref([])
const loading = ref(false)
const saving = ref(false)

const layoutLoading = ref(false)
const layoutSaving = ref(false)
const menuLayoutDraft = ref([])
const expandedGroups = ref([])

const newMainMenu = ref({
  title: '',
  icon: 'Menu'
})

const iconOptions = [
  'Menu',
  'House',
  'User',
  'Money',
  'FirstAidKit',
  'Wallet',
  'Checked',
  'Setting',
  'Folder',
  'Document',
  'Calendar'
]

const menuLibrary = computed(() => buildMenuLibrary(menuConfig))

const selectedRole = computed(() => {
  return roles.value.find((role) => role.id === selectedRoleId.value) || null
})

const isBuiltInAllVisibleRole = computed(() => {
  const roleName = selectedRole.value?.name
  return roleName === 'super_admin' || roleName === 'admin'
})

const allSubmenuOptions = computed(() => {
  const items = Object.values(menuLibrary.value.submenuMap || {})
  return items
    .map((item) => ({
      key: item.menuKey,
      title: item.title,
      path: item.path,
      sourceParentId: item.sourceParentId
    }))
    .sort((a, b) => a.path.localeCompare(b.path))
})

const groupedSubmenuOptions = computed(() => {
  const layout = normalizeMenuLayout(menuLayoutDraft.value, menuLibrary.value)
  const submenuMap = menuLibrary.value.submenuMap || {}

  const groups = layout
    .map((group) => ({
      id: group.id,
      title: group.title,
      children: (group.children || [])
        .map((key) => submenuMap[key])
        .filter(Boolean)
        .map((item) => ({
          key: item.menuKey,
          title: item.title,
          path: item.path
        }))
    }))
    .filter((group) => group.children.length > 0)

  return groups
})

const fetchRoles = async () => {
  try {
    const res = await request.get('/roles')
    if (res.success) {
      roles.value = res.data || []
      if (roles.value.length > 0) {
        await handleSelectRole(String(roles.value[0].id))
      }
    }
  } catch (error) {
    ElMessage.error('获取角色列表失败')
  }
}

const fetchMenuLayout = async () => {
  layoutLoading.value = true
  try {
    const res = await getMenuLayout()
    if (res.success) {
      menuLayoutDraft.value = normalizeMenuLayout(res.data, menuLibrary.value)
      expandedGroups.value = menuLayoutDraft.value.map((group) => group.id)
    } else {
      menuLayoutDraft.value = normalizeMenuLayout([], menuLibrary.value)
      expandedGroups.value = menuLayoutDraft.value.map((group) => group.id)
    }
  } catch (error) {
    menuLayoutDraft.value = normalizeMenuLayout([], menuLibrary.value)
    expandedGroups.value = menuLayoutDraft.value.map((group) => group.id)
  } finally {
    layoutLoading.value = false
  }
}

const handleSelectRole = async (roleId) => {
  selectedRoleId.value = Number(roleId)
  loading.value = true

  try {
    const res = await request.get(`/roles/${roleId}`)
    if (res.success) {
      const role = res.data
      const roleIndex = roles.value.findIndex((item) => item.id === role.id)
      if (roleIndex !== -1) {
        roles.value[roleIndex].visible_menus = role.visible_menus
      }

      selectedMenus.value = normalizeVisibleMenuKeys(role.visible_menus, menuLibrary.value)
    }
  } catch (error) {
    ElMessage.error('获取角色详情失败')
  } finally {
    loading.value = false
  }
}

const handleSaveVisibleMenus = async () => {
  if (!selectedRoleId.value) {
    ElMessage.warning('请先选择角色')
    return
  }

  saving.value = true
  try {
    const payload = Array.from(new Set(selectedMenus.value))
    const res = await request.put(`/roles/${selectedRoleId.value}/visible-menus`, {
      visible_menus: payload
    })
    if (res.success) {
      ElMessage.success('角色菜单保存成功')
      await fetchRoles()
    }
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '保存失败')
  } finally {
    saving.value = false
  }
}

const getAllMenuKeys = () => allSubmenuOptions.value.map((item) => item.key)

const handleSelectAllMenus = () => {
  selectedMenus.value = getAllMenuKeys()
}

const handleClearMenus = () => {
  selectedMenus.value = []
}

const isGroupAllChecked = (group) => {
  if (!group.children.length) return false
  return group.children.every((child) => selectedMenus.value.includes(child.key))
}

const isGroupIndeterminate = (group) => {
  const checkedCount = group.children.filter((child) => selectedMenus.value.includes(child.key)).length
  return checkedCount > 0 && checkedCount < group.children.length
}

const getGroupCheckedCount = (group) => {
  return group.children.filter((child) => selectedMenus.value.includes(child.key)).length
}

const handleGroupCheckAll = (group, checked) => {
  const groupKeys = group.children.map((item) => item.key)
  if (checked) {
    selectedMenus.value = Array.from(new Set([...selectedMenus.value, ...groupKeys]))
  } else {
    selectedMenus.value = selectedMenus.value.filter((key) => !groupKeys.includes(key))
  }
}

const isChildUsedByOtherMenu = (childKey, currentMenuId) => {
  return menuLayoutDraft.value.some((menu) => {
    if (menu.id === currentMenuId) return false
    return Array.isArray(menu.children) && menu.children.includes(childKey)
  })
}

const handleAddMainMenu = () => {
  const title = newMainMenu.value.title.trim()
  if (!title) {
    ElMessage.warning('请输入主菜单名称')
    return
  }

  const id = `custom_${Date.now()}`
  menuLayoutDraft.value.push({
    id,
    title,
    icon: newMainMenu.value.icon || 'Menu',
    children: []
  })

  newMainMenu.value.title = ''
  newMainMenu.value.icon = 'Menu'
  expandedGroups.value = menuLayoutDraft.value.map((group) => group.id)
}

const removeMainMenu = (index) => {
  menuLayoutDraft.value.splice(index, 1)
}

const moveMainMenuUp = (index) => {
  if (index <= 0) return
  const list = [...menuLayoutDraft.value]
  const temp = list[index - 1]
  list[index - 1] = list[index]
  list[index] = temp
  menuLayoutDraft.value = list
}

const moveMainMenuDown = (index) => {
  const list = [...menuLayoutDraft.value]
  if (index >= list.length - 1) return
  const temp = list[index + 1]
  list[index + 1] = list[index]
  list[index] = temp
  menuLayoutDraft.value = list
}

const resetLayoutToDefault = () => {
  menuLayoutDraft.value = normalizeMenuLayout([], menuLibrary.value)
  expandedGroups.value = menuLayoutDraft.value.map((group) => group.id)
}

const handleSaveLayout = async () => {
  layoutSaving.value = true
  try {
    const normalized = normalizeMenuLayout(menuLayoutDraft.value, menuLibrary.value)
    const res = await updateMenuLayout(normalized)
    if (res.success) {
      menuLayoutDraft.value = normalizeMenuLayout(res.data, menuLibrary.value)
      expandedGroups.value = menuLayoutDraft.value.map((group) => group.id)
      ElMessage.success('菜单布局保存成功')
    }
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '保存菜单布局失败')
  } finally {
    layoutSaving.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchMenuLayout(), fetchRoles()])
})
</script>

<style scoped>
.role-menus-page {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0 0 8px;
  font-size: 20px;
}

.description {
  margin: 0;
  color: #909399;
}

.role-list-card {
  height: calc(100vh - 240px);
  overflow-y: auto;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.empty-box {
  padding: 32px;
}

.visibility-groups {
  padding-top: 6px;
}

.group-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.permission-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  padding: 8px 0;
}

.add-main-menu {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.layout-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.layout-item {
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 12px;
}

.layout-item-header {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 10px;
}

.menu-id {
  color: #909399;
  font-size: 12px;
}
</style>
