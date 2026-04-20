/**
 * 日期时间格式化指令
 * 使用方法：v-date-time="dateString" 或 v-date-time:format="dateString"
 */

import { formatDateTime, formatDate, formatTime, formatRelativeTime } from '@/utils/dateFormat'

export default {
  mounted(el, binding) {
    updateDateTime(el, binding)
  },
  updated(el, binding) {
    updateDateTime(el, binding)
  }
}

function updateDateTime(el, binding) {
  const { value, arg } = binding
  
  if (!value) {
    el.textContent = ''
    return
  }
  
  let formattedTime = ''
  
  switch (arg) {
    case 'date':
      formattedTime = formatDate(value)
      break
    case 'time':
      formattedTime = formatTime(value)
      break
    case 'relative':
      formattedTime = formatRelativeTime(value)
      break
    case 'datetime':
    default:
      formattedTime = formatDateTime(value)
      break
  }
  
  el.textContent = formattedTime
}
