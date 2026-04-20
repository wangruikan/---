"use strict";
const common_vendor = require("../../common/vendor.js");
const api_onboarding = require("../../api/onboarding.js");
const utils_request = require("../../utils/request.js");
const _sfc_main = {
  data() {
    return {
      formData: {
        registration_date: "",
        name: "",
        gender: "male",
        ethnicity: "",
        political_status: "",
        place_of_origin: "",
        birth_date: "",
        id_number: "",
        current_residence: "",
        household_registration: "",
        marital_status: "",
        health_status: "",
        height: "",
        weight: "",
        graduated_school: "",
        graduation_date: "",
        education_level: "",
        major: "",
        degree: "",
        technical_title: "",
        position: "",
        desired_location: "",
        accept_assignment: false,
        contact_address: "",
        contact_phone: "",
        remarks: "",
        signature: "",
        signaturePath: "",
        photo: "",
        // 一寸照片URL
        photoPath: "",
        // 一寸照片路径
        education_background: [],
        work_experience: [],
        family_info: []
      },
      submitting: false,
      // 签名相关
      signatureCtx: null,
      isDrawing: false,
      lastPoint: null,
      hasSignature: false,
      canvasWidth: 0,
      canvasHeight: 0,
      // 选项数组
      ethnicityOptions: ["汉族", "蒙古族", "回族", "藏族", "维吾尔族", "苗族", "彝族", "壮族", "布依族", "朝鲜族", "满族", "侗族", "瑶族", "白族", "土家族", "哈尼族", "哈萨克族", "傣族", "黎族", "傈僳族", "佤族", "畲族", "高山族", "拉祜族", "水族", "东乡族", "纳西族", "景颇族", "柯尔克孜族", "土族", "达斡尔族", "仫佬族", "羌族", "布朗族", "撒拉族", "毛南族", "仡佬族", "锡伯族", "阿昌族", "普米族", "塔吉克族", "怒族", "乌孜别克族", "俄罗斯族", "鄂温克族", "德昂族", "保安族", "裕固族", "京族", "塔塔尔族", "独龙族", "鄂伦春族", "赫哲族", "门巴族", "珞巴族", "基诺族"],
      ethnicityIndex: 0,
      politicalOptions: ["群众", "中共党员", "中共预备党员", "共青团员", "民革党员", "民盟盟员", "民建会员", "民进会员", "农工党党员", "致公党党员", "九三学社社员", "台盟盟员", "无党派人士"],
      politicalIndex: 0,
      maritalOptions: ["未婚", "已婚", "离异", "丧偶"],
      maritalIndex: 0,
      healthOptions: ["健康", "良好", "一般", "较差"],
      healthIndex: 0,
      placeOfOriginArray: []
    };
  },
  onLoad() {
    const today = /* @__PURE__ */ new Date();
    const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
    this.formData.registration_date = dateStr;
    const systemInfo = common_vendor.index.getSystemInfoSync();
    const screenWidth = systemInfo.windowWidth;
    this.canvasWidth = screenWidth - 60;
    this.canvasHeight = 200;
    this.$nextTick(() => {
      this.initSignatureCanvas();
    });
    this.loadSavedData();
  },
  methods: {
    // 加载已保存的数据
    async loadSavedData() {
      try {
        const res = await api_onboarding.getMyOnboardingForm();
        if (res.success && res.data) {
          const data = res.data;
          if (data.education_background) {
            data.education_background = data.education_background.map((item) => ({
              ...item,
              date_range: item.start_date && item.end_date ? `${item.start_date}-${item.end_date}` : ""
            }));
          }
          if (data.work_experience) {
            data.work_experience = data.work_experience.map((item) => ({
              ...item,
              date_range: item.start_date && item.end_date ? `${item.start_date}-${item.end_date}` : ""
            }));
          }
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
            this.hasSignature = true;
          }
          if (data.photo) {
            this.formData.photo = data.photo;
            if (data.photo.includes("/uploads/")) {
              const match = data.photo.match(/\/uploads\/(.+)$/);
              if (match) {
                this.formData.photoPath = "uploads/" + match[1];
              }
            } else if (data.photo.includes("/storage/")) {
              const match = data.photo.match(/\/storage\/(.+)$/);
              if (match) {
                this.formData.photoPath = match[1];
              }
            }
          }
          const { signature, photo, ...otherData } = data;
          this.formData = { ...this.formData, ...otherData };
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/onboarding/index.vue:498", "加载数据失败:", error);
      }
    },
    // 日期选择
    onDateChange(e) {
      this.formData.registration_date = e.detail.value;
    },
    // 格式化日期用于picker的value（需要YYYY-MM-DD格式）
    formatDateForPicker(dateStr) {
      if (!dateStr)
        return "";
      if (dateStr.includes("T")) {
        return dateStr.split("T")[0];
      }
      return dateStr;
    },
    // 格式化日期用于显示
    formatDateDisplay(dateStr) {
      if (!dateStr)
        return "";
      if (dateStr.includes("T")) {
        return dateStr.split("T")[0];
      }
      return dateStr;
    },
    // 格式化出生年月显示
    formatBirthDate(dateStr) {
      if (!dateStr)
        return "";
      if (dateStr.includes("T")) {
        const date = new Date(dateStr);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        return `${year}-${month}`;
      }
      if (dateStr.length === 7) {
        return dateStr;
      }
      if (dateStr.length === 10) {
        return dateStr.substring(0, 7);
      }
      return dateStr;
    },
    // 性别选择
    onGenderChange(e) {
      this.formData.gender = e.detail.value;
    },
    // 身份证号码变化时校验并提取出生日期
    onIdNumberChange(e) {
      const idNumber = this.formData.id_number;
      if (!idNumber)
        return;
      if (!this.validateIdNumber(idNumber)) {
        common_vendor.index.showToast({
          title: "请输入正确的身份证号码",
          icon: "none"
        });
        return;
      }
      const birthYear = idNumber.substring(6, 10);
      const birthMonth = idNumber.substring(10, 12);
      this.formData.birth_date = `${birthYear}-${birthMonth}`;
      const genderCode = parseInt(idNumber.substring(16, 17));
      this.formData.gender = genderCode % 2 === 1 ? "male" : "female";
    },
    // 校验身份证号码
    validateIdNumber(idNumber) {
      if (!idNumber || idNumber.length !== 18)
        return false;
      const reg = /^[1-9]\d{5}(19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/;
      if (!reg.test(idNumber))
        return false;
      const weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
      const checkCodes = ["1", "0", "X", "9", "8", "7", "6", "5", "4", "3", "2"];
      let sum = 0;
      for (let i = 0; i < 17; i++) {
        sum += parseInt(idNumber[i]) * weights[i];
      }
      const checkCode = checkCodes[sum % 11];
      return idNumber[17].toUpperCase() === checkCode;
    },
    // 民族选择
    onEthnicityChange(e) {
      this.ethnicityIndex = e.detail.value;
      this.formData.ethnicity = this.ethnicityOptions[e.detail.value];
    },
    // 籍贯选择
    onPlaceOfOriginChange(e) {
      this.placeOfOriginArray = e.detail.value;
      this.formData.place_of_origin = e.detail.value.join("");
    },
    // 政治面貌选择
    onPoliticalChange(e) {
      this.politicalIndex = e.detail.value;
      this.formData.political_status = this.politicalOptions[e.detail.value];
    },
    // 婚姻状况选择
    onMaritalChange(e) {
      this.maritalIndex = e.detail.value;
      this.formData.marital_status = this.maritalOptions[e.detail.value];
    },
    // 健康状况选择
    onHealthChange(e) {
      this.healthIndex = e.detail.value;
      this.formData.health_status = this.healthOptions[e.detail.value];
    },
    // 选择一寸照片
    async choosePhoto() {
      common_vendor.index.chooseImage({
        count: 1,
        sizeType: ["compressed"],
        sourceType: ["album", "camera"],
        success: async (res) => {
          const tempFilePath = res.tempFilePaths[0];
          common_vendor.index.showLoading({ title: "上传中...", mask: true });
          try {
            const uploadRes = await this.uploadPhoto(tempFilePath);
            common_vendor.index.hideLoading();
            if (uploadRes.success) {
              this.formData.photo = uploadRes.data.url;
              this.formData.photoPath = uploadRes.data.path;
              common_vendor.index.showToast({ title: "上传成功", icon: "success" });
            } else {
              common_vendor.index.showToast({ title: uploadRes.message || "上传失败", icon: "none" });
            }
          } catch (error) {
            common_vendor.index.hideLoading();
            common_vendor.index.__f__("error", "at pages/onboarding/index.vue:651", "上传照片失败:", error);
            common_vendor.index.showToast({ title: "上传失败", icon: "none" });
          }
        }
      });
    },
    // 上传照片到服务器
    uploadPhoto(filePath) {
      return new Promise((resolve, reject) => {
        const token = common_vendor.index.getStorageSync("token");
        common_vendor.index.uploadFile({
          url: utils_request.BASE_URL + "/upload-photo",
          filePath,
          name: "photo",
          header: {
            "Authorization": "Bearer " + token,
            "X-Auth-Token": token
          },
          success: (res) => {
            try {
              const data = JSON.parse(res.data);
              resolve(data);
            } catch (e) {
              reject(e);
            }
          },
          fail: (err) => {
            reject(err);
          }
        });
      });
    },
    // 毕业时间选择
    onGraduationDateChange(e) {
      this.formData.graduation_date = e.detail.value;
    },
    // 是否服从调配
    onAcceptAssignmentChange(e) {
      this.formData.accept_assignment = e.detail.value;
    },
    // 添加学习简历
    addEducationBackground() {
      this.formData.education_background.push({
        date_range: "",
        school: "",
        level: "",
        certifier: ""
      });
    },
    // 删除学习简历
    deleteEducationBackground(index) {
      this.formData.education_background.splice(index, 1);
    },
    // 学习简历日期输入
    onEducationDateChange(index, e) {
      const value = e.detail.value;
      const dates = value.split("-");
      if (dates.length === 2) {
        this.formData.education_background[index].start_date = dates[0].trim();
        this.formData.education_background[index].end_date = dates[1].trim();
      }
    },
    // 添加工作经历
    addWorkExperience() {
      this.formData.work_experience.push({
        date_range: "",
        employer: "",
        job_content: "",
        certifier: ""
      });
    },
    // 删除工作经历
    deleteWorkExperience(index) {
      this.formData.work_experience.splice(index, 1);
    },
    // 工作经历日期输入
    onWorkDateChange(index, e) {
      const value = e.detail.value;
      const dates = value.split("-");
      if (dates.length === 2) {
        this.formData.work_experience[index].start_date = dates[0].trim();
        this.formData.work_experience[index].end_date = dates[1].trim();
      }
    },
    // 添加家庭情况
    addFamilyInfo() {
      this.formData.family_info.push({
        name: "",
        relationship: "",
        employer: "",
        phone: ""
      });
    },
    // 删除家庭情况
    deleteFamilyInfo(index) {
      this.formData.family_info.splice(index, 1);
    },
    // 一键填充示例数据
    fillSampleData() {
      common_vendor.index.showModal({
        title: "提示",
        content: "确定要填充示例数据吗？这将覆盖当前已填写的内容。",
        success: (res) => {
          if (res.confirm) {
            const today = /* @__PURE__ */ new Date();
            const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
            const birthYear = 1990;
            const birthMonth = 5;
            const gradYear = 2012;
            const gradMonth = 6;
            this.formData = {
              registration_date: dateStr,
              name: "张三",
              gender: "male",
              ethnicity: "汉",
              political_status: "群众",
              place_of_origin: "北京市",
              birth_date: `${birthYear}-${String(birthMonth).padStart(2, "0")}`,
              id_number: "110101199005011234",
              current_residence: "北京市朝阳区某某街道某某号",
              household_registration: "北京市朝阳区",
              marital_status: "已婚",
              health_status: "健康",
              height: "175",
              weight: "70.5",
              graduated_school: "北京大学",
              graduation_date: `${gradYear}-${String(gradMonth).padStart(2, "0")}`,
              education_level: "本科",
              major: "计算机科学与技术",
              degree: "学士",
              technical_title: "工程师",
              position: "软件工程师",
              desired_location: "北京市",
              accept_assignment: true,
              contact_address: "北京市朝阳区某某街道某某号",
              contact_phone: "13800138000",
              remarks: "本人具有良好的沟通能力和团队合作精神，熟悉Java、Python等编程语言。",
              education_background: [
                {
                  date_range: "2006.09-2009.06",
                  school: "北京市第一中学",
                  level: "高中",
                  certifier: "李老师",
                  start_date: "2006.09",
                  end_date: "2009.06"
                },
                {
                  date_range: "2009.09-2012.06",
                  school: "北京大学",
                  level: "本科",
                  certifier: "王老师",
                  start_date: "2009.09",
                  end_date: "2012.06"
                }
              ],
              work_experience: [
                {
                  date_range: "2012.07-2018.06",
                  employer: "某某科技有限公司",
                  job_content: "负责公司核心产品的开发和维护，参与系统架构设计，指导 junior 开发人员。",
                  certifier: "赵经理",
                  start_date: "2012.07",
                  end_date: "2018.06"
                },
                {
                  date_range: "2018.07-2024.12",
                  employer: "某某互联网公司",
                  job_content: "担任技术负责人，负责团队管理和项目推进，参与多个重要项目的开发。",
                  certifier: "钱总监",
                  start_date: "2018.07",
                  end_date: "2024.12"
                }
              ],
              family_info: [
                {
                  name: "张父",
                  relationship: "父亲",
                  employer: "某某公司",
                  phone: "13900139000"
                },
                {
                  name: "张母",
                  relationship: "母亲",
                  employer: "某某单位",
                  phone: "13900139001"
                },
                {
                  name: "李四",
                  relationship: "配偶",
                  employer: "某某企业",
                  phone: "13900139002"
                }
              ]
            };
            common_vendor.index.showToast({
              title: "示例数据已填充",
              icon: "success"
            });
          }
        }
      });
    },
    // 提交表单
    async submitForm() {
      if (!this.formData.registration_date) {
        common_vendor.index.showToast({
          title: "请选择登记日期",
          icon: "none"
        });
        return;
      }
      if (!this.formData.name) {
        common_vendor.index.showToast({
          title: "请输入姓名",
          icon: "none"
        });
        return;
      }
      if (!this.formData.id_number) {
        common_vendor.index.showToast({
          title: "请输入身份证号码",
          icon: "none"
        });
        return;
      }
      if (!this.validateIdNumber(this.formData.id_number)) {
        common_vendor.index.showToast({
          title: "请输入正确的身份证号码",
          icon: "none"
        });
        return;
      }
      if (!this.formData.signaturePath) {
        common_vendor.index.showToast({
          title: "请先完成手写签名",
          icon: "none"
        });
        return;
      }
      this.submitting = true;
      try {
        const submitData = { ...this.formData };
        submitData.signature = this.formData.signaturePath;
        delete submitData.signaturePath;
        if (this.formData.photoPath) {
          submitData.photo = this.formData.photoPath;
        }
        delete submitData.photoPath;
        common_vendor.index.__f__("log", "at pages/onboarding/index.vue:931", "提交签名路径:", submitData.signature);
        common_vendor.index.__f__("log", "at pages/onboarding/index.vue:932", "提交寸照路径:", submitData.photo);
        if (submitData.education_background) {
          submitData.education_background = submitData.education_background.map((item) => {
            const { date_range, ...rest } = item;
            return rest;
          });
        }
        if (submitData.work_experience) {
          submitData.work_experience = submitData.work_experience.map((item) => {
            const { date_range, ...rest } = item;
            return rest;
          });
        }
        const res = await api_onboarding.submitOnboardingForm(submitData);
        if (res.success) {
          common_vendor.index.showToast({
            title: "提交成功",
            icon: "success"
          });
          setTimeout(() => {
            common_vendor.index.navigateBack();
          }, 1500);
        } else {
          common_vendor.index.showToast({
            title: res.message || "提交失败",
            icon: "none"
          });
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/onboarding/index.vue:969", "提交失败:", error);
        common_vendor.index.showToast({
          title: "提交失败，请重试",
          icon: "none"
        });
      } finally {
        this.submitting = false;
      }
    },
    // 初始化签名canvas
    initSignatureCanvas() {
      if (!this.signatureCtx) {
        this.signatureCtx = common_vendor.index.createCanvasContext("signatureCanvas", this);
      }
      this.signatureCtx.setStrokeStyle("#000000");
      this.signatureCtx.setLineWidth(3);
      this.signatureCtx.setLineCap("round");
      this.signatureCtx.setLineJoin("round");
    },
    // 开始绘制
    handleTouchStart(e) {
      if (!this.signatureCtx) {
        this.initSignatureCanvas();
      }
      this.isDrawing = true;
      this.hasSignature = true;
      const touch = e.touches[0];
      const x = touch.x;
      const y = touch.y;
      this.signatureCtx.beginPath();
      this.signatureCtx.moveTo(x, y);
      this.signatureCtx.lineTo(x, y);
      this.signatureCtx.stroke();
      this.signatureCtx.draw(true);
      this.lastPoint = { x, y };
    },
    // 绘制中
    handleTouchMove(e) {
      if (!this.isDrawing || !this.lastPoint)
        return;
      const touch = e.touches[0];
      const x = touch.x;
      const y = touch.y;
      this.signatureCtx.beginPath();
      this.signatureCtx.moveTo(this.lastPoint.x, this.lastPoint.y);
      this.signatureCtx.lineTo(x, y);
      this.signatureCtx.stroke();
      this.signatureCtx.draw(true);
      this.lastPoint = { x, y };
    },
    // 结束绘制（结束当前笔画，不保存，允许继续绘制下一笔）
    handleTouchEnd() {
      if (!this.isDrawing)
        return;
      this.isDrawing = false;
      this.lastPoint = null;
    },
    // 完成签名（用户主动点击完成按钮）
    async finishSignature() {
      if (!this.hasSignature) {
        common_vendor.index.showToast({
          title: "请先签名",
          icon: "none"
        });
        return;
      }
      common_vendor.index.showLoading({
        title: "上传中..."
      });
      this.saveSignature(async (success, base64Data) => {
        if (!success) {
          common_vendor.index.hideLoading();
          common_vendor.index.showToast({
            title: "签名生成失败",
            icon: "none"
          });
          return;
        }
        try {
          const res = await api_onboarding.uploadSignature(base64Data);
          common_vendor.index.hideLoading();
          common_vendor.index.__f__("log", "at pages/onboarding/index.vue:1070", "上传签名响应:", res);
          if (res.success) {
            this.formData.signature = res.data.url;
            this.formData.signaturePath = res.data.path;
            common_vendor.index.__f__("log", "at pages/onboarding/index.vue:1077", "保存到formData:");
            common_vendor.index.__f__("log", "at pages/onboarding/index.vue:1078", "  signature:", this.formData.signature);
            common_vendor.index.__f__("log", "at pages/onboarding/index.vue:1079", "  signaturePath:", this.formData.signaturePath);
            common_vendor.index.showToast({
              title: "签名已保存",
              icon: "success"
            });
          } else {
            common_vendor.index.showToast({
              title: res.message || "上传失败",
              icon: "none"
            });
          }
        } catch (error) {
          common_vendor.index.hideLoading();
          common_vendor.index.__f__("error", "at pages/onboarding/index.vue:1093", "上传签名失败:", error);
          common_vendor.index.showToast({
            title: "上传失败",
            icon: "none"
          });
        }
      });
    },
    // 保存签名为base64
    saveSignature(callback) {
      common_vendor.index.canvasToTempFilePath({
        canvasId: "signatureCanvas",
        fileType: "png",
        quality: 1,
        width: this.canvasWidth,
        height: this.canvasHeight,
        destWidth: this.canvasWidth * 2,
        // 提高导出质量
        destHeight: this.canvasHeight * 2,
        success: (res) => {
          common_vendor.index.getFileSystemManager().readFile({
            filePath: res.tempFilePath,
            encoding: "base64",
            success: (data) => {
              const base64Data = "data:image/png;base64," + data.data;
              common_vendor.index.__f__("log", "at pages/onboarding/index.vue:1119", "签名生成成功", base64Data.substring(0, 50));
              if (callback)
                callback(true, base64Data);
            },
            fail: (error) => {
              common_vendor.index.__f__("error", "at pages/onboarding/index.vue:1123", "读取签名失败:", error);
              if (callback)
                callback(false, null);
            }
          });
        },
        fail: (error) => {
          common_vendor.index.__f__("error", "at pages/onboarding/index.vue:1129", "导出签名失败:", error);
          if (callback)
            callback(false, null);
        }
      }, this);
    },
    // 清除签名
    clearSignature() {
      this.formData.signature = "";
      this.formData.signaturePath = "";
      this.isDrawing = false;
      this.lastPoint = null;
      this.hasSignature = false;
      this.$nextTick(() => {
        if (this.signatureCtx) {
          this.signatureCtx.clearRect(0, 0, this.canvasWidth, this.canvasHeight);
          this.signatureCtx.draw(false);
          this.signatureCtx.setStrokeStyle("#000000");
          this.signatureCtx.setLineWidth(3);
          this.signatureCtx.setLineCap("round");
          this.signatureCtx.setLineJoin("round");
        } else {
          this.initSignatureCanvas();
        }
      });
      common_vendor.index.showToast({
        title: "已清除，请重新签名",
        icon: "none"
      });
    }
  }
};
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  return common_vendor.e({
    a: common_vendor.o((...args) => $options.fillSampleData && $options.fillSampleData(...args)),
    b: common_vendor.t($options.formatDateDisplay($data.formData.registration_date) || "请选择日期"),
    c: $options.formatDateForPicker($data.formData.registration_date),
    d: common_vendor.o((...args) => $options.onDateChange && $options.onDateChange(...args)),
    e: $data.formData.name,
    f: common_vendor.o(($event) => $data.formData.name = $event.detail.value),
    g: $data.formData.gender === "male",
    h: $data.formData.gender === "female",
    i: common_vendor.o((...args) => $options.onGenderChange && $options.onGenderChange(...args)),
    j: common_vendor.o((...args) => $options.onIdNumberChange && $options.onIdNumberChange(...args)),
    k: $data.formData.id_number,
    l: common_vendor.o(($event) => $data.formData.id_number = $event.detail.value),
    m: common_vendor.t($options.formatBirthDate($data.formData.birth_date) || "输入身份证后自动获取"),
    n: $data.formData.photo
  }, $data.formData.photo ? {
    o: $data.formData.photo
  } : {}, {
    p: common_vendor.o((...args) => $options.choosePhoto && $options.choosePhoto(...args)),
    q: common_vendor.t($data.formData.ethnicity || "请选择民族"),
    r: $data.ethnicityOptions,
    s: $data.ethnicityIndex,
    t: common_vendor.o((...args) => $options.onEthnicityChange && $options.onEthnicityChange(...args)),
    v: common_vendor.t($data.formData.place_of_origin || "请选择籍贯"),
    w: $data.placeOfOriginArray,
    x: common_vendor.o((...args) => $options.onPlaceOfOriginChange && $options.onPlaceOfOriginChange(...args)),
    y: common_vendor.t($data.formData.political_status || "请选择政治面貌"),
    z: $data.politicalOptions,
    A: $data.politicalIndex,
    B: common_vendor.o((...args) => $options.onPoliticalChange && $options.onPoliticalChange(...args)),
    C: $data.formData.current_residence,
    D: common_vendor.o(($event) => $data.formData.current_residence = $event.detail.value),
    E: $data.formData.household_registration,
    F: common_vendor.o(($event) => $data.formData.household_registration = $event.detail.value),
    G: common_vendor.t($data.formData.marital_status || "请选择婚姻状况"),
    H: $data.maritalOptions,
    I: $data.maritalIndex,
    J: common_vendor.o((...args) => $options.onMaritalChange && $options.onMaritalChange(...args)),
    K: common_vendor.t($data.formData.health_status || "请选择健康状况"),
    L: $data.healthOptions,
    M: $data.healthIndex,
    N: common_vendor.o((...args) => $options.onHealthChange && $options.onHealthChange(...args)),
    O: $data.formData.height,
    P: common_vendor.o(($event) => $data.formData.height = $event.detail.value),
    Q: $data.formData.weight,
    R: common_vendor.o(($event) => $data.formData.weight = $event.detail.value),
    S: $data.formData.graduated_school,
    T: common_vendor.o(($event) => $data.formData.graduated_school = $event.detail.value),
    U: common_vendor.t($data.formData.graduation_date || "请选择"),
    V: $data.formData.graduation_date,
    W: common_vendor.o((...args) => $options.onGraduationDateChange && $options.onGraduationDateChange(...args)),
    X: $data.formData.education_level,
    Y: common_vendor.o(($event) => $data.formData.education_level = $event.detail.value),
    Z: $data.formData.major,
    aa: common_vendor.o(($event) => $data.formData.major = $event.detail.value),
    ab: $data.formData.degree,
    ac: common_vendor.o(($event) => $data.formData.degree = $event.detail.value),
    ad: $data.formData.technical_title,
    ae: common_vendor.o(($event) => $data.formData.technical_title = $event.detail.value),
    af: common_vendor.o((...args) => $options.addEducationBackground && $options.addEducationBackground(...args)),
    ag: common_vendor.f($data.formData.education_background, (item, index, i0) => {
      return {
        a: common_vendor.t(index + 1),
        b: common_vendor.o(($event) => $options.deleteEducationBackground(index), index),
        c: common_vendor.o([($event) => item.date_range = $event.detail.value, ($event) => $options.onEducationDateChange(index, $event)], index),
        d: item.date_range,
        e: item.school,
        f: common_vendor.o(($event) => item.school = $event.detail.value, index),
        g: item.level,
        h: common_vendor.o(($event) => item.level = $event.detail.value, index),
        i: item.certifier,
        j: common_vendor.o(($event) => item.certifier = $event.detail.value, index),
        k: index
      };
    }),
    ah: common_vendor.o((...args) => $options.addWorkExperience && $options.addWorkExperience(...args)),
    ai: common_vendor.f($data.formData.work_experience, (item, index, i0) => {
      return {
        a: common_vendor.t(index + 1),
        b: common_vendor.o(($event) => $options.deleteWorkExperience(index), index),
        c: common_vendor.o([($event) => item.date_range = $event.detail.value, ($event) => $options.onWorkDateChange(index, $event)], index),
        d: item.date_range,
        e: item.employer,
        f: common_vendor.o(($event) => item.employer = $event.detail.value, index),
        g: item.job_content,
        h: common_vendor.o(($event) => item.job_content = $event.detail.value, index),
        i: item.certifier,
        j: common_vendor.o(($event) => item.certifier = $event.detail.value, index),
        k: index
      };
    }),
    aj: common_vendor.o((...args) => $options.addFamilyInfo && $options.addFamilyInfo(...args)),
    ak: common_vendor.f($data.formData.family_info, (item, index, i0) => {
      return {
        a: common_vendor.t(index + 1),
        b: common_vendor.o(($event) => $options.deleteFamilyInfo(index), index),
        c: item.name,
        d: common_vendor.o(($event) => item.name = $event.detail.value, index),
        e: item.relationship,
        f: common_vendor.o(($event) => item.relationship = $event.detail.value, index),
        g: item.employer,
        h: common_vendor.o(($event) => item.employer = $event.detail.value, index),
        i: item.phone,
        j: common_vendor.o(($event) => item.phone = $event.detail.value, index),
        k: index
      };
    }),
    al: $data.formData.position,
    am: common_vendor.o(($event) => $data.formData.position = $event.detail.value),
    an: $data.formData.desired_location,
    ao: common_vendor.o(($event) => $data.formData.desired_location = $event.detail.value),
    ap: $data.formData.accept_assignment,
    aq: common_vendor.o((...args) => $options.onAcceptAssignmentChange && $options.onAcceptAssignmentChange(...args)),
    ar: $data.formData.contact_address,
    as: common_vendor.o(($event) => $data.formData.contact_address = $event.detail.value),
    at: $data.formData.contact_phone,
    av: common_vendor.o(($event) => $data.formData.contact_phone = $event.detail.value),
    aw: $data.formData.remarks,
    ax: common_vendor.o(($event) => $data.formData.remarks = $event.detail.value),
    ay: !$data.formData.signature
  }, !$data.formData.signature ? common_vendor.e({
    az: $data.canvasWidth + "px",
    aA: $data.canvasHeight + "px",
    aB: common_vendor.o((...args) => $options.handleTouchStart && $options.handleTouchStart(...args)),
    aC: common_vendor.o((...args) => $options.handleTouchMove && $options.handleTouchMove(...args)),
    aD: common_vendor.o((...args) => $options.handleTouchEnd && $options.handleTouchEnd(...args)),
    aE: common_vendor.o((...args) => $options.clearSignature && $options.clearSignature(...args)),
    aF: $data.hasSignature
  }, $data.hasSignature ? {
    aG: common_vendor.o((...args) => $options.finishSignature && $options.finishSignature(...args))
  } : {}) : {
    aH: $data.formData.signature,
    aI: common_vendor.o((...args) => $options.clearSignature && $options.clearSignature(...args))
  }, {
    aJ: common_vendor.o((...args) => $options.submitForm && $options.submitForm(...args)),
    aK: $data.submitting
  });
}
const MiniProgramPage = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render], ["__scopeId", "data-v-ca6eb76f"]]);
wx.createPage(MiniProgramPage);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/onboarding/index.js.map
