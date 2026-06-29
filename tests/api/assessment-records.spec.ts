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

// multipart 文件上传辅助函数（绕过 Playwright extraHTTPHeaders 的 Content-Type 冲突）
function uploadFile(
  token: string,
  filePath: string,
  fileName: string,
  mimeType: string
): Promise<{ status: number; body: string }> {
  return new Promise((resolve, reject) => {
    const boundary = '----FormBoundary' + Date.now();
    const fileData = fs.readFileSync(filePath);
    const parts: Buffer[] = [];
    parts.push(
      Buffer.from(
        `--${boundary}\r\nContent-Disposition: form-data; name="file"; filename="${fileName}"\r\nContent-Type: ${mimeType}\r\n\r\n`
      )
    );
    parts.push(fileData);
    parts.push(Buffer.from(`\r\n--${boundary}--\r\n`));
    const body = Buffer.concat(parts);

    const req = http.request(
      {
        hostname: '127.0.0.1',
        port: 8000,
        path: '/api/assessment-records/upload-appeal-image',
        method: 'POST',
        headers: {
          'Content-Type': `multipart/form-data; boundary=${boundary}`,
          Authorization: `Bearer ${token}`,
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

// 登录获取 token
async function login(request: APIRequestContext): Promise<{ token: string; userId: number }> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: { username: USERNAME, password: PASSWORD },
  });
  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(data.success).toBe(true);
  expect(data.data.token).toBeDefined();
  return { token: data.data.token, userId: data.data.user?.id };
}

// 获取带 auth + account_set_id 的请求头
function authHeaders(token: string) {
  return {
    Authorization: `Bearer ${token}`,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  };
}

// 测试图片路径
const TEST_PNG_PATH = path.join(__dirname, 'test-image.png');
const TEST_JPG_PATH = path.join(__dirname, 'test-image.jpg');

// 生成测试图片（如果不存在）
if (!fs.existsSync(TEST_PNG_PATH)) {
  // 1x1 透明 PNG
  fs.writeFileSync(
    TEST_PNG_PATH,
    Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      'base64'
    )
  );
}
if (!fs.existsSync(TEST_JPG_PATH)) {
  // 最小 JFIF
  fs.writeFileSync(
    TEST_JPG_PATH,
    Buffer.from(
      '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYI4Q/SFhSRFYnIycnKDk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqCg4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ufo6ery8/T19vf4+fr/2gAMAwEAAhEDEQA/AKtAB//Z',
      'base64'
    )
  );
}

test.describe('考核记录 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    const { token, userId } = await login(request);
    authToken = token;
    currentUserId = userId;
    console.log('登录成功，userId:', currentUserId);
  });

  // ============================================================
  // 1. GET /assessment-records - 列表
  // ============================================================
  test.describe('1. 获取考核记录列表', () => {
    test('未登录访问列表应返回 401', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/assessment-records`, {
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(401);
    });

    test('缺少 account_set_id 应返回 400', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/assessment-records`, {
        headers: { Authorization: `Bearer ${authToken}` },
      });
      expect(response.status()).toBe(400);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('请选择账套');
    });

    test('正常获取列表成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
      expect(typeof data.total).toBe('number');
      expect(typeof data.per_page).toBe('number');
      expect(typeof data.current_page).toBe('number');
    });

    test('列表只返回当前用户自己的记录', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      for (const record of data.data) {
        expect(record.handler_id).toBe(currentUserId);
      }
    });

    test('按 business_type 筛选成功', async ({ request }) => {
      // 先获取全部列表找到一个存在的 business_type
      const allRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const allData = await allRes.json();

      if (allData.data.length === 0) {
        test.skip();
        return;
      }

      const targetType = allData.data[0].business_type;
      const response = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, business_type: targetType },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      for (const record of data.data) {
        expect(record.business_type).toBe(targetType);
      }
    });

    test('按 status 筛选成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'pending' },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      for (const record of data.data) {
        expect(record.status).toBe('pending');
      }
    });

    test('分页参数生效', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1, page: 1 },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.per_page).toBe(1);
      expect(data.current_page).toBe(1);
      expect(data.data.length).toBeLessThanOrEqual(1);
    });

    test('按 start_date / end_date 筛选成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: {
          account_set_id: ACCOUNT_SET_ID,
          start_date: '2024-01-01',
          end_date: '2027-12-31',
        },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
    });
  });

  // ============================================================
  // 2. GET /assessment-records/statistics - 统计
  // ============================================================
  test.describe('2. 获取考核统计', () => {
    test('缺少 account_set_id 应返回 400', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/assessment-records/statistics`, {
        headers: { Authorization: `Bearer ${authToken}` },
      });
      expect(response.status()).toBe(400);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('请选择账套');
    });

    test('获取统计成功且结构正确', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/assessment-records/statistics`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);

      // 验证 overall 结构
      expect(data.data).toHaveProperty('overall');
      expect(data.data.overall).toHaveProperty('total_count');
      expect(data.data.overall).toHaveProperty('pending_count');
      expect(data.data.overall).toHaveProperty('overdue_count');
      expect(data.data.overall).toHaveProperty('completed_count');

      // 验证 by_handler 结构
      expect(data.data).toHaveProperty('by_handler');
      expect(Array.isArray(data.data.by_handler)).toBe(true);
      if (data.data.by_handler.length > 0) {
        const handler = data.data.by_handler[0];
        expect(handler).toHaveProperty('handler_id');
        expect(handler).toHaveProperty('handler_name');
        expect(handler).toHaveProperty('total_count');
        expect(handler).toHaveProperty('pending_count');
        expect(handler).toHaveProperty('overdue_count');
        expect(handler).toHaveProperty('completed_count');
      }

      // 验证 by_business 结构
      expect(data.data).toHaveProperty('by_business');
      expect(Array.isArray(data.data.by_business)).toBe(true);
      if (data.data.by_business.length > 0) {
        const biz = data.data.by_business[0];
        expect(biz).toHaveProperty('business_type');
        expect(biz).toHaveProperty('total_count');
        expect(biz).toHaveProperty('pending_count');
        expect(biz).toHaveProperty('overdue_count');
        expect(biz).toHaveProperty('completed_count');
      }
    });
  });

  // ============================================================
  // 3. PUT /assessment-records/{id}/complete - 标记完成
  // ============================================================
  test.describe('3. 标记考核记录完成', () => {
    test('对不存在的记录标记完成应返回 404', async ({ request }) => {
      const response = await request.put(`${BASE_URL}/assessment-records/999999/complete`, {
        headers: authHeaders(authToken),
      });
      expect(response.status()).toBe(404);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('记录不存在');
    });

    test('对自己的记录标记完成成功', async ({ request }) => {
      // 先获取一条自己的 pending/overdue 记录
      const listRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const pendingRecord = listData.data.find(
        (r: any) => r.status === 'pending' || r.status === 'overdue'
      );

      if (!pendingRecord) {
        console.log('跳过: 没有待处理的考核记录');
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/assessment-records/${pendingRecord.id}/complete`,
        { headers: authHeaders(authToken) }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.message).toBe('已标记为完成');
      expect(data.data.status).toBe('completed');
      expect(data.data.actual_complete_date).toBeTruthy();
    });
  });

  // ============================================================
  // 4. PUT /assessment-records/{id}/remark - 更新备注
  // ============================================================
  test.describe('4. 更新考核记录备注', () => {
    test('对不存在的记录更新备注应返回 404', async ({ request }) => {
      const response = await request.put(`${BASE_URL}/assessment-records/999999/remark`, {
        headers: authHeaders(authToken),
        data: { remark: '测试备注' },
      });
      expect(response.status()).toBe(404);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('记录不存在');
    });

    test('对自己的记录更新备注成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();

      if (listData.data.length === 0) {
        console.log('跳过: 没有考核记录');
        test.skip();
        return;
      }

      const record = listData.data[0];
      const remarkText = `API测试备注_${Date.now()}`;

      const response = await request.put(
        `${BASE_URL}/assessment-records/${record.id}/remark`,
        {
          headers: authHeaders(authToken),
          data: { remark: remarkText },
        }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.message).toBe('备注已更新');
      expect(data.data.remark).toBe(remarkText);
    });
  });

  // ============================================================
  // 5. DELETE /assessment-records/{id} - 删除记录
  // ============================================================
  test.describe('5. 删除考核记录', () => {
    test('删除不存在的记录应返回 404', async ({ request }) => {
      const response = await request.delete(`${BASE_URL}/assessment-records/999999`, {
        headers: authHeaders(authToken),
      });
      expect(response.status()).toBe(404);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('记录不存在');
    });
  });

  // ============================================================
  // 6. POST /assessment-records/refresh-status - 刷新状态
  // ============================================================
  test.describe('6. 刷新考核状态', () => {
    test('刷新状态成功', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/assessment-records/refresh-status`, {
        headers: authHeaders(authToken),
        data: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.message).toContain('已更新');
    });
  });

  // ============================================================
  // 7. POST /assessment-records/trigger-check - 触发检查
  // ============================================================
  test.describe('7. 触发检查', () => {
    test('触发检查返回可预期结构', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/assessment-records/trigger-check`, {
        headers: authHeaders(authToken),
        data: {},
      });
      // 可能成功(200)也可能失败(500,如命令不存在)
      expect([200, 500]).toContain(response.status());
      const data = await response.json();
      expect(data).toHaveProperty('success');
      expect(data).toHaveProperty('message');
    });
  });

  // ============================================================
  // 8. POST /assessment-records/check-new-employee-documents - 检查新员工资料
  // ============================================================
  test.describe('8. 检查新入职员工资料', () => {
    test('检查新员工资料返回可预期结构', async ({ request }) => {
      const response = await request.post(
        `${BASE_URL}/assessment-records/check-new-employee-documents`,
        {
          headers: authHeaders(authToken),
          data: { account_set_id: ACCOUNT_SET_ID },
        }
      );
      expect([200, 500]).toContain(response.status());
      const data = await response.json();
      expect(data).toHaveProperty('success');
      expect(data).toHaveProperty('message');
    });
  });

  // ============================================================
  // 9. POST /assessment-records/upload-appeal-image - 上传申诉图片
  // ============================================================
  test.describe('9. 上传申诉图片', () => {
    test('上传合法 PNG 图片成功', async () => {
      const res = await uploadFile(authToken, TEST_PNG_PATH, 'test.png', 'image/png');
      const data = JSON.parse(res.body);
      expect(res.status).toBe(200);
      expect(data.success).toBe(true);
      expect(data.message).toBe('图片上传成功');
      expect(data.data).toHaveProperty('file_name');
      expect(data.data).toHaveProperty('file_path');
      expect(data.data).toHaveProperty('url');
    });

    test('上传合法 JPG 图片成功', async () => {
      const res = await uploadFile(authToken, TEST_JPG_PATH, 'test.jpg', 'image/jpeg');
      const data = JSON.parse(res.body);
      expect(res.status).toBe(200);
      expect(data.success).toBe(true);
    });

    test('上传非法文件类型应返回 422', async () => {
      // 创建临时 txt 文件
      const txtPath = path.join(__dirname, 'test-file.txt');
      fs.writeFileSync(txtPath, 'not an image');
      const res = await uploadFile(authToken, txtPath, 'test.txt', 'text/plain');
      fs.unlinkSync(txtPath);
      const data = JSON.parse(res.body);
      expect(res.status).toBe(422);
      expect(data.success).toBe(false);
      expect(data.message).toBe('验证失败');
    });

    test('不传文件应返回 422', async () => {
      // 空 multipart 请求
      const boundary = '----FormBoundary' + Date.now();
      const body = Buffer.from(`\r\n--${boundary}--\r\n`);
      const res = await new Promise<{ status: number; body: string }>((resolve, reject) => {
        const req = http.request(
          {
            hostname: '127.0.0.1',
            port: 8000,
            path: '/api/assessment-records/upload-appeal-image',
            method: 'POST',
            headers: {
              'Content-Type': `multipart/form-data; boundary=${boundary}`,
              Authorization: `Bearer ${authToken}`,
              'Content-Length': body.length,
            },
          },
          (response) => {
            let data = '';
            response.on('data', (chunk) => (data += chunk));
            response.on('end', () => resolve({ status: response.statusCode!, body: data }));
          }
        );
        req.on('error', reject);
        req.write(body);
        req.end();
      });
      const data = JSON.parse(res.body);
      expect(res.status).toBe(422);
      expect(data.success).toBe(false);
    });
  });

  // ============================================================
  // 10. POST /assessment-records/{id}/appeals - 提交申诉
  // ============================================================
  test.describe('10. 提交申诉', () => {
    test('对不存在的记录提交申诉应返回 404', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/assessment-records/999999/appeals`, {
        headers: authHeaders(authToken),
        data: {
          description: '测试申诉',
          images: ['assessment_appeals/images/test.png'],
        },
      });
      expect(response.status()).toBe(404);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('记录不存在');
    });

    test('空 description 应返回 422', async ({ request }) => {
      // 先找一条自己的记录
      const listRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }
      const recordId = listData.data[0].id;

      const response = await request.post(`${BASE_URL}/assessment-records/${recordId}/appeals`, {
        headers: authHeaders(authToken),
        data: {
          description: '',
          images: ['assessment_appeals/images/test.png'],
        },
      });
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('验证失败');
    });

    test('空 images 数组应返回 422', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }
      const recordId = listData.data[0].id;

      const response = await request.post(`${BASE_URL}/assessment-records/${recordId}/appeals`, {
        headers: authHeaders(authToken),
        data: {
          description: '测试申诉描述',
          images: [],
        },
      });
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
    });

    test('缺少 description 应返回 422', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }
      const recordId = listData.data[0].id;

      const response = await request.post(`${BASE_URL}/assessment-records/${recordId}/appeals`, {
        headers: authHeaders(authToken),
        data: {
          images: ['assessment_appeals/images/test.png'],
        },
      });
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
    });

    test('description 超过 1000 字应返回 422', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }
      const recordId = listData.data[0].id;

      const response = await request.post(`${BASE_URL}/assessment-records/${recordId}/appeals`, {
        headers: authHeaders(authToken),
        data: {
          description: 'x'.repeat(1001),
          images: ['assessment_appeals/images/test.png'],
        },
      });
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
    });

    test('成功提交申诉（需有可用记录）', async ({ request }) => {
      // 找一条可以申诉的记录（没有发起过申诉的）
      const listRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();

      // 找一条 can_appeal 为 true 的记录
      const canAppealRecord = listData.data.find((r: any) => r.can_appeal === true);

      if (!canAppealRecord) {
        console.log('跳过: 没有可申诉的考核记录');
        test.skip();
        return;
      }

      // 先上传图片获取路径
      const uploadRes = await uploadFile(authToken, TEST_PNG_PATH, 'appeal.png', 'image/png');
      const uploadData = JSON.parse(uploadRes.body);
      const imagePath = uploadData.data.file_path;

      const response = await request.post(
        `${BASE_URL}/assessment-records/${canAppealRecord.id}/appeals`,
        {
          headers: authHeaders(authToken),
          data: {
            description: `API测试申诉_${Date.now()}`,
            images: [imagePath],
          },
        }
      );

      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.message).toBe('申诉提交成功');
      expect(data.data.status).toBe('pending');
      expect(data.data.assessment_record_id).toBe(canAppealRecord.id);
      expect(data.data.description).toBeTruthy();
      expect(Array.isArray(data.data.images)).toBe(true);
    });

    test('同一记录重复申诉应失败', async ({ request }) => {
      // 找一条已有申诉的记录
      const listRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const hasAppealRecord = listData.data.find(
        (r: any) => r.latest_appeal && r.latest_appeal.status !== 'rejected'
      );

      if (!hasAppealRecord) {
        console.log('跳过: 没有已申诉的记录');
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/assessment-records/${hasAppealRecord.id}/appeals`,
        {
          headers: authHeaders(authToken),
          data: {
            description: '重复申诉测试',
            images: ['assessment_appeals/images/test.png'],
          },
        }
      );
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('该考核已发起过申诉');
    });

    test('被驳回的申诉不能再次申诉', async ({ request }) => {
      // 找一条最新申诉被驳回的记录
      const listRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const rejectedRecord = listData.data.find(
        (r: any) => r.latest_appeal && r.latest_appeal.status === 'rejected'
      );

      if (!rejectedRecord) {
        console.log('跳过: 没有申诉被驳回的记录');
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/assessment-records/${rejectedRecord.id}/appeals`,
        {
          headers: authHeaders(authToken),
          data: {
            description: '再次申诉测试',
            images: ['assessment_appeals/images/test.png'],
          },
        }
      );
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('该考核申诉已被驳回，不可再次申诉');
    });
  });

  // ============================================================
  // 11. GET /assessment-records/{id}/appeals - 获取申诉记录
  // ============================================================
  test.describe('11. 获取申诉记录', () => {
    test('获取不存在记录的申诉应返回 404', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/assessment-records/999999/appeals`, {
        headers: authHeaders(authToken),
      });
      expect(response.status()).toBe(404);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toBe('记录不存在');
    });

    test('获取自己的申诉记录成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/assessment-records`, {
        headers: authHeaders(authToken),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();

      if (listData.data.length === 0) {
        console.log('跳过: 没有考核记录');
        test.skip();
        return;
      }

      const recordId = listData.data[0].id;
      const response = await request.get(`${BASE_URL}/assessment-records/${recordId}/appeals`, {
        headers: authHeaders(authToken),
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);

      // 验证申诉记录结构
      if (data.data.length > 0) {
        const appeal = data.data[0];
        expect(appeal).toHaveProperty('id');
        expect(appeal).toHaveProperty('assessment_record_id');
        expect(appeal).toHaveProperty('appellant_id');
        expect(appeal).toHaveProperty('description');
        expect(appeal).toHaveProperty('images');
        expect(appeal).toHaveProperty('status');
        expect(['pending', 'approved', 'rejected']).toContain(appeal.status);
      }
    });
  });
});
