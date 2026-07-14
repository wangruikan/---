# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: personnel-change-requests.spec.ts >> E. 完成提交接口 >> E2. rejected 状态提交成功
- Location: tests\api\personnel-change-requests.spec.ts:630:7

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: 200
Received: 400
```

# Test source

```ts
  536 |       expect(res.body.data.file_path).toContain(`personnel_change_requests/${ID.UPLOAD_TARGET}/`);
  537 |     }
  538 |   });
  539 | 
  540 |   test('D7. 上传后详情 attachments 数量增加', async ({ request }) => {
  541 |     const api = new PersonnelChangeRequestApi(request, authToken);
  542 |     const before = await api.show(ID.UPLOAD_TARGET);
  543 |     const countBefore = before.body.data.attachments.length;
  544 |     await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'detail-inc.txt');
  545 |     const after = await api.show(ID.UPLOAD_TARGET);
  546 |     expect(after.body.data.attachments.length).toBe(countBefore + 1);
  547 |   });
  548 | 
  549 |   test('D8. 上传后列表 attachment_count 同步增加', async ({ request }) => {
  550 |     const api = new PersonnelChangeRequestApi(request, authToken);
  551 |     const before = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 100 });
  552 |     const itemBefore = before.body.data.data.find((r: any) => r.id === Number(ID.UPLOAD_TARGET));
  553 |     const countBefore = itemBefore?.attachment_count ?? 0;
  554 |     await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'list-count.txt');
  555 |     const after = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 100 });
  556 |     const itemAfter = after.body.data.data.find((r: any) => r.id === Number(ID.UPLOAD_TARGET));
  557 |     expect(itemAfter?.attachment_count).toBe(countBefore + 1);
  558 |   });
  559 | 
  560 |   test('D9. 缺少 file 返回 422', async ({ request }) => {
  561 |     const api = new PersonnelChangeRequestApi(request, authToken);
  562 |     const res = await api.uploadAttachmentRaw({ personnel_change_request_id: ID.UPLOAD_TARGET });
  563 |     expect(res.status).toBe(422);
  564 |     assertFail(res.body);
  565 |   });
  566 | 
  567 |   test('D10. 缺少 personnel_change_request_id 返回 422', async ({ request }) => {
  568 |     const api = new PersonnelChangeRequestApi(request, authToken);
  569 |     const res = await api.uploadAttachmentRaw({
  570 |       file: { name: 'no-id.txt', mimeType: 'text/plain', buffer: smallFile() },
  571 |     });
  572 |     expect(res.status).toBe(422);
  573 |     assertFail(res.body);
  574 |   });
  575 | 
  576 |   test('D11. personnel_change_request_id 不存在返回 422', async ({ request }) => {
  577 |     const api = new PersonnelChangeRequestApi(request, authToken);
  578 |     const res = await api.uploadAttachment('999999', smallFile(), 'not-exist.txt');
  579 |     expect(res.status).toBe(422);
  580 |     assertFail(res.body);
  581 |   });
  582 | 
  583 |   test('D12. 上传超过 50MB 文件返回 422', async ({ request }) => {
  584 |     const api = new PersonnelChangeRequestApi(request, authToken);
  585 |     const bigBuffer = Buffer.alloc(51 * 1024 * 1024 + 1, 'x');
  586 |     const res = await api.uploadAttachment(ID.UPLOAD_TARGET, bigBuffer, 'big-file.bin');
  587 |     expect([413, 422]).toContain(res.status);
  588 |     if (res.body) assertFail(res.body);
  589 |   });
  590 | 
  591 |   test('D13. 给其他账套 request_id 上传附件', async ({ request }) => {
  592 |     const api = new PersonnelChangeRequestApi(request, authToken);
  593 |     const res = await api.uploadAttachment(ID.OTHER_ACCOUNT, smallFile(), 'cross-acct.txt');
  594 |     if (res.status === 200) {
  595 |       console.error('🚨 跨账套越权：upload-attachment 可对其他账套 request_id 操作');
  596 |       assertSuccess(res.body);
  597 |     }
  598 |   });
  599 | 
  600 |   test('D14. 给 in_approval 状态上传附件 - 记录当前行为', async ({ request }) => {
  601 |     const api = new PersonnelChangeRequestApi(request, authToken);
  602 |     const res = await api.uploadAttachment(ID.IN_APPROVAL_ADD, smallFile(), 'in-approval.txt');
  603 |     console.log(`in_approval 上传附件: status=${res.status}, success=${res.body?.success}`);
  604 |   });
  605 | 
  606 |   test('D15. 给 approved 状态上传附件 - 记录当前行为', async ({ request }) => {
  607 |     const api = new PersonnelChangeRequestApi(request, authToken);
  608 |     const res = await api.uploadAttachment(ID.APPROVED_REMOVE, smallFile(), 'approved.txt');
  609 |     console.log(`approved 上传附件: status=${res.status}, success=${res.body?.success}`);
  610 |   });
  611 | });
  612 | 
  613 | // ============================================================
  614 | // E. 完成提交接口 POST /api/personnel-change-requests/complete-submission
  615 | // ============================================================
  616 | test.describe('E. 完成提交接口', () => {
  617 |   test('E1. pending 状态提交成功', async ({ request }) => {
  618 |     const api = new PersonnelChangeRequestApi(request, authToken);
  619 |     const res = await api.completeSubmission({
  620 |       personnel_change_request_id: ID.SUBMIT_PENDING,
  621 |       current_account_set_id: ACCOUNT_SET_A,
  622 |     });
  623 |     expect(res.status).toBe(200);
  624 |     assertSuccess(res.body);
  625 |     expect(res.body.message).toContain('提交');
  626 |     assertJsonHeaders(res.headers);
  627 |     assertResponseTime(res.elapsed);
  628 |   });
  629 | 
  630 |   test('E2. rejected 状态提交成功', async ({ request }) => {
  631 |     const api = new PersonnelChangeRequestApi(request, authToken);
  632 |     const res = await api.completeSubmission({
  633 |       personnel_change_request_id: ID.SUBMIT_REJECTED,
  634 |       current_account_set_id: ACCOUNT_SET_A,
  635 |     });
> 636 |     expect(res.status).toBe(200);
      |                        ^ Error: expect(received).toBe(expected) // Object.is equality
  637 |     assertSuccess(res.body);
  638 |   });
  639 | 
  640 |   test('E3. 成功后返回 request 和 approval_instance', async ({ request }) => {
  641 |     const api = new PersonnelChangeRequestApi(request, authToken);
  642 |     // 查询已被 E1 提交的记录
  643 |     const showRes = await api.show(ID.SUBMIT_PENDING);
  644 |     if (showRes.body.data.status !== 'in_approval') { test.skip(); return; }
  645 |     // 验证结构（通过 show 间接验证）
  646 |     expect(showRes.body.data).toHaveProperty('approval_flow_id');
  647 |   });
  648 | 
  649 |   test('E4. 成功后 request.status 变为 in_approval', async ({ request }) => {
  650 |     const api = new PersonnelChangeRequestApi(request, authToken);
  651 |     const res = await api.show(ID.SUBMIT_PENDING);
  652 |     expect(res.body.data.status).toBe('in_approval');
  653 |   });
  654 | 
  655 |   test('E5. 成功后 approval_flow_id 有值', async ({ request }) => {
  656 |     const api = new PersonnelChangeRequestApi(request, authToken);
  657 |     const res = await api.show(ID.SUBMIT_PENDING);
  658 |     expect(res.body.data.approval_flow_id).toBeTruthy();
  659 |     expect(Number(res.body.data.approval_flow_id)).toBeGreaterThan(0);
  660 |   });
  661 | 
  662 |   test('E6. 不传 stamp_method 时默认 online', async ({ request }) => {
  663 |     const api = new PersonnelChangeRequestApi(request, authToken);
  664 |     // 使用一个 pending 记录
  665 |     const before = await api.show(ID.DOUBLE_SUBMIT);
  666 |     if (before.body.data.status !== 'pending') { test.skip(); return; }
  667 |     const res = await api.completeSubmission({
  668 |       personnel_change_request_id: ID.DOUBLE_SUBMIT,
  669 |       current_account_set_id: ACCOUNT_SET_A,
  670 |     });
  671 |     if (res.status === 200 && res.body.data?.approval_instance) {
  672 |       expect(res.body.data.approval_instance.stamp_method).toBe('online');
  673 |     }
  674 |   });
  675 | 
  676 |   test('E7. 显式传 stamp_method=online 成功', async ({ request }) => {
  677 |     const api = new PersonnelChangeRequestApi(request, authToken);
  678 |     const before = await api.show(ID.PENDING_ADD);
  679 |     if (before.body.data.status !== 'pending') { test.skip(); return; }
  680 |     const res = await api.completeSubmission({
  681 |       personnel_change_request_id: ID.PENDING_ADD,
  682 |       current_account_set_id: ACCOUNT_SET_A,
  683 |       stamp_method: 'online',
  684 |     });
  685 |     if (res.status === 200) {
  686 |       assertSuccess(res.body);
  687 |     }
  688 |   });
  689 | 
  690 |   test('E8. 显式传 stamp_method=offline 成功', async ({ request }) => {
  691 |     const api = new PersonnelChangeRequestApi(request, authToken);
  692 |     const before = await api.show(ID.WITH_ATTACHMENT);
  693 |     if (before.body.data.status !== 'pending') { test.skip(); return; }
  694 |     const res = await api.completeSubmission({
  695 |       personnel_change_request_id: ID.WITH_ATTACHMENT,
  696 |       current_account_set_id: ACCOUNT_SET_A,
  697 |       stamp_method: 'offline',
  698 |     });
  699 |     if (res.status === 200) {
  700 |       assertSuccess(res.body);
  701 |     }
  702 |   });
  703 | 
  704 |   test('E9. pending 记录重复提交 - 第二次应失败', async ({ request }) => {
  705 |     const api = new PersonnelChangeRequestApi(request, authToken);
  706 |     // SUBMIT_PENDING 已在 E1 被提交
  707 |     const res = await api.completeSubmission({
  708 |       personnel_change_request_id: ID.SUBMIT_PENDING,
  709 |       current_account_set_id: ACCOUNT_SET_A,
  710 |     });
  711 |     expect(res.status).toBe(400);
  712 |     assertFail(res.body);
  713 |   });
  714 | 
  715 |   test('E10. 对 in_approval 状态提交返回 400', async ({ request }) => {
  716 |     const api = new PersonnelChangeRequestApi(request, authToken);
  717 |     const res = await api.completeSubmission({
  718 |       personnel_change_request_id: ID.IN_APPROVAL_ADD,
  719 |       current_account_set_id: ACCOUNT_SET_A,
  720 |     });
  721 |     expect(res.status).toBe(400);
  722 |     assertFail(res.body);
  723 |   });
  724 | 
  725 |   test('E11. 对 approved 状态提交返回 400', async ({ request }) => {
  726 |     const api = new PersonnelChangeRequestApi(request, authToken);
  727 |     const res = await api.completeSubmission({
  728 |       personnel_change_request_id: ID.APPROVED_REMOVE,
  729 |       current_account_set_id: ACCOUNT_SET_A,
  730 |     });
  731 |     expect(res.status).toBe(400);
  732 |     assertFail(res.body);
  733 |   });
  734 | 
  735 |   test('E12. 缺少 personnel_change_request_id 返回 422', async ({ request }) => {
  736 |     const api = new PersonnelChangeRequestApi(request, authToken);
```