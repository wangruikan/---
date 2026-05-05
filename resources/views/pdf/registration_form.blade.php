<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>从业人员情况登记表</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "SimSun", "Microsoft YaHei", sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #000;
        }
        .page {
            padding: 5px 10px;
        }
        .page-cover {
            height: 100%;
            display: table;
            width: 100%;
        }
        .cover-content {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }
        .title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            letter-spacing: 6px;
        }
        .notice-box {
            margin-bottom: 15px;
            text-align: left;
        }
        .notice-title {
            font-weight: bold;
            text-align: center;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .notice-text {
            text-indent: 2em;
            line-height: 1.8;
            font-size: 11px;
        }
        .header-info {
            margin-bottom: 12px;
        }
        .section-title {
            font-weight: bold;
            font-size: 13px;
            margin: 10px 0 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th, td {
            padding: 5px 6px;
            text-align: left;
            font-size: 11px;
            vertical-align: middle;
        }
        th {
            background-color: #fff;
            font-weight: normal;
            text-align: center;
        }
        .signature-section {
            margin-top: 12px;
        }
        .declaration {
            font-size: 11px;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        .declaration p {
            text-indent: 2em;
            margin-bottom: 5px;
        }
        .signature-line {
            margin-top: 20px;
        }
        .signature-img {
            max-width: 120px;
            max-height: 50px;
        }
        .page-break {
            page-break-before: always;
        }
        .underline {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }
        .text-center {
            text-align: center;
        }
        .small-text {
            font-size: 10px;
        }
        .cover-info {
            text-align: center;
            margin: 80px auto;
        }
        .cover-info-item {
            margin-bottom: 30px;
            font-size: 15px;
            text-align: left;
            display: inline-block;
            width: 300px;
        }
        .cover-info-label {
            display: inline-block;
            width: 80px;
            text-align: justify;
            text-align-last: justify;
        }
        .cover-date {
            margin-top: 100px;
            font-size: 14px;
        }
        .empty-row td {
            height: 28px;
        }
    </style>
</head>
<body>
    @php
        $checkedSymbol = '☑';
        $uncheckedSymbol = '□';

        $normalize = function ($value) {
            if ($value === null) {
                return '';
            }
            $text = mb_strtolower(trim((string) $value), 'UTF-8');
            return str_replace([' ', '　', "\t", "\r", "\n", '（', '）', '(', ')', '/', '\\', '，', ',', '、', ';', '；', '|'], '', $text);
        };

        $toNormalizedArray = function ($value) use ($normalize) {
            if (is_array($value)) {
                $items = $value;
            } elseif ($value instanceof \Illuminate\Support\Collection) {
                $items = $value->all();
            } elseif (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $items = $decoded;
                } else {
                    $items = preg_split('/[,\s，、;；|\/]+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
                }
            } else {
                $items = [];
            }

            $normalized = [];
            foreach ($items as $item) {
                if (is_scalar($item)) {
                    $token = $normalize($item);
                    if ($token !== '') {
                        $normalized[] = $token;
                    }
                }
            }

            return array_values(array_unique($normalized));
        };

        $hasAny = function ($value, array $candidates) use ($toNormalizedArray, $normalize) {
            $source = $toNormalizedArray($value);
            if (empty($source) && is_scalar($value)) {
                $token = $normalize($value);
                if ($token !== '') {
                    $source = [$token];
                }
            }

            foreach ($candidates as $candidate) {
                if (in_array($normalize($candidate), $source, true)) {
                    return true;
                }
            }

            return false;
        };

        $isYes = function ($value) use ($hasAny) {
            return $hasAny($value, ['有', '是', 'yes', 'true', '1', 'y']);
        };

        $isNo = function ($value) use ($hasAny) {
            return $hasAny($value, ['无', '否', 'no', 'false', '0', 'n']);
        };

        $tick = function ($checked, $label = '') use ($checkedSymbol, $uncheckedSymbol) {
            return ($checked ? $checkedSymbol : $uncheckedSymbol) . $label;
        };

        $languageSkills = $form->language_skills ?? [];
        $engineeringSkills = $form->engineering_skills ?? [];
        $hobbies = $form->hobbies ?? [];
        $employmentDocuments = $form->employment_documents ?? [];
    @endphp
    <!-- 第一页：封面 -->
    <div class="page">
        <div style="text-align: center; padding-top: 40px;">
            <div class="title">从业人员情况登记表</div>
            
            <!-- 填表说明 -->
            <div class="notice-box" style="margin: 20px 20px;">
                <div class="notice-title">填表说明</div>
                <div class="notice-text">
                    此表内容由应聘者本人填写，公司人力资源部配合说明。薪酬福利部分由人力资源与入住员工协商一致后，由人力资源部填写，入职者确认签名。填写要求字迹清楚、工整，内容较多时，可另纸接续。填写人须保证填写内容的真实性并同意作为公司进行背景调查与核实的依据。一经发现填写内容与事实不符，公司将根据情节轻重给予记过或开除的处分并解除劳动合同。
                </div>
            </div>
            
            <!-- 姓名、部门、职务 - 居中 -->
            <div style="margin-top: 60px; text-align: center;">
                <p style="margin-bottom: 30px; font-size: 15px;">
                    姓　　名：<span class="underline" style="min-width: 180px;">{{ $form->name ?? '' }}</span>
                </p>
                <p style="margin-bottom: 30px; font-size: 15px;">
                    部　　门：<span class="underline" style="min-width: 180px;">{{ $form->department ?? '' }}</span>
                </p>
                <p style="margin-bottom: 30px; font-size: 15px;">
                    职　　务：<span class="underline" style="min-width: 180px;">{{ $form->job_title ?? '' }}</span>
                </p>
            </div>
            
            <!-- 填表日期 - 置底居中 -->
            <div style="margin-top: 420px; text-align: center; font-size: 14px;">
                填表日期：<span class="underline" style="min-width: 40px;">{{ $form->fill_date ? (is_object($form->fill_date) ? $form->fill_date->format('Y') : date('Y', strtotime($form->fill_date))) : '' }}</span>年
                <span class="underline" style="min-width: 25px;">{{ $form->fill_date ? (is_object($form->fill_date) ? $form->fill_date->format('m') : date('m', strtotime($form->fill_date))) : '' }}</span>月
                <span class="underline" style="min-width: 25px;">{{ $form->fill_date ? (is_object($form->fill_date) ? $form->fill_date->format('d') : date('d', strtotime($form->fill_date))) : '' }}</span>日
            </div>
        </div>
    </div>
    
    <!-- 第二页：基本信息和个人资料 -->
    <div class="page page-break">
        <!-- 头部信息 -->
        <div class="header-info">
            <p style="margin-bottom: 8px; font-size: 12px;">
                入职职位：<span class="underline">{{ $form->entry_position ?? '' }}</span>
                <span style="margin-left: 80px;">入职日期：<span class="underline">{{ $form->entry_date ? (is_object($form->entry_date) ? $form->entry_date->format('Y-m-d') : $form->entry_date) : '' }}</span></span>
            </p>
            <p style="margin-bottom: 8px; font-size: 12px;">
                公积金账户：<span class="underline" style="min-width: 180px;">{{ $form->housing_fund_account ?? '' }}</span>
            </p>
            <p style="margin-bottom: 8px; font-size: 12px;">
                银行账号：<span class="underline" style="min-width: 140px;">{{ $form->bank_account ?? '' }}</span>
                <span style="margin-left: 30px;">开户支行名称：<span class="underline" style="min-width: 140px;">{{ $form->bank_name ?? '' }}</span></span>
            </p>
        </div>

        <!-- 一、个人资料 -->
        <div class="section-title">一、个人资料</div>
        <table>
            <tr>
                <th style="width: 70px;">姓名</th>
                <td style="width: 90px;">{{ $form->name ?? '' }}</td>
                <th style="width: 70px;">英文名</th>
                <td style="width: 90px;">{{ $form->english_name ?? '' }}</td>
                <th style="width: 70px;">性别/身高</th>
                <td>{{ $form->gender == 'male' ? '男' : ($form->gender == 'female' ? '女' : '') }}{{ $form->height ? '/'.$form->height.'cm' : '' }}</td>
            </tr>
            <tr>
                <th>出生日期</th>
                <td>{{ $form->birth_date ? (is_object($form->birth_date) ? $form->birth_date->format('Y-m-d') : $form->birth_date) : '' }}</td>
                <th>政治面貌</th>
                <td>{{ $form->political_status ?? '' }}</td>
                <th>文化程度</th>
                <td>{{ $form->education_level ?? '' }}</td>
            </tr>
            <tr>
                <th>籍贯</th>
                <td>{{ $form->native_place ?? '' }}</td>
                <th>婚姻状况</th>
<td>{{ $tick($hasAny($form->marital_status ?? null, ['single', '未婚']), '未婚') }} {{ $tick($hasAny($form->marital_status ?? null, ['married', '已婚']), '已婚') }} {{ $tick($hasAny($form->marital_status ?? null, ['divorced', '离婚']), '离婚') }}</td>
                <th>是否有子女</th>
                <td>{{ $form->has_children ?? '' }}</td>
            </tr>
            <tr>
                <th>身份证/护照</th>
                <td colspan="3">{{ $form->id_number ?? '' }}</td>
                <th>户口状态</th>
<td>{{ $tick($hasAny($form->household_type ?? null, ['urban', 'non_agricultural', 'nonagricultural', '城镇', '非农业']), '城镇') }} {{ $tick($hasAny($form->household_type ?? null, ['rural', 'agricultural', '非城镇', '农业', '农村']), '非城镇') }}</td>
            </tr>
            <tr>
                <th>现居住</th>
                <td colspan="3">{{ $form->current_address ?? '' }}</td>
                <th>邮编</th>
                <td>{{ $form->postal_code ?? '' }}</td>
            </tr>
            <tr>
                <th>户口地址</th>
                <td colspan="3">{{ $form->household_address ?? '' }}</td>
                <th>联系电话</th>
                <td>{{ $form->contact_phone ?? '' }}</td>
            </tr>
            <tr>
                <th>文书送达地址</th>
                <td colspan="3">{{ $form->document_address ?? '' }}</td>
                <th>是否残疾证</th>
                <td class="small-text">{{ $form->disability_level ?? '无' }}</td>
            </tr>
        </table>
        
        <!-- 二、个人技能 -->
        <div class="section-title">二、个人技能</div>
        <table>
            <tr>
                <th style="width: 70px;">语言</th>
<td>英语：{{ $tick($hasAny($languageSkills, ['四级', 'cet4', '英语四级', '4级']), '四级') }} {{ $tick($hasAny($languageSkills, ['六级', 'cet6', '英语六级', '6级']), '六级') }} {{ $tick($hasAny($languageSkills, ['托福', 'toefl']), '托福') }} {{ $tick($hasAny($languageSkills, ['雅思', 'ielts']), '雅思') }}</td>
                <th style="width: 70px;">工程</th>
<td>{{ $tick($hasAny($engineeringSkills, ['电工证', '电工']), '电工证') }} {{ $tick($hasAny($engineeringSkills, ['高压证', '高压工', '高压']), '高压工') }} {{ $tick($hasAny($engineeringSkills, ['其他', 'other']), '其他') }}___</td>
            </tr>
            <tr>
                <th>职称</th>
<td>{{ $tick($hasAny($form->professional_title ?? null, ['初级']), '初级') }} {{ $tick($hasAny($form->professional_title ?? null, ['中级']), '中级') }} {{ $tick($hasAny($form->professional_title ?? null, ['高级']), '高级') }} {{ $tick($hasAny($form->professional_title ?? null, ['其他', '无', 'none']), '其他') }}___</td>
                <th>兴趣</th>
<td>{{ $tick($hasAny($hobbies, ['唱歌']), '唱歌') }} {{ $tick($hasAny($hobbies, ['棋类']), '棋类') }} {{ $tick($hasAny($hobbies, ['球类']), '球类') }} {{ $tick($hasAny($hobbies, ['其他', 'other']), '其他') }}___</td>
            </tr>
            <tr>
                <th>其他技能</th>
                <td colspan="3">{{ $form->other_skills ?? '' }}</td>
            </tr>
        </table>

        <!-- 三、教育情况 -->
        <div class="section-title">三、教育情况</div>
        <table>
            <tr>
                <th style="width: 70px;">教育培训</th>
                <th style="width: 110px;">自____至____</th>
                <th>学校及专业</th>
                <th style="width: 100px;">所获证书</th>
            </tr>
            @php $eduHistory = is_array($form->education_history) ? $form->education_history : []; @endphp
            @for($i = 0; $i < max(4, count($eduHistory)); $i++)
            <tr class="empty-row">
                <td></td>
                <td>{{ $eduHistory[$i]['date_range'] ?? '' }}</td>
                <td>{{ $eduHistory[$i]['school_major'] ?? '' }}</td>
                <td>{{ $eduHistory[$i]['certificate'] ?? '' }}</td>
            </tr>
            @endfor
        </table>
        
        <!-- 四、工作履历 -->
        <div class="section-title">四、工作履历</div>
        <table>
            <tr>
                <th style="width: 95px;">自____至____</th>
                <th>公司</th>
                <th style="width: 70px;">职位</th>
                <th style="width: 55px;">薪酬</th>
                <th style="width: 85px;">离职原因</th>
            </tr>
            @php $workHistory = is_array($form->work_history) ? $form->work_history : []; @endphp
            @for($i = 0; $i < max(4, count($workHistory)); $i++)
            <tr class="empty-row">
                <td>{{ $workHistory[$i]['date_range'] ?? '' }}</td>
                <td>{{ $workHistory[$i]['company'] ?? '' }}</td>
                <td>{{ $workHistory[$i]['position'] ?? '' }}</td>
                <td>{{ $workHistory[$i]['salary'] ?? '' }}</td>
                <td>{{ $workHistory[$i]['leave_reason'] ?? '' }}</td>
            </tr>
            @endfor
            <tr>
                <td colspan="5" class="small-text text-center">请您提供对您以前受雇情况了解的前单位人员</td>
            </tr>
            <tr>
                <th colspan="2">前单位名称</th>
                <th colspan="3">职位/联系电话</th>
            </tr>
            <tr class="empty-row">
                <td colspan="2">{{ $form->reference_company ?? '' }}</td>
                <td colspan="3">{{ $form->reference_contact ?? '' }}</td>
            </tr>
        </table>
    </div>
    
    <!-- 第三页：其他信息 -->
    <div class="page page-break">
        <!-- 五、奖罚情况 -->
        <div class="section-title">五、奖罚情况</div>
        <table>
            <tr>
                <td colspan="5" style="height: 60px; vertical-align: top; padding: 6px;">
                    <div class="small-text" style="margin-bottom: 4px;">何时何地担任何职务获得何种内容的奖励，或受到何种处罚，有否证明，请简述：</div>
                    {{ $form->rewards_punishments ?? '' }}
                </td>
            </tr>
        </table>
        
        <!-- 六、家庭情况 -->
        <div class="section-title">六、家庭情况</div>
        <table>
            <tr>
                <th style="width: 70px;">姓名</th>
                <th style="width: 70px;">关系</th>
                <th style="width: 55px;">年龄</th>
                <th>工作单位</th>
                <th style="width: 110px;">电话</th>
            </tr>
            @php $familyMembers = is_array($form->family_members) ? $form->family_members : []; @endphp
            @for($i = 0; $i < max(4, count($familyMembers)); $i++)
            <tr class="empty-row">
                <td>{{ $familyMembers[$i]['name'] ?? '' }}</td>
                <td>{{ $familyMembers[$i]['relation'] ?? '' }}</td>
                <td>{{ $familyMembers[$i]['age'] ?? '' }}</td>
                <td>{{ $familyMembers[$i]['employer'] ?? '' }}</td>
                <td>{{ $familyMembers[$i]['phone'] ?? '' }}</td>
            </tr>
            @endfor
        </table>

        <!-- 七、紧急联系方式 -->
        <div class="section-title">七、紧急联系方式</div>
        <table>
            <tr class="empty-row">
                <th style="width: 110px;">第一联系人姓名</th>
                <td style="width: 90px;">{{ $form->emergency_contact1_name ?? '' }}</td>
                <th style="width: 70px;">与己关系</th>
                <td style="width: 70px;">{{ $form->emergency_contact1_relation ?? '' }}</td>
                <th style="width: 70px;">联系电话</th>
                <td>{{ $form->emergency_contact1_phone ?? '' }}</td>
            </tr>
            <tr class="empty-row">
                <th>第二联系人姓名</th>
                <td>{{ $form->emergency_contact2_name ?? '' }}</td>
                <th>与己关系</th>
                <td>{{ $form->emergency_contact2_relation ?? '' }}</td>
                <th>联系电话</th>
                <td>{{ $form->emergency_contact2_phone ?? '' }}</td>
            </tr>
        </table>
        
        <!-- 八、其他情况 -->
        <div class="section-title">八、其他情况</div>
        <table>
            <tr class="empty-row">
                <th style="width: 110px;">精神病</th>
<td colspan="2">{{ $tick($isYes($form->mental_illness ?? null), '有') }}（请注明详情）：{{ $form->mental_illness_detail ?? '' }}　　{{ $tick($isNo($form->mental_illness ?? null), '无') }}</td>
            </tr>
            <tr class="empty-row">
                <th>其他疾病</th>
<td colspan="2">{{ $tick($isYes($form->other_illness ?? null), '有') }}（请注明详情）：{{ $form->other_illness_detail ?? '' }}　　{{ $tick($isNo($form->other_illness ?? null), '无') }}</td>
            </tr>
            <tr class="empty-row">
                <th>最近6个月内有无<br/>住院记录</th>
<td style="width: 140px;">{{ $tick($isYes($form->hospitalized_recently ?? null), '有') }}　　{{ $tick($isNo($form->hospitalized_recently ?? null), '无') }}</td>
                <td>病因：{{ $form->hospitalized_reason ?? '' }}</td>
            </tr>
            <tr class="empty-row">
                <th>有无违法犯罪记录</th>
<td>{{ $tick($isYes($form->criminal_record ?? null), '有') }}　　{{ $tick($isNo($form->criminal_record ?? null), '无') }}</td>
                <td>时间：{{ $form->criminal_record_time ?? '' }}</td>
            </tr>
            <tr class="empty-row">
                <th>就业证件</th>
<td colspan="2">{{ $tick($hasAny($employmentDocuments, ['劳动手册']), '劳动手册') }}　{{ $tick($hasAny($employmentDocuments, ['离职证明']), '离职证明') }}　{{ $tick($hasAny($employmentDocuments, ['应届毕业']), '应届毕业') }}　{{ $tick($hasAny($employmentDocuments, ['下岗/协保证明', '下岗', '协保证明']), '下岗/协保证明') }}　{{ $tick($hasAny($employmentDocuments, ['其他', 'other']), '其他') }}___</td>
            </tr>
        </table>
        
        <!-- 九、其他需要说明的情况 -->
        <div class="section-title">九、其他需要说明的情况（个人对职业发展目标、需求、建议等阐述）</div>
        <table>
            <tr>
                <th style="width: 55px;">备注</th>
                <td style="height: 60px; vertical-align: top; padding: 6px;">{{ $form->remarks ?? '' }}</td>
            </tr>
        </table>

        <!-- 十、其他需要核实的情况 -->
        <div class="section-title">十、其他需要核实的情况</div>
        <table>
            <tr class="empty-row">
                <th style="width: 140px;">您是否怀孕</th>
<td>{{ $tick($isYes($form->is_pregnant ?? null), '有') }}（请注明详情）：{{ $form->pregnant_detail ?? '' }}　　{{ $tick($isNo($form->is_pregnant ?? null), '无') }}</td>
            </tr>
            <tr class="empty-row">
                <th>您是否接受加班、出差</th>
<td>{{ $tick($hasAny($form->accept_overtime ?? null, ['接受', 'accept', 'yes', '是']), '接受') }}　　{{ $tick($hasAny($form->accept_overtime ?? null, ['不接受', '拒绝', 'no', '否']), '不接受') }}</td>
            </tr>
            <tr class="empty-row">
                <th>您是否需要提供住宿</th>
<td>{{ $tick($isYes($form->need_accommodation ?? null), '有') }}（请注明详情）：{{ $form->accommodation_detail ?? '' }}　　{{ $tick($isNo($form->need_accommodation ?? null), '无') }}</td>
            </tr>
            <tr class="empty-row">
                <th>您是否有驾照</th>
<td>{{ $tick($isYes($form->has_driving_license ?? null), '有') }}（请注明详情）：{{ $form->driving_license_detail ?? '' }}　　{{ $tick($isNo($form->has_driving_license ?? null), '无') }}</td>
            </tr>
        </table>
        
        <!-- 声明与签名 -->
        <div class="signature-section">
            <div class="declaration">
                <p>1、本人保证以上填写资料与所提供的证明文件均属实，并且本人授权调查上述资料之真实性，如有虚报隐瞒愿接受立即解雇之处分，并承担所有责任。</p>
                <p>2、员工在职及离职两年内如果送达地址有变更，应当书面及时告知公司变更后的送达地址。如未做变更，公司如有书面文件或通知送达上述地址，即默认为送达，造成后果由本人自行承担。</p>
            </div>
            
            <table style="border: none; width: 100%; margin-top: 20px;">
                <tr>
                    <td style="border: none; text-align: left; width: 50%;">
                        <span>申请人签名：</span>
                        @if(!empty($form->signature))
                            @php
                                $signatureUrl = $form->signature;
                                if (strpos($signatureUrl, 'http') !== 0) {
                                    if (strpos($signatureUrl, 'uploads/') === 0) {
                                        $signatureUrl = public_path(ltrim($signatureUrl, '/'));
                                    } else {
                                        $signatureUrl = public_path('storage/' . ltrim($signatureUrl, '/'));
                                    }
                                }
                            @endphp
                            <img src="{{ $signatureUrl }}" class="signature-img" />
                        @else
                            <span class="underline" style="min-width: 130px;"></span>
                        @endif
                    </td>
                    <td style="border: none; text-align: right; width: 50%;">
                        <span>日期：</span>
                        <span class="underline" style="min-width: 130px;">{{ $form->signature_date ? (is_object($form->signature_date) ? $form->signature_date->format('Y年m月d日') : $form->signature_date) : '' }}</span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
