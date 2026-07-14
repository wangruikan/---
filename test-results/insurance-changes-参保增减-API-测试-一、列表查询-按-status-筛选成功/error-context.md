# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: insurance-changes.spec.ts >> 参保增减 API 测试 >> 一、列表查询 >> 按 status 筛选成功
- Location: tests\api\insurance-changes.spec.ts:153:9

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: "pending"
Received: "submitted"
```

# Test source

```ts
  62  |       parts.push(fileData);
  63  |       parts.push(Buffer.from(`\r\n`));
  64  |     }
  65  | 
  66  |     parts.push(Buffer.from(`--${boundary}--\r\n`));
  67  |     const body = Buffer.concat(parts);
  68  | 
  69  |     const url = new URL(`${BASE_URL}${urlPath}`);
  70  |     const req = http.request(
  71  |       {
  72  |         hostname: '127.0.0.1',
  73  |         port: url.port,
  74  |         path: url.pathname,
  75  |         method: 'POST',
  76  |         headers: {
  77  |           'Content-Type': `multipart/form-data; boundary=${boundary}`,
  78  |           Authorization: `Bearer ${token}`,
  79  |           'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  80  |           'Content-Length': body.length,
  81  |         },
  82  |       },
  83  |       (res) => {
  84  |         let data = '';
  85  |         res.on('data', (chunk) => (data += chunk));
  86  |         res.on('end', () => resolve({ status: res.statusCode!, body: data }));
  87  |       }
  88  |     );
  89  |     req.on('error', reject);
  90  |     req.write(body);
  91  |     req.end();
  92  |   });
  93  | }
  94  | 
  95  | const TEST_PNG_PATH = path.join(__dirname, 'test-image.png');
  96  | if (!fs.existsSync(TEST_PNG_PATH)) {
  97  |   fs.writeFileSync(
  98  |     TEST_PNG_PATH,
  99  |     Buffer.from(
  100 |       'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  101 |       'base64'
  102 |     )
  103 |   );
  104 | }
  105 | 
  106 | const TEST_PDF_PATH = path.join(__dirname, 'test-document.pdf');
  107 | if (!fs.existsSync(TEST_PDF_PATH)) {
  108 |   fs.writeFileSync(
  109 |     TEST_PDF_PATH,
  110 |     Buffer.from(
  111 |       '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n190\n%%EOF'
  112 |     )
  113 |   );
  114 | }
  115 | 
  116 | test.describe('参保增减 API 测试', () => {
  117 |   test.beforeAll(async ({ request }) => {
  118 |     const { token, userId } = await login(request, USERNAME, PASSWORD);
  119 |     authToken = token;
  120 |     currentUserId = userId;
  121 |     console.log('登录成功，userId:', currentUserId);
  122 |   });
  123 | 
  124 |   // ============================================================
  125 |   // 一、列表查询
  126 |   // ============================================================
  127 |   test.describe('一、列表查询', () => {
  128 |     test('未登录访问列表应返回 401', async ({ request }) => {
  129 |       const response = await request.get(`${BASE_URL}/insurance-changes`, {
  130 |         params: { account_set_id: ACCOUNT_SET_ID },
  131 |       });
  132 |       expect(response.status()).toBe(401);
  133 |     });
  134 | 
  135 |     test('缺少 account_set_id 应返回 422 或 200', async ({ request }) => {
  136 |       const response = await request.get(`${BASE_URL}/insurance-changes`, {
  137 |         headers: authHeaders(),
  138 |       });
  139 |       expect([200, 400, 422]).toContain(response.status());
  140 |     });
  141 | 
  142 |     test('正常获取列表成功', async ({ request }) => {
  143 |       const response = await request.get(`${BASE_URL}/insurance-changes`, {
  144 |         headers: authHeaders(),
  145 |         params: { account_set_id: ACCOUNT_SET_ID },
  146 |       });
  147 |       expect(response.status()).toBe(200);
  148 |       const data = await response.json();
  149 |       expect(data.success).toBe(true);
  150 |       expect(Array.isArray(data.data)).toBe(true);
  151 |     });
  152 | 
  153 |     test('按 status 筛选成功', async ({ request }) => {
  154 |       const response = await request.get(`${BASE_URL}/insurance-changes`, {
  155 |         headers: authHeaders(),
  156 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'pending' },
  157 |       });
  158 |       expect(response.status()).toBe(200);
  159 |       const data = await response.json();
  160 |       expect(data.success).toBe(true);
  161 |       for (const item of data.data) {
> 162 |         expect(item.status).toBe('pending');
      |                             ^ Error: expect(received).toBe(expected) // Object.is equality
  163 |       }
  164 |     });
  165 | 
  166 |     test('按 month 筛选成功', async ({ request }) => {
  167 |       const now = new Date();
  168 |       const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  169 |       const response = await request.get(`${BASE_URL}/insurance-changes`, {
  170 |         headers: authHeaders(),
  171 |         params: { account_set_id: ACCOUNT_SET_ID, month },
  172 |       });
  173 |       expect(response.status()).toBe(200);
  174 |       const data = await response.json();
  175 |       expect(data.success).toBe(true);
  176 |     });
  177 | 
  178 |     test('按 region_name 筛选成功', async ({ request }) => {
  179 |       const response = await request.get(`${BASE_URL}/insurance-changes`, {
  180 |         headers: authHeaders(),
  181 |         params: { account_set_id: ACCOUNT_SET_ID, region_name: '全部' },
  182 |       });
  183 |       expect(response.status()).toBe(200);
  184 |       const data = await response.json();
  185 |       expect(data.success).toBe(true);
  186 |     });
  187 | 
  188 |     test('列表返回字段完整性校验', async ({ request }) => {
  189 |       const response = await request.get(`${BASE_URL}/insurance-changes`, {
  190 |         headers: authHeaders(),
  191 |         params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
  192 |       });
  193 |       const data = await response.json();
  194 |       if (data.data.length === 0) {
  195 |         test.skip();
  196 |         return;
  197 |       }
  198 |       const item = data.data[0];
  199 |       expect(item).toHaveProperty('id');
  200 |       expect(item).toHaveProperty('employee_id');
  201 |       expect(item).toHaveProperty('employee_name');
  202 |       expect(item).toHaveProperty('change_type');
  203 |       expect(item).toHaveProperty('status');
  204 |       expect(item).toHaveProperty('project_id');
  205 |       expect(item).toHaveProperty('account_set_id');
  206 |       expect(['increase', 'decrease']).toContain(item.change_type);
  207 |       expect(['pending', 'processing', 'submitted', 'completed']).toContain(item.status);
  208 |     });
  209 |   });
  210 | 
  211 |   // ============================================================
  212 |   // 二、详情查询
  213 |   // ============================================================
  214 |   test.describe('二、详情查询', () => {
  215 |     test('获取详情成功', async ({ request }) => {
  216 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  217 |         headers: authHeaders(),
  218 |         params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
  219 |       });
  220 |       const listData = await listRes.json();
  221 |       if (listData.data.length === 0) {
  222 |         test.skip();
  223 |         return;
  224 |       }
  225 | 
  226 |       const id = listData.data[0].id;
  227 |       const response = await request.get(`${BASE_URL}/insurance-changes/${id}`, {
  228 |         headers: authHeaders(),
  229 |       });
  230 |       expect(response.status()).toBe(200);
  231 |       const data = await response.json();
  232 |       expect(data.success).toBe(true);
  233 |       expect(data.data.id).toBe(id);
  234 |     });
  235 | 
  236 |     test('详情返回快照字段', async ({ request }) => {
  237 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  238 |         headers: authHeaders(),
  239 |         params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
  240 |       });
  241 |       const listData = await listRes.json();
  242 |       const withInsurance = listData.data.find(
  243 |         (item: any) => item.social_security_region_id || item.medical_insurance_region_id
  244 |       );
  245 |       if (!withInsurance) {
  246 |         test.skip();
  247 |         return;
  248 |       }
  249 | 
  250 |       const response = await request.get(`${BASE_URL}/insurance-changes/${withInsurance.id}`, {
  251 |         headers: authHeaders(),
  252 |       });
  253 |       const data = await response.json();
  254 |       expect(data.data).toHaveProperty('social_security_types');
  255 |       expect(data.data).toHaveProperty('medical_insurance_types');
  256 |       expect(data.data).toHaveProperty('housing_fund_params');
  257 |       expect(data.data).toHaveProperty('other_insurance_policies');
  258 |     });
  259 | 
  260 |     test('获取不存在的详情应返回 404', async ({ request }) => {
  261 |       const response = await request.get(`${BASE_URL}/insurance-changes/999999`, {
  262 |         headers: authHeaders(),
```