/**
 * Excel 导出通用组合式函数
 * 
 * 使用示例：
 * const { exportToExcel, exporting } = useExcelExport()
 * 
 * exportToExcel({
 *   url: '/api/invoice-summaries/export',
 *   params: { month: '2025-12' },
 *   filename: '发票汇总.xlsx'
 * })
 */

import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/api/request'

export function useExcelExport() {
  const exporting = ref(false)

  /**
   * 导出 Excel
   * @param {Object} options 导出选项
   * @param {string} options.url 导出接口地址
   * @param {Object} options.params 请求参数
   * @param {string} options.filename 文件名（可选，如果不提供则使用响应头中的文件名）
   * @param {string} options.method 请求方法，默认 'get'
   */
  const exportToExcel = async (options) => {
    const {
      url,
      params = {},
      filename,
      method = 'get'
    } = options

    if (exporting.value) {
      ElMessage.warning('正在导出中，请稍候...')
      return
    }

    try {
      exporting.value = true
      ElMessage.info('正在导出，请稍候...')

      const response = await request({
        url,
        method,
        [method === 'get' ? 'params' : 'data']: params,
        responseType: 'blob', // 重要：设置响应类型为 blob
        timeout: 60000 // 60秒超时
      })

      // 创建 Blob 对象
      const blob = new Blob([response], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      })

      // 创建下载链接
      const downloadUrl = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      
      // 设置文件名
      if (filename) {
        link.download = filename
      } else {
        // 尝试从响应头获取文件名
        const contentDisposition = response.headers?.['content-disposition']
        if (contentDisposition) {
          const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
          if (filenameMatch && filenameMatch[1]) {
            link.download = decodeURIComponent(filenameMatch[1].replace(/['"]/g, ''))
          }
        }
        
        // 如果还是没有文件名，使用默认值
        if (!link.download) {
          link.download = `导出数据_${new Date().getTime()}.xlsx`
        }
      }

      // 触发下载
      document.body.appendChild(link)
      link.click()
      
      // 清理
      document.body.removeChild(link)
      window.URL.revokeObjectURL(downloadUrl)

      ElMessage.success('导出成功')
    } catch (error) {
      console.error('导出失败:', error)
      ElMessage.error(error.message || '导出失败，请重试')
    } finally {
      exporting.value = false
    }
  }

  return {
    exportToExcel,
    exporting
  }
}
