import request from './request'

/**
 * 提交未开票原因
 */
export function submitInvoiceReason(data) {
  return request({
    url: '/invoice-reminders/submit-reason',
    method: 'post',
    data
  })
}
