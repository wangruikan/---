<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\LargeMedicalPaymentCycleService;

class InsuranceDetailRecord extends Model
{
    use HasFactory;

    protected $table = 'insurance_detail_records';

    protected $fillable = [
        'insurance_personnel_id',
        'employee_id',
        'employee_name',
        'employee_id_number',
        'employee_gender',
        'employee_birth_date',
        'employee_phone',
        'project_id',
        'project_name',
        'account_set_id',
        'record_year',
        'record_month',
        'employee_social_security_base',
        'employee_medical_insurance_base',
        'employee_housing_fund_base',
        'employee_large_medical_base',
        'social_security_types',
        'medical_insurance_types',
        'housing_fund_params',
        'other_insurance_policies',
        'large_medical_insurance_config',
        'social_security_company_amount',
        'social_security_employee_amount',
        'medical_insurance_company_amount',
        'medical_insurance_employee_amount',
        'housing_fund_company_amount',
        'housing_fund_employee_amount',
        'large_medical_company_amount',
        'large_medical_employee_amount',
        'other_insurance_total_amount',
        'status',
        'generated_at',
        'confirmed_at'
    ];

    protected $casts = [
        'employee_birth_date' => 'date',
        'employee_social_security_base' => 'decimal:2',
        'employee_medical_insurance_base' => 'decimal:2',
        'employee_housing_fund_base' => 'decimal:2',
        'employee_large_medical_base' => 'decimal:2',
        'social_security_company_amount' => 'decimal:2',
        'social_security_employee_amount' => 'decimal:2',
        'medical_insurance_company_amount' => 'decimal:2',
        'medical_insurance_employee_amount' => 'decimal:2',
        'housing_fund_company_amount' => 'decimal:2',
        'housing_fund_employee_amount' => 'decimal:2',
        'large_medical_company_amount' => 'decimal:2',
        'large_medical_employee_amount' => 'decimal:2',
        'other_insurance_total_amount' => 'decimal:2',
        'generated_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    // 闂備胶顭堢换鎰版偪閸ャ劎顩烽柛顐犲劚缁€鍌溾偓瑙勬礀濞差參宕?
    public function insurancePersonnel()
    {
        return $this->belongsTo(InsurancePersonnel::class, 'insurance_personnel_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    // 濠电偛顕慨瀛橆殽缁嬫鍤曟い鎺嶈兌閳瑰秵绻濋棃娑欏櫤闁活亙绮欓弻娑樷槈濞咁厼鍚嬬粚杈ㄧ節閸パ呯暢濡炪倖鐗楃划宥夊疾閹间焦鐓欓悗娑櫭慨鍫熶繆閸欏鐏╃紒杈ㄥ浮瀹曞崬顫濋悡搴㈡毆闁?
    public static function generateFromPersonnel($personnel, $year, $month)
    {
        // 婵犵妲呴崑鈧柛瀣崌閺岋紕浠︾拠鎻掑濠碘€冲级閹倸鐣烽妷鈺傛櫇闁逞屽墴瀹曟瑩鏁嶉崟銊ヤ壕婵炴垶顏鍕惰€挎い蹇撴鐎氭岸鏌曟径娑㈡缂佺姵鍨甸湁闁绘ê鐏氬▍鏇熺箾閸喎鐏╅柟宄邦儔瀵墎鎹勯妸顬?
        $existingRecord = self::where('employee_id', $personnel->employee_id)
            ->where('project_id', $personnel->project_id)
            ->where('account_set_id', $personnel->account_set_id)
            ->where('record_year', $year)
            ->where('record_month', $month)
            ->first();

        $normalizedLargeMedicalConfig = self::normalizeLargeMedicalConfigSnapshot($personnel);

        if ($existingRecord) {
            // 濠电姰鍨煎▔娑氣偓姘煎櫍楠炲啯绻濋崶褏顦ч梺鍝勭墢閺佹悂鏌ч崒姣插酣宕堕妸褏鐤勫┑鐐叉噺閹瑰洭寮鍜佹僵妞ゆ巻鍋撻柣蹇旂叀閺岋繝宕掗妶鍛闂佷紮鑵归崜婵堢矙婢舵劦鏁傞柛鎰典簽椤旀棃姊洪崨濠庢畷婵炲弶锚鍗遍柟闂寸鐟欙箓鏌ㄩ弴妤€浜鹃梺鍛婂煀缁绘繂鐣烽敐澶婄＜婵炲棙鍨归?
            $otherInsurancePolicies = $personnel->other_insurance_policies;
            $policiesToEnroll = [];
            
            if ($otherInsurancePolicies && !empty($otherInsurancePolicies) && $otherInsurancePolicies !== '[]') {
                $policies = json_decode($otherInsurancePolicies, true);
                
                if (is_array($policies)) {
                    // 闂備礁鍚嬮崕鎶藉床閼艰翰浜归柛銉戝苯鏅犻梺鍦帛鐢帒螞濡懙搴ㄥ炊閵娧呯暭濠电偛鐗婇崹鍓佺矚閸楃偐鏀介柛鈩冾殘椤︿即姊洪崨濠傜瑲妞ゃ劌顦垫俊?
                    $enrolledPolicies = [];
                    if ($personnel->other_insurance_policy_versions) {
                        $enrolledPolicies = json_decode($personnel->other_insurance_policy_versions, true) ?? [];
                    }
                    
                    // 婵犵妲呴崑鈧柛瀣崌閺岋紕浠︾拠鎻掑闂佷紮鑵归崜婵堢矙婢舵劦鏁傞柛鎰典簽椤旀棃姊洪崨濠庢畷婵炲弶锚鍗遍柟闂寸鐟欙箓鏌ㄩ弴妤€浜鹃梺鍛婂煀缁绘繂鐣烽敐澶婄＜婵炲棙鍨归鏃堟⒑閹稿海鈽夐柣妤佸姍閸┾偓妞ゆ巻鍋撻柛鐔峰暱椤﹦绱掑Ο灏栨敵濠电娀娼уΛ妤冪矆鐎ｎ喗鐓涢柛顐ｇ箥濡插綊鏌￠崨顐㈠姦鐎殿喚鏁婚幃銏犵暋閹殿喚袦闂備礁鎼ú鈺呭箵椤忓棙顫?
                    foreach ($policies as $policy) {
                        $policyId = $policy['id'] ?? null;
                        if (!$policyId) continue;
                        
                        // 闂?闂備胶顭堢换鎴炵箾婵犲洤鏋佹い鎾跺枔閳瑰秵銇勯弮鍥撻柡鍡╁弮閺屻劌鈽夊▎鎴犲彎缂備緡鍠氭繛鈧€殿噮鍋婇幃褔宕煎┑鍫涘亰闂佸湱鍘ч悺銊╁箲娓氣偓閻涱喛绠涘☉妯肩厬闂佸搫绋勬俊鍥╃不娴犲鐓曟俊銈勭劍缁€鍫熺箾閸喎鐏寸€殿喚鏁婚崺鈧い鎺戝濡﹢鏌ｅΔ鈧悧濠勭不閹烘鐓涘ù锝呮憸婢ф稒绻濋埀顒勬晸閻樿櫕娅栭梺鍓插亖閸ㄦ椽寮宠箛鎾佺懓顭ㄩ崘顭戝妷濡炪們鍎遍幊搴ㄦ箒闁诲函缍嗛崜娑㈠极閸撗呯＜闁规儳鎳撻铏圭磼?
                        $latestPolicy = \App\Models\OtherInsurancePolicy::find($policyId);
                        if ($latestPolicy) {
                            // 濠电偠鎻紞鈧繛澶嬫礋瀵偊濡舵径濠冪€梺缁橆殔閻楀棛绮婇敃鍌氱閻庢稒蓱鐏忣偊鏌ら崹顐㈢伌闁诡垰鍟村畷鐔碱敆娴ｉ甯涢梻浣告惈閸婄粯鏅跺Δ鈧敃銏＄瑹閳ь剙顕ｉ锕€閱囬柣鏃傤焾閻?
                            $policy['policy_start_date'] = $latestPolicy->policy_start_date ?? $latestPolicy->start_date;
                            $policy['policy_end_date'] = $latestPolicy->policy_end_date_new ?? $latestPolicy->end_date;
                        }
                        
                        // 闂?闂備礁鎲＄敮鍥磹閺嶎厼钃熼柛銉簵娴滃綊鏌熼幆褍鏆辨い銈呮嚇閺岋繝宕煎┑鍫濐暪闂佸摜鍋戦崐婵嗩嚕娴兼惌鏁嶆慨姗嗗幖閸撱劑姊洪棃鈺勭闁告柨顑囬幑銏狀潩鐠鸿櫣顢呴梺鍝勬川閸犳劗绮婚幒妤佺厸濞达絽鎽滄晶娑欑節閳ь剟鏁撻悩鑼槰?
                        $targetMonthStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
                        $targetMonthEnd = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();
                        $rawStartDate = $policy['policy_start_date'] ?? $policy['start_date'] ?? null;
                        $rawEndDate = $policy['policy_end_date'] ?? $policy['end_date'] ?? null;

                        if (empty($rawStartDate) || empty($rawEndDate)) {
                            continue;
                        }

                        $startDate = \Carbon\Carbon::parse($rawStartDate)->startOfDay();
                        $endDate = \Carbon\Carbon::parse($rawEndDate)->endOfDay();
                        $hasOverlap = $startDate->lte($targetMonthEnd) && $endDate->gte($targetMonthStart);

                        if (!$hasOverlap) {
                            // 濠电偞鍨堕幐鍝ョ矓閺夋埊鑰挎い蹇撴噽閳瑰秵绻濋棃娑欘棞缂佲偓鐎ｎ喗鐓涢柛顐ｇ箥濡插綊鏌￠崨顐㈠姦鐎殿喚鏁婚幃銏犵暋閻楀牊娈介梻浣瑰缁嬫垿鎯屾笟鈧幃婊堟晜閼恒儰姘?
                            \Log::info('Policy not in effective period when generating detail, skip', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'current_date' => $targetMonthStart->format('Y-m'),
                                'start_date' => $startDate ? $startDate->format('Y-m-d') : 'null',
                                'end_date' => $endDate ? $endDate->format('Y-m-d') : 'null'
                            ]);
                            continue;
                        }
                        
                        // 闂備焦鐪归崹濠氬窗閹版澘鍨傛慨姗嗗幘閳瑰秵绻濋棃娑欘棞缂佲偓鐎ｎ喗鐓曢柣鎴濇閻忕喓绱掗弮鈧幐鍐差嚕娴犲鐐婃い蹇撴椤岸姊洪幐搴ｂ姇闁活収鍠氶幑銏狀潩鐠鸿櫣顢呴梺鍝勫暟缁?+ 闂備礁鎼悧鍡浰囨导鏉戠柧闁靛鏅涚€?
                        $policyKey = self::generatePolicyKey($policy);
                        
                        if (!isset($enrolledPolicies[$policyKey])) {
                            // 闂佽崵濮村ú銏ゅ磿閹殿喗宕叉慨妯挎硾绾偓闂佸搫娴傛禍鐐电矆閸曨垱鐓欓悗娑欘焽婢ц櫕銇勯弴鐔峰摵闁诡啫鍥ч唶闁绘柨鎲＄€氭娊姊洪幐搴ｂ槈妞わ附婢橀敃銏ゎ敇閵忕姷鐓戦梺绋挎湰閻熝呯不娴犲鐓ユ繛鎴烆焽閻掑憡绻涢幘鍐差暢闁硅尙澧楅幏鍛存倻濡粯顏熼梻浣烘嚀閻°劑宕濋弽顬綁骞嬮悩鐢碉紲?
                            $policiesToEnroll[] = $policy;
                            
                            \Log::info('Other insurance detail generation: policy not enrolled yet, mark for enrollment', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'policy_key' => $policyKey,
                                'policy_name' => $policy['name'] ?? 'unknown',
                                'year' => $year,
                                'month' => $month
                            ]);
                        } else {
                            \Log::info('Other insurance detail generation: policy already enrolled, skip duplicate enrollment', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'policy_key' => $policyKey,
                                'enrolled_at' => $enrolledPolicies[$policyKey] ?? 'unknown',
                                'year' => $year,
                                'month' => $month
                            ]);
                        }
                    }
                    
                    // 闂備礁鎲￠悷顖涚濞嗘垶宕叉慨妯垮煐閸嬧晜绻涢崱妯诲鞍濞寸姵锕㈤幃鐑藉即濮橀硸妲悗娈垮櫘閸撴氨绮氶崡鐐╂斀闁割偆鍠撻弳鐘崇箾閿濆懏澶勭紒璇插€搁悾?
                    if (!empty($policiesToEnroll)) {
                        $otherInsurancePolicies = json_encode($policiesToEnroll);
                    } else {
                        $otherInsurancePolicies = '';
                    }
                }
            }
            
            // 闂備礁鎼ú銈夋偤閵娾晛钃熷┑鐘叉处閸嬫繈鏌ｅΔ鈧悧濠勭不閹烘鍋ｅù锝堫潐缁惰尙绱?
            $existingRecord->update([
                'employee_name' => $personnel->employee_name,
                'employee_id_number' => $personnel->employee_id_number,
                'employee_gender' => $personnel->employee_gender,
                'employee_birth_date' => $personnel->employee_birth_date,
                'employee_phone' => $personnel->employee_phone,
                'project_name' => $personnel->project ? $personnel->project->name : null,
                'employee_social_security_base' => $personnel->employee_social_security_base,
                'employee_medical_insurance_base' => $personnel->employee_medical_insurance_base,
                'employee_housing_fund_base' => $personnel->employee_housing_fund_base,
                'employee_large_medical_base' => $personnel->employee_large_medical_base,
                'social_security_types' => $personnel->social_security_types,
                'medical_insurance_types' => $personnel->medical_insurance_types,
                'housing_fund_params' => $personnel->housing_fund_params,
                'other_insurance_policies' => $otherInsurancePolicies,
                'large_medical_insurance_config' => $normalizedLargeMedicalConfig,
                'generated_at' => now(),
            ]);

            // 濠电姷顣介埀顒€鍟块埀顒€缍婇幃妯诲緞閹邦剙鐝樻繝銏ｆ硾椤戝棝鎮￠埀顒勬⒑閸涘﹦鎳冮柛濠囶棑閹广垹顫濋懜闈涘壄闂佸憡娲︽禍鐐电不娴犲鐓曟俊銈勭劍缁岃法绱掓潏銊х畼闁瑰嘲顑夊鍓佹崉閵婎灝顓㈡⒑閸涘﹦鎳冮柛濠囶棑閹广垹顫濋鐘敵闂佹枼鏅涢崯鈺冩?
            if (!empty($policiesToEnroll)) {
                $now = now();
                
                // 闂備礁鍚嬮崕鎶藉床閼艰翰浜归柛銉戝苯鏅犻梺鍦帛鐢帒螞濡懙搴ㄥ炊閵娧呯暤闂佸壊鐓堟禍鐐哄箖?
                $enrolledPolicies = [];
                if ($personnel->other_insurance_policy_versions) {
                    $enrolledPolicies = json_decode($personnel->other_insurance_policy_versions, true) ?? [];
                }
                
                // 婵犵數鍎戠紞鈧い鏇嗗嫭鍙忛柣鎰惈濡﹢鏌℃径濠勪虎婵☆偅锚鑿愰柛銉ｅ妿缁犳ɑ绻涢崼鐔风仸缂佸苯宕埞鎴﹀幢濡ゅ喚妲归梻浣瑰缁嬫垿鎮ч崨顔剧闁搞儺鍓氶崑銊╂煏婵炲灝鈧绮绘禒瀣厱婵°倓鐒﹂‖濯弝闂備焦瀵х粙鎴︽嚐椤栨壕鍋撻崹顐€跨€规洏鍎甸、鏇㈡晲閸℃瑧鏆㈤梻浣芥〃缁€渚€鎮ф繝鍌﹁€块柨鐔哄У閺?
                foreach ($policiesToEnroll as $policy) {
                    $policyKey = self::generatePolicyKey($policy);
                    $enrolledPolicies[$policyKey] = $now->format('Y-m-d H:i:s');
                }
                
                // 濠电儑绲藉ú锔炬崲閸岀偞鍋ら柕濞炬櫅閻鏌涚仦鍓ф噮缂佺姳绮欓幃瑙勬媴鐟欏嫮鍑＄紓?
                $personnel->other_insurance_enrolled_at = $now;
                $personnel->other_insurance_policy_versions = json_encode($enrolledPolicies);
                $personnel->save();
                
                \Log::info('Other insurance enrollment versions updated after detail regeneration', [
                    'employee_id' => $personnel->employee_id,
                    'personnel_id' => $personnel->id,
                    'enrolled_policies' => $enrolledPolicies,
                    'policy_count' => count($policiesToEnroll)
                ]);
            }
            
            // 闂傚倷鐒﹁ぐ鍐矓閻㈢钃熷┑鐘插婵ジ鏌ㄥ☉妯侯伀闁哄棭鍓熷娲矗婢跺﹦浼屽?
            $existingRecord->calculateAmounts();
            
            return $existingRecord;
        } else {
            // 濠电姰鍨煎▔娑氣偓姘煎櫍楠炲啯绻濋崶褏顦ч梺鍝勭墢閺佹悂鏌ч崒姣插酣宕堕妸褏鐤勫┑鐐叉噺閹瑰洭寮鍜佹僵妞ゆ巻鍋撻柣蹇旂叀閺岋繝宕掗妶鍛闂佷紮鑵归崜婵堢矙婢舵劦鏁傞柛鎰典簽椤旀棃姊洪崨濠庢畷婵炲弶锚鍗遍柟闂寸鐟欙箓鏌ㄩ弴妤€浜鹃梺鍛婂煀缁绘繂鐣烽敐澶婄＜婵炲棙鍨归?
            $otherInsurancePolicies = $personnel->other_insurance_policies;
            $policiesToEnroll = [];
            $shouldEnroll = false;
            
            if ($otherInsurancePolicies && !empty($otherInsurancePolicies) && $otherInsurancePolicies !== '[]') {
                $policies = json_decode($otherInsurancePolicies, true);
                
                if (is_array($policies)) {
                    // 闂備礁鍚嬮崕鎶藉床閼艰翰浜归柛銉戝苯鏅犻梺鍦帛鐢帒螞濡懙搴ㄥ炊閵娧呯暭濠电偛鐗婇崹鍓佺矚閸楃偐鏀介柛鈩冾殘椤︿即姊洪崨濠傜瑲妞ゃ劌顦垫俊?
                    $enrolledPolicies = [];
                    if ($personnel->other_insurance_policy_versions) {
                        $enrolledPolicies = json_decode($personnel->other_insurance_policy_versions, true) ?? [];
                    }
                    
                    // 婵犵妲呴崑鈧柛瀣崌閺岋紕浠︾拠鎻掑闂佷紮鑵归崜婵堢矙婢舵劦鏁傞柛鎰典簽椤旀棃姊洪崨濠庢畷婵炲弶锚鍗遍柟闂寸鐟欙箓鏌ㄩ弴妤€浜鹃梺鍛婂煀缁绘繂鐣烽敐澶婄＜婵炲棙鍨归鏃堟⒑閹稿海鈽夐柣妤佸姍閸┾偓妞ゆ巻鍋撻柛鐔峰暱椤﹦绱掑Ο灏栨敵濠电娀娼уΛ妤冪矆鐎ｎ喗鐓涢柛顐ｇ箥濡插綊鏌￠崨顐㈠姦鐎殿喚鏁婚幃銏犵暋閹殿喚袦闂備礁鎼ú鈺呭箵椤忓棙顫?
                    foreach ($policies as $policy) {
                        $policyId = $policy['id'] ?? null;
                        if (!$policyId) continue;
                        
                        // 闂?闂備胶顭堢换鎴炵箾婵犲洤鏋佹い鎾跺枔閳瑰秵銇勯弮鍥撻柡鍡╁弮閺屻劌鈽夊▎鎴犲彎缂備緡鍠氭繛鈧€殿噮鍋婇幃褔宕煎┑鍫涘亰闂佸湱鍘ч悺銊╁箲娓氣偓閻涱喛绠涘☉妯肩厬闂佸搫绋勬俊鍥╃不娴犲鐓曟俊銈勭劍缁€鍫熺箾閸喎鐏寸€殿喚鏁婚崺鈧い鎺戝濡﹢鏌ｅΔ鈧悧濠勭不閹烘鐓涘ù锝呮憸婢ф稒绻濋埀顒勬晸閻樿櫕娅栭梺鍓插亖閸ㄦ椽寮宠箛鎾佺懓顭ㄩ崘顭戝妷濡炪們鍎遍幊搴ㄦ箒闁诲函缍嗛崜娑㈠极閸撗呯＜闁规儳鎳撻铏圭磼?
                        $latestPolicy = \App\Models\OtherInsurancePolicy::find($policyId);
                        if ($latestPolicy) {
                            // 濠电偠鎻紞鈧繛澶嬫礋瀵偊濡舵径濠冪€梺缁橆殔閻楀棛绮婇敃鍌氱閻庢稒蓱鐏忣偊鏌ら崹顐㈢伌闁诡垰鍟村畷鐔碱敆娴ｉ甯涢梻浣告惈閸婄粯鏅跺Δ鈧敃銏＄瑹閳ь剙顕ｉ锕€閱囬柣鏃傤焾閻?
                            $policy['policy_start_date'] = $latestPolicy->policy_start_date ?? $latestPolicy->start_date;
                            $policy['policy_end_date'] = $latestPolicy->policy_end_date_new ?? $latestPolicy->end_date;
                        }
                        
                        // 闂?闂備礁鎲＄敮鍥磹閺嶎厼钃熼柛銉簵娴滃綊鏌熼幆褍鏆辨い銈呮嚇閺岋繝宕煎┑鍫濐暪闂佸摜鍋戦崐婵嗩嚕娴兼惌鏁嶆慨姗嗗幖閸撱劑姊洪棃鈺勭闁告柨顑囬幑銏狀潩鐠鸿櫣顢呴梺鍝勬川閸犳劗绮婚幒妤佺厸濞达絽鎽滄晶娑欑節閳ь剟鏁撻悩鑼槰?
                        $targetMonthStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
                        $targetMonthEnd = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();
                        $rawStartDate = $policy['policy_start_date'] ?? $policy['start_date'] ?? null;
                        $rawEndDate = $policy['policy_end_date'] ?? $policy['end_date'] ?? null;

                        if (empty($rawStartDate) || empty($rawEndDate)) {
                            continue;
                        }

                        $startDate = \Carbon\Carbon::parse($rawStartDate)->startOfDay();
                        $endDate = \Carbon\Carbon::parse($rawEndDate)->endOfDay();
                        $hasOverlap = $startDate->lte($targetMonthEnd) && $endDate->gte($targetMonthStart);

                        if (!$hasOverlap) {
                            // 濠电偞鍨堕幐鍝ョ矓閺夋埊鑰挎い蹇撴噽閳瑰秵绻濋棃娑欘棞缂佲偓鐎ｎ喗鐓涢柛顐ｇ箥濡插綊鏌￠崨顐㈠姦鐎殿喚鏁婚幃銏犵暋閻楀牊娈介梻浣瑰缁嬫垿鎯屾笟鈧幃婊堟晜閼恒儰姘?
                            \Log::info('Other insurance detail generation: policy out of effective period, skip', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'current_date' => $targetMonthStart->format('Y-m'),
                                'start_date' => $startDate ? $startDate->format('Y-m-d') : 'null',
                                'end_date' => $endDate ? $endDate->format('Y-m-d') : 'null'
                            ]);
                            continue;
                        }
                        
                        // 闂備焦鐪归崹濠氬窗閹版澘鍨傛慨姗嗗幘閳瑰秵绻濋棃娑欘棞缂佲偓鐎ｎ喗鐓曢柣鎴濇閻忕喓绱掗弮鈧幐鍐差嚕娴犲鐐婃い蹇撴椤岸姊洪幐搴ｂ姇闁活収鍠氶幑銏狀潩鐠鸿櫣顢呴梺鍝勫暟缁?+ 闂備礁鎼悧鍡浰囨导鏉戠柧闁靛鏅涚€?
                        $policyKey = self::generatePolicyKey($policy);
                        
                        if (!isset($enrolledPolicies[$policyKey])) {
                            // 闂佽崵濮村ú銏ゅ磿閹殿喗宕叉慨妯挎硾绾偓闂佸搫娴傛禍鐐电矆閸曨垱鐓欓悗娑欘焽婢ц櫕銇勯弴鐔峰摵闁诡啫鍥ч唶闁绘柨鎲＄€氭娊姊洪幐搴ｂ槈妞わ附婢橀敃銏ゎ敇閵忕姷鐓戦梺绋挎湰閻熝呯不娴犲鐓ユ繛鎴烆焽閻掑憡绻涢幘鍐差暢闁硅尙澧楅幏鍛存倻濡粯顏熼梻浣烘嚀閻°劑宕濋弽顬綁骞嬮悩鐢碉紲?
                            $policiesToEnroll[] = $policy;
                            $shouldEnroll = true;
                            
                            \Log::info('Other insurance detail generation: policy not enrolled yet, mark for enrollment', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'policy_key' => $policyKey,
                                'policy_name' => $policy['name'] ?? 'unknown',
                                'year' => $year,
                                'month' => $month
                            ]);
                        } else {
                            \Log::info('Other insurance detail generation: policy already enrolled, skip duplicate enrollment', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'policy_key' => $policyKey,
                                'enrolled_at' => $enrolledPolicies[$policyKey] ?? 'unknown',
                                'year' => $year,
                                'month' => $month
                            ]);
                        }
                    }
                    
                    // 闂備礁鎲￠悷顖涚濞嗘垶宕叉慨妯垮煐閸嬧晜绻涢崱妯诲鞍濞寸姵锕㈤幃鐑藉即濮橀硸妲悗娈垮櫘閸撴氨绮氶崡鐐╂斀闁割偆鍠撻弳鐘崇箾閿濆懏澶勭紒璇插€搁悾?
                    if (!empty($policiesToEnroll)) {
                        $otherInsurancePolicies = json_encode($policiesToEnroll);
                    } else {
                        $otherInsurancePolicies = '';
                    }
                } else {
                    $otherInsurancePolicies = '';
                }
            }
            
            // 闂備礁鎲＄敮妤冪矙閹寸姷纾介柟鎹愵嚙濡﹢鏌熷畡鎵劸闁哥偛澧庨幉?
            $record = self::create([
                'insurance_personnel_id' => $personnel->id,
                'employee_id' => $personnel->employee_id,
                'employee_name' => $personnel->employee_name,
                'employee_id_number' => $personnel->employee_id_number,
                'employee_gender' => $personnel->employee_gender,
                'employee_birth_date' => $personnel->employee_birth_date,
                'employee_phone' => $personnel->employee_phone,
                'project_id' => $personnel->project_id,
                'project_name' => $personnel->project ? $personnel->project->name : null,
                'account_set_id' => $personnel->account_set_id,
                'record_year' => $year,
                'record_month' => $month,
                'employee_social_security_base' => $personnel->employee_social_security_base,
                'employee_medical_insurance_base' => $personnel->employee_medical_insurance_base,
                'employee_housing_fund_base' => $personnel->employee_housing_fund_base,
                'employee_large_medical_base' => $personnel->employee_large_medical_base,
                'social_security_types' => $personnel->social_security_types,
                'medical_insurance_types' => $personnel->medical_insurance_types,
                'housing_fund_params' => $personnel->housing_fund_params,
                'other_insurance_policies' => $otherInsurancePolicies,
                'large_medical_insurance_config' => $normalizedLargeMedicalConfig,
                'status' => 'generated',
                'generated_at' => now(),
            ]);

            // 濠电姷顣介埀顒€鍟块埀顒€缍婇幃妯诲緞閹邦剙鐝樻繝銏ｆ硾椤戝棝鎮￠埀顒勬⒑閸涘﹦鎳冮柛濠囶棑閹广垹顫濋懜闈涘壄闂佸憡娲︽禍鐐电不娴犲鐓曟俊銈勭劍缁岃法绱掓潏銊х畼闁瑰嘲顑夊鍓佹崉閵婎灝顓㈡⒑閸涘﹦鎳冮柛濠囶棑閹广垹顫濋鐘敵闂佹枼鏅涢崯鈺冩?
            if ($shouldEnroll && !empty($policiesToEnroll)) {
                $now = now();
                
                // 闂備礁鍚嬮崕鎶藉床閼艰翰浜归柛銉戝苯鏅犻梺鍦帛鐢帒螞濡懙搴ㄥ炊閵娧呯暤闂佸壊鐓堟禍鐐哄箖?
                $enrolledPolicies = [];
                if ($personnel->other_insurance_policy_versions) {
                    $enrolledPolicies = json_decode($personnel->other_insurance_policy_versions, true) ?? [];
                }
                
                // 婵犵數鍎戠紞鈧い鏇嗗嫭鍙忛柣鎰惈濡﹢鏌℃径濠勪虎婵☆偅锚鑿愰柛銉ｅ妿缁犳ɑ绻涢崼鐔风仸缂佸苯宕埞鎴﹀幢濡ゅ喚妲归梻浣瑰缁嬫垿鎮ч崨顔剧闁搞儺鍓氶崑銊╂煏婵炲灝鈧绮绘禒瀣厱婵°倓鐒﹂‖濯弝闂備焦瀵х粙鎴︽嚐椤栨壕鍋撻崹顐€跨€规洏鍎甸、鏇㈡晲閸℃瑧鏆㈤梻浣芥〃缁€渚€鎮ф繝鍌﹁€块柨鐔哄У閺?
                foreach ($policiesToEnroll as $policy) {
                    $policyKey = self::generatePolicyKey($policy);
                    $enrolledPolicies[$policyKey] = $now->format('Y-m-d H:i:s');
                }
                
                // 濠电儑绲藉ú锔炬崲閸岀偞鍋ら柕濞炬櫅閻鏌涚仦鍓ф噮缂佺姳绮欓幃瑙勬媴鐟欏嫮鍑＄紓?
                $personnel->other_insurance_enrolled_at = $now; // 闂佽崵濮抽悞锕€顭垮Ο鑲╃鐎广儱顦€氬鏌嶈閸撶喎鐣烽妷锔藉劅闁炽儴娅曢ˉ婵囩箾閿濆懏澶勭紒璇插暙椤啴宕掗悙绮规嫽?
                $personnel->other_insurance_policy_versions = json_encode($enrolledPolicies);
                $personnel->save();
                
                \Log::info('Other insurance enrollment versions initialized on first detail generation', [
                    'employee_id' => $personnel->employee_id,
                    'personnel_id' => $personnel->id,
                    'enrolled_policies' => $enrolledPolicies,
                    'policy_count' => count($policiesToEnroll)
                ]);
            }

            // 闂佽崵濮崇欢銈囨閺囥垺鍋╁┑鐘崇閻撳倿鏌熸潏鎯х槣闁?
            $record->calculateAmounts();
            
            return $record;
        }
    }

    /**
     * 闂備焦鐪归崹濠氬窗閹版澘鍨傛慨姗嗗幘閳瑰秵绻濋棃娑欘棞缂佲偓鐎ｎ喗鐓曢柣鎴濇閻忕喓绱掗弮鈧幐鍐差嚕娴犲鐐婃い蹇撴椤岸姊洪幐搴ｂ槈闁绘鎳愰幑銏狀潩鐠鸿櫣顢呴梺鍝勫暟缁?+ 闂備礁鎼悧鍡浰囨导鏉戠柧闁靛鏅涚€氬鏌ｉ弴鐐测偓鑽ょ矆?
     * 闂備焦妞垮鍧楀礉鐎ｎ剝濮虫い鎺戝缁€宀勬煕濠靛棗顏╅柣搴☆煼閺屾稑顫濋鈧〃娆戠磼閺傝法鎽犵紒灞藉船閳规垿宕卞Δ鍐炬Ч闂備焦鐪归崝宀€鈧凹浜炵划顓熷緞閹邦剝袝閻庡厜鍋撻柍褜鍓氶弲璺何旈崨顓炵彉濡炪倖甯掗敃锔剧矆閸曨厾纾藉〒姘搐楠炴﹢鎮楃憴鍕枙闁?
     */
    /**
     * Normalize large medical snapshot so detail amount calculation has consistent inputs.
     */
    private static function normalizeLargeMedicalConfigSnapshot($personnel)
    {
        $config = [];
        if (!empty($personnel->large_medical_insurance_config)) {
            $decoded = json_decode($personnel->large_medical_insurance_config, true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        $employeeBase = $personnel->employee_large_medical_base;
        $companyBase = $personnel->employee_large_medical_company_base;
        if (is_null($companyBase) || $companyBase === '') {
            $companyBase = $employeeBase;
        }

        $config['is_enabled'] = (bool) $personnel->large_medical_insurance_enabled;
        if (!is_null($employeeBase) && $employeeBase !== '') {
            $config['employee_base'] = (float) $employeeBase;
        }
        if (!is_null($companyBase) && $companyBase !== '') {
            $config['company_base'] = (float) $companyBase;
        }

        return json_encode($config, JSON_UNESCAPED_UNICODE);
    }

    private static function generatePolicyKey($policy)
    {
        $policyId = $policy['id'] ?? 'unknown';
        $startDate = $policy['policy_start_date'] ?? $policy['start_date'] ?? '';
        $endDate = $policy['policy_end_date'] ?? $policy['end_date'] ?? '';
        
        // 闂備焦鐪归崹濠氬窗閹版澘鍨傛慨妯挎硾閼歌銇勯弽銊ф噮妞ゅ繒婀奺y: policy_id_startdate_enddate
        // 濠电偞鎸婚懝楣冾敄閸涙番鈧? 3_20250101_20251231
        $key = $policyId;
        if ($startDate && $endDate) {
            // 闂備礁婀辩划顖炲礉閺嚶颁汗闁搞儺鍓欑猾宥夋煏婢舵盯妾柛鈺佸€垮娲敊閸濆嫬顬堥梺鎼炲妼闁帮綁寮澶婇唶婵犻潧妫涢宀勬⒑閻熺増鎯堟い锔芥緲椤啴宕掗悙绮规嫽闂佸湱铏庨崰鏍礉閵夆晜鐓曢柟鐑樺灦閿涙梻绱?
            $startDate = substr($startDate, 0, 10); // 闂備礁鎲￠悷锕傛偋閺囩喐娅?0濠电偠鎻徊鐣岀矙閸曨厽顫曢柣褜娅塝Y-MM-DD
            $endDate = substr($endDate, 0, 10);
            
            $key .= '_' . str_replace('-', '', $startDate) . '_' . str_replace('-', '', $endDate);
        }
        
        return $key;
    }

    // 闂佽崵濮崇欢銈囨閺囥垺鍋╁┑鐘宠壘鐟欙箓鏌涢弴銊ュ季婵炲吋鑹捐彁闁搞儯鍔庣粻鐐寸箾閸涱喗宕岄柡灞界焸楠炲鏁傜紒妯荤翻 - 闂備胶鍎甸弲娑㈡偤閵娧勬殰閻庢稒蓱婵挳鎮归幁鎺戝闁哄棗绻橀弻锟犲磼濞戞﹩鈧粓鏌曢崱妤€鏆熼柍褜鍓氱粙鎺椼€冮崱娑辨晩鐎光偓閸曨剚娅栭柣蹇曞仧閸嬫捇鏁撻妷鈺傗拻闁搞儻绲芥禍楣冩⒒娴ｇ懓绲荤紒澶屾暬瀵粯绻濋崟顒€顎涢梺鎸庣☉鐎氼噣寮?
    public function calculateAmounts()
    {
        // 濠电偛顕慨瀛橆殽閹间礁绠甸柍鍝勬噺閸嬪倻鎲告惔銊ユ辈闁绘梻鍘х粻鍙夈亜閺囨浜鹃梺闈╃稻閹倸顕ｉ鍕鐎光偓婵犲倹鍊庣紓鍌欒兌婵敻銆冮崱妯绘珷鐎广儱顦€氬顭块懜闈涘婵炲懌鍔岃灋?
        $amounts = $this->calculateInsuranceAmounts();
        
        $this->update([
            'social_security_company_amount' => $amounts['social_security']['company'],
            'social_security_employee_amount' => $amounts['social_security']['employee'],
            'medical_insurance_company_amount' => $amounts['medical_insurance']['company'],
            'medical_insurance_employee_amount' => $amounts['medical_insurance']['employee'],
            'housing_fund_company_amount' => $amounts['housing_fund']['company'],
            'housing_fund_employee_amount' => $amounts['housing_fund']['employee'],
            'large_medical_company_amount' => $amounts['large_medical']['company'],
            'large_medical_employee_amount' => $amounts['large_medical']['employee'],
            'other_insurance_total_amount' => $amounts['other_insurance']['total'],
        ]);
    }

    // 闂佽崵濮崇欢銈囨閺囥垺鍋╃紓浣诡焽閳瑰秵绻濋棃娑卞剱闁绘繐绠撳娲矗婢跺﹦浼屽銈冨€曢崐鍧楀箚閸愵喖绀嬫い鎰╁灩缁额喗绻涙潏鍓хɑ闁圭懓娲崺鈧い鎺嶇劍椤绱?
    private function calculateInsuranceAmounts()
    {
        $amounts = [
            'social_security' => ['company' => 0, 'employee' => 0],
            'medical_insurance' => ['company' => 0, 'employee' => 0],
            'housing_fund' => ['company' => 0, 'employee' => 0],
            'large_medical' => ['company' => 0, 'employee' => 0],
            'other_insurance' => ['total' => 0],
        ];

        // 闂佽崵濮崇欢銈囨閺囥垺鍋╅柤濮愬€楅惌鍡涙煕濞嗗秴鍔ょ紒鐘辩矙濮婃椽宕ｆ径濠勪紝濡?
        if ($this->social_security_types && $this->employee_social_security_base) {
            $socialSecurityTypes = json_decode($this->social_security_types, true);
            if (is_array($socialSecurityTypes)) {
                foreach ($socialSecurityTypes as $type) {
                    $base = $this->employee_social_security_base;
                    $companyRatio = $type['company_ratio'] ?? 0;
                    $employeeRatio = $type['employee_ratio'] ?? 0;
                    
                    $amounts['social_security']['company'] += $base * $companyRatio;
                    $amounts['social_security']['employee'] += $base * $employeeRatio;
                }
            }
        }

        // 闂佽崵濮崇欢銈囨閺囥垺鍋╁┑鐘宠壘缁€宀勬偣娴ｅ摜锛嶇紒鐘辩矙濮婃椽宕ｆ径濠勪紝濡?
        if ($this->medical_insurance_types && $this->employee_medical_insurance_base) {
            $medicalInsuranceTypes = json_decode($this->medical_insurance_types, true);
            if (is_array($medicalInsuranceTypes)) {
                foreach ($medicalInsuranceTypes as $type) {
                    $base = $this->employee_medical_insurance_base;
                    $companyRatio = $type['company_ratio'] ?? 0;
                    $employeeRatio = $type['employee_ratio'] ?? 0;
                    
                    $amounts['medical_insurance']['company'] += $base * $companyRatio;
                    $amounts['medical_insurance']['employee'] += $base * $employeeRatio;
                }
            }
        }

        // 闂佽崵濮崇欢銈囨閺囥垺鍋╁┑鐘宠壘缁€鍌涖亜閹烘垵鈧劕鈹戠€ｎ偆鍘掗梺纭呮彧缁犳垵鈻嶉妸銉?
        if ($this->housing_fund_params && $this->employee_housing_fund_base) {
            $housingFundParams = json_decode($this->housing_fund_params, true);
            if (is_array($housingFundParams)) {
                $base = $this->employee_housing_fund_base;
                $companyRatio = $housingFundParams['company_ratio'] ?? 0;
                $employeeRatio = $housingFundParams['employee_ratio'] ?? 0;
                
                $amounts['housing_fund']['company'] = $base * $companyRatio;
                $amounts['housing_fund']['employee'] = $base * $employeeRatio;
            }
        }

        // 闂佽崵濮崇欢銈囨閺囥垺鍋╃紓浣姑欢鐐垫喐閺冨煪鐑藉磼閻愯尙顦遍梺鐓庮潟閸婃牠鎮樺鍚冲酣宕堕妸褏鐤勫┑鐐叉噺閹瑰洭寮诲畝鍕闁挎梻绮鎾绘⒑閹稿海鈽夐柣妤€锕顐︻敋閳ь剟鐛幇顓熷濡炲楠哥粭鈥斥攽閻愬弶顥為柛搴㈠▕楠炲﹪宕卞☉妯虹彉闂佺粯娲栭崐鑽ょ矆?
        // 婵犵數鍋涢ˇ顓㈠礉瀹ュ绀堝ù鐓庣摠閺咁剚鎱ㄥ鍡楀缂佷礁銈搁幃妤冩喆閸曨偄鎽甸梺闈╃悼閸嬫捇鏁嶉幇顑芥斀闁搞倝鈧稓顦︽い鏇秬缁犳稒鎯旈敐鍥ㄥ枓闂傚倸鍊稿ú鐘诲磻閹剧粯鍋￠柡鍥ㄦ皑椤︼箓鏌ｉ弬鍝勪壕闂備浇妗ㄧ粈浣规償濠婂懏顫曟繝闈涱儏缁犮儵鏌嶈閸撴氨绮嬮幒妤€唯闁靛牆娲ㄩ幉褰掓⒑閻撳骸鏆遍柍褜鍓涢崳銉ッ洪敐鍡樺弿?employee_large_medical_base 闂傚鍋勫ú銈夊箠濮椻偓婵＄绠涘☉妯虹彉婵犮垼娉涜墝闁?
        if ($this->large_medical_insurance_config) {
            $largeMedicalConfig = json_decode($this->large_medical_insurance_config, true);
            if (is_array($largeMedicalConfig)) {
                $isEnabled = array_key_exists('is_enabled', $largeMedicalConfig)
                    ? (bool) $largeMedicalConfig['is_enabled']
                    : true;

                if ($isEnabled) {
                    $calculationType = $largeMedicalConfig['calculation_type'] ?? 'base';
                    $isPaymentMonth = app(LargeMedicalPaymentCycleService::class)->isPaymentMonth(
                        $this->employee_id,
                        (int) $this->record_year,
                        (int) $this->record_month,
                        $this->project_id,
                        $this->account_set_id
                    );

                    if ($isPaymentMonth) {
                        if ($calculationType === 'fixed') {
                            $amounts['large_medical']['company'] = floatval($largeMedicalConfig['company_amount'] ?? 0);
                            $amounts['large_medical']['employee'] = floatval($largeMedicalConfig['employee_amount'] ?? 0);
                        } else {
                            $employeeBase = floatval($largeMedicalConfig['employee_base'] ?? $this->employee_large_medical_base ?? 0);
                            $companyBase = floatval($largeMedicalConfig['company_base'] ?? $employeeBase);
                            $companyRatio = floatval($largeMedicalConfig['company_ratio'] ?? 0);
                            $employeeRatio = floatval($largeMedicalConfig['employee_ratio'] ?? 0);

                            $amounts['large_medical']['company'] = $companyBase * $companyRatio;
                            $amounts['large_medical']['employee'] = $employeeBase * $employeeRatio;
                        }
                    }
                }
            }
        }

        // 闂佽崵濮崇欢銈囨閺囥垺鍋╁┑鐘宠壘缁€鍌炴煛閸垺鏆╅梺鎯у€歌彁闁搞儯鍔庣粻鐐寸箾閸涱喗宕岄柡灞界焸楠炲鏁傜紒妯荤翻
        if ($this->other_insurance_policies) {
            $otherInsurancePolicies = json_decode($this->other_insurance_policies, true);
            if (is_array($otherInsurancePolicies)) {
                foreach ($otherInsurancePolicies as $policy) {
                    $amounts['other_insurance']['total'] += $policy['employee_per_capita_cost'] ?? 0;
                }
            }
        }

        return $amounts;
    }
}
