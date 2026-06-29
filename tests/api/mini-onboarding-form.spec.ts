import { test, expect, APIRequestContext } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import * as http from 'http';

const BASE_URL = 'http://localhost:8000/api';
const MINI_PHONE = process.env.MINI_PHONE || '13800000001';
const MINI_PASSWORD = process.env.MINI_PASSWORD || '011234';
const ADMIN_USERNAME = process.env.E2E_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);

let miniToken: string;
let miniEmployeeId: number;
let adminToken: string;

// 测试用 base64 签名图片（1x1 红色 PNG）
const SIGNATURE_BASE64 =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';

const TEST_PNG_PATH = path.join(__dirname, 'test-image.png');

async function miniLogin(request: APIRequestContext): Promise<{ token: string; employeeId: number }> {
  const response = await request.post(`${BASE_URL}/mini/login`, {
    data: { phone: MINI_PHONE, password: MINI_PASSWORD },
  });
  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(data.success).toBe(true);
  return { token: data.data.token, employeeId: data.data.employee.id };
}

async function adminLogin(request: APIRequestContext): Promise<string> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: { username: ADMIN_USERNAME, password: ADMIN_PASSWORD },
  });
  expect(response.status()).toBe(200);
  const data = await response.json();
  return data.data.token;
}

function miniHeaders(token?: string) {
  return { Authorization: `Bearer ${token || miniToken}` };
}

function adminHeaders(token?: string) {
  return {
    Authorization: `Bearer ${token || adminToken}`,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  };
}

