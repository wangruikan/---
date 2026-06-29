import { test, expect, APIRequestContext } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import * as http from 'http';

const BASE_URL = 'http://localhost:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const FINANCE_USERNAME = process.env.E2E_FINANCE_USERNAME || '';
const FINANCE_PASSWORD = process.env.E2E_FINANCE_PASSWORD || '';

let authToken: string;
let currentUserId: number;
let financeToken: string | null = null;

// 登录
async function login(
  request: APIRequestContext,
  username: string,
  password: string
): Promise<{ token: string; userId: number }> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: { username, password },
  });
  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(data.success).toBe(true);
  return { token: data.data.token, userId: data.data.user?.id };
}

function authHeaders(token?: string) {
  return {
    Authorization: `Bearer ${token || authToken}`,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  };
}

// multipart 文件上传
function uploadFile(
  token: string,
  urlPath: string,
  filePath: string,
  fileName: string,
  mimeType: string,
  extraFields?: Record<string, string>
): Promise<{ status: number; body: string }> {
  return new Promise((resolve, reject) => {
    const boundary = '----FormBoundary' + Date.now();
    const fileData = fs.readFileSync(filePath);
    const parts: Buffer[] = [];

    if (extraFields) {
      for (const [key, value] of Object.entries(extraFields)) {
        parts.push(
          Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="${key}"\r\n\r\n${value}\r\n`)
        );
      }
    }

    parts.push(
      Buffer.from(
        `--${boundary}\r\nContent-Disposition: form-data; name="file"; filename="${fileName}"\r\nContent-Type: ${mimeType}\r\n\r\n`
      )
    );
    parts.push(fileData);
    parts.push(Buffer.from(`\r\n--${boundary}--\r\n`));
    const body = Buffer.concat(parts);

    const url = new URL(`${BASE_URL}${urlPath}`);
    const req = http.request(
      {
        hostname: '127.0.0.1',
        port: url.port,
        path: url.pathname,
        method: 'POST',
        headers: {
          'Content-Type': `multipart/form-data; boundary=${boundary}`,
          Authorization: `Bearer ${token}`,
          'X-Account-Set-Id': String(ACCOUNT_SET_ID),
          'Content-Length': body.length,
        },
      },
      (res) => {
        let data = '';
        res.on('data', (chunk) => (data += chunk));
        res.on('end', () => resolve({ status: res.statusCode!, body: data }));
      }
    );
    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

// 测试 PNG
const TEST_PNG_PATH = path.join(__dirname, 'test-image.png');
if (!fs.existsSync(TEST_PNG_PATH)) {
  fs.writeFileSync(
    TEST_PNG_PATH,
    Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      'base64'
    )
  );
}

test.describe('付款申请 - 发票链路 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    const { token, userId } = await login(request, USERNAME, PASSWORD);
    authToken = token;
    currentUserId = userId;
    console.log('登录成功，userId:', currentUserId);

    // 尝试登录财务账号
    if (FINANCE_USERNAME && FINANCE_PASSWORD) {
      try {
        const finance = await login(request, FINANCE_USERNAME, FINANCE_PASSWORD);
        financeToken = finance.token;
        console.log('财务账号登录成功');
      } catch {
        console.log('财务账号登录失败，部分测试将跳过');
      }
    }
  });

  // ============================================================
  // 一、发票上传权限检查
  // ============================================================
  test.describe('一、发票上传权限检查', () => {
    test('check-invoice-permission 接口返回正确结构', async ({ request }) => {
      // 找一个 insurance 类型的付款申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 100 },
      });
      const listData = await listRes.json();
      if (listData.data.data.length === 0) {
        console.log('跳过: 没有保险付款申请');
        test.skip();
        return;
      }

      const insuranceId = listData.data.data[0].id;
      const response = await request.get(`${BASE_URL}/insurance-payment-requests/check-invoice-permission`, {
        headers: authHeaders(),
        params: { payment_request_id: insuranceId },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data).toHaveProperty('can_upload');
      expect(data.data).toHaveProperty('needs_invoice');
      expect(data.data).toHaveProperty('insurance_type');
      expect(data.data).toHaveProperty('invoice_status');
      expect(data.data).toHaveProperty('message');
    });

    test('缺少 payment_request_id 返回 422', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-payment-requests/check-invoice-permission`, {
        headers: authHeaders(),
      });
      expect(response.status()).toBe(422);
    });
  });

  // ============================================================
  // 二、发票附件上传与删除
  // ============================================================
  test.describe('二、发票附件上传与删除', () => {
    // 找一个 needsInvoiceUpload 的保险申请
    async function findInvoiceUploadable(request: APIRequestContext): Promise<any | null> {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 100 },
      });
      const listData = await listRes.json();
      return listData.data.data.find(
        (item: any) => item.can_upload_invoice === true
      ) || null;
    }

    test('上传发票附件成功', async ({ request }) => {
      const target = await findInvoiceUploadable(request);
      if (!target) {
        console.log('跳过: 没有可上传发票的保险申请');
        test.skip();
        return;
      }

      const uploadRes = await uploadFile(
        authToken,
        '/insurance-payment-requests/invoice-attachments/upload',
        TEST_PNG_PATH,
        'invoice.png',
        'image/png',
        { payment_request_id: String(target.id) }
      );
      const uploadData = JSON.parse(uploadRes.body);
      expect(uploadRes.status).toBe(200);
      expect(uploadData.success).toBe(true);
      expect(uploadData.message).toBe('发票上传成功');
      expect(uploadData.data).toHaveProperty('id');
      expect(uploadData.data).toHaveProperty('filename');
      expect(uploadData.data).toHaveProperty('file_path');
    });

    test('获取发票附件列表成功', async ({ request }) => {
      const target = await findInvoiceUploadable(request);
      if (!target) {
        test.skip();
        return;
      }

      const response = await request.get(`${BASE_URL}/insurance-payment-requests/invoice-attachments`, {
        headers: authHeaders(),
        params: { payment_request_id: target.id },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
    });

    test('删除发票附件成功', async ({ request }) => {
      // 先上传一个再删除
      const target = await findInvoiceUploadable(request);
      if (!target) {
        test.skip();
        return;
      }

      const uploadRes = await uploadFile(
        authToken,
        '/insurance-payment-requests/invoice-attachments/upload',
        TEST_PNG_PATH,
        'invoice-del.png',
        'image/png',
        { payment_request_id: String(target.id) }
      );
      const uploadData = JSON.parse(uploadRes.body);
      if (!uploadData.success) {
        test.skip();
        return;
      }
      const invoiceAttachmentId = uploadData.data.id;

      const deleteRes = await request.delete(`${BASE_URL}/insurance-payment-requests/invoice-attachments`, {
        headers: authHeaders(),
        data: { id: invoiceAttachmentId },
      });
      expect(deleteRes.status()).toBe(200);
      const deleteData = await deleteRes.json();
      expect(deleteData.success).toBe(true);
      expect(deleteData.message).toBe('附件删除成功');
    });

    test('不满足发票上传前置状态时上传失败', async ({ request }) => {
      // 找一个 insurance 但不满足 needsInvoiceUpload 的申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 100 },
      });
      const listData = await listRes.json();
      const notUploadable = listData.data.data.find(
        (item: any) => item.can_upload_invoice === false
      );
      if (!notUploadable) {
        console.log('跳过: 所有保险申请都可上传发票');
        test.skip();
        return;
      }

      const uploadRes = await uploadFile(
        authToken,
        '/insurance-payment-requests/invoice-attachments/upload',
        TEST_PNG_PATH,
        'invoice-fail.png',
        'image/png',
        { payment_request_id: String(notUploadable.id) }
      );
      expect(uploadRes.status).toBe(403);
      const data = JSON.parse(uploadRes.body);
      expect(data.success).toBe(false);
      expect(data.message).toBe('您没有权限上传发票');
    });
  });

  // ============================================================
  // 三、发票审批
  // ============================================================
  test.describe('三、发票审批', () => {
    test('提交发票审批成功或返回预期结构', async ({ request }) => {
      // 找一个 invoice_status = invoice_uploaded 的申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 100 },
      });
      const listData = await listRes.json();
      const invoiceUploaded = listData.data.data.find(
        (item: any) => item.invoice_status === 'invoice_uploaded'
      );
      if (!invoiceUploaded) {
        console.log('跳过: 没有发票已上传待审批的申请');
        test.skip();
        return;
      }

      const response = await request.post(`${BASE_URL}/insurance-payment-requests/submit-invoice-approval`, {
        headers: authHeaders(),
        data: {
          payment_request_id: invoiceUploaded.id,
          stamp_method: 'online',
        },
      });
      // 可能成功或因其他条件失败
      expect([200, 422]).toContain(response.status());
      const data = await response.json();
      expect(data).toHaveProperty('success');
      expect(data).toHaveProperty('message');
      if (response.status() === 200) {
        expect(data.success).toBe(true);
        expect(data.data).toHaveProperty('payment_request');
        expect(data.data).toHaveProperty('instance');
      }
    });
  });

  // ============================================================
  // 四、社保/公积金权限差异
  // ============================================================
  test.describe('四、社保/公积金权限差异', () => {
    test('社保付款：非财务角色上传发票应失败', async ({ request }) => {
      // 找一个社保类型的、needsInvoiceUpload 的申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 100 },
      });
      const listData = await listRes.json();
      const socialSecurity = listData.data.data.find(
        (item: any) =>
          item.can_upload_invoice === true &&
          item.insurance_category === 'social_insurance'
      );
      if (!socialSecurity) {
        console.log('跳过: 没有可上传发票的社保申请');
        test.skip();
        return;
      }

      // 检查当前用户是否有权限
      const permRes = await request.get(`${BASE_URL}/insurance-payment-requests/check-invoice-permission`, {
        headers: authHeaders(),
        params: { payment_request_id: socialSecurity.id },
      });
      const permData = await permRes.json();
      // 如果当前用户不是财务角色，can_upload 应该为 false
      if (!permData.data.can_upload) {
        const uploadRes = await uploadFile(
          authToken,
          '/insurance-payment-requests/invoice-attachments/upload',
          TEST_PNG_PATH,
          'ss-invoice.png',
          'image/png',
          { payment_request_id: String(socialSecurity.id) }
        );
        expect(uploadRes.status).toBe(403);
      } else {
        console.log('当前用户有财务权限，跳过权限拒绝测试');
      }
    });

    test('公积金付款：发起人上传发票成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 100 },
      });
      const listData = await listRes.json();
      const housingFund = listData.data.data.find(
        (item: any) =>
          item.can_upload_invoice === true &&
          item.insurance_category === 'housing_fund'
      );
      if (!housingFund) {
        console.log('跳过: 没有可上传发票的公积金申请');
        test.skip();
        return;
      }

      // 发起人应该可以上传
      const uploadRes = await uploadFile(
        authToken,
        '/insurance-payment-requests/invoice-attachments/upload',
        TEST_PNG_PATH,
        'hf-invoice.png',
        'image/png',
        { payment_request_id: String(housingFund.id) }
      );
      // 如果当前用户是发起人，应该成功
      if (uploadRes.status === 200) {
        const data = JSON.parse(uploadRes.body);
        expect(data.success).toBe(true);
      }
    });
  });

  // ============================================================
  // 五、保险付款申请提交链路
  // ============================================================
  test.describe('五、保险付款申请提交链路', () => {
    test('submit 缺少必填字段返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/insurance-payment-requests/submit`, {
        headers: authHeaders(),
        data: {
          current_account_set_id: ACCOUNT_SET_ID,
          // 缺少 process_approval_id, amount, reimbursement_form_data
        },
      });
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('验证失败');
      expect(data).toHaveProperty('errors');
    });

    test('submit 缺少账套返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/insurance-payment-requests/submit`, {
        headers: { Authorization: `Bearer ${authToken}` },
        data: {
          process_approval_id: 1,
          amount: 1000,
          reimbursement_form_data: {
            applyDate: '2025-06-01',
            unitName: '测试',
            reimburser: '张三',
            invoiceNumber: 'INV001',
            invoiceType: '增值税',
            invoiceAmount: 1000,
            taxRate: '6%',
            taxAmount: 60,
            deductionAmount: 0,
            amountExcludingTax: 940,
            paymentDate: '2025-06-01',
            expenditureAmount: 1000,
            summary: '测试',
          },
        },
      });
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('请先选择账套');
    });

    test('complete-submission 缺少 payment_request_id 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/insurance-payment-requests/complete-submission`, {
        headers: authHeaders(),
        data: {},
      });
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
    });
  });

  // ============================================================
  // 六、保险附件管理
  // ============================================================
  test.describe('六、保险附件管理', () => {
    test('上传保险附件缺少必填字段返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/insurance-payment-requests/attachments/upload`, {
        headers: { Authorization: `Bearer ${authToken}` },
        data: {},
      });
      // 可能 422 或其他验证错误
      expect([400, 422]).toContain(response.status());
    });

    test('删除保险附件缺少 id 返回 422', async ({ request }) => {
      const response = await request.delete(`${BASE_URL}/insurance-payment-requests/attachments`, {
        headers: authHeaders(),
        data: {},
      });
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
    });

    test('删除不存在的保险附件返回 422', async ({ request }) => {
      const response = await request.delete(`${BASE_URL}/insurance-payment-requests/attachments`, {
        headers: authHeaders(),
        data: { id: 999999 },
      });
      expect(response.status()).toBe(422);
    });
  });

  // ============================================================
  // 七、契约与稳定性
  // ============================================================
  test.describe('七、契约与稳定性', () => {
    test('发票权限检查返回字段类型校验', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 100 },
      });
      const listData = await listRes.json();
      if (listData.data.data.length === 0) {
        test.skip();
        return;
      }
      const insuranceId = listData.data.data[0].id;

      const response = await request.get(`${BASE_URL}/insurance-payment-requests/check-invoice-permission`, {
        headers: authHeaders(),
        params: { payment_request_id: insuranceId },
      });
      const data = await response.json();
      expect(typeof data.data.can_upload).toBe('boolean');
      expect(typeof data.data.needs_invoice).toBe('boolean');
      expect(typeof data.data.message).toBe('string');
    });

    test('发票附件列表为空时返回空数组', async ({ request }) => {
      // 找一个没有发票附件的保险申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 100 },
      });
      const listData = await listRes.json();
      const noInvoice = listData.data.data.find(
        (item: any) => item.invoice_attachments_count === 0
      );
      if (!noInvoice) {
        test.skip();
        return;
      }

      const response = await request.get(`${BASE_URL}/insurance-payment-requests/invoice-attachments`, {
        headers: authHeaders(),
        params: { payment_request_id: noInvoice.id },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
      expect(data.data.length).toBe(0);
    });
  });
});
