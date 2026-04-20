"use strict";
const utils_request = require("../utils/request.js");
function getMyRegistrationForm() {
  return utils_request.request.get("/registration-form");
}
function submitRegistrationForm(data) {
  return utils_request.request.post("/registration-form", data);
}
exports.getMyRegistrationForm = getMyRegistrationForm;
exports.submitRegistrationForm = submitRegistrationForm;
//# sourceMappingURL=../../.sourcemap/mp-weixin/api/registration.js.map
