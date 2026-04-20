"use strict";
const common_vendor = require("../../common/vendor.js");
const api_registration = require("../../api/registration.js");
const SignatureCanvas = () => "../../components/SignatureCanvas.js";
const _sfc_main = {
  components: { SignatureCanvas },
  data() {
    return {
      formData: {
        // 头部信息
        fill_date: "",
        entry_position: "",
        entry_date: "",
        department: "",
        job_title: "",
        housing_fund_account: "",
        bank_account: "",
        bank_name: "",
        // 一、个人资料
        name: "",
        english_name: "",
        gender: "male",
        height: "",
        birth_date: "",
        political_status: "",
        education_level: "",
        native_place: "",
        marital_status: "",
        has_children: "",
        id_number: "",
        household_type: "",
        current_address: "",
        postal_code: "",
        household_address: "",
        contact_phone: "",
        document_address: "",
        disability_level: "",
        // 二、个人技能
        language_skills: [],
        engineering_skills: [],
        engineering_other: "",
        professional_title: "",
        hobbies: [],
        hobby_other: "",
        other_skills: "",
        // 三、教育情况
        education_history: [],
        // 四、工作履历
        work_history: [],
        reference_company: "",
        reference_contact: "",
        // 五、奖罚情况
        rewards_punishments: "",
        // 六、家庭情况
        family_members: [],
        // 七、紧急联系方式
        emergency_contact1_name: "",
        emergency_contact1_relation: "",
        emergency_contact1_phone: "",
        emergency_contact2_name: "",
        emergency_contact2_relation: "",
        emergency_contact2_phone: "",
        // 八、其他情况
        mental_illness: "无",
        mental_illness_detail: "",
        other_illness: "无",
        other_illness_detail: "",
        hospitalized_recently: "无",
        hospitalized_reason: "",
        criminal_record: "无",
        criminal_record_time: "",
        employment_documents: [],
        // 九、其他需要说明的情况
        remarks: "",
        // 十、其他需要核实的情况
        is_pregnant: "无",
        pregnant_detail: "",
        accept_overtime: "接受",
        need_accommodation: "无",
        accommodation_detail: "",
        has_driving_license: "无",
        driving_license_detail: "",
        // 签名
        signature: "",
        signaturePath: "",
        signature_date: ""
      },
      submitting: false,
      // 选项数组
      politicalOptions: ["群众", "中共党员", "中共预备党员", "共青团员", "民革党员", "民盟盟员", "民建会员", "民进会员", "农工党党员", "致公党党员", "九三学社社员", "台盟盟员", "无党派人士"],
      politicalIndex: 0,
      educationOptions: ["小学", "初中", "高中", "中专", "大专", "本科", "硕士", "博士"],
      educationIndex: 0,
      maritalOptions: ["未婚", "已婚", "离婚"],
      maritalIndex: 0,
      childrenOptions: ["无", "女孩", "男孩", "女孩和男孩"],
      childrenIndex: 0,
      householdOptions: ["城镇", "非城镇"],
      householdIndex: 0,
      englishOptions: ["四级", "六级", "托福", "雅思"],
      engineeringOptions: ["电工证", "高压证"],
      titleOptions: ["无", "初级", "中级", "高级"],
      titleIndex: 0,
      hobbyOptions: ["唱歌", "棋类", "球类"],
      employmentDocOptions: ["劳动手册", "离职证明", "应届毕业", "下岗/协保证明"],
      nativePlaceArray: []
    };
  },
  onLoad() {
    const today = /* @__PURE__ */ new Date();
    const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
    this.formData.fill_date = dateStr;
    this.formData.signature_date = dateStr;
    this.loadSavedData();
  },
  methods: {
    // 加载已保存的数据
    async loadSavedData() {
      try {
        const res = await api_registration.getMyRegistrationForm();
        if (res.success && res.data) {
          const data = res.data;
          if (data.signature) {
            this.formData.signature = data.signature;
            if (data.signature.includes("/uploads/signatures/")) {
              const match = data.signature.match(/\/uploads\/signatures\/(.+)$/);
              if (match) {
                this.formData.signaturePath = "uploads/signatures/" + match[1];
              }
            } else if (data.signature.includes("/storage/")) {
              const match = data.signature.match(/\/storage\/(.+)$/);
              if (match) {
                this.formData.signaturePath = match[1];
              }
            }
          }
          const { signature, ...otherData } = data;
          this.formData = { ...this.formData, ...otherData };
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/registration/index.vue:672", "加载数据失败:", error);
      }
    },
    // 日期格式化
    formatDateForPicker(dateStr) {
      if (!dateStr)
        return "";
      if (dateStr.includes("T"))
        return dateStr.split("T")[0];
      return dateStr;
    },
    formatDateDisplay(dateStr) {
      if (!dateStr)
        return "";
      if (dateStr.includes("T"))
        return dateStr.split("T")[0];
      return dateStr;
    },
    // 各种选择器变化处理
    onFillDateChange(e) {
      this.formData.fill_date = e.detail.value;
    },
    onEntryDateChange(e) {
      this.formData.entry_date = e.detail.value;
    },
    onGenderChange(e) {
      this.formData.gender = e.detail.value;
    },
    onPoliticalChange(e) {
      this.politicalIndex = e.detail.value;
      this.formData.political_status = this.politicalOptions[e.detail.value];
    },
    onEducationChange(e) {
      this.educationIndex = e.detail.value;
      this.formData.education_level = this.educationOptions[e.detail.value];
    },
    onNativePlaceChange(e) {
      this.nativePlaceArray = e.detail.value;
      this.formData.native_place = e.detail.value.join("");
    },
    onMaritalChange(e) {
      this.maritalIndex = e.detail.value;
      this.formData.marital_status = ["single", "married", "divorced"][e.detail.value];
    },
    onChildrenChange(e) {
      this.childrenIndex = e.detail.value;
      this.formData.has_children = this.childrenOptions[e.detail.value];
    },
    onHouseholdChange(e) {
      this.householdIndex = e.detail.value;
      this.formData.household_type = ["urban", "rural"][e.detail.value];
    },
    onTitleChange(e) {
      this.titleIndex = e.detail.value;
      this.formData.professional_title = this.titleOptions[e.detail.value];
    },
    onSignatureDateChange(e) {
      this.formData.signature_date = e.detail.value;
    },
    getMaritalText(val) {
      const map = { single: "未婚", married: "已婚", divorced: "离婚" };
      return map[val] || val;
    },
    getHouseholdText(val) {
      const map = { urban: "城镇", rural: "非城镇" };
      return map[val] || val;
    },
    // 身份证号码变化
    onIdNumberChange() {
      const idNumber = this.formData.id_number;
      if (!idNumber || idNumber.length !== 18)
        return;
      if (!this.validateIdNumber(idNumber)) {
        common_vendor.index.showToast({ title: "请输入正确的身份证号码", icon: "none" });
        return;
      }
      const birthYear = idNumber.substring(6, 10);
      const birthMonth = idNumber.substring(10, 12);
      const birthDay = idNumber.substring(12, 14);
      this.formData.birth_date = `${birthYear}-${birthMonth}-${birthDay}`;
      const genderCode = parseInt(idNumber.substring(16, 17));
      this.formData.gender = genderCode % 2 === 1 ? "male" : "female";
    },
    validateIdNumber(idNumber) {
      if (!idNumber || idNumber.length !== 18)
        return false;
      const reg = /^[1-9]\d{5}(19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/;
      if (!reg.test(idNumber))
        return false;
      const weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
      const checkCodes = ["1", "0", "X", "9", "8", "7", "6", "5", "4", "3", "2"];
      let sum = 0;
      for (let i = 0; i < 17; i++)
        sum += parseInt(idNumber[i]) * weights[i];
      return idNumber[17].toUpperCase() === checkCodes[sum % 11];
    },
    // 多选切换
    toggleLanguageSkill(item) {
      if (!this.formData.language_skills)
        this.formData.language_skills = [];
      const idx = this.formData.language_skills.indexOf(item);
      if (idx > -1)
        this.formData.language_skills.splice(idx, 1);
      else
        this.formData.language_skills.push(item);
    },
    toggleEngineeringSkill(item) {
      if (!this.formData.engineering_skills)
        this.formData.engineering_skills = [];
      const idx = this.formData.engineering_skills.indexOf(item);
      if (idx > -1)
        this.formData.engineering_skills.splice(idx, 1);
      else
        this.formData.engineering_skills.push(item);
    },
    toggleHobby(item) {
      if (!this.formData.hobbies)
        this.formData.hobbies = [];
      const idx = this.formData.hobbies.indexOf(item);
      if (idx > -1)
        this.formData.hobbies.splice(idx, 1);
      else
        this.formData.hobbies.push(item);
    },
    toggleEmploymentDoc(item) {
      if (!this.formData.employment_documents)
        this.formData.employment_documents = [];
      const idx = this.formData.employment_documents.indexOf(item);
      if (idx > -1)
        this.formData.employment_documents.splice(idx, 1);
      else
        this.formData.employment_documents.push(item);
    },
    // 添加/删除列表项
    addEducation() {
      this.formData.education_history.push({ date_range: "", school_major: "", certificate: "" });
    },
    deleteEducation(index) {
      this.formData.education_history.splice(index, 1);
    },
    addWorkHistory() {
      this.formData.work_history.push({ date_range: "", company: "", position: "", salary: "", leave_reason: "" });
    },
    deleteWorkHistory(index) {
      this.formData.work_history.splice(index, 1);
    },
    addFamilyMember() {
      this.formData.family_members.push({ name: "", relation: "", age: "", employer: "", phone: "" });
    },
    deleteFamilyMember(index) {
      this.formData.family_members.splice(index, 1);
    },
    // 签名变化
    onSignatureChange(data) {
      this.formData.signature = data.url;
      this.formData.signaturePath = data.path;
    },
    // 填充示例数据
    fillSampleData() {
      common_vendor.index.showModal({
        title: "提示",
        content: "确定要填充示例数据吗？这将覆盖当前已填写的内容。",
        success: (res) => {
          if (res.confirm) {
            const today = /* @__PURE__ */ new Date();
            const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
            this.formData = {
              ...this.formData,
              fill_date: dateStr,
              entry_position: "软件工程师",
              entry_date: dateStr,
              department: "技术部",
              job_title: "工程师",
              housing_fund_account: "1234567890",
              bank_account: "6222021234567890123",
              bank_name: "中国工商银行北京分行",
              name: "张三",
              english_name: "Zhang San",
              gender: "male",
              height: "175",
              birth_date: "1990-05-15",
              id_number: "110101199005150015",
              political_status: "群众",
              education_level: "本科",
              native_place: "北京市",
              marital_status: "married",
              has_children: "男孩",
              household_type: "urban",
              current_address: "北京市朝阳区某某街道某某号",
              postal_code: "100000",
              household_address: "北京市朝阳区",
              contact_phone: "13800138000",
              document_address: "北京市朝阳区某某街道某某号",
              disability_level: "",
              language_skills: ["四级", "六级"],
              engineering_skills: [],
              professional_title: "中级",
              hobbies: ["球类"],
              other_skills: "熟悉Java、Python等编程语言",
              education_history: [
                { date_range: "2008.09-2012.06", school_major: "北京大学 计算机科学", certificate: "学士学位" }
              ],
              work_history: [
                { date_range: "2012.07-2020.06", company: "某某科技公司", position: "软件工程师", salary: "15000", leave_reason: "个人发展" }
              ],
              reference_company: "某某科技公司",
              reference_contact: "李经理 13900139000",
              rewards_punishments: "2018年获得优秀员工称号",
              family_members: [
                { name: "张父", relation: "父亲", age: "55", employer: "某某公司", phone: "13900139001" }
              ],
              emergency_contact1_name: "张父",
              emergency_contact1_relation: "父亲",
              emergency_contact1_phone: "13900139001",
              emergency_contact2_name: "李四",
              emergency_contact2_relation: "配偶",
              emergency_contact2_phone: "13900139002",
              mental_illness: "无",
              other_illness: "无",
              hospitalized_recently: "无",
              criminal_record: "无",
              employment_documents: ["离职证明"],
              remarks: "希望在公司长期发展",
              is_pregnant: "无",
              accept_overtime: "接受",
              need_accommodation: "无",
              has_driving_license: "有",
              driving_license_detail: "C1",
              signature_date: dateStr
            };
            common_vendor.index.showToast({ title: "示例数据已填充", icon: "success" });
          }
        }
      });
    },
    // 提交表单
    async submitForm() {
      if (!this.formData.name) {
        common_vendor.index.showToast({ title: "请输入姓名", icon: "none" });
        return;
      }
      if (!this.formData.id_number) {
        common_vendor.index.showToast({ title: "请输入身份证号码", icon: "none" });
        return;
      }
      if (!this.validateIdNumber(this.formData.id_number)) {
        common_vendor.index.showToast({ title: "请输入正确的身份证号码", icon: "none" });
        return;
      }
      if (!this.formData.signaturePath) {
        common_vendor.index.showToast({ title: "请先完成手写签名", icon: "none" });
        return;
      }
      this.submitting = true;
      try {
        const submitData = { ...this.formData };
        submitData.signature = this.formData.signaturePath;
        delete submitData.signaturePath;
        const res = await api_registration.submitRegistrationForm(submitData);
        if (res.success) {
          common_vendor.index.showToast({ title: "提交成功", icon: "success" });
          setTimeout(() => {
            common_vendor.index.navigateBack();
          }, 1500);
        } else {
          common_vendor.index.showToast({ title: res.message || "提交失败", icon: "none" });
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/registration/index.vue:925", "提交失败:", error);
        common_vendor.index.showToast({ title: "提交失败，请重试", icon: "none" });
      } finally {
        this.submitting = false;
      }
    }
  }
};
if (!Array) {
  const _component_SignatureCanvas = common_vendor.resolveComponent("SignatureCanvas");
  _component_SignatureCanvas();
}
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  return common_vendor.e({
    a: common_vendor.o((...args) => $options.fillSampleData && $options.fillSampleData(...args)),
    b: common_vendor.t($options.formatDateDisplay($data.formData.fill_date) || "请选择日期"),
    c: $options.formatDateForPicker($data.formData.fill_date),
    d: common_vendor.o((...args) => $options.onFillDateChange && $options.onFillDateChange(...args)),
    e: $data.formData.entry_position,
    f: common_vendor.o(($event) => $data.formData.entry_position = $event.detail.value),
    g: common_vendor.t($options.formatDateDisplay($data.formData.entry_date) || "请选择日期"),
    h: $options.formatDateForPicker($data.formData.entry_date),
    i: common_vendor.o((...args) => $options.onEntryDateChange && $options.onEntryDateChange(...args)),
    j: $data.formData.department,
    k: common_vendor.o(($event) => $data.formData.department = $event.detail.value),
    l: $data.formData.job_title,
    m: common_vendor.o(($event) => $data.formData.job_title = $event.detail.value),
    n: $data.formData.housing_fund_account,
    o: common_vendor.o(($event) => $data.formData.housing_fund_account = $event.detail.value),
    p: $data.formData.bank_account,
    q: common_vendor.o(($event) => $data.formData.bank_account = $event.detail.value),
    r: $data.formData.bank_name,
    s: common_vendor.o(($event) => $data.formData.bank_name = $event.detail.value),
    t: $data.formData.name,
    v: common_vendor.o(($event) => $data.formData.name = $event.detail.value),
    w: $data.formData.english_name,
    x: common_vendor.o(($event) => $data.formData.english_name = $event.detail.value),
    y: $data.formData.gender === "male",
    z: $data.formData.gender === "female",
    A: common_vendor.o((...args) => $options.onGenderChange && $options.onGenderChange(...args)),
    B: $data.formData.height,
    C: common_vendor.o(($event) => $data.formData.height = $event.detail.value),
    D: common_vendor.o((...args) => $options.onIdNumberChange && $options.onIdNumberChange(...args)),
    E: $data.formData.id_number,
    F: common_vendor.o(($event) => $data.formData.id_number = $event.detail.value),
    G: common_vendor.t($options.formatDateDisplay($data.formData.birth_date) || "输入身份证后自动获取"),
    H: common_vendor.t($data.formData.political_status || "请选择政治面貌"),
    I: $data.politicalOptions,
    J: $data.politicalIndex,
    K: common_vendor.o((...args) => $options.onPoliticalChange && $options.onPoliticalChange(...args)),
    L: common_vendor.t($data.formData.education_level || "请选择文化程度"),
    M: $data.educationOptions,
    N: $data.educationIndex,
    O: common_vendor.o((...args) => $options.onEducationChange && $options.onEducationChange(...args)),
    P: common_vendor.t($data.formData.native_place || "请选择籍贯"),
    Q: $data.nativePlaceArray,
    R: common_vendor.o((...args) => $options.onNativePlaceChange && $options.onNativePlaceChange(...args)),
    S: common_vendor.t($options.getMaritalText($data.formData.marital_status) || "请选择婚姻状况"),
    T: $data.maritalOptions,
    U: $data.maritalIndex,
    V: common_vendor.o((...args) => $options.onMaritalChange && $options.onMaritalChange(...args)),
    W: common_vendor.t($data.formData.has_children || "请选择"),
    X: $data.childrenOptions,
    Y: $data.childrenIndex,
    Z: common_vendor.o((...args) => $options.onChildrenChange && $options.onChildrenChange(...args)),
    aa: common_vendor.t($options.getHouseholdText($data.formData.household_type) || "请选择户口状态"),
    ab: $data.householdOptions,
    ac: $data.householdIndex,
    ad: common_vendor.o((...args) => $options.onHouseholdChange && $options.onHouseholdChange(...args)),
    ae: $data.formData.current_address,
    af: common_vendor.o(($event) => $data.formData.current_address = $event.detail.value),
    ag: $data.formData.postal_code,
    ah: common_vendor.o(($event) => $data.formData.postal_code = $event.detail.value),
    ai: $data.formData.household_address,
    aj: common_vendor.o(($event) => $data.formData.household_address = $event.detail.value),
    ak: $data.formData.contact_phone,
    al: common_vendor.o(($event) => $data.formData.contact_phone = $event.detail.value),
    am: $data.formData.document_address,
    an: common_vendor.o(($event) => $data.formData.document_address = $event.detail.value),
    ao: $data.formData.disability_level,
    ap: common_vendor.o(($event) => $data.formData.disability_level = $event.detail.value),
    aq: common_vendor.f($data.englishOptions, (item, k0, i0) => {
      return {
        a: item,
        b: $data.formData.language_skills && $data.formData.language_skills.includes(item),
        c: common_vendor.o(($event) => $options.toggleLanguageSkill(item), item),
        d: common_vendor.t(item),
        e: item
      };
    }),
    ar: common_vendor.f($data.engineeringOptions, (item, k0, i0) => {
      return {
        a: item,
        b: $data.formData.engineering_skills && $data.formData.engineering_skills.includes(item),
        c: common_vendor.o(($event) => $options.toggleEngineeringSkill(item), item),
        d: common_vendor.t(item),
        e: item
      };
    }),
    as: $data.formData.engineering_other,
    at: common_vendor.o(($event) => $data.formData.engineering_other = $event.detail.value),
    av: common_vendor.t($data.formData.professional_title || "请选择职称"),
    aw: $data.titleOptions,
    ax: $data.titleIndex,
    ay: common_vendor.o((...args) => $options.onTitleChange && $options.onTitleChange(...args)),
    az: common_vendor.f($data.hobbyOptions, (item, k0, i0) => {
      return {
        a: item,
        b: $data.formData.hobbies && $data.formData.hobbies.includes(item),
        c: common_vendor.o(($event) => $options.toggleHobby(item), item),
        d: common_vendor.t(item),
        e: item
      };
    }),
    aA: $data.formData.hobby_other,
    aB: common_vendor.o(($event) => $data.formData.hobby_other = $event.detail.value),
    aC: $data.formData.other_skills,
    aD: common_vendor.o(($event) => $data.formData.other_skills = $event.detail.value),
    aE: common_vendor.o((...args) => $options.addEducation && $options.addEducation(...args)),
    aF: common_vendor.f($data.formData.education_history, (item, index, i0) => {
      return {
        a: common_vendor.t(index + 1),
        b: common_vendor.o(($event) => $options.deleteEducation(index), index),
        c: item.date_range,
        d: common_vendor.o(($event) => item.date_range = $event.detail.value, index),
        e: item.school_major,
        f: common_vendor.o(($event) => item.school_major = $event.detail.value, index),
        g: item.certificate,
        h: common_vendor.o(($event) => item.certificate = $event.detail.value, index),
        i: index
      };
    }),
    aG: common_vendor.o((...args) => $options.addWorkHistory && $options.addWorkHistory(...args)),
    aH: common_vendor.f($data.formData.work_history, (item, index, i0) => {
      return {
        a: common_vendor.t(index + 1),
        b: common_vendor.o(($event) => $options.deleteWorkHistory(index), index),
        c: item.date_range,
        d: common_vendor.o(($event) => item.date_range = $event.detail.value, index),
        e: item.company,
        f: common_vendor.o(($event) => item.company = $event.detail.value, index),
        g: item.position,
        h: common_vendor.o(($event) => item.position = $event.detail.value, index),
        i: item.salary,
        j: common_vendor.o(($event) => item.salary = $event.detail.value, index),
        k: item.leave_reason,
        l: common_vendor.o(($event) => item.leave_reason = $event.detail.value, index),
        m: index
      };
    }),
    aI: $data.formData.reference_company,
    aJ: common_vendor.o(($event) => $data.formData.reference_company = $event.detail.value),
    aK: $data.formData.reference_contact,
    aL: common_vendor.o(($event) => $data.formData.reference_contact = $event.detail.value),
    aM: $data.formData.rewards_punishments,
    aN: common_vendor.o(($event) => $data.formData.rewards_punishments = $event.detail.value),
    aO: common_vendor.o((...args) => $options.addFamilyMember && $options.addFamilyMember(...args)),
    aP: common_vendor.f($data.formData.family_members, (item, index, i0) => {
      return {
        a: common_vendor.t(index + 1),
        b: common_vendor.o(($event) => $options.deleteFamilyMember(index), index),
        c: item.name,
        d: common_vendor.o(($event) => item.name = $event.detail.value, index),
        e: item.relation,
        f: common_vendor.o(($event) => item.relation = $event.detail.value, index),
        g: item.age,
        h: common_vendor.o(($event) => item.age = $event.detail.value, index),
        i: item.employer,
        j: common_vendor.o(($event) => item.employer = $event.detail.value, index),
        k: item.phone,
        l: common_vendor.o(($event) => item.phone = $event.detail.value, index),
        m: index
      };
    }),
    aQ: $data.formData.emergency_contact1_name,
    aR: common_vendor.o(($event) => $data.formData.emergency_contact1_name = $event.detail.value),
    aS: $data.formData.emergency_contact1_relation,
    aT: common_vendor.o(($event) => $data.formData.emergency_contact1_relation = $event.detail.value),
    aU: $data.formData.emergency_contact1_phone,
    aV: common_vendor.o(($event) => $data.formData.emergency_contact1_phone = $event.detail.value),
    aW: $data.formData.emergency_contact2_name,
    aX: common_vendor.o(($event) => $data.formData.emergency_contact2_name = $event.detail.value),
    aY: $data.formData.emergency_contact2_relation,
    aZ: common_vendor.o(($event) => $data.formData.emergency_contact2_relation = $event.detail.value),
    ba: $data.formData.emergency_contact2_phone,
    bb: common_vendor.o(($event) => $data.formData.emergency_contact2_phone = $event.detail.value),
    bc: $data.formData.mental_illness === "无",
    bd: $data.formData.mental_illness === "有",
    be: common_vendor.o((e) => $data.formData.mental_illness = e.detail.value),
    bf: $data.formData.mental_illness === "有"
  }, $data.formData.mental_illness === "有" ? {
    bg: $data.formData.mental_illness_detail,
    bh: common_vendor.o(($event) => $data.formData.mental_illness_detail = $event.detail.value)
  } : {}, {
    bi: $data.formData.other_illness === "无",
    bj: $data.formData.other_illness === "有",
    bk: common_vendor.o((e) => $data.formData.other_illness = e.detail.value),
    bl: $data.formData.other_illness === "有"
  }, $data.formData.other_illness === "有" ? {
    bm: $data.formData.other_illness_detail,
    bn: common_vendor.o(($event) => $data.formData.other_illness_detail = $event.detail.value)
  } : {}, {
    bo: $data.formData.hospitalized_recently === "无",
    bp: $data.formData.hospitalized_recently === "有",
    bq: common_vendor.o((e) => $data.formData.hospitalized_recently = e.detail.value),
    br: $data.formData.hospitalized_recently === "有"
  }, $data.formData.hospitalized_recently === "有" ? {
    bs: $data.formData.hospitalized_reason,
    bt: common_vendor.o(($event) => $data.formData.hospitalized_reason = $event.detail.value)
  } : {}, {
    bv: $data.formData.criminal_record === "无",
    bw: $data.formData.criminal_record === "有",
    bx: common_vendor.o((e) => $data.formData.criminal_record = e.detail.value),
    by: $data.formData.criminal_record === "有"
  }, $data.formData.criminal_record === "有" ? {
    bz: $data.formData.criminal_record_time,
    bA: common_vendor.o(($event) => $data.formData.criminal_record_time = $event.detail.value)
  } : {}, {
    bB: common_vendor.f($data.employmentDocOptions, (item, k0, i0) => {
      return {
        a: item,
        b: $data.formData.employment_documents && $data.formData.employment_documents.includes(item),
        c: common_vendor.o(($event) => $options.toggleEmploymentDoc(item), item),
        d: common_vendor.t(item),
        e: item
      };
    }),
    bC: $data.formData.remarks,
    bD: common_vendor.o(($event) => $data.formData.remarks = $event.detail.value),
    bE: $data.formData.is_pregnant === "无",
    bF: $data.formData.is_pregnant === "有",
    bG: common_vendor.o((e) => $data.formData.is_pregnant = e.detail.value),
    bH: $data.formData.is_pregnant === "有"
  }, $data.formData.is_pregnant === "有" ? {
    bI: $data.formData.pregnant_detail,
    bJ: common_vendor.o(($event) => $data.formData.pregnant_detail = $event.detail.value)
  } : {}, {
    bK: $data.formData.accept_overtime === "接受",
    bL: $data.formData.accept_overtime === "不接受",
    bM: common_vendor.o((e) => $data.formData.accept_overtime = e.detail.value),
    bN: $data.formData.need_accommodation === "无",
    bO: $data.formData.need_accommodation === "有",
    bP: common_vendor.o((e) => $data.formData.need_accommodation = e.detail.value),
    bQ: $data.formData.need_accommodation === "有"
  }, $data.formData.need_accommodation === "有" ? {
    bR: $data.formData.accommodation_detail,
    bS: common_vendor.o(($event) => $data.formData.accommodation_detail = $event.detail.value)
  } : {}, {
    bT: $data.formData.has_driving_license === "无",
    bU: $data.formData.has_driving_license === "有",
    bV: common_vendor.o((e) => $data.formData.has_driving_license = e.detail.value),
    bW: $data.formData.has_driving_license === "有"
  }, $data.formData.has_driving_license === "有" ? {
    bX: $data.formData.driving_license_detail,
    bY: common_vendor.o(($event) => $data.formData.driving_license_detail = $event.detail.value)
  } : {}, {
    bZ: common_vendor.sr("signatureCanvas", "fc320273-0"),
    ca: common_vendor.o($options.onSignatureChange),
    cb: common_vendor.p({
      label: "申请人签名：",
      ["canvas-id"]: "registrationSignature",
      value: $data.formData.signature
    }),
    cc: common_vendor.t($options.formatDateDisplay($data.formData.signature_date) || "请选择日期"),
    cd: $options.formatDateForPicker($data.formData.signature_date),
    ce: common_vendor.o((...args) => $options.onSignatureDateChange && $options.onSignatureDateChange(...args)),
    cf: common_vendor.o((...args) => $options.submitForm && $options.submitForm(...args)),
    cg: $data.submitting
  });
}
const MiniProgramPage = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render], ["__scopeId", "data-v-fc320273"]]);
wx.createPage(MiniProgramPage);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/registration/index.js.map
