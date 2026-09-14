const express = require("express");
const { getOrganizationStructureController } = require("../controllers/organizationController.js");

const router = express.Router();

router.get("/structure", getOrganizationStructureController);

module.exports = router;
