<template>
  <span>{{ formattedDateTime }}</span>
</template>

<script setup>
import { computed } from 'vue'
import { formatDateTime, formatDate, formatTime, formatRelativeTime } from '@/utils/dateFormat'

const props = defineProps({
  value: {
    type: [String, Date],
    default: null
  },
  format: {
    type: String,
    default: 'datetime', // 'datetime', 'date', 'time', 'relative'
    validator: (value) => ['datetime', 'date', 'time', 'relative'].includes(value)
  }
})

const formattedDateTime = computed(() => {
  if (!props.value) return ''
  
  switch (props.format) {
    case 'date':
      return formatDate(props.value)
    case 'time':
      return formatTime(props.value)
    case 'relative':
      return formatRelativeTime(props.value)
    case 'datetime':
    default:
      return formatDateTime(props.value)
  }
})
</script>
