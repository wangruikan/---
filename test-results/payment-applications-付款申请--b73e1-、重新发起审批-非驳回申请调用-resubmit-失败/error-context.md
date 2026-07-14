# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: payment-applications.spec.ts >> 付款申请 API 测试 >> 四、重新发起审批 >> 非驳回申请调用 resubmit 失败
- Location: tests\api\payment-applications.spec.ts:599:9

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: "只有被驳回的申请才能重新发起审批"
Received: "工资或汇总付款申请请回原模块重新发起"
```

# Test source

```ts
  525 |         params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
  526 |       });
  527 |       const listData = await listRes.json();
  528 |       const withAttachment = listData.data.data.find(
  529 |         (item: any) =>
  530 |           (item.status === 'draft' || item.status === 'pending') &&
  531 |           !item.approval_instance &&
  532 |           item.attachments_count > 0
  533 |       );
  534 |       if (!withAttachment) {
  535 |         console.log('跳过: 没有带附件的可提交申请');
  536 |         test.skip();
  537 |         return;
  538 |       }
  539 | 
  540 |       const response = await request.post(`${BASE_URL}/payment-applications/${withAttachment.id}/submit`, {
  541 |         headers: authHeaders(),
  542 |         data: {
  543 |           stamp_selection_mode: 'stamp',
  544 |           stamp_method: 'online',
  545 |           stamp_id: 999999,
  546 |           stamp_company: '不存在的公司',
  547 |           stamp_type: 'official',
  548 |           current_account_set_id: ACCOUNT_SET_ID,
  549 |         },
  550 |       });
  551 |       expect(response.status()).toBe(422);
  552 |       const data = await response.json();
  553 |       expect(data.success).toBe(false);
  554 |       expect(data.message).toBe('所选公司印章不存在，请重新选择');
  555 |     });
  556 | 
  557 |     test('stamp_selection_mode=none 时可正常提交', async ({ request }) => {
  558 |       // 找一个有附件的 draft/pending 且无审批实例的申请
  559 |       const listRes = await request.get(`${BASE_URL}/payment-applications`, {
  560 |         headers: authHeaders(),
  561 |         params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
  562 |       });
  563 |       const listData = await listRes.json();
  564 |       const submittable = listData.data.data.find(
  565 |         (item: any) =>
  566 |           (item.status === 'draft' || item.status === 'pending') &&
  567 |           !item.approval_instance &&
  568 |           item.attachments_count > 0
  569 |       );
  570 |       if (!submittable) {
  571 |         console.log('跳过: 没有带附件的可提交申请');
  572 |         test.skip();
  573 |         return;
  574 |       }
  575 | 
  576 |       const response = await request.post(`${BASE_URL}/payment-applications/${submittable.id}/submit`, {
  577 |         headers: authHeaders(),
  578 |         data: {
  579 |           stamp_selection_mode: 'none',
  580 |           stamp_method: 'online',
  581 |           payment_method: 'transfer',
  582 |           current_account_set_id: ACCOUNT_SET_ID,
  583 |         },
  584 |       });
  585 |       expect(response.status()).toBe(200);
  586 |       const data = await response.json();
  587 |       expect(data.success).toBe(true);
  588 |       expect(data.message).toBe('付款申请已提交审批');
  589 |       // 验证状态变化
  590 |       expect(data.data.status).toBe('pending');
  591 |       expect(data.data.approval_instance_id).toBeTruthy();
  592 |     });
  593 |   });
  594 | 
  595 |   // ============================================================
  596 |   // 四、重新发起审批
  597 |   // ============================================================
  598 |   test.describe('四、重新发起审批', () => {
  599 |     test('非驳回申请调用 resubmit 失败', async ({ request }) => {
  600 |       // 找一个非 rejected 状态的申请
  601 |       const listRes = await request.get(`${BASE_URL}/payment-applications`, {
  602 |         headers: authHeaders(),
  603 |         params: { current_account_set_id: ACCOUNT_SET_ID, per_page: 100 },
  604 |       });
  605 |       const listData = await listRes.json();
  606 |       const nonRejected = listData.data.data.find(
  607 |         (item: any) => item.status !== 'rejected'
  608 |       );
  609 |       if (!nonRejected) {
  610 |         test.skip();
  611 |         return;
  612 |       }
  613 | 
  614 |       const response = await request.post(`${BASE_URL}/payment-applications/${nonRejected.id}/resubmit`, {
  615 |         headers: authHeaders(),
  616 |         data: {
  617 |           stamp_method: 'online',
  618 |           stamp_selection_mode: 'none',
  619 |           current_account_set_id: ACCOUNT_SET_ID,
  620 |         },
  621 |       });
  622 |       expect(response.status()).toBe(400);
  623 |       const data = await response.json();
  624 |       expect(data.success).toBe(false);
> 625 |       expect(data.message).toBe('只有被驳回的申请才能重新发起审批');
      |                            ^ Error: expect(received).toBe(expected) // Object.is equality
  626 |     });
  627 | 
  628 |     test('驳回的工资申请 resubmit 失败，提示回原模块', async ({ request }) => {
  629 |       const listRes = await request.get(`${BASE_URL}/payment-applications`, {
  630 |         headers: authHeaders(),
  631 |         params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'salary', per_page: 100 },
  632 |       });
  633 |       const listData = await listRes.json();
  634 |       const rejectedSalary = listData.data.data.find((item: any) => item.status === 'rejected');
  635 |       if (!rejectedSalary) {
  636 |         console.log('跳过: 没有被驳回的工资申请');
  637 |         test.skip();
  638 |         return;
  639 |       }
  640 | 
  641 |       const response = await request.post(`${BASE_URL}/payment-applications/${rejectedSalary.id}/resubmit`, {
  642 |         headers: authHeaders(),
  643 |         data: {
  644 |           stamp_method: 'online',
  645 |           stamp_selection_mode: 'none',
  646 |           current_account_set_id: ACCOUNT_SET_ID,
  647 |         },
  648 |       });
  649 |       expect(response.status()).toBe(400);
  650 |       const data = await response.json();
  651 |       expect(data.success).toBe(false);
  652 |       expect(data.message).toBe('工资或汇总付款申请请回原模块重新发起');
  653 |     });
  654 | 
  655 |     test('驳回的保险申请 resubmit 失败，提示回原模块', async ({ request }) => {
  656 |       const listRes = await request.get(`${BASE_URL}/payment-applications`, {
  657 |         headers: authHeaders(),
  658 |         params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'insurance', per_page: 100 },
  659 |       });
  660 |       const listData = await listRes.json();
  661 |       const rejectedInsurance = listData.data.data.find((item: any) => item.status === 'rejected');
  662 |       if (!rejectedInsurance) {
  663 |         console.log('跳过: 没有被驳回的保险申请');
  664 |         test.skip();
  665 |         return;
  666 |       }
  667 | 
  668 |       const response = await request.post(`${BASE_URL}/payment-applications/${rejectedInsurance.id}/resubmit`, {
  669 |         headers: authHeaders(),
  670 |         data: {
  671 |           stamp_method: 'online',
  672 |           stamp_selection_mode: 'none',
  673 |           current_account_set_id: ACCOUNT_SET_ID,
  674 |         },
  675 |       });
  676 |       expect(response.status()).toBe(400);
  677 |       const data = await response.json();
  678 |       expect(data.success).toBe(false);
  679 |       expect(data.message).toBe('工资或汇总付款申请请回原模块重新发起');
  680 |     });
  681 | 
  682 |     test('驳回的报销类申请可以 resubmit 成功', async ({ request }) => {
  683 |       const listRes = await request.get(`${BASE_URL}/payment-applications`, {
  684 |         headers: authHeaders(),
  685 |         params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'reimbursement', per_page: 100 },
  686 |       });
  687 |       const listData = await listRes.json();
  688 |       const rejectedReimbursement = listData.data.data.find(
  689 |         (item: any) => item.status === 'rejected'
  690 |       );
  691 |       if (!rejectedReimbursement) {
  692 |         console.log('跳过: 没有被驳回的报销申请');
  693 |         test.skip();
  694 |         return;
  695 |       }
  696 | 
  697 |       const response = await request.post(
  698 |         `${BASE_URL}/payment-applications/${rejectedReimbursement.id}/resubmit`,
  699 |         {
  700 |           headers: authHeaders(),
  701 |           data: {
  702 |             stamp_method: 'online',
  703 |             stamp_selection_mode: 'none',
  704 |             payment_method: 'transfer',
  705 |             current_account_set_id: ACCOUNT_SET_ID,
  706 |           },
  707 |         }
  708 |       );
  709 |       expect(response.status()).toBe(200);
  710 |       const data = await response.json();
  711 |       expect(data.success).toBe(true);
  712 |       expect(data.message).toBe('重新发起审批成功');
  713 |       expect(data.data.status).toBe('pending');
  714 |     });
  715 | 
  716 |     test('resubmit 时若没有附件应失败', async ({ request }) => {
  717 |       // 找一个被驳回且无附件的报销申请
  718 |       const listRes = await request.get(`${BASE_URL}/payment-applications`, {
  719 |         headers: authHeaders(),
  720 |         params: { current_account_set_id: ACCOUNT_SET_ID, payment_type: 'reimbursement', per_page: 100 },
  721 |       });
  722 |       const listData = await listRes.json();
  723 |       const rejectedNoAttach = listData.data.data.find(
  724 |         (item: any) => item.status === 'rejected' && item.attachments_count === 0
  725 |       );
```