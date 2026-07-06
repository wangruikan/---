import request from './request'

export function getPaymentPayees(params) {
  return request({
    url: '/payment-payees',
    method: 'get',
    params
  })
}

export function createPaymentPayee(data) {
  return request({
    url: '/payment-payees',
    method: 'post',
    data
  })
}

export function updatePaymentPayee(id, data) {
  return request({
    url: `/payment-payees/${id}`,
    method: 'put',
    data
  })
}

export function deletePaymentPayee(id) {
  return request({
    url: `/payment-payees/${id}`,
    method: 'delete'
  })
}
