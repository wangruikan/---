/**
 * 表单草稿暂存 composable
 * 功能：自动保存表单数据到 localStorage，关闭重开后自动恢复，提交成功后自动清除
 *
 * 使用示例：
 * const draft = useFormDraft('project-create', form)
 * draft.restore()        // 恢复草稿到 form
 * draft.clear()          // 清除草稿
 * draft.save()           // 手动保存（通常不需要手动调用，watch 会自动保存）
 */
import { watch } from 'vue'

const STORAGE_PREFIX = 'form_draft:'
const EXPIRE_DAYS = 7 // 草稿7天后过期

function getStorageKey(key) {
  return STORAGE_PREFIX + key
}

function load(key) {
  try {
    const raw = localStorage.getItem(getStorageKey(key))
    if (!raw) return null
    const data = JSON.parse(raw)
    // 检查是否过期
    if (data._expire && Date.now() > data._expire) {
      localStorage.removeItem(getStorageKey(key))
      return null
    }
    return data._data ?? null
  } catch {
    return null
  }
}

function save(key, data) {
  try {
    const payload = {
      _data: data,
      _expire: Date.now() + EXPIRE_DAYS * 24 * 60 * 60 * 1000,
      _version: 1
    }
    localStorage.setItem(getStorageKey(key), JSON.stringify(payload))
  } catch {
    // 存储满了或其他异常，忽略
  }
}

function clear(key) {
  try {
    localStorage.removeItem(getStorageKey(key))
  } catch {}
}

/**
 * @param {string} key - 草稿唯一标识
 * @param {import('vue').Ref | import('vue').ReactiveObject} formRef - 表单 reactive/ref 对象
 * @param {Object} options - 配置项
 * @param {boolean} options.autoSave - 是否自动 watch 保存（默认 true）
 * @param {Function} options.filter - 可选，过滤函数，pick 要保存的字段
 */
export function useFormDraft(key, formRef, options = {}) {
  const { autoSave = true, filter } = options

  // 自动保存：每次 formRef 变化时自动保存草稿
  if (autoSave) {
    watch(
      () => formRef,
      (newVal) => {
        if (!newVal) return
        const data = filter ? filter(Object.assign({}, newVal)) : Object.assign({}, newVal)
        save(key, data)
      },
      { deep: true }
    )
  }

  return {
    /** 从 localStorage 恢复草稿数据并合并到 formRef */
    restore() {
      const data = load(key)
      if (!data) return false
      Object.assign(formRef, data)
      return true
    },

    /** 清除本地草稿 */
    clear() {
      clear(key)
    },

    /** 手动保存一次 */
    save() {
      const data = filter ? filter(Object.assign({}, formRef)) : Object.assign({}, formRef)
      save(key, data)
    },

    /** 检查是否有草稿 */
    hasDraft() {
      return load(key) !== null
    }
  }
}
