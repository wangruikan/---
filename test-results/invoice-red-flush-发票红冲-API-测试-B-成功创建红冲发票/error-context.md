# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: invoice-red-flush.spec.ts >> 发票红冲 API 测试 >> B. 成功创建红冲发票
- Location: tests\api\invoice-red-flush.spec.ts:177:7

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: 37
Received: undefined
```

# Test source

```ts
  120 |     const searchRes = await request.get(`${BASE_URL}/invoice-applications/red-flush-candidates`, {
  121 |       headers: { Authorization: `Bearer ${authToken}` },
  122 |       params: {
  123 |         current_account_set_id: ACCOUNT_SET_ID,
  124 |         before_year: 2026,
  125 |         before_month: 6,
  126 |         keyword: keyword,
  127 |       },
  128 |     });
  129 | 
  130 |     const searchData = await searchRes.json();
  131 |     console.log(`keyword="${keyword}" 搜索结果数量:`, searchData.data?.length || 0);
  132 |     expect(searchRes.status()).toBe(200);
  133 |     expect(searchData.data.length).toBeGreaterThan(0);
  134 | 
  135 |     // 验证搜索结果中包含匹配的记录
  136 |     const found = searchData.data.some((item: any) =>
  137 |       item.application_no?.includes(keyword) ||
  138 |       item.company_name?.includes(keyword) ||
  139 |       item.invoice_number?.includes(keyword) ||
  140 |       item.project_name?.includes(keyword)
  141 |     );
  142 |     expect(found).toBe(true);
  143 |   });
  144 | 
  145 |   // 准备测试数据：找到一个可用的源发票
  146 |   test('准备: 找到可用的红冲源发票', async ({ request }) => {
  147 |     const response = await request.get(`${BASE_URL}/invoice-applications/red-flush-candidates`, {
  148 |       headers: { Authorization: `Bearer ${authToken}` },
  149 |       params: {
  150 |         current_account_set_id: ACCOUNT_SET_ID,
  151 |         before_year: 2026,
  152 |         before_month: 6,
  153 |       },
  154 |     });
  155 | 
  156 |     const data = await response.json();
  157 |     console.log('可用候选数量:', data.data?.length || 0);
  158 | 
  159 |     if (data.data && data.data.length > 0) {
  160 |       sourceInvoiceId = data.data[0].id;
  161 |       console.log('选中的源发票 ID:', sourceInvoiceId);
  162 |       console.log('源发票详情:', {
  163 |         application_no: data.data[0].application_no,
  164 |         year: data.data[0].year,
  165 |         month: data.data[0].month,
  166 |         status: data.data[0].status,
  167 |         approval_status: data.data[0].approval_status,
  168 |         is_completed: data.data[0].is_completed,
  169 |         invoice_number: data.data[0].invoice_number,
  170 |       });
  171 |     } else {
  172 |       console.log('警告: 没有可用的红冲候选发票，后续测试可能跳过');
  173 |     }
  174 |   });
  175 | 
  176 |   // 测试 B: 成功创建红冲发票
  177 |   test('B. 成功创建红冲发票', async ({ request }) => {
  178 |     if (!sourceInvoiceId) {
  179 |       console.log('跳过: 没有可用的源发票');
  180 |       return;
  181 |     }
  182 | 
  183 |     // 先获取源发票详情
  184 |     const sourceRes = await request.get(`${BASE_URL}/invoice-applications/${sourceInvoiceId}`, {
  185 |       headers: { Authorization: `Bearer ${authToken}` },
  186 |     });
  187 |     const sourceData = await sourceRes.json();
  188 |     const source = sourceData.data;
  189 |     console.log('源发票详情:', {
  190 |       id: source.id,
  191 |       year: source.year,
  192 |       month: source.month,
  193 |       status: source.status,
  194 |     });
  195 | 
  196 |     // 创建红冲发票：年月必须大于源发票
  197 |     const redFlushYear = source.year === 2026 ? 2026 : source.year + 1;
  198 |     const redFlushMonth = source.year === 2026 ? source.month + 1 : 1;
  199 | 
  200 |     const createRes = await request.post(`${BASE_URL}/invoice-applications`, {
  201 |       headers: { Authorization: `Bearer ${authToken}` },
  202 |       data: {
  203 |         task_name: `红冲-${source.application_no}`,
  204 |         year: redFlushYear,
  205 |         month: redFlushMonth,
  206 |         project_name: source.project_name || '红冲测试',
  207 |         status: 'red_flushed',
  208 |         red_flush_source_id: sourceInvoiceId,
  209 |         current_account_set_id: ACCOUNT_SET_ID,
  210 |       },
  211 |     });
  212 | 
  213 |     console.log('创建红冲发票响应状态:', createRes.status());
  214 |     const createData = await createRes.json();
  215 |     console.log('创建红冲发票响应:', createData);
  216 | 
  217 |     expect(createRes.status()).toBe(200);
  218 |     expect(createData.success).toBe(true);
  219 |     expect(createData.data.status).toBe('red_flushed');
> 220 |     expect(createData.data.red_flush_source_id).toBe(sourceInvoiceId);
      |                                                 ^ Error: expect(received).toBe(expected) // Object.is equality
  221 | 
  222 |     redFlushedInvoiceId = createData.data.id;
  223 |     console.log('红冲发票创建成功，ID:', redFlushedInvoiceId);
  224 |   });
  225 | 
  226 |   // 测试 C: 回查源发票状态已变成 red_flushed
  227 |   test('C. 源发票状态已变成 red_flushed', async ({ request }) => {
  228 |     if (!sourceInvoiceId) {
  229 |       console.log('跳过: 没有源发票');
  230 |       return;
  231 |     }
  232 | 
  233 |     const response = await request.get(`${BASE_URL}/invoice-applications/${sourceInvoiceId}`, {
  234 |       headers: { Authorization: `Bearer ${authToken}` },
  235 |     });
  236 | 
  237 |     const data = await response.json();
  238 |     console.log('源发票当前状态:', data.data.status);
  239 |     expect(data.data.status).toBe('red_flushed');
  240 |   });
  241 | 
  242 |   // 测试 D: 再次对同一个 source 发起红冲，应失败
  243 |   test('D. 重复红冲同一源发票应失败', async ({ request }) => {
  244 |     if (!sourceInvoiceId) {
  245 |       console.log('跳过: 没有源发票');
  246 |       return;
  247 |     }
  248 | 
  249 |     const response = await request.post(`${BASE_URL}/invoice-applications`, {
  250 |       headers: { Authorization: `Bearer ${authToken}` },
  251 |       data: {
  252 |         task_name: '重复红冲测试',
  253 |         year: 2026,
  254 |         month: 7,
  255 |         project_name: '测试项目',
  256 |         status: 'red_flushed',
  257 |         red_flush_source_id: sourceInvoiceId,
  258 |         current_account_set_id: ACCOUNT_SET_ID,
  259 |       },
  260 |     });
  261 | 
  262 |     console.log('重复红冲响应状态:', response.status());
  263 |     const data = await response.json();
  264 |     console.log('重复红冲响应:', data);
  265 | 
  266 |     expect(response.status()).toBe(422);
  267 |     expect(data.success).toBe(false);
  268 |     expect(data.message).toBe('该发票已经是红冲状态');
  269 |   });
  270 | 
  271 |   // 测试 E: 缺少 red_flush_source_id 时应返回 422
  272 |   test('E. 缺少 red_flush_source_id 应返回 422', async ({ request }) => {
  273 |     const response = await request.post(`${BASE_URL}/invoice-applications`, {
  274 |       headers: { Authorization: `Bearer ${authToken}` },
  275 |       data: {
  276 |         task_name: '缺少source测试',
  277 |         year: 2026,
  278 |         month: 7,
  279 |         project_name: '测试项目',
  280 |         status: 'red_flushed',
  281 |         // 故意不传 red_flush_source_id
  282 |         current_account_set_id: ACCOUNT_SET_ID,
  283 |       },
  284 |     });
  285 | 
  286 |     console.log('缺少source响应状态:', response.status());
  287 |     const data = await response.json();
  288 |     console.log('缺少source响应:', data);
  289 | 
  290 |     expect(response.status()).toBe(422);
  291 |     expect(data.success).toBe(false);
  292 |     expect(data.message).toBe('请选择需要红冲的发票');
  293 |   });
  294 | 
  295 |   // 测试 F: 使用当前月 source 或未来月 source 时应返回 422
  296 |   test('F. 使用当前月或未来月 source 应返回 422', async ({ request }) => {
  297 |     // 获取一个年月 >= 2026-06 的发票作为 source
  298 |     const candidatesRes = await request.get(`${BASE_URL}/invoice-applications`, {
  299 |       headers: { Authorization: `Bearer ${authToken}` },
  300 |       params: {
  301 |         current_account_set_id: ACCOUNT_SET_ID,
  302 |         per_page: 100,
  303 |       },
  304 |     });
  305 |     const candidatesData = await candidatesRes.json();
  306 | 
  307 |     // 找一个年月 >= 2026-06 的发票
  308 |     const futureInvoice = candidatesData.data?.data?.find(
  309 |       (item: any) => item.year > 2026 || (item.year === 2026 && item.month >= 6)
  310 |     );
  311 | 
  312 |     if (!futureInvoice) {
  313 |       console.log('跳过: 没有当前月或未来月的发票');
  314 |       return;
  315 |     }
  316 | 
  317 |     console.log('使用发票作为source:', {
  318 |       id: futureInvoice.id,
  319 |       year: futureInvoice.year,
  320 |       month: futureInvoice.month,
```