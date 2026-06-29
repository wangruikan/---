import { test, expect, APIRequestContext } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import * as http from 'http';

const BASE_URL = 'http://localhost:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);

let authToken: string;
let currentUserId: number;

// 登录
async function login(request: APIRequestContext): Promise<{ token: string; userId: number }> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: { username: USERNAME, password: PASSWORD },
  });
  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(data.success).toBe(true);
  return { token: data.data.token, userId: data.data.user?.id };
}

// 带 auth + 账套的请求头
function authHeaders() {
  return {
    Authorization: `Bearer ${authToken}`,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  };
}

// multipart 文件上传（绕过 extraHTTPHeaders 的 Content-Type 冲突）
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

    // 额外字段
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

// 生成测试 PNG
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

test.describe('付款申请 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    const { token, userId } = await login(request);
    authToken = token;
    currentUserId = userId;
    console.log('登录成功，userId:', currentUserId);
  });

  // ============================================================
  // 一、列表与详情
  // ============================================================
  test.describe('一、列表与详情', () => {
    test('未登录访问列表应返回 401', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        params: { current_account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(401);
    });

    test('缺少账套参数获取列表应返回 400', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        headers: { Authorization: `Bearer ${authToken}` },
      });
      expect(response.status()).toBe(400);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('请先选择账套');
    });

    test('正常获取付款申请列表成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data).toHaveProperty('data');
      expect(Array.isArray(data.data.data)).toBe(true);
      expect(typeof data.data.current_page).toBe('number');
      expect(typeof data.data.per_page).toBe('number');
      expect(typeof data.data.total).toBe('number');
      expect(typeof data.data.last_page).toBe('number');
    });

    test('payment_type=salary 筛选成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'salary', per_page: 5 },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      for (const item of data.data.data) {
        expect(item.payment_type).toBe('salary');
      }
    });

    test('payment_type=insurance 筛选成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 5 },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      for (const item of data.data.data) {
        expect(item.payment_type).toBe('insurance');
      }
    });

    test('payment_type=reimbursement 能覆盖报销类归并筛选', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'reimbursement', per_page: 20 },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      const reimbursementTypes = ['reimbursement', '报销', '差旅', '采购', '项目', '其他'];
      for (const item of data.data.data) {
        expect(reimbursementTypes).toContain(item.payment_type);
      }
    });

    test('month=YYYY-MM 筛选成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, month: '2025-06', per_page: 5 },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
    });

    test('status 筛选成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 5 },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      for (const item of data.data.data) {
        expect(item.status).toBe('pending');
      }
    });

    test('分页参数生效', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 2, page: 1 },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.data.per_page).toBe(2);
      expect(data.data.current_page).toBe(1);
      expect(data.data.data.length).toBeLessThanOrEqual(2);
    });

    test('列表返回字段完整性校验', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      if (data.data.data.length > 0) {
        const item = data.data.data[0];
        expect(item).toHaveProperty('id');
        expect(item).toHaveProperty('payment_type');
        expect(item).toHaveProperty('status');
        expect(item).toHaveProperty('amount');
        expect(item).toHaveProperty('type_name');
        expect(item).toHaveProperty('attachments_count');
        expect(item).toHaveProperty('upload_later');
      }
    });

    test('获取详情成功', async ({ request }) => {
      // 先获取列表找一个 ID
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.data.length === 0) {
        console.log('跳过: 没有付款申请数据');
        test.skip();
        return;
      }
      const id = listData.data.data[0].id;

      const response = await request.get(`${BASE_URL}/payment-applications/${id}`, {
        headers: authHeaders(),
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data).toHaveProperty('id');
      expect(data.data).toHaveProperty('payment_type');
      expect(data.data).toHaveProperty('status');
      expect(data.data).toHaveProperty('amount');
      expect(data.data).toHaveProperty('type_name');
      expect(data.data).toHaveProperty('attachments');
      expect(Array.isArray(data.data.attachments)).toBe(true);
      expect(data.data).toHaveProperty('initiator');
      expect(data.data).toHaveProperty('approval_instance');
      expect(data.data.id).toBe(id);
    });

    test('获取不存在的付款申请详情返回 404', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications/999999`, {
        headers: authHeaders(),
      });
      expect(response.status()).toBe(404);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('付款申请不存在');
    });
  });

  // ============================================================
  // 二、常规附件
  // ============================================================
  test.describe('二、常规附件', () => {
    test('上传附件 -> 详情可见 -> 删除成功', async ({ request }) => {
      // 找一个可用的付款申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      if (listData.data.data.length === 0) {
        console.log('跳过: 没有付款申请数据');
        test.skip();
        return;
      }
      const targetId = listData.data.data[0].id;

      // 上传附件
      const uploadRes = await uploadFile(
        authToken,
        `/payment-applications/${targetId}/upload-attachment`,
        TEST_PNG_PATH,
        'test-attachment.png',
        'image/png'
      );
      const uploadData = JSON.parse(uploadRes.body);
      expect(uploadRes.status).toBe(200);
      expect(uploadData.success).toBe(true);
      expect(uploadData.message).toBe('附件上传成功');
      expect(uploadData.data).toHaveProperty('id');
      expect(uploadData.data).toHaveProperty('filename');
      expect(uploadData.data).toHaveProperty('path');
      const attachmentId = uploadData.data.id;

      // 详情中能看到附件
      const detailRes = await request.get(`${BASE_URL}/payment-applications/${targetId}`, {
        headers: authHeaders(),
      });
      const detailData = await detailRes.json();
      const found = detailData.data.attachments.find((a: any) => a.id === attachmentId);
      expect(found).toBeTruthy();

      // 删除附件
      const deleteRes = await request.delete(
        `${BASE_URL}/payment-applications/${targetId}/attachments/${attachmentId}`,
        { headers: authHeaders() }
      );
      expect(deleteRes.status()).toBe(200);
      const deleteData = await deleteRes.json();
      expect(deleteData.success).toBe(true);
      expect(deleteData.message).toBe('附件删除成功');
    });

    test('删除不存在附件返回合理错误', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.data.length === 0) {
        test.skip();
        return;
      }
      const targetId = listData.data.data[0].id;

      const response = await request.delete(
        `${BASE_URL}/payment-applications/${targetId}/attachments/999999`,
        { headers: authHeaders() }
      );
      expect(response.status()).toBe(404);
      const data = await response.json();
      expect(data.success).toBe(false);
    });

    test('附件下载接口返回文件流成功', async ({ request }) => {
      // 先上传一个附件
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      if (listData.data.data.length === 0) {
        test.skip();
        return;
      }
      const targetId = listData.data.data[0].id;

      const uploadRes = await uploadFile(
        authToken,
        `/payment-applications/${targetId}/upload-attachment`,
        TEST_PNG_PATH,
        'download-test.png',
        'image/png'
      );
      const uploadData = JSON.parse(uploadRes.body);
      if (!uploadData.success) {
        test.skip();
        return;
      }
      const attachmentId = uploadData.data.id;

      // 下载（使用 raw HTTP，Playwright 的 request.get 处理文件流会报错）
      const downloadResult = await new Promise<{ status: number; contentType: string }>((resolve, reject) => {
        const req = http.request(
          {
            hostname: '127.0.0.1',
            port: 8000,
            path: `/api/payment-request-attachments/${attachmentId}/download`,
            method: 'GET',
            headers: {
              Authorization: `Bearer ${authToken}`,
              'X-Account-Set-Id': String(ACCOUNT_SET_ID),
            },
          },
          (res) => {
            res.resume(); // 消费数据流
            resolve({ status: res.statusCode!, contentType: res.headers['content-type'] || '' });
          }
        );
        req.on('error', reject);
        req.end();
      });
      expect(downloadResult.status).toBe(200);
      expect(
        downloadResult.contentType.includes('application/octet-stream') ||
        downloadResult.contentType.includes('image/') ||
        downloadResult.contentType.includes('application/pdf')
      ).toBe(true);

      // 清理
      await request.delete(`${BASE_URL}/payment-applications/${targetId}/attachments/${attachmentId}`, {
        headers: authHeaders(),
      });
    });
  });

  // ============================================================
  // 三、提交审批
  // ============================================================
  test.describe('三、提交审批', () => {
    test('没有附件时提交失败', async ({ request }) => {
      // 找一个没有附件的 draft/pending 申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const noAttachmentItem = listData.data.data.find(
        (item: any) =>
          (item.status === 'draft' || item.status === 'pending') &&
          !item.approval_instance &&
          item.attachments_count === 0
      );
      if (!noAttachmentItem) {
        console.log('跳过: 没有无附件的草稿申请');
        test.skip();
        return;
      }

      const response = await request.post(`${BASE_URL}/payment-applications/${noAttachmentItem.id}/submit`, {
        headers: authHeaders(),
        data: {
          stamp_selection_mode: 'none',
          stamp_method: 'online',
          payment_method: 'transfer',
          current_account_set_id: ACCOUNT_SET_ID,
        },
      });
      expect(response.status()).toBe(400);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('请至少上传一个附件后再提交');
    });

    test('重复提交同一申请失败', async ({ request }) => {
      // 找一个已有审批实例的申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const hasApprovalItem = listData.data.data.find(
        (item: any) => item.approval_instance
      );
      if (!hasApprovalItem) {
        console.log('跳过: 没有已提交审批的申请');
        test.skip();
        return;
      }

      const response = await request.post(`${BASE_URL}/payment-applications/${hasApprovalItem.id}/submit`, {
        headers: authHeaders(),
        data: {
          stamp_selection_mode: 'none',
          stamp_method: 'online',
          current_account_set_id: ACCOUNT_SET_ID,
        },
      });
      // 已有审批实例 → 422
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('该申请已经创建了审批流程');
    });

    test('非允许状态提交失败', async ({ request }) => {
      // 找一个 approved/rejected 状态的申请（通常已有审批实例）
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const nonSubmittable = listData.data.data.find(
        (item: any) => !['draft', 'pending'].includes(item.status)
      );
      if (!nonSubmittable) {
        console.log('跳过: 没有非可提交状态的申请');
        test.skip();
        return;
      }

      const response = await request.post(`${BASE_URL}/payment-applications/${nonSubmittable.id}/submit`, {
        headers: authHeaders(),
        data: {
          stamp_selection_mode: 'none',
          stamp_method: 'online',
          current_account_set_id: ACCOUNT_SET_ID,
        },
      });
      // 已有审批实例的返回 422，无审批实例但状态不对的返回 400
      expect([400, 422]).toContain(response.status());
      const data = await response.json();
      expect(data.success).toBe(false);
    });

    test('stamp_selection_mode=stamp 但传无效印章时失败', async ({ request }) => {
      // 找一个有附件的 draft/pending 申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const withAttachment = listData.data.data.find(
        (item: any) =>
          (item.status === 'draft' || item.status === 'pending') &&
          !item.approval_instance &&
          item.attachments_count > 0
      );
      if (!withAttachment) {
        console.log('跳过: 没有带附件的可提交申请');
        test.skip();
        return;
      }

      const response = await request.post(`${BASE_URL}/payment-applications/${withAttachment.id}/submit`, {
        headers: authHeaders(),
        data: {
          stamp_selection_mode: 'stamp',
          stamp_method: 'online',
          stamp_id: 999999,
          stamp_company: '不存在的公司',
          stamp_type: 'official',
          current_account_set_id: ACCOUNT_SET_ID,
        },
      });
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('所选公司印章不存在，请重新选择');
    });

    test('stamp_selection_mode=none 时可正常提交', async ({ request }) => {
      // 找一个有附件的 draft/pending 且无审批实例的申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const submittable = listData.data.data.find(
        (item: any) =>
          (item.status === 'draft' || item.status === 'pending') &&
          !item.approval_instance &&
          item.attachments_count > 0
      );
      if (!submittable) {
        console.log('跳过: 没有带附件的可提交申请');
        test.skip();
        return;
      }

      const response = await request.post(`${BASE_URL}/payment-applications/${submittable.id}/submit`, {
        headers: authHeaders(),
        data: {
          stamp_selection_mode: 'none',
          stamp_method: 'online',
          payment_method: 'transfer',
          current_account_set_id: ACCOUNT_SET_ID,
        },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.message).toBe('付款申请已提交审批');
      // 验证状态变化
      expect(data.data.status).toBe('pending');
      expect(data.data.approval_instance_id).toBeTruthy();
    });
  });

  // ============================================================
  // 四、重新发起审批
  // ============================================================
  test.describe('四、重新发起审批', () => {
    test('非驳回申请调用 resubmit 失败', async ({ request }) => {
      // 找一个非 rejected 状态的申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const nonRejected = listData.data.data.find(
        (item: any) => item.status !== 'rejected'
      );
      if (!nonRejected) {
        test.skip();
        return;
      }

      const response = await request.post(`${BASE_URL}/payment-applications/${nonRejected.id}/resubmit`, {
        headers: authHeaders(),
        data: {
          stamp_method: 'online',
          stamp_selection_mode: 'none',
          current_account_set_id: ACCOUNT_SET_ID,
        },
      });
      expect(response.status()).toBe(400);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('只有被驳回的申请才能重新发起审批');
    });

    test('驳回的工资申请 resubmit 失败，提示回原模块', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'salary', per_page: 100 },
      });
      const listData = await listRes.json();
      const rejectedSalary = listData.data.data.find((item: any) => item.status === 'rejected');
      if (!rejectedSalary) {
        console.log('跳过: 没有被驳回的工资申请');
        test.skip();
        return;
      }

      const response = await request.post(`${BASE_URL}/payment-applications/${rejectedSalary.id}/resubmit`, {
        headers: authHeaders(),
        data: {
          stamp_method: 'online',
          stamp_selection_mode: 'none',
          current_account_set_id: ACCOUNT_SET_ID,
        },
      });
      expect(response.status()).toBe(400);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('工资或汇总付款申请请回原模块重新发起');
    });

    test('驳回的保险申请 resubmit 失败，提示回原模块', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 100 },
      });
      const listData = await listRes.json();
      const rejectedInsurance = listData.data.data.find((item: any) => item.status === 'rejected');
      if (!rejectedInsurance) {
        console.log('跳过: 没有被驳回的保险申请');
        test.skip();
        return;
      }

      const response = await request.post(`${BASE_URL}/payment-applications/${rejectedInsurance.id}/resubmit`, {
        headers: authHeaders(),
        data: {
          stamp_method: 'online',
          stamp_selection_mode: 'none',
          current_account_set_id: ACCOUNT_SET_ID,
        },
      });
      expect(response.status()).toBe(400);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('工资或汇总付款申请请回原模块重新发起');
    });

    test('驳回的报销类申请可以 resubmit 成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'reimbursement', per_page: 100 },
      });
      const listData = await listRes.json();
      const rejectedReimbursement = listData.data.data.find(
        (item: any) => item.status === 'rejected'
      );
      if (!rejectedReimbursement) {
        console.log('跳过: 没有被驳回的报销申请');
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/payment-applications/${rejectedReimbursement.id}/resubmit`,
        {
          headers: authHeaders(),
          data: {
            stamp_method: 'online',
            stamp_selection_mode: 'none',
            payment_method: 'transfer',
            current_account_set_id: ACCOUNT_SET_ID,
          },
        }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.message).toBe('重新发起审批成功');
      expect(data.data.status).toBe('pending');
    });

    test('resubmit 时若没有附件应失败', async ({ request }) => {
      // 找一个被驳回且无附件的报销申请
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'reimbursement', per_page: 100 },
      });
      const listData = await listRes.json();
      const rejectedNoAttach = listData.data.data.find(
        (item: any) => item.status === 'rejected' && item.attachments_count === 0
      );
      if (!rejectedNoAttach) {
        console.log('跳过: 没有被驳回且无附件的报销申请');
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/payment-applications/${rejectedNoAttach.id}/resubmit`,
        {
          headers: authHeaders(),
          data: {
            stamp_method: 'online',
            stamp_selection_mode: 'none',
            current_account_set_id: ACCOUNT_SET_ID,
          },
        }
      );
      expect(response.status()).toBe(400);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('请至少上传一个附件后再重新发起审批');
    });
  });

  // ============================================================
  // 五、补传附件
  // ============================================================
  test.describe('五、补传附件', () => {
    test('补传附件接口可用（需有 upload_later=1 的申请）', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const supplementable = listData.data.data.find(
        (item: any) => item.upload_later === true || item.upload_later === 1
      );
      if (!supplementable) {
        console.log('跳过: 没有 upload_later=1 的申请');
        test.skip();
        return;
      }

      // 检查 can_supplement_attachment 字段
      expect(supplementable).toHaveProperty('can_supplement_attachment');
      expect(supplementable).toHaveProperty('supplement_deadline_at');
      expect(supplementable).toHaveProperty('supplement_remaining_seconds');
    });

    test('没有 supplement 类型附件时确认补传完成失败', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const supplementable = listData.data.data.find(
        (item: any) =>
          (item.upload_later === true || item.upload_later === 1) &&
          item.can_supplement_attachment
      );
      if (!supplementable) {
        console.log('跳过: 没有可补传的申请');
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/payment-applications/${supplementable.id}/supplement-attachment`,
        {
          headers: authHeaders(),
          data: { current_account_set_id: ACCOUNT_SET_ID },
        }
      );
      // 没有 supplement 附件时应返回 400
      expect(response.status()).toBe(400);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('请先上传至少一个附件');
    });

    test('补传附件上传 + 确认完成全流程', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const supplementable = listData.data.data.find(
        (item: any) =>
          (item.upload_later === true || item.upload_later === 1) &&
          item.can_supplement_attachment
      );
      if (!supplementable) {
        console.log('跳过: 没有可补传的申请');
        test.skip();
        return;
      }

      // 上传 supplement 附件
      const uploadRes = await uploadFile(
        authToken,
        '/payment-request-attachments',
        TEST_PNG_PATH,
        'supplement.png',
        'image/png',
        { payment_request_id: String(supplementable.id) }
      );
      const uploadData = JSON.parse(uploadRes.body);
      expect(uploadRes.status).toBe(200);
      expect(uploadData.success).toBe(true);
      expect(uploadData.data.attachment_type).toBe('supplement');

      // 确认补传完成
      const confirmRes = await request.put(
        `${BASE_URL}/payment-applications/${supplementable.id}/supplement-attachment`,
        {
          headers: authHeaders(),
          data: { current_account_set_id: ACCOUNT_SET_ID },
        }
      );
      expect(confirmRes.status()).toBe(200);
      const confirmData = await confirmRes.json();
      expect(confirmData.success).toBe(true);
      expect(confirmData.message).toBe('附件补传完成');

      // 验证 upload_later 变为 0
      const detailRes = await request.get(`${BASE_URL}/payment-applications/${supplementable.id}`, {
        headers: authHeaders(),
      });
      const detailData = await detailRes.json();
      // 列表接口的 upload_later 字段
      const listRes2 = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData2 = await listRes2.json();
      const updated = listData2.data.data.find((item: any) => item.id === supplementable.id);
      if (updated) {
        expect(updated.upload_later === false || updated.upload_later === 0).toBe(true);
      }
    });
  });

  // ============================================================
  // 六、补传附件列表与删除
  // ============================================================
  test.describe('六、补传附件列表与删除', () => {
    test('获取补传附件列表成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const supplementable = listData.data.data.find(
        (item: any) => item.upload_later === true || item.upload_later === 1
      );
      if (!supplementable) {
        console.log('跳过: 没有 upload_later=1 的申请');
        test.skip();
        return;
      }

      const response = await request.get(`${BASE_URL}/payment-request-attachments`, {
        headers: authHeaders(),
        params: { payment_request_id: supplementable.id },
      });
      // 可能 200（有权限）或 403（无权限）
      expect([200, 403]).toContain(response.status());
      if (response.status() === 200) {
        const data = await response.json();
        expect(data.success).toBe(true);
        expect(Array.isArray(data.data)).toBe(true);
      }
    });

    test('缺少 payment_request_id 获取附件列表返回 422', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-request-attachments`, {
        headers: authHeaders(),
      });
      expect(response.status()).toBe(422);
    });
  });

  // ============================================================
  // 七、稳定性与契约检查
  // ============================================================
  test.describe('七、稳定性与契约检查', () => {
    test('列表响应结构契约', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications`, {
        headers: authHeaders(),
        params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 3 },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();

      // 顶层结构
      expect(data).toHaveProperty('success');
      expect(data).toHaveProperty('data');
      expect(data.success).toBe(true);

      // 分页结构
      expect(data.data).toHaveProperty('data');
      expect(data.data).toHaveProperty('current_page');
      expect(data.data).toHaveProperty('per_page');
      expect(data.data).toHaveProperty('total');
      expect(data.data).toHaveProperty('last_page');

      // 每条记录的字段
      for (const item of data.data.data) {
        expect(typeof item.id).toBe('number');
        expect(typeof item.status).toBe('string');
        expect(item).toHaveProperty('payment_type');
        expect(item).toHaveProperty('type_name');
        expect(item).toHaveProperty('amount');
        expect(item).toHaveProperty('attachments_count');
        expect(item).toHaveProperty('invoice_attachments_count');
        expect(item).toHaveProperty('upload_later');
        expect(item).toHaveProperty('can_upload_invoice');
        expect(item).toHaveProperty('can_supplement_attachment');
        expect(item).toHaveProperty('supplement_deadline_at');
        expect(item).toHaveProperty('supplement_remaining_seconds');
      }
    });

    test('错误返回结构一致', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/payment-applications/999999`, {
        headers: authHeaders(),
      });
      expect(response.status()).toBe(404);
      const data = await response.json();
      expect(data).toHaveProperty('success');
      expect(data).toHaveProperty('message');
      expect(data.success).toBe(false);
    });
  });
});