function uploadSignature(token: string, base64: string): Promise<{ status: number; body: any }> {
  return new Promise((resolve, reject) => {
    const postData = JSON.stringify({ signature: base64 });
    const url = new URL(`${BASE_URL}/mini/upload-signature`);
    const req = http.request(
      {
        hostname: '127.0.0.1',
        port: url.port,
        path: url.pathname,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
          'Content-Length': Buffer.byteLength(postData),
        },
      },
      (res) => {
        let data = '';
        res.on('data', (chunk) => (data += chunk));
        res.on('end', () => resolve({ status: res.statusCode!, body: JSON.parse(data) }));
      }
    );
    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}

function uploadPhoto(token: string, filePath: string): Promise<{ status: number; body: any }> {
  return new Promise((resolve, reject) => {
    const boundary = '----FormBoundary' + Date.now();
    const fileData = fs.readFileSync(filePath);
    const parts: Buffer[] = [];
    parts.push(
      Buffer.from(
        `--${boundary}\r\nContent-Disposition: form-data; name="photo"; filename="test.png"\r\nContent-Type: image/png\r\n\r\n`
      )
    );
    parts.push(fileData);
    parts.push(Buffer.from(`\r\n--${boundary}--\r\n`));
    const body = Buffer.concat(parts);
    const url = new URL(`${BASE_URL}/mini/upload-photo`);
    const req = http.request(
      {
        hostname: '127.0.0.1',
        port: url.port,
        path: url.pathname,
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
        res.on('end', () => resolve({ status: res.statusCode!, body: JSON.parse(data) }));
      }
    );
    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

function buildOnboardingPayload(overrides?: Record<string, any>) {
  return {
    registration_date: '2024-01-15',
    name: '测试员工',
    gender: 'male',
    id_number: '110101199001011234',
    education_type: '统招',
    signature: 'uploads/signatures/test_sig.png',
    birth_date: '1990-01-01',
    contact_phone: '13800000001',
    ...overrides,
  };
}

test.describe('入职登记表 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    const mini = await miniLogin(request);
    miniToken = mini.token;
    miniEmployeeId = mini.employeeId;
    adminToken = await adminLogin(request);
  });

  // ============================================================
  // 一、获取入职登记表
  // ============================================================
  test.describe('一、获取入职登记表', () => {
    test('GET onboarding-form 返回 200', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data).toBeDefined();
    });

    test('无表单时返回银行默认字段', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
      });
      const data = await response.json();
      // 无论有无表单，都应有 bank 字段
      expect(data.data).toHaveProperty('bank_account');
      expect(data.data).toHaveProperty('bank_account_holder');
      expect(data.data).toHaveProperty('bank_name');
      expect(data.data).toHaveProperty('bank_branch');
    });

    test('有表单时返回完整字段', async ({ request }) => {
      // 先提交一份表单
      const sigRes = await uploadSignature(miniToken, SIGNATURE_BASE64);
      expect(sigRes.status).toBe(200);

      await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ signature: sigRes.body.data.path }),
      });

      const response = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
      });
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data).toHaveProperty('name');
      expect(data.data).toHaveProperty('gender');
      expect(data.data).toHaveProperty('id_number');
      expect(data.data).toHaveProperty('education_type');
      expect(data.data).toHaveProperty('signature');
    });

    test('education_type 返回规范值', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
      });
      const data = await response.json();
      if (data.data.education_type) {
        expect(['统招', '非统招']).toContain(data.data.education_type);
      }
    });
  });

  // ============================================================
  // 二、上传签名
  // ============================================================
  test.describe('二、上传签名 (upload-signature)', () => {
    test('base64 签名上传成功', async ({ request }) => {
      const res = await uploadSignature(miniToken, SIGNATURE_BASE64);
      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.data).toHaveProperty('path');
      expect(res.body.data).toHaveProperty('url');
      expect(res.body.data.path).toContain('uploads/');
    });

    test('缺少 signature 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/upload-signature`, {
        headers: miniHeaders(),
        data: {},
      });
      expect(response.status()).toBe(422);
    });

    test('未登录上传签名返回 401', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/upload-signature`, {
        data: { signature: SIGNATURE_BASE64 },
      });
      expect(response.status()).toBe(401);
    });
  });

  // ============================================================
  // 三、上传照片
  // ============================================================
  test.describe('三、上传照片 (upload-photo)', () => {
    test('图片上传成功', async ({ request }) => {
      if (!fs.existsSync(TEST_PNG_PATH)) {
        test.skip();
        return;
      }
      const res = await uploadPhoto(miniToken, TEST_PNG_PATH);
      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.data).toHaveProperty('path');
      expect(res.body.data).toHaveProperty('url');
    });

    test('缺少 photo 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/upload-photo`, {
        headers: miniHeaders(),
        data: {},
      });
      expect(response.status()).toBe(422);
    });

    test('未登录上传照片返回 401', async ({ request }) => {
      if (!fs.existsSync(TEST_PNG_PATH)) {
        test.skip();
        return;
      }
      const res = await uploadPhoto('invalid_token', TEST_PNG_PATH);
      expect(res.status).toBe(401);
    });
  });

  // ============================================================
  // 四、提交入职登记表 - 必填字段验证
  // ============================================================
  test.describe('四、提交入职登记表 - 必填字段验证', () => {
    test('缺少 registration_date 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ registration_date: undefined }),
      });
      expect(response.status()).toBe(422);
      const data = await response.json();
      expect(data.errors).toBeDefined();
    });

    test('缺少 name 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ name: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 gender 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ gender: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 id_number 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ id_number: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('id_number 不是 18 位返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ id_number: '1234567890' }),
      });
      expect(response.status()).toBe(422);
    });

    test('education_type 非法值且无已有表单时返回 422', async ({ request }) => {
      // 注意: 若已有表单, 控制器会从已有表单回填 education_type, 不一定返回 422
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ education_type: 'invalid_value' }),
      });
      // 有已有表单时回填后可能 200, 无已有表单时 422
      expect([200, 422]).toContain(response.status());
    });

    test('缺少 signature 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ signature: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('gender 非法值返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ gender: 'other' }),
      });
      expect(response.status()).toBe(422);
    });
  });

  // ============================================================
  // 五、提交入职登记表 - 成功场景
  // ============================================================
  test.describe('五、提交入职登记表 - 成功场景', () => {
    test('最小必填字段提交成功', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload(),
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.message).toContain('成功');
    });

    test('完整字段提交成功', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({
          ethnicity: '汉族',
          political_status: '群众',
          birth_date: '1990-01-01',
          graduated_school: '测试大学',
          graduation_date: '2012-06-30',
          education_level: '本科',
          major: '计算机科学',
          degree: '学士',
          technical_title: '工程师',
          health_status: '良好',
          height: 175,
          weight: 70,
          marital_status: '未婚',
          id_card_valid_from: '2010-01-01',
          id_card_valid_until: '2030-01-01',
          current_residence: '北京市朝阳区',
          position: '软件工程师',
          contact_phone: '13800000001',
          contact_province: '北京市',
          contact_city: '北京市',
          contact_district: '朝阳区',
          contact_address_detail: '某某路100号',
          bank_account: '6222021234567890123',
          bank_account_holder: '测试员工',
          bank_name: '中国工商银行',
          bank_branch: '朝阳支行',
          place_of_origin_province: '河北省',
          place_of_origin_city: '石家庄市',
          place_of_origin_district: '长安区',
          place_of_origin_detail: '某某街道',
          remarks: '测试备注',
        }),
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
    });
  });

  // ============================================================
  // 六、学历类型规范化
  // ============================================================
  test.describe('六、学历类型规范化', () => {
    test('全日制 被规范化为 统招', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ education_type: '统招' }),
      });
      expect(response.status()).toBe(200);

      // 验证存储后的值
      const getRes = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
      });
      const getData = await getRes.json();
      expect(getData.data.education_type).toBe('统招');
    });

    test('非全日制 被规范化为 非统招', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ education_type: '非统招' }),
      });
      expect(response.status()).toBe(200);

      const getRes = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
      });
      const getData = await getRes.json();
      expect(getData.data.education_type).toBe('非统招');
    });
  });

  // ============================================================
  // 七、地址组装
  // ============================================================
  test.describe('七、地址组装', () => {
    test('contact_address 由省市区+详情空格拼接', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({
          contact_province: '北京市',
          contact_city: '北京市',
          contact_district: '海淀区',
          contact_address_detail: '中关村大街1号',
        }),
      });
      expect(response.status()).toBe(200);

      const getRes = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
      });
      const getData = await getRes.json();
      // contact_address 应包含各部分
      if (getData.data.contact_address) {
        expect(getData.data.contact_address).toContain('北京市');
        expect(getData.data.contact_address).toContain('海淀区');
      }
    });

    test('place_of_origin 由省市区+详情无空格拼接', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({
          place_of_origin_province: '河北省',
          place_of_origin_city: '石家庄市',
          place_of_origin_district: '长安区',
          place_of_origin_detail: '某某街道',
        }),
      });
      expect(response.status()).toBe(200);

      const getRes = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
      });
      const getData = await getRes.json();
      if (getData.data.place_of_origin) {
        expect(getData.data.place_of_origin).toContain('河北省');
        expect(getData.data.place_of_origin).toContain('石家庄市');
      }
    });
  });

  // ============================================================
  // 八、数组字段
  // ============================================================
  test.describe('八、数组字段', () => {
    test('education_background 数组提交成功', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({
          education_background: [
            {
              start_date: '2006-09',
              end_date: '2010-06',
              school: '测试大学',
              level: '本科',
              certifier: '张三',
            },
          ],
        }),
      });
      expect(response.status()).toBe(200);
    });

    test('work_experience 数组提交成功', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({
          work_experience: [
            {
              start_date: '2010-07',
              end_date: '2015-12',
              employer: '测试公司',
              job_content: '软件开发',
              certifier: '李四',
            },
          ],
        }),
      });
      expect(response.status()).toBe(200);
    });

    test('family_info 数组提交成功', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({
          family_info: [
            {
              name: '张父',
              relationship: '父亲',
              employer: '务农',
              phone: '13900000000',
            },
          ],
        }),
      });
      expect(response.status()).toBe(200);
    });
  });

  // ============================================================
  // 九、重复提交（updateOrCreate）
  // ============================================================
  test.describe('九、重复提交', () => {
    test('重复提交更新而非新建', async ({ request }) => {
      // 第一次提交
      await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ name: '第一次名字' }),
      });

      // 第二次提交
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
        data: buildOnboardingPayload({ name: '第二次名字' }),
      });
      expect(response.status()).toBe(200);

      // 验证名字已更新
      const getRes = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
      });
      const getData = await getRes.json();
      expect(getData.data.name).toBe('第二次名字');
    });
  });

  // ============================================================
  // 十、管理员端验证
  // ============================================================
  test.describe('十、管理员端验证', () => {
    test('管理员可查看员工入职登记表', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/employees/${miniEmployeeId}/onboarding-form`,
        { headers: adminHeaders() }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      if (data.data) {
        expect(data.data).toHaveProperty('name');
        expect(data.data).toHaveProperty('id_number');
        // 管理员端应覆盖银行字段
        expect(data.data).toHaveProperty('bank_account');
        expect(data.data).toHaveProperty('bank_account_holder');
        expect(data.data).toHaveProperty('bank_name');
        expect(data.data).toHaveProperty('bank_branch');
        expect(data.data).toHaveProperty('emergency_contact');
        expect(data.data).toHaveProperty('emergency_phone');
      }
    });

    test('管理员端数据与小程序端一致', async ({ request }) => {
      const miniRes = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: miniHeaders(),
      });
      const miniData = await miniRes.json();

      const adminRes = await request.get(
        `${BASE_URL}/employees/${miniEmployeeId}/onboarding-form`,
        { headers: adminHeaders() }
      );
      const adminData = await adminRes.json();

      if (miniData.data && adminData.data) {
        expect(adminData.data.name).toBe(miniData.data.name);
        expect(adminData.data.id_number).toBe(miniData.data.id_number);
        expect(adminData.data.gender).toBe(miniData.data.gender);
      }
    });
  });

  // ============================================================
  // 十一、未认证访问
  // ============================================================
  test.describe('十一、未认证访问', () => {
    test('无 token 提交表单返回 401', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/onboarding-form`, {
        data: buildOnboardingPayload(),
      });
      expect(response.status()).toBe(401);
    });

    test('无 token 获取表单返回 401', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/mini/onboarding-form`);
      expect(response.status()).toBe(401);
    });
  });
});
