<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>入职登记表 - {{ $form->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-size: 12px;
            line-height: 1.5;
            color: #000;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #000;
            padding-bottom: 15px;
        }
        
        .company-name {
            font-size: 11px;
            margin-bottom: 10px;
            color: #666;
        }
        
        .title {
            font-size: 22px;
            font-weight: bold;
            margin: 10px 0;
            letter-spacing: 8px;
            color: #000;
        }
        
        .registration-date {
            font-size: 11px;
            margin-bottom: 15px;
            text-align: right;
            padding-right: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }
        
        table td {
            border: 1px solid #000;
            padding: 6px 10px;
            font-size: 11px;
            vertical-align: middle;
        }
        
        .label-cell {
            background-color: #e8f4f8;
            text-align: center;
            width: 12%;
            font-size: 11px;
        }
        
        .photo-box {
            width: 90px;
            height: 120px;
            border: 1px solid #000;
            text-align: center;
            vertical-align: middle;
            font-size: 11px;
            color: #666;
        }
        
        .section-title {
            background-color: #e8f4f8;
            font-weight: bold;
            padding: 6px;
            text-align: center;
            font-size: 11px;
        }
        
        .experience-table {
            font-size: 10px;
        }
        
        .experience-table td {
            padding: 5px 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">新工人力资源服务有限公司</div>
        <div class="title">入 职 登 记 表</div>
    </div>
    <div class="registration-date">登记日期：{{ $form->registration_date ? date('Y年m月d日', strtotime($form->registration_date)) : '______年____月____日' }}</div>
    
    <!-- 基本信息 -->
    <table>
        <tr>
            <td class="label-cell">姓名</td>
            <td>{{ $form->name ?? '' }}</td>
            <td class="label-cell">性别</td>
            <td>{{ $form->gender == 'male' ? '男' : ($form->gender == 'female' ? '女' : '') }}</td>
            <td class="label-cell">民族</td>
            <td>{{ $form->ethnicity ?? '' }}</td>
            <td width="90" rowspan="5" style="text-align: center; vertical-align: middle; color: #999; font-size: 10px;">
                （照片位置）
            </td>
        </tr>
        <tr>
            <td class="label-cell">政治面貌</td>
            <td>{{ $form->political_status ?? '' }}</td>
            <td class="label-cell">籍贯</td>
            <td colspan="3">{{ $form->place_of_origin ?? '' }}</td>
        </tr>
        <tr>
            <td class="label-cell">出生年月</td>
            <td colspan="5">{{ $form->birth_date ? date('Y-m-d', strtotime($form->birth_date)) : '' }}</td>
        </tr>
        <tr>
            <td class="label-cell">毕业学校</td>
            <td colspan="2">{{ $form->graduated_school ?? '-' }}</td>
            <td class="label-cell">毕业时间</td>
            <td>{{ $form->graduation_date ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label-cell">文化程度</td>
            <td>{{ $form->education_level ?? '-' }}</td>
            <td class="label-cell">所学专业</td>
            <td colspan="2">{{ $form->major ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label-cell">学位</td>
            <td>{{ $form->degree ?? '' }}</td>
            <td class="label-cell">技术职称</td>
            <td colspan="2">{{ $form->technical_title ?? '' }}</td>
            <td class="label-cell">健康状况</td>
            <td>{{ $form->health_status ?? '' }}</td>
        </tr>
        <tr>
            <td class="label-cell">身高</td>
            <td>{{ $form->height ? $form->height . 'cm' : '' }}</td>
            <td class="label-cell">体重</td>
            <td>{{ $form->weight ? $form->weight . 'kg' : '' }}</td>
            <td class="label-cell">婚姻状况</td>
            <td colspan="2">{{ $form->marital_status ?? '' }}</td>
        </tr>
        <tr>
            <td class="label-cell">身份证号码</td>
            <td colspan="6">{{ $form->id_number ?? '' }}</td>
        </tr>
        <tr>
            <td class="label-cell">现居住地</td>
            <td colspan="6">{{ $form->current_residence ?? '' }}</td>
        </tr>
        <tr>
            <td class="label-cell">户口所在地</td>
            <td colspan="6">{{ $form->household_registration ?? '' }}</td>
        </tr>
    </table>
    
    <!-- 学习简历 -->
    @if($form->education_background && count($form->education_background) > 0)
    <table class="experience-table">
        <tr>
            <td rowspan="{{ count($form->education_background) + 1 }}" class="section-title" style="width: 12%;">学习简历<br/>(从中学填起)</td>
            <td class="label-cell" width="20%">起止时间</td>
            <td class="label-cell" width="30%">在何学校学习</td>
            <td class="label-cell" width="15%">学习层次</td>
            <td class="label-cell" width="23%">证明人</td>
        </tr>
        @foreach($form->education_background as $edu)
        <tr>
            <td>{{ ($edu['start_date'] ?? '') . ' - ' . ($edu['end_date'] ?? '') }}</td>
            <td>{{ $edu['school'] ?? '-' }}</td>
            <td>{{ $edu['level'] ?? '-' }}</td>
            <td>{{ $edu['reference'] ?? '-' }}</td>
        </tr>
        @endforeach
    </table>
    @endif
    
    <!-- 工作经历 -->
    @if($form->work_experience && count($form->work_experience) > 0)
    <table class="experience-table">
        <tr>
            <td rowspan="{{ count($form->work_experience) + 1 }}" class="section-title" style="width: 12%;">工作经历<br/>(最近二份至三份工作简历)</td>
            <td class="label-cell" width="20%">起止时间</td>
            <td class="label-cell" width="25%">在何工作单位</td>
            <td class="label-cell" width="30%">主要工作内容</td>
            <td class="label-cell" width="13%">证明人</td>
        </tr>
        @foreach($form->work_experience as $work)
        <tr>
            <td>{{ ($work['start_date'] ?? '') . ' - ' . ($work['end_date'] ?? '') }}</td>
            <td>{{ $work['employer'] ?? '-' }}</td>
            <td>{{ $work['job_content'] ?? '-' }}</td>
            <td>{{ $work['certifier'] ?? '-' }}</td>
        </tr>
        @endforeach
    </table>
    @endif
    
    <!-- 家庭情况 -->
    @if($form->family_info && count($form->family_info) > 0)
    <table class="experience-table">
        <tr>
            <td rowspan="{{ count($form->family_info) + 1 }}" class="section-title" style="width: 12%;">家庭情况</td>
            <td class="label-cell" width="18%">姓名</td>
            <td class="label-cell" width="12%">关系</td>
            <td class="label-cell" width="30%">所在单位</td>
            <td class="label-cell" width="28%">联系电话</td>
        </tr>
        @foreach($form->family_info as $family)
        <tr>
            <td>{{ $family['name'] ?? '-' }}</td>
            <td>{{ $family['relationship'] ?? '-' }}</td>
            <td>{{ $family['employer'] ?? '-' }}</td>
            <td>{{ $family['phone'] ?? '-' }}</td>
        </tr>
        @endforeach
    </table>
    @endif
    
    <!-- 其他信息 -->
    <table>
        <tr>
            <td class="label-cell" width="15%">岗位</td>
            <td width="35%">{{ $form->position ?? '-' }}</td>
            <td class="label-cell" width="15%">求职地区</td>
            <td width="35%">{{ $form->desired_location ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label-cell">是否服从调配</td>
            <td colspan="3">
                <span style="margin-right: 20px;">□ 是</span>
                <span>□ 否</span>
                @if($form->accept_assignment !== null)
                    （已选：{{ $form->accept_assignment ? '是' : '否' }}）
                @endif
            </td>
        </tr>
        <tr>
            <td class="label-cell">联系地址</td>
            <td colspan="3">{{ $form->contact_address ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label-cell">联系电话</td>
            <td colspan="3">{{ $form->contact_phone ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label-cell">备注</td>
            <td colspan="3">{{ $form->remarks ?? '-' }}</td>
        </tr>
    </table>
    
    <!-- 声明和签名 -->
    <table style="margin-top: 20px;">
        <tr>
            <td colspan="4" style="border: none; padding: 15px; font-size: 11px; line-height: 1.8;">
                <div style="text-indent: 2em;">
                    本人保证以上所填写的信息真实可靠，如有虚假，本人愿承担一切法律责任。
                </div>
                <div style="text-align: right; margin-top: 20px; padding-right: 30px;">
                    本人签名：
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
