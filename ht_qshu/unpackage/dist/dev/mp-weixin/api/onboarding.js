"use strict";
const utils_request = require("../utils/request.js");
function getMyOnboardingForm() {
  return utils_request.request.get("/onboarding-form");
}
function submitOnboardingForm(data) {
  return utils_request.request.post("/onboarding-form", data);
}
function uploadSignature(signature) {
  return utils_request.request.post("/upload-signature", { signature });
}
exports.getMyOnboardingForm = getMyOnboardingForm;
exports.submitOnboardingForm = submitOnboardingForm;
exports.uploadSignature = uploadSignature;
//# sourceMappingURL=../../.sourcemap/mp-weixin/api/onboarding.js.map
