/**
 * 表单草稿暂存 composable
 *
 * 使用方式（每个表单只需 3 行）：
 *   const draft = useFormDraft('project-create-v1', form)
 *   // 关闭弹窗时：draft.save()
 *   // 提交成功时：draft.clear()
 *   // 打开弹窗时：draft.restore()
 */

const STORAGE_PREFIX = 'form_draft:'
const EXPIRE_DAYS = 7

function storageKey(key) {
  return STORAGE_PREFIX + key
}

function resolveKey(key) {
  return typeof key === 'function' ? key() : key
}

function loadRaw(key) {
  try {
    const raw = localStorage.getItem(storageKey(key))
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (parsed._expire && Date.now() > parsed._expire) {
      localStorage.removeItem(storageKey(key))
      return null
    }
    return parsed._data ?? null
  } catch {
    return null
  }
}

function saveRaw(key, data) {
  try {
    localStorage.setItem(storageKey(key), JSON.stringify({
      _data: data,
      _expire: Date.now() + EXPIRE_DAYS * 24 * 60 * 60 * 1000
    }))
  } catch {}
}

function clearRaw(key) {
  try {
    localStorage.removeItem(storageKey(key))
  } catch {}
}

/**
 * @param {string} key        草稿唯一 key（同一表单用同一个 key）
 * @param {object} form       Vue reactive 表单对象
 * @param {Function} [isEmpty] 判断表单是否为空，为空时不保存草稿
 *                             默认：所有字段都是空字符串/null/空数组则视为空
 */
export function useFormDraft(key, form, isEmpty) {
  const defaultIsEmpty = (f) => {
    return Object.values(f).every(v => {
      if (Array.isArray(v)) return v.length === 0
      return v === '' || v === null || v === undefined || v === false || v === 0
    })
  }

  const checkEmpty = isEmpty ?? defaultIsEmpty

  return {
    /** 手动保存草稿（在弹窗关闭时调用） */
    save() {
      const currentKey = resolveKey(key)
      const snapshot = JSON.parse(JSON.stringify(form))
      if (checkEmpty(snapshot)) return  // 表单为空不保存
      saveRaw(currentKey, snapshot)
    },

    /** 恢复草稿到 form（在弹窗打开时调用） */
    restore() {
      const data = loadRaw(resolveKey(key))
      if (!data) return false
      Object.assign(form, data)
      return true
    },

    /** 是否存在有效草稿 */
    hasDraft() {
      return loadRaw(resolveKey(key)) !== null
    },

    /** 清除草稿（提交成功后调用） */
    clear() {
      clearRaw(resolveKey(key))
    }
  }
}
