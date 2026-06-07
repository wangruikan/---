<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function resolveApprovalStampOptions(Request $request, $accountSetId): array
    {
        $validator = Validator::make($request->all(), [
            'stamp_selection_mode' => 'nullable|in:stamp,none',
            'stamp_company' => 'nullable|string|max:100',
            'stamp_type' => 'nullable|in:bank,cash,official,finance,contract,legal_person,business,hr',
            'stamp_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors(),
            ], 422));
        }

        $options = [
            'stamp_selection_mode' => $request->input('stamp_selection_mode', 'none'),
            'stamp_company' => $request->input('stamp_company'),
            'stamp_type' => $request->input('stamp_type'),
            'stamp_id' => $request->input('stamp_id'),
        ];

        if ($options['stamp_selection_mode'] !== 'stamp') {
            return [
                'stamp_selection_mode' => 'none',
                'stamp_company' => null,
                'stamp_type' => null,
                'stamp_id' => null,
            ];
        }

        $stamp = \App\Models\UserBankStamp::where('id', $options['stamp_id'])
            ->where('account_set_id', $accountSetId)
            ->where('company', $options['stamp_company'])
            ->where('type', $options['stamp_type'])
            ->first();

        if (!$stamp) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => '所选公司印章不存在，请重新选择',
            ], 422));
        }

        return $options;
    }
}
