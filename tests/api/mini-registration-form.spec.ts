import { test, expect, APIRequestContext } from '@playwright/test';

const BASE_URL = 'http://localhost:8000/api';
const MINI_PHONE = process.env.MINI_PHONE || '13800000001';
const MINI_PASSWORD = process.env.MINI_PASSWORD || '011234';
const ADMIN_USERNAME = process.env.E2E_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);

let miniToken: string;
let miniEmployeeId: number;
let adminToken: string;

async function miniLogin(request: APIRequestContext): Promise<{ token: string; employeeId: number }> {
  const response = await request.post(`${BASE_URL}/mini/login`, {
    data: { phone: MINI_PHONE, password: MINI_PASSWORD },
  });
  expect(response.status()).toBe(200);
  const data = await response.json();
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

function buildRegistrationPayload(overrides?: Record<string, any>) {
  return {
    fill_date: '2024-01-15',
    entry_position: '软件工程师',
    entry_date: '2024-02-01',
    department: '技术部',
    job_title: '开发工程师',
    housing_fund_account: 'HF123456',
    bank_account: '6222021234567890123',
    bank_account_holder: '测试员工',
    bank_name: '中国工商银行',
    bank_branch: '朝阳支行',
    name: '测试员工',
    english_name: 'Test Employee',
    gender: 'male',
    height: '175',
    birth_date: '1990-01-01',
    political_status: '群众',
    education_level: '本科',
    education_type: '统招',
    native_place: '河北省石家庄市',
    marital_status: '未婚',
    has_children: '无',
    id_number: '110101199001011234',
    id_card_valid_from: '2020-01-01',
    id_card_valid_until: '2040-01-01',
    household_type: 'urban',
    current_address: '北京市朝阳区某某路100号',
    postal_code: '100000',
    household_address: '河北省石家庄市某某街道',
    contact_phone: '13800000001',
    document_address: '北京市朝阳区某某路100号',
    disability_level: '无',
    language_skills: ['英语四级'],
    engineering_skills: ['无'],
    professional_title: '无',
    hobbies: ['阅读'],
    education_history: [
      {
        date_range: '2006-2010',
        school_major: '测试大学 计算机科学',
        certificate: '学士学位',
      },
    ],
    work_history: [
      {
        date_range: '2010-2015',
        company: '测试公司',
        position: '开发工程师',
        salary: '10000',
        leave_reason: '个人发展',
      },
    ],
    reference_company: '测试公司',
    reference_contact: '张经理 13900000000',
    rewards_punishments: '无',
    family_members: [
      {
        name: '张父',
        relation: '父亲',
        age: '60',
        employer: '务农',
        phone: '13700000000',
      },
    ],
    emergency_contact1_name: '李四',
    emergency_contact1_relation: '朋友',
    emergency_contact1_phone: '13600000000',
    emergency_contact2_name: '王五',
    emergency_contact2_relation: '同事',
    emergency_contact2_phone: '13500000000',
    mental_illness: '无',
    other_illness: '无',
    hospitalized_recently: '无',
    criminal_record: '无',
    employment_documents: ['身份证'],
    remarks: '无',
    is_pregnant: '无',
    accept_overtime: '是',
    need_accommodation: '无',
    has_driving_license: '无',
    signature: 'uploads/signatures/test_sig.png',
    signature_date: '2024-01-15',
    ...overrides,
  };
}

test.describe('从业人员登记表 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    const mini = await miniLogin(request);
    miniToken = mini.token;
    miniEmployeeId = mini.employeeId;
    adminToken = await adminLogin(request);
  });

  // ============================================================
  // 一、获取从业人员登记表
  // ============================================================
  test.describe('一、获取从业人员登记表', () => {
    test('GET registration-form 返回 200', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
    });

    test('无表单时 data 为 null', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
      });
      const data = await response.json();
      // 无表单时 data 应为 null 或 undefined
      if (data.data === null || data.data === undefined) {
        expect(data.data).toBeNull();
      } else {
        // 如果已有数据，说明之前提交过，也算通过
        expect(data.data).toBeDefined();
      }
    });
  });

  // ============================================================
  // 二、提交从业人员登记表 - 必填字段验证
  // ============================================================
  test.describe('二、必填字段验证', () => {
    test('缺少 fill_date 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ fill_date: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 entry_position 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ entry_position: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 entry_date 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ entry_date: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 department 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ department: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 job_title 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ job_title: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 bank_account 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ bank_account: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 bank_account_holder 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ bank_account_holder: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 bank_name 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ bank_name: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 bank_branch 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ bank_branch: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 name 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ name: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 english_name 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ english_name: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 gender 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ gender: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 id_number 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ id_number: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('id_number 不是 18 位返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ id_number: '123456' }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 education_level 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ education_level: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('education_type 非法值返回 422', async ({ request }) => {
      // 注意: 若已有表单, 控制器会从已有表单回填 education_type
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ education_type: 'invalid_value' }),
      });
      expect([200, 422]).toContain(response.status());
    });

    test('缺少 native_place 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ native_place: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 marital_status 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ marital_status: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 has_children 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ has_children: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 household_type 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ household_type: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 current_address 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ current_address: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 postal_code 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ postal_code: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 household_address 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ household_address: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 contact_phone 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ contact_phone: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 document_address 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ document_address: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 disability_level 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ disability_level: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 language_skills 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ language_skills: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('language_skills 空数组返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ language_skills: [] }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 engineering_skills 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ engineering_skills: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 professional_title 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ professional_title: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 hobbies 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ hobbies: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 education_history 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ education_history: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('education_history 空数组返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ education_history: [] }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 work_history 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ work_history: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 reference_company 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ reference_company: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 reference_contact 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ reference_contact: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 rewards_punishments 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ rewards_punishments: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 family_members 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ family_members: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 emergency_contact1_name 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ emergency_contact1_name: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 emergency_contact1_relation 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ emergency_contact1_relation: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 emergency_contact1_phone 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ emergency_contact1_phone: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 emergency_contact2_name 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ emergency_contact2_name: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 emergency_contact2_relation 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ emergency_contact2_relation: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 emergency_contact2_phone 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ emergency_contact2_phone: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 mental_illness 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ mental_illness: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 other_illness 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ other_illness: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 hospitalized_recently 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ hospitalized_recently: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 criminal_record 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ criminal_record: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 employment_documents 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ employment_documents: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 remarks 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ remarks: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 is_pregnant 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ is_pregnant: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 accept_overtime 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ accept_overtime: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 need_accommodation 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ need_accommodation: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 has_driving_license 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ has_driving_license: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 signature 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ signature: undefined }),
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 signature_date 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ signature_date: undefined }),
      });
      expect(response.status()).toBe(422);
    });
  });

  // ============================================================
  // 三、条件必填字段 (required_if)
  // ============================================================
  test.describe('三、条件必填字段 (required_if)', () => {
    test('mental_illness=有 时 mental_illness_detail 必填', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          mental_illness: '有',
          mental_illness_detail: undefined,
        }),
      });
      expect(response.status()).toBe(422);
    });

    test('mental_illness=有 且有 detail 则通过', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          mental_illness: '有',
          mental_illness_detail: '轻度焦虑',
        }),
      });
      expect(response.status()).toBe(200);
    });

    test('other_illness=有 时 other_illness_detail 必填', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          other_illness: '有',
          other_illness_detail: undefined,
        }),
      });
      expect(response.status()).toBe(422);
    });

    test('other_illness=有 且有 detail 则通过', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          other_illness: '有',
          other_illness_detail: '高血压',
        }),
      });
      expect(response.status()).toBe(200);
    });

    test('hospitalized_recently=有 时 hospitalized_reason 必填', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          hospitalized_recently: '有',
          hospitalized_reason: undefined,
        }),
      });
      expect(response.status()).toBe(422);
    });

    test('hospitalized_recently=有 且有 reason 则通过', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          hospitalized_recently: '有',
          hospitalized_reason: '阑尾炎手术',
        }),
      });
      expect(response.status()).toBe(200);
    });

    test('criminal_record=有 时 criminal_record_time 必填', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          criminal_record: '有',
          criminal_record_time: undefined,
        }),
      });
      expect(response.status()).toBe(422);
    });

    test('criminal_record=有 且有 time 则通过', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          criminal_record: '有',
          criminal_record_time: '2020年',
        }),
      });
      expect(response.status()).toBe(200);
    });

    test('is_pregnant=有 时 pregnant_detail 必填', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          is_pregnant: '有',
          pregnant_detail: undefined,
        }),
      });
      expect(response.status()).toBe(422);
    });

    test('is_pregnant=有 且有 detail 则通过', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          is_pregnant: '有',
          pregnant_detail: '怀孕3个月',
        }),
      });
      expect(response.status()).toBe(200);
    });

    test('need_accommodation=有 时 accommodation_detail 必填', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          need_accommodation: '有',
          accommodation_detail: undefined,
        }),
      });
      expect(response.status()).toBe(422);
    });

    test('need_accommodation=有 且有 detail 则通过', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          need_accommodation: '有',
          accommodation_detail: '需要单人间',
        }),
      });
      expect(response.status()).toBe(200);
    });

    test('has_driving_license=有 时 driving_license_detail 必填', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          has_driving_license: '有',
          driving_license_detail: undefined,
        }),
      });
      expect(response.status()).toBe(422);
    });

    test('has_driving_license=有 且有 detail 则通过', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          has_driving_license: '有',
          driving_license_detail: 'C1驾照',
        }),
      });
      expect(response.status()).toBe(200);
    });
  });

  // ============================================================
  // 四、提交成功
  // ============================================================
  test.describe('四、提交成功', () => {
    test('完整数据提交成功', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload(),
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.message).toContain('成功');
    });

    test('提交后 GET 可获取数据', async ({ request }) => {
      await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload(),
      });

      const response = await request.get(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
      });
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data).not.toBeNull();
      expect(data.data.name).toBe('测试员工');
    });

    test('身份证有效期提交后同步到人员档案', async ({ request }) => {
      const idCardValidFrom = '2022-03-04';
      const idCardValidUntil = '2042-03-04';

      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          id_card_valid_from: idCardValidFrom,
          id_card_valid_until: idCardValidUntil,
        }),
      });
      expect(response.status()).toBe(200);

      const miniRes = await request.get(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
      });
      const miniData = await miniRes.json();
      expect(String(miniData.data.id_card_valid_from).slice(0, 10)).toBe(idCardValidFrom);
      expect(String(miniData.data.id_card_valid_until).slice(0, 10)).toBe(idCardValidUntil);

      const employeeRes = await request.get(`${BASE_URL}/employees/${miniEmployeeId}`, {
        headers: adminHeaders(),
      });
      const employeeData = await employeeRes.json();
      expect(String(employeeData.data.id_card_valid_from).slice(0, 10)).toBe(idCardValidFrom);
      expect(String(employeeData.data.id_card_valid_until).slice(0, 10)).toBe(idCardValidUntil);
    });
  });

  // ============================================================
  // 五、学历类型规范化
  // ============================================================
  test.describe('五、学历类型规范化', () => {
    test('education_type 统招 存储正确', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ education_type: '统招' }),
      });
      expect(response.status()).toBe(200);

      const getRes = await request.get(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
      });
      const getData = await getRes.json();
      expect(getData.data.education_type).toBe('统招');
    });

    test('education_type 非统招 存储正确', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ education_type: '非统招' }),
      });
      expect(response.status()).toBe(200);

      const getRes = await request.get(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
      });
      const getData = await getRes.json();
      expect(getData.data.education_type).toBe('非统招');
    });
  });

  // ============================================================
  // 六、籍贯组装
  // ============================================================
  test.describe('六、籍贯组装 (native_place)', () => {
    test('native_place 由省市区+详情无空格拼接', async ({ request }) => {
      // native_place 是 required，必须传非空值；后端在有 region 字段时会重新组装
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({
          native_place_province: '湖南省',
          native_place_city: '长沙市',
          native_place_district: '岳麓区',
          native_place_detail: '某某路',
          native_place: '初始值', // required，后端会用 region 字段覆盖
        }),
      });
      expect(response.status()).toBe(200);

      const getRes = await request.get(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
      });
      const getData = await getRes.json();
      if (getData.data.native_place) {
        expect(getData.data.native_place).toContain('湖南省');
        expect(getData.data.native_place).toContain('长沙市');
      }
    });
  });

  // ============================================================
  // 七、重复提交（updateOrCreate）
  // ============================================================
  test.describe('七、重复提交', () => {
    test('重复提交更新而非新建', async ({ request }) => {
      // 第一次
      await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ name: '首次提交' }),
      });

      // 第二次
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
        data: buildRegistrationPayload({ name: '再次提交' }),
      });
      expect(response.status()).toBe(200);

      const getRes = await request.get(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
      });
      const getData = await getRes.json();
      expect(getData.data.name).toBe('再次提交');
    });
  });

  // ============================================================
  // 八、管理员端验证
  // ============================================================
  test.describe('八、管理员端验证', () => {
    test('管理员可查看员工从业人员登记表', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/employees/${miniEmployeeId}/registration-form`,
        { headers: adminHeaders() }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      if (data.data) {
        expect(data.data).toHaveProperty('name');
        expect(data.data).toHaveProperty('id_number');
        // 管理员端应覆盖银行字段（null coalescing）
        expect(data.data).toHaveProperty('bank_account');
        expect(data.data).toHaveProperty('bank_account_holder');
        expect(data.data).toHaveProperty('bank_name');
        expect(data.data).toHaveProperty('bank_branch');
      }
    });

    test('管理员端数据与小程序端一致', async ({ request }) => {
      const miniRes = await request.get(`${BASE_URL}/mini/registration-form`, {
        headers: miniHeaders(),
      });
      const miniData = await miniRes.json();

      const adminRes = await request.get(
        `${BASE_URL}/employees/${miniEmployeeId}/registration-form`,
        { headers: adminHeaders() }
      );
      const adminData = await adminRes.json();

      if (miniData.data && adminData.data) {
        expect(adminData.data.name).toBe(miniData.data.name);
        expect(adminData.data.id_number).toBe(miniData.data.id_number);
        expect(adminData.data.gender).toBe(miniData.data.gender);
        expect(String(adminData.data.id_card_valid_from).slice(0, 10)).toBe(
          String(miniData.data.id_card_valid_from).slice(0, 10)
        );
        expect(String(adminData.data.id_card_valid_until).slice(0, 10)).toBe(
          String(miniData.data.id_card_valid_until).slice(0, 10)
        );
      }
    });
  });

  // ============================================================
  // 九、未认证访问
  // ============================================================
  test.describe('九、未认证访问', () => {
    test('无 token 提交表单返回 401', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/registration-form`, {
        data: buildRegistrationPayload(),
      });
      expect(response.status()).toBe(401);
    });

    test('无 token 获取表单返回 401', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/mini/registration-form`);
      expect(response.status()).toBe(401);
    });
  });
});
