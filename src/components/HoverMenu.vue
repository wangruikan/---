<template>
  <div class="hover-menu-container">
    <!-- 左侧一级菜单 -->
    <div class="primary-menu">
      <div class="logo">
        <div class="logo-icon">🏢</div>
      </div>
      
      <div 
        v-for="menu in visibleMenus" 
        :key="menu.id"
        :ref="el => setMenuRef(menu.id, el)"
        class="menu-item"
        :class="{ active: isMenuActive(menu) }"
        @mouseenter="handleMenuHover(menu, $event)"
        @click="handleMenuClick(menu)"
      >
        <el-icon :size="24">
          <component :is="menu.icon" />
        </el-icon>
        <span class="menu-title">{{ menu.title }}</span>
      </div>
    </div>
    
    <!-- 右侧悬浮子菜单面板 -->
    <transition name="submenu-fade">
      <div 
        v-if="activeMenu && activeMenu.children"
        class="submenu-panel"
        :style="{ top: submenuTop + 'px' }"
        @mouseenter="keepSubmenuOpen"
        @mouseleave="closeSubmenu"
      >
        <div class="submenu-header">
          <h3>{{ activeMenu.title }}</h3>
        </div>
        <div class="submenu-grid">
          <div
            v-for="item in visibleSubmenuItems"
            :key="item.path"
            class="submenu-item"
            :class="{ active: $route.path === item.path }"
            @click="navigateTo(item.path)"
          >
            <el-icon :size="20">
              <component :is="item.icon" />
            </el-icon>
            <span>{{ item.title }}</span>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import { usePermissionStore } from '@/stores/permission'
import { menuConfig } from '@/config/menuConfig'
import {
  House, User, CircleClose, UserFilled, Document, Money, Wallet, 
  Calendar, Setting, Folder, Edit, Files, Checked, FirstAidKit,
  DocumentChecked, List, Tickets, DocumentCopy, FolderOpened, 
  Link, Box, Key, Suitcase
} from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

const activeMenu = ref(null)
const hoverTimer = ref(null)
const leaveTimer = ref(null)
const submenuTop = ref(0)
const menuRefs = ref({})

// 设置菜单项的ref
const setMenuRef = (menuId, el) => {
  if (el) {
    menuRefs.value[menuId] = el
  }
}

// 权限判断
const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const hasAccountSet = computed(() => {
  return accountSetStore.myAccountSets && accountSetStore.myAccountSets.length > 0
})

const hasBusinessModuleAccess = computed(() => {
  return permissionStore.hasModuleAccess('projects') ||
    permissionStore.hasModuleAccess('attendance') ||
    permissionStore.hasModuleAccess('salaries') ||
    permissionStore.hasModuleAccess('social_security') ||
    permissionStore.hasModuleAccess('housing_fund') ||
    permissionStore.hasModuleAccess('other_insurance') ||
    permissionStore.hasModuleAccess('large_medical') ||
    permissionStore.hasModuleAccess('base_adjustment') ||
    permissionStore.hasModuleAccess('insurance_change') ||
    permissionStore.hasModuleAccess('payment_applications') ||
    permissionStore.hasModuleAccess('payment_summaries') ||
    permissionStore.hasModuleAccess('approvals') ||
    permissionStore.hasModuleAccess('account_sets')
})

// 过滤可见菜单
const visibleMenus = computed(() => {
  return menuConfig.filter(menu => {
    // 基础权限检查
    if (menu.requireAdmin && !isAdmin.value) return false
    if (menu.requireBusiness && !isAdmin.value && !hasAccountSet.value && !hasBusinessModuleAccess.value) return false
    
    // 角色菜单显示权限检查
    const userVisibleMenus = userStore.userInfo?.visible_menus
    
    // 如果用户的 visible_menus 为 null 或 undefined，表示可以看到所有菜单（管理员）
    if (!userVisibleMenus) return true
    
    // 如果 visible_menus 是空数组，表示没有任何菜单权限
    if (Array.isArray(userVisibleMenus) && userVisibleMenus.length === 0) return false
    
    // 如果是有子菜单的一级菜单，检查是否有任意子菜单权限
    if (menu.children && menu.children.length > 0) {
      // 只要有任意一个子菜单在可见列表中，就显示这个一级菜单
      const hasAnyChildVisible = menu.children.some(child => {
        const submenuId = `${menu.id}-${child.path.replace(/\//g, '-')}`
        return userVisibleMenus.includes(submenuId)
      })
      if (hasAnyChildVisible) return true
    }
    
    // 检查当前菜单ID是否在用户的可见菜单列表中
    if (Array.isArray(userVisibleMenus) && !userVisibleMenus.includes(menu.id)) return false
    
    return true
  })
})

