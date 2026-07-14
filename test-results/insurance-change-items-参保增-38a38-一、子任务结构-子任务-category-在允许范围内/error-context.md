# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: insurance-change-items.spec.ts >> 参保增减 - 子任务分险种确认 >> 一、子任务结构 >> 子任务 category 在允许范围内
- Location: tests\api\insurance-change-items.spec.ts:137:9

# Error details

```
Error: expect(received).toContain(expected) // indexOf

Expected value: "other_policy:11"
Received array: ["social_security", "medical_insurance", "housing_fund", "large_medical_insurance", "other_insurance"]
```

# Test source

```ts
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
  96  | const TEST_PDF_PATH = path.join(__dirname, 'test-document.pdf');
  97  | 
  98  | test.describe('参保增减 - 子任务分险种确认', () => {
  99  |   test.beforeAll(async ({ request }) => {
  100 |     const { token, userId } = await login(request, USERNAME, PASSWORD);
  101 |     authToken = token;
  102 |     currentUserId = userId;
  103 |   });
  104 | 
  105 |   // ============================================================
  106 |   // 一、子任务结构
  107 |   // ============================================================
  108 |   test.describe('一、子任务结构', () => {
  109 |     test('子任务返回正确字段', async ({ request }) => {
  110 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  111 |         headers: authHeaders(),
  112 |         params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
  113 |       });
  114 |       const listData = await listRes.json();
  115 |       const withItems = listData.data.find(
  116 |         (item: any) => item.change_items && item.change_items.length > 0
  117 |       );
  118 |       if (!withItems) {
  119 |         test.skip();
  120 |         return;
  121 |       }
  122 | 
  123 |       const response = await request.get(`${BASE_URL}/insurance-changes/${withItems.id}/items`, {
  124 |         headers: authHeaders(),
  125 |       });
  126 |       const data = await response.json();
  127 |       expect(data.data.length).toBeGreaterThan(0);
  128 | 
  129 |       const item = data.data[0];
  130 |       expect(item).toHaveProperty('id');
  131 |       expect(item).toHaveProperty('insurance_change_id');
  132 |       expect(item).toHaveProperty('category');
  133 |       expect(item).toHaveProperty('status');
  134 |       expect(item).toHaveProperty('change_type');
  135 |     });
  136 | 
  137 |     test('子任务 category 在允许范围内', async ({ request }) => {
  138 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  139 |         headers: authHeaders(),
  140 |         params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
  141 |       });
  142 |       const listData = await listRes.json();
  143 |       const withItems = listData.data.find(
  144 |         (item: any) => item.change_items && item.change_items.length > 0
  145 |       );
  146 |       if (!withItems) {
  147 |         test.skip();
  148 |         return;
  149 |       }
  150 | 
  151 |       const response = await request.get(`${BASE_URL}/insurance-changes/${withItems.id}/items`, {
  152 |         headers: authHeaders(),
  153 |       });
  154 |       const data = await response.json();
  155 |       const allowed = [
  156 |         'social_security',
  157 |         'medical_insurance',
  158 |         'housing_fund',
  159 |         'large_medical_insurance',
  160 |         'other_insurance',
  161 |       ];
  162 |       for (const item of data.data) {
> 163 |         expect(allowed).toContain(item.category);
      |                         ^ Error: expect(received).toContain(expected) // indexOf
  164 |       }
  165 |     });
  166 | 
  167 |     test('子任务 status 在允许范围内', async ({ request }) => {
  168 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  169 |         headers: authHeaders(),
  170 |         params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
  171 |       });
  172 |       const listData = await listRes.json();
  173 |       const withItems = listData.data.find(
  174 |         (item: any) => item.change_items && item.change_items.length > 0
  175 |       );
  176 |       if (!withItems) {
  177 |         test.skip();
  178 |         return;
  179 |       }
  180 | 
  181 |       const response = await request.get(`${BASE_URL}/insurance-changes/${withItems.id}/items`, {
  182 |         headers: authHeaders(),
  183 |       });
  184 |       const data = await response.json();
  185 |       const allowedStatuses = ['pending', 'submitted', 'completed'];
  186 |       for (const item of data.data) {
  187 |         expect(allowedStatuses).toContain(item.status);
  188 |       }
  189 |     });
  190 |   });
  191 | 
  192 |   // ============================================================
  193 |   // 二、按分类确认
  194 |   // ============================================================
  195 |   test.describe('二、按分类确认', () => {
  196 |     test('按分类确认需要该分类的附件', async ({ request }) => {
  197 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  198 |         headers: authHeaders(),
  199 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 10 },
  200 |       });
  201 |       const listData = await listRes.json();
  202 |       const submitted = listData.data.find(
  203 |         (item: any) => item.change_items && item.change_items.length > 0
  204 |       );
  205 |       if (!submitted) {
  206 |         test.skip();
  207 |         return;
  208 |       }
  209 | 
  210 |       const itemsRes = await request.get(
  211 |         `${BASE_URL}/insurance-changes/${submitted.id}/items`,
  212 |         { headers: authHeaders() }
  213 |       );
  214 |       const itemsData = await itemsRes.json();
  215 |       const pendingItem = itemsData.data.find((item: any) => item.status === 'pending');
  216 |       if (!pendingItem) {
  217 |         test.skip();
  218 |         return;
  219 |       }
  220 | 
  221 |       const response = await request.put(
  222 |         `${BASE_URL}/insurance-changes/${submitted.id}/confirm-process`,
  223 |         { headers: authHeaders(), data: { category: pendingItem.category } }
  224 |       );
  225 |       if (response.status() === 400) {
  226 |         const data = await response.json();
  227 |         expect(data.success).toBe(false);
  228 |       }
  229 |     });
  230 | 
  231 |     test('不存在的分类应返回失败', async ({ request }) => {
  232 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  233 |         headers: authHeaders(),
  234 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 1 },
  235 |       });
  236 |       const listData = await listRes.json();
  237 |       if (listData.data.length === 0) {
  238 |         test.skip();
  239 |         return;
  240 |       }
  241 | 
  242 |       const id = listData.data[0].id;
  243 |       const response = await request.put(
  244 |         `${BASE_URL}/insurance-changes/${id}/confirm-process`,
  245 |         { headers: authHeaders(), data: { category: 'nonexistent_category' } }
  246 |       );
  247 |       expect([400, 422]).toContain(response.status());
  248 |     });
  249 | 
  250 |     test('confirm-other-insurance-only 接口可用', async ({ request }) => {
  251 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  252 |         headers: authHeaders(),
  253 |         params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
  254 |       });
  255 |       const listData = await listRes.json();
  256 |       const withOtherInsurance = listData.data.find(
  257 |         (item: any) => item.other_insurance_policies && item.status !== 'completed'
  258 |       );
  259 |       if (!withOtherInsurance) {
  260 |         test.skip();
  261 |         return;
  262 |       }
  263 | 
```