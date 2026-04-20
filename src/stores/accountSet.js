import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import request from '@/api/request'

export const useAccountSetStore = defineStore('accountSet', () => {
  // 当前选中的账套ID（从 localStorage 读取时转换为数字）
  const storedId = localStorage.getItem('current_account_set_id')
  const currentAccountSetId = ref(storedId ? parseInt(storedId) : null)
  
  // 当前账套信息
  const currentAccountSet = ref(null)
  
  // 用户可访问的账套列表
  const myAccountSets = ref([])

  /**
   * 获取用户可访问的账套列表
   */
  const loadMyAccountSets = async () => {
    try {
      const response = await request({
        url: '/account-sets/my',
        method: 'get'
      })
      
      if (response.success) {
        myAccountSets.value = response.data || []
        
        console.log('加载账套列表:', myAccountSets.value)
        
        // 从数据库获取用户当前选择的账套
        try {
          const currentResponse = await request({
            url: '/users/current-account-set',
            method: 'get'
          })
          
          if (currentResponse.success && currentResponse.data.current_account_set_id) {
            const savedAccountSetId = currentResponse.data.current_account_set_id
            console.log('从数据库读取的账套ID:', savedAccountSetId)
            
            // 检查这个账套是否在用户可访问的列表中
            const accountSet = myAccountSets.value.find(s => s.id === savedAccountSetId)
            if (accountSet) {
              currentAccountSetId.value = savedAccountSetId
              currentAccountSet.value = accountSet
              localStorage.setItem('current_account_set_id', savedAccountSetId)
              console.log('✅ 使用数据库中保存的账套:', accountSet.name)
              return
            }
          }
        } catch (error) {
          console.log('读取数据库账套失败，使用默认逻辑:', error.message)
        }
        
        // 如果数据库中没有或读取失败，使用默认逻辑
        if (myAccountSets.value.length > 0) {
          // 先尝试使用 localStorage
          const storedId = localStorage.getItem('current_account_set_id')
          if (storedId) {
            const parsedId = parseInt(storedId)
            const currentSet = myAccountSets.value.find(s => s.id === parsedId)
            if (currentSet) {
              currentAccountSetId.value = parsedId
              currentAccountSet.value = currentSet
              console.log('✅ 使用 localStorage 中的账套:', currentSet.name)
              // 同步到数据库
              saveCurrentAccountSetToDb(parsedId)
              return
            }
          }
          
          // 如果都没有，选择默认账套或第一个
          console.log('选择默认账套')
          const defaultSet = myAccountSets.value.find(s => s.is_default)
          const selectedSet = defaultSet || myAccountSets.value[0]
          setCurrentAccountSet(selectedSet.id)
        }
      }
    } catch (error) {
      console.error('加载账套列表失败:', error)
    }
  }

  /**
   * 保存当前账套到数据库
   */
  const saveCurrentAccountSetToDb = async (accountSetId) => {
    try {
      await request({
        url: '/users/current-account-set',
        method: 'put',
        data: { account_set_id: accountSetId }
      })
      console.log('✅ 账套选择已保存到数据库')
    } catch (error) {
      console.error('保存账套到数据库失败:', error)
    }
  }

  /**
   * 设置当前账套
   */
  const setCurrentAccountSet = (accountSetId) => {
    console.log('设置当前账套:', accountSetId)
    currentAccountSetId.value = accountSetId
    localStorage.setItem('current_account_set_id', accountSetId)
    
    // 更新当前账套信息
    const accountSet = myAccountSets.value.find(s => s.id === accountSetId)
    if (accountSet) {
      currentAccountSet.value = accountSet
      console.log('✅ 账套已切换:', accountSet.name)
      
      // 保存到数据库
      saveCurrentAccountSetToDb(accountSetId)
    }
  }

  /**
   * 清除当前账套
   */
  const clearCurrentAccountSet = () => {
    currentAccountSetId.value = null
    currentAccountSet.value = null
    myAccountSets.value = []
    localStorage.removeItem('current_account_set_id')
  }

  /**
   * 获取当前账套ID（用于API请求）
   */
  const getCurrentAccountSetId = computed(() => {
    return currentAccountSetId.value
  })

  return {
    currentAccountSetId,
    currentAccountSet,
    myAccountSets,
    loadMyAccountSets,
    setCurrentAccountSet,
    clearCurrentAccountSet,
    getCurrentAccountSetId
  }
})