// 过滤可见子菜单项
const visibleSubmenuItems = computed(() => {
  if (!activeMenu.value || !activeMenu.value.children) return []
  
  return activeMenu.value.children.filter(item => {
    // 如果设置了跳过权限检查，直接显示
    if (item.skipPermissionCheck) return true
    
    // 检查账套权限
    if (item.requireAccountSet && !isAdmin.value && !hasAccountSet.value) return false
    
    // 检查审批级别（经办人不可见）
    if (item.notForLevel1 && userStore.userInfo?.approval_level === 1) return false
    
    // 检查特定权限
    if (item.permission && !permissionStore.hasPermission(item.permission)) return false
    
    // 角色菜单显示权限检查（二级菜单）
    const userVisibleMenus = userStore.userInfo?.visible_menus
    
    // 如果用户的 visible_menus 为 null 或 undefined，表示可以看到所有菜单（管理员）
    if (!userVisibleMenus) return true
    
    // 如果 visible_menus 是空数组，表示没有任何菜单权限
    if (Array.isArray(userVisibleMenus) && userVisibleMenus.length === 0) return false
    
    // 生成二级菜单的唯一ID：父菜单ID + 子菜单path
    const submenuId = `${activeMenu.value.id}-${item.path.replace(/\//g, '-')}`
    
    // 检查二级菜单ID是否在用户的可见菜单列表中
    if (Array.isArray(userVisibleMenus) && !userVisibleMenus.includes(submenuId)) return false
    
    // 这些需要动态检查，暂时都显示
    // if (item.requireInvoice) return hasInvoiceAccess.value
    // if (item.requireDelivery) return hasDeliveryConfigAccess.value
    // if (item.requireProcessRecord) return canViewProcessRecords.value
    
    return true
  })
})

// 判断菜单是否激活
const isMenuActive = (menu) => {
  if (menu.path) {
    return route.path === menu.path
  }
  if (menu.children) {
    return menu.children.some(item => route.path === item.path)
  }
  return false
}

// 处理菜单悬浮
const handleMenuHover = (menu, event) => {
  // 清除之前的定时器
  if (leaveTimer.value) {
    clearTimeout(leaveTimer.value)
    leaveTimer.value = null
  }
  
  // 延迟显示子菜单
  if (hoverTimer.value) {
    clearTimeout(hoverTimer.value)
  }
  
  hoverTimer.value = setTimeout(() => {
    if (menu.children && menu.children.length > 0) {
      activeMenu.value = menu
      
      // 计算弹窗位置
      const menuElement = menuRefs.value[menu.id]
      if (menuElement) {
        const rect = menuElement.getBoundingClientRect()
        submenuTop.value = rect.top
      }
    } else {
      activeMenu.value = null
    }
  }, 100)
}

// 处理菜单点击
const handleMenuClick = (menu) => {
  if (menu.path) {
    router.push(menu.path)
    activeMenu.value = null
  }
}

// 保持子菜单打开
const keepSubmenuOpen = () => {
  if (leaveTimer.value) {
    clearTimeout(leaveTimer.value)
    leaveTimer.value = null
  }
}

// 关闭子菜单
const closeSubmenu = () => {
  leaveTimer.value = setTimeout(() => {
    activeMenu.value = null
  }, 200)
}

// 导航到子菜单项
const navigateTo = (path) => {
  router.push(path)
  activeMenu.value = null
}

// 监听路由变化，关闭子菜单
watch(() => route.path, () => {
  activeMenu.value = null
})
</script>

<style scoped>
.hover-menu-container {
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  z-index: 1000;
}

.primary-menu {
  width: 80px;
  height: 100vh;
  background: #304156;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 10px;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
}

.logo {
  width: 100%;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 10px;
}

.logo-icon {
  font-size: 32px;
}

.menu-item {
  width: 100%;
  height: 70px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  cursor: pointer;
  color: #bfcbd9;
  transition: all 0.3s;
  position: relative;
}

.menu-item:hover {
  background: #263445;
  color: #409eff;
}

.menu-item.active {
  background: #409eff;
  color: white;
}

.menu-item.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 4px;
  height: 40px;
  background: white;
  border-radius: 0 2px 2px 0;
}

.menu-title {
  font-size: 12px;
  text-align: center;
  line-height: 1.2;
  max-width: 60px;
  word-break: keep-all;
}

.submenu-panel {
  position: fixed;
  left: 80px;
  max-height: 40vh;
  width: 280px;
  background: white;
  box-shadow: 2px 0 12px rgba(0, 0, 0, 0.15);
  overflow-y: auto;
  overflow-x: hidden;
  z-index: 999;
  border-radius: 8px;
}

.submenu-header {
  padding: 20px 24px;
  border-bottom: 1px solid #e4e7ed;
  background: #f5f7fa;
  position: sticky;
  top: 0;
  z-index: 1;
}

.submenu-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #303133;
}

.submenu-grid {
  padding: 16px;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  max-height: calc(40vh - 80px);
  overflow-y: auto;
}

.submenu-item {
  padding: 16px 12px;
  border-radius: 8px;
  background: #f5f7fa;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #606266;
}

.submenu-item:hover {
  background: #ecf5ff;
  color: #409eff;
  transform: translateY(-2px);
  box-shadow: 0 2px 8px rgba(64, 158, 255, 0.2);
}

.submenu-item.active {
  background: #409eff;
  color: white;
}

.submenu-item span {
  font-size: 14px;
  font-weight: 500;
}

/* 动画 */
.submenu-fade-enter-active,
.submenu-fade-leave-active {
  transition: all 0.3s ease;
}

.submenu-fade-enter-from {
  opacity: 0;
  transform: translateX(-20px);
}

.submenu-fade-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}

/* 滚动条样式 */
.submenu-panel::-webkit-scrollbar {
  width: 6px;
}

.submenu-panel::-webkit-scrollbar-thumb {
  background: #dcdfe6;
  border-radius: 3px;
}

.submenu-panel::-webkit-scrollbar-thumb:hover {
  background: #c0c4cc;
}
</style>
