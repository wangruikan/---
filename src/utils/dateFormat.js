/**
 * 日期时间格式化工具函数
 */

/**
 * 格式化日期时间为本地时间
 * @param {string|Date} dateTime - 日期时间字符串或Date对象
 * @returns {string} 格式化后的日期时间字符串
 */
export function formatDateTime(dateTime) {
  if (!dateTime) return ''
  
  const date = new Date(dateTime)
  
  // 检查日期是否有效
  if (isNaN(date.getTime())) {
    return ''
  }
  
  // 转换为本地时间（中国时区 UTC+8）
  const localDate = new Date(date.getTime() + (8 * 60 * 60 * 1000))
  
  return localDate.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
    timeZone: 'Asia/Shanghai'
  })
}

/**
 * 格式化日期（不包含时间）
 * @param {string|Date} dateTime - 日期时间字符串或Date对象
 * @returns {string} 格式化后的日期字符串
 */
export function formatDate(dateTime) {
  if (!dateTime) return ''
  
  const date = new Date(dateTime)
  
  if (isNaN(date.getTime())) {
    return ''
  }
  
  return date.toLocaleDateString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
}

/**
 * 格式化时间（不包含日期）
 * @param {string|Date} dateTime - 日期时间字符串或Date对象
 * @returns {string} 格式化后的时间字符串
 */
export function formatTime(dateTime) {
  if (!dateTime) return ''
  
  const date = new Date(dateTime)
  
  if (isNaN(date.getTime())) {
    return ''
  }
  
  return date.toLocaleTimeString('zh-CN', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  })
}

/**
 * 相对时间格式化（如：刚刚、5分钟前、2小时前）
 * @param {string|Date} dateTime - 日期时间字符串或Date对象
 * @returns {string} 相对时间字符串
 */
export function formatRelativeTime(dateTime) {
  if (!dateTime) return ''
  
  const date = new Date(dateTime)
  const now = new Date()
  
  if (isNaN(date.getTime())) {
    return ''
  }
  
  const diffInSeconds = Math.floor((now - date) / 1000)
  
  if (diffInSeconds < 60) {
    return '刚刚'
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60)
    return `${minutes}分钟前`
  } else if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600)
    return `${hours}小时前`
  } else {
    const days = Math.floor(diffInSeconds / 86400)
    return `${days}天前`
  }
}
