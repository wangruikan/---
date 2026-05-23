export const MENU_LAYOUT_SETTING_KEY = 'menu_layout'

export const buildLegacySubmenuId = (parentId, path) => {
  if (!parentId || !path) return ''
  return `${parentId}-${path.replace(/\//g, '-')}`
}

export const buildMenuLibrary = (baseMenuConfig = []) => {
  const directMenus = []
  const defaultLayout = []
  const submenuMap = {}
  const legacyToKeyMap = {}

  baseMenuConfig.forEach((menu) => {
    const hasChildren = Array.isArray(menu.children) && menu.children.length > 0

    if (!hasChildren) {
      directMenus.push({ ...menu })
      return
    }

    const childKeys = []
    menu.children.forEach((child) => {
      const key = child.menuKey || child.path
      if (!key) return

      childKeys.push(key)
      submenuMap[key] = {
        ...child,
        menuKey: key,
        sourceParentId: menu.id,
        sourceParentTitle: menu.title,
        sourceParentRequireAdmin: Boolean(menu.requireAdmin),
        sourceParentRequireBusiness: Boolean(menu.requireBusiness)
      }
      legacyToKeyMap[buildLegacySubmenuId(menu.id, child.path)] = key
    })

    defaultLayout.push({
      id: menu.id,
      title: menu.title,
      icon: menu.icon,
      children: childKeys
    })
  })

  return {
    directMenus,
    defaultLayout,
    submenuMap,
    legacyToKeyMap
  }
}

export const normalizeVisibleMenuKeys = (rawKeys, menuLibrary) => {
  if (!Array.isArray(rawKeys)) return []
  const keys = new Set()
  const submenuMap = menuLibrary?.submenuMap || {}
  const legacyToKeyMap = menuLibrary?.legacyToKeyMap || {}
  const parentToChildrenMap = (menuLibrary?.defaultLayout || []).reduce((acc, group) => {
    if (!group?.id) return acc
    acc[group.id] = Array.isArray(group.children) ? group.children : []
    return acc
  }, {})

  rawKeys.forEach((item) => {
    if (typeof item !== 'string') return
    if (submenuMap[item]) {
      keys.add(item)
      return
    }
    if (legacyToKeyMap[item]) {
      keys.add(legacyToKeyMap[item])
      return
    }
    if (parentToChildrenMap[item]) {
      parentToChildrenMap[item].forEach((childKey) => {
        if (submenuMap[childKey]) {
          keys.add(childKey)
        }
      })
    }
  })

  return Array.from(keys)
}

export const normalizeMenuLayout = (rawLayout, menuLibrary) => {
  const defaultLayout = menuLibrary?.defaultLayout || []
  const submenuMap = menuLibrary?.submenuMap || {}
  const allSubmenuKeys = Object.keys(submenuMap)

  const sourceLayout = Array.isArray(rawLayout) && rawLayout.length > 0 ? rawLayout : defaultLayout
  const usedKeys = new Set()
  const normalized = []

  sourceLayout.forEach((menu, index) => {
    if (!menu || typeof menu !== 'object') return
    const id = typeof menu.id === 'string' && menu.id.trim() ? menu.id.trim() : `menu_${index + 1}`
    const title = typeof menu.title === 'string' && menu.title.trim() ? menu.title.trim() : `菜单${index + 1}`
    const icon = typeof menu.icon === 'string' && menu.icon.trim() ? menu.icon.trim() : 'Menu'

    const children = []
    const childList = Array.isArray(menu.children) ? menu.children : []
    childList.forEach((childKey) => {
      if (typeof childKey !== 'string') return
      if (!submenuMap[childKey]) return
      if (usedKeys.has(childKey)) return
      usedKeys.add(childKey)
      children.push(childKey)
    })

    normalized.push({
      id,
      title,
      icon,
      children
    })
  })

  const missedKeys = allSubmenuKeys.filter((key) => !usedKeys.has(key))
  if (missedKeys.length > 0) {
    if (normalized.length === 0) {
      normalized.push({
        id: 'settings',
        title: '系统设置',
        icon: 'Setting',
        children: [...missedKeys]
      })
    } else {
      normalized[normalized.length - 1].children.push(...missedKeys)
    }
  }

  return normalized
}

export const buildMenusWithLayout = (baseMenuConfig = [], rawLayout = []) => {
  const menuLibrary = buildMenuLibrary(baseMenuConfig)
  const layout = normalizeMenuLayout(rawLayout, menuLibrary)
  const { directMenus, submenuMap } = menuLibrary

  const groupedMenus = layout.map((menu) => ({
    id: menu.id,
    title: menu.title,
    icon: menu.icon,
    children: menu.children
      .map((key) => submenuMap[key])
      .filter(Boolean)
      .map((item) => ({ ...item }))
  }))

  return {
    menuLibrary,
    layout,
    menus: [...directMenus.map((item) => ({ ...item })), ...groupedMenus]
  }
}
