import uploader from "../config/cloudinary.js";

const imageUploader = async (req) => {
  try {
    const tmp = req.files.image.tempFilePath;
    const Result = await uploader.upload(
      tmp,
      { folder: "Card" },
      (_, result) => result
    );
    return Result;
  } catch (error) {
    console.log(error);
  }
};

export default imageUploader;