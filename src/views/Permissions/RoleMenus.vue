<template>
  <div class="role-menus-page">
    <div class="page-header">
      <h1>角色菜单配置</h1>
      <p class="description">为每个角色配置可见的菜单项，控制用户能看到哪些页面</p>
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
              <el-tag v-if="role.visible_menus === null" size="small" type="success" style="margin-left: 10px">
                全部
              </el-tag>
              <el-tag v-else size="small" type="info" style="margin-left: 10px">
                {{ role.visible_menus?.length || 0 }}项
              </el-tag>
            </el-menu-item>
          </el-menu>
        </el-card>
      </el-col>

      <!-- 菜单配置 -->
      <el-col :span="18">
        <el-card v-loading="loading">
          <template #header>
            <div class="menu-header">
              <span>{{ selectedRole?.display_name || '请选择角色' }} - 菜单配置</span>
              <div v-if="selectedRole && selectedRole.name !== 'super_admin'" class="header-actions">
                <el-button @click="handleSelectAll">全选</el-button>
                <el-button @click="handleSelectNone">全不选</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存配置</el-button>
              </div>
            </div>
          </template>

          <div v-if="!selectedRole" class="no-selection">
            <el-empty description="请从左侧选择一个角色" />
          </div>

          <div v-else-if="selectedRole.name === 'super_admin'" class="admin-notice">
            <el-alert 
              title="超级管理员可见所有菜单" 
              type="info" 
              description="超级管理员角色默认可以看到所有菜单，无需单独配置" 
              show-icon 
              :closable="false" 
            />
          </div>

          <div v-else class="menu-tree">
            <el-tree
              ref="menuTreeRef"
              :data="menuTreeData"
              :props="treeProps"
              show-checkbox
              node-key="id"
              :default-checked-keys="selectedMenus"
              @check="handleMenuCheck"
            >
              <template #default="{ node, data }">
                <div class="tree-node">
                  <el-icon v-if="data.icon" style="margin-right: 8px">
                    <component :is="data.icon" />
                  </el-icon>
                  <span>{{ data.title }}</span>
                  <el-tag v-if="data.path" size="small" type="info" style="margin-left: 10px">
                    {{ data.path }}
                  </el-tag>
                </div>
              </template>
            </el-tree>
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
import { menuConfig } from '@/config/menuConfig'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'

const roles = ref([])
const selectedRoleId = ref(null)
const selectedMenus = ref([])
const loading = ref(false)
const saving = ref(false)
const menuTreeRef = ref(null)

const selectedRole = computed(() => roles.value.find(r => r.id === selectedRoleId.value))

// 树形控件配置
const treeProps = {
  children: 'children',
  label: 'title'
}

// 将菜单配置转换为树形数据
const menuTreeData = computed(() => {
  return menuConfig.map(menu => {
    const item = {
      id: menu.id,
      title: menu.title,
      icon: menu.icon,
      path: menu.path,
      children: []
    }
    
    if (menu.children && menu.children.length > 0) {
      item.children = menu.children.map(child => ({
        // 生成二级菜单ID：父菜单ID + 子菜单path（将/替换为-）
        id: `${menu.id}-${child.path.replace(/\//g, '-')}`,
        title: child.title,
        icon: child.icon,
        path: child.path
      }))
    }
    
    return item
  })
})

onMounted(() => {
  fetchRoles()
})

const fetchRoles = async () => {
  try {
    const res = await request.get('/roles')
    if (res.success) {
      roles.value = res.data
      if (roles.value.length > 0) {
        handleSelectRole(roles.value[0].id.toString())
      }
    }
  } catch (error) {
    ElMessage.error('获取角色列表失败')
  }
}

const handleSelectRole = async (roleId) => {
  selectedRoleId.value = parseInt(roleId)
  loading.value = true
  
  try {
    const res = await request.get(`/roles/${roleId}`)
    if (res.success) {
      const role = res.data
      
      // 更新当前角色信息
      const roleIndex = roles.value.findIndex(r => r.id === role.id)
      if (roleIndex !== -1) {
        roles.value[roleIndex].visible_menus = role.visible_menus
      }
      
      // 如果是管理员或超级管理员，visible_menus 为 null，表示可见所有菜单
      if (role.visible_menus === null) {
        selectedMenus.value = []
      } else {
        selectedMenus.value = role.visible_menus || []
      }
      
      // 等待下一帧再设置选中状态
      setTimeout(() => {
        if (menuTreeRef.value) {
          menuTreeRef.value.setCheckedKeys(selectedMenus.value)
        }
      }, 100)
    }
  } catch (error) {
    ElMessage.error('获取角色信息失败')
  } finally {
    loading.value = false
  }
}

const handleMenuCheck = () => {
  if (menuTreeRef.value) {
    selectedMenus.value = menuTreeRef.value.getCheckedKeys()
  }
}

const handleSave = async () => {
  if (!selectedRoleId.value) {
    ElMessage.warning('请先选择角色')
    return
  }
  
  saving.value = true
  try {
    const res = await request.put(`/roles/${selectedRoleId.value}/visible-menus`, {
      visible_menus: selectedMenus.value
    })
    
    if (res.success) {
      ElMessage.success('菜单配置保存成功')
      fetchRoles()
    }
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '保存菜单配置失败')
  } finally {
    saving.value = false
  }
}

const handleSelectAll = () => {
  if (menuTreeRef.value) {
    const allKeys = []
    const collectKeys = (nodes) => {
      nodes.forEach(node => {
        allKeys.push(node.id)
        if (node.children && node.children.length > 0) {
          collectKeys(node.children)
        }
      })
    }
    collectKeys(menuTreeData.value)
    menuTreeRef.value.setCheckedKeys(allKeys)
    selectedMenus.value = allKeys
  }
}

const handleSelectNone = () => {
  if (menuTreeRef.value) {
    menuTreeRef.value.setCheckedKeys([])
    selectedMenus.value = []
  }
}
</script>

<style scoped>
.role-menus-page {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0 0 8px 0;
  font-size: 20px;
}

.page-header .description {
  color: #909399;
  margin: 0;
}

.role-list-card {
  height: calc(100vh - 180px);
  overflow-y: auto;
}

.menu-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.no-selection,
.admin-notice {
  padding: 40px;
}

.menu-tree {
  padding: 20px;
}

.tree-node {
  display: flex;
  align-items: center;
  flex: 1;
}

:deep(.el-tree-node__content) {
  height: 40px;
}
</style>
