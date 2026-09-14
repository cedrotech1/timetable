import { getOrganizationStructure } from "../services/organizationService.js";

export const getOrganizationStructureController = async (req, res) => {
  try {
    const data = await getOrganizationStructure();
    return res.status(200).json({
      success: true,
      message: "Organization structure retrieved successfully",
      data,
    });
  } catch (error) {
    return res.status(500).json({
      success: false,
      message: "Something went wrong",
      error: error.message,
    });
  }
};
