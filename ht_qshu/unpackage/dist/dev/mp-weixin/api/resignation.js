"use strict";
const utils_request = require("../utils/request.js");
function getMyResignationCertificates() {
  return utils_request.request.get("/my-resignation-certificates");
}
function uploadResignationCertificate(filePath) {
  return utils_request.request.upload("/my-resignation-certificates/upload", filePath);
}
function deleteResignationCertificate(id) {
  return utils_request.request.delete(`/resignation-certificates/${id}`);
}
exports.deleteResignationCertificate = deleteResignationCertificate;
exports.getMyResignationCertificates = getMyResignationCertificates;
exports.uploadResignationCertificate = uploadResignationCertificate;
//# sourceMappingURL=../../.sourcemap/mp-weixin/api/resignation.js.map
