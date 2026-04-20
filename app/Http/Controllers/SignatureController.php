<?php

namespace App\Http\Controllers;

use App\Models\UserSignature;
use App\Models\UserSeal;
use App\Models\UserBankStamp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SignatureController extends Controller
{
    /**
     * 获取我的签名
     */
    public function getMySignature(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }

        $signature = UserSignature::where('user_id', $user->id)->first();

        if ($signature) {
            $signature->image_url = asset('storage/' . $signature->image_path);
        }

        return response()->json([
            'success' => true,
            'data' => $signature
        ]);
    }

    /**
     * 上传/更新签名
     */
    public function uploadSignature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'signature_image' => 'required|file|mimes:png,jpg,jpeg|max:2048', // 最大2MB
        ], [
            'signature_image.required' => '请上传签名图片',
            'signature_image.mimes' => '只支持PNG、JPG格式',
            'signature_image.max' => '文件大小不能超过2MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '用户未认证'
                ], 401);
            }

            $file = $request->file('signature_image');
            $originalFilename = $file->getClientOriginalName();
            
            // 保存文件
            $path = $file->store('signatures', 'public');

            // 查找或创建签名记录
            $signature = UserSignature::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'image_path' => $path,
                    'original_filename' => $originalFilename,
                ]
            );

            // 如果是更新，删除旧文件
            if ($signature->wasChanged('image_path')) {
                $oldPath = $signature->getOriginal('image_path');
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $signature->image_url = asset('storage/' . $signature->image_path);

            return response()->json([
                'success' => true,
                'message' => '签名上传成功',
                'data' => $signature
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除我的签名
     */
    public function deleteSignature(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }

        $signature = UserSignature::where('user_id', $user->id)->first();

        if (!$signature) {
            return response()->json([
                'success' => false,
                'message' => '签名不存在'
            ], 404);
        }

        // 删除文件
        if ($signature->image_path && Storage::disk('public')->exists($signature->image_path)) {
            Storage::disk('public')->delete($signature->image_path);
        }

        $signature->delete();

        return response()->json([
            'success' => true,
            'message' => '签名删除成功'
        ]);
    }

    /**
     * 获取我的印章列表
     */
    public function getMySeals(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }

        $seals = UserSeal::where('user_id', $user->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($seals as $seal) {
            $seal->image_url = asset('storage/' . $seal->image_path);
        }

        return response()->json([
            'success' => true,
            'data' => $seals
        ]);
    }

    /**
     * 上传印章
     */
    public function uploadSeal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'seal_image' => 'required|file|mimes:png,jpg,jpeg|max:2048',
        ], [
            'name.required' => '请输入印章名称',
            'seal_image.required' => '请上传印章图片',
            'seal_image.mimes' => '只支持PNG、JPG格式',
            'seal_image.max' => '文件大小不能超过2MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '用户未认证'
                ], 401);
            }

            $file = $request->file('seal_image');
            $originalFilename = $file->getClientOriginalName();
            
            // 保存文件
            $path = $file->store('seals', 'public');

            $seal = UserSeal::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'image_path' => $path,
                'original_filename' => $originalFilename,
                'is_default' => $request->is_default ?? false,
            ]);

            // 如果设置为默认，取消其他印章的默认状态
            if ($seal->is_default) {
                UserSeal::where('user_id', $user->id)
                    ->where('id', '!=', $seal->id)
                    ->update(['is_default' => false]);
            }

            $seal->image_url = asset('storage/' . $seal->image_path);

            return response()->json([
                'success' => true,
                'message' => '印章上传成功',
                'data' => $seal
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 设置默认印章
     */
    public function setDefaultSeal(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }

        $seal = UserSeal::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$seal) {
            return response()->json([
                'success' => false,
                'message' => '印章不存在'
            ], 404);
        }

        $seal->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => '已设置为默认印章'
        ]);
    }

    /**
     * 删除印章
     */
    public function deleteSeal(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }

        $seal = UserSeal::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$seal) {
            return response()->json([
                'success' => false,
                'message' => '印章不存在'
            ], 404);
        }

        // 删除文件
        if ($seal->image_path && Storage::disk('public')->exists($seal->image_path)) {
            Storage::disk('public')->delete($seal->image_path);
        }

        $seal->delete();

        return response()->json([
            'success' => true,
            'message' => '印章删除成功'
        ]);
    }

    /**
     * 获取我的银行付讫章
     */
    public function getMyBankStamp(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }

        $bankStamp = UserBankStamp::where('user_id', $user->id)->first();

        if ($bankStamp) {
            $bankStamp->image_url = asset('storage/' . $bankStamp->image_path);
        }

        return response()->json([
            'success' => true,
            'data' => $bankStamp
        ]);
    }

    /**
     * 上传/更新银行付讫章
     */
    public function uploadBankStamp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_stamp_image' => 'required|file|mimes:png,jpg,jpeg|max:2048',
            'name' => 'nullable|string|max:100',
            'position_x' => 'nullable|integer|min:0|max:100',
            'position_y' => 'nullable|integer|min:0|max:100',
            'width' => 'nullable|integer|min:20|max:300',
            'height' => 'nullable|integer|min:20|max:300',
        ], [
            'bank_stamp_image.required' => '请上传银行付讫章图片',
            'bank_stamp_image.mimes' => '只支持PNG、JPG格式',
            'bank_stamp_image.max' => '文件大小不能超过2MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '用户未认证'
                ], 401);
            }

            $file = $request->file('bank_stamp_image');
            $originalFilename = $file->getClientOriginalName();
            
            // 保存文件
            $path = $file->store('bank_stamps', 'public');

            // 查找或创建银行付讫章记录
            $bankStamp = UserBankStamp::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $request->name ?? '银行付讫',
                    'image_path' => $path,
                    'original_filename' => $originalFilename,
                    'position_x' => $request->position_x ?? 70,
                    'position_y' => $request->position_y ?? 80,
                    'width' => $request->width ?? 100,
                    'height' => $request->height ?? 50,
                ]
            );

            // 如果是更新，删除旧文件
            if ($bankStamp->wasChanged('image_path')) {
                $oldPath = $bankStamp->getOriginal('image_path');
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $bankStamp->image_url = asset('storage/' . $bankStamp->image_path);

            return response()->json([
                'success' => true,
                'message' => '银行付讫章上传成功',
                'data' => $bankStamp
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新银行付讫章位置设置
     */
    public function updateBankStampPosition(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'position_x' => 'required|integer|min:0|max:100',
            'position_y' => 'required|integer|min:0|max:100',
            'width' => 'nullable|integer|min:20|max:300',
            'height' => 'nullable|integer|min:20|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }

        $bankStamp = UserBankStamp::where('user_id', $user->id)->first();

        if (!$bankStamp) {
            return response()->json([
                'success' => false,
                'message' => '银行付讫章不存在'
            ], 404);
        }

        $bankStamp->update([
            'position_x' => $request->position_x,
            'position_y' => $request->position_y,
            'width' => $request->width ?? $bankStamp->width,
            'height' => $request->height ?? $bankStamp->height,
        ]);

        $bankStamp->image_url = asset('storage/' . $bankStamp->image_path);

        return response()->json([
            'success' => true,
            'message' => '位置设置已更新',
            'data' => $bankStamp
        ]);
    }

    /**
     * 删除银行付讫章
     */
    public function deleteBankStamp(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }

        $bankStamp = UserBankStamp::where('user_id', $user->id)->first();

        if (!$bankStamp) {
            return response()->json([
                'success' => false,
                'message' => '银行付讫章不存在'
            ], 404);
        }

        // 删除文件
        if ($bankStamp->image_path && Storage::disk('public')->exists($bankStamp->image_path)) {
            Storage::disk('public')->delete($bankStamp->image_path);
        }

        $bankStamp->delete();

        return response()->json([
            'success' => true,
            'message' => '银行付讫章删除成功'
        ]);
    }
}

