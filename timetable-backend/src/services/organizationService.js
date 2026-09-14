import db from "../database/models/index.js";

const { College, School, Program, Module, Campus, Intake, StudentGroup } = db;

export const getOrganizationStructure = async () => {
  const [colleges, schools, programs, modules, campuses, intakes] = await Promise.all([
    College.findAll({ order: [["name", "ASC"]] }),
    School.findAll({
      include: [{ association: "college", attributes: ["id", "name", "fullName"] }],
      order: [["name", "ASC"]],
    }),
    Program.findAll({
      include: [{ association: "school", attributes: ["id", "name", "collegeId"] }],
      order: [["name", "ASC"]],
    }),
    Module.findAll({ attributes: ["id", "programId"] }),
    Campus.findAll({ order: [["name", "ASC"]] }),
    Intake.findAll({
      include: [
        { model: Campus, as: "campus", attributes: ["id", "name"] },
        {
          model: StudentGroup,
          as: "groups",
          attributes: ["id", "name", "size"],
        },
      ],
      order: [
        ["yearOfStudy", "ASC"],
        ["id", "ASC"],
      ],
    }),
  ]);

  const moduleCountByProgram = {};
  for (const mod of modules) {
    const pid = mod.programId;
    moduleCountByProgram[pid] = (moduleCountByProgram[pid] || 0) + 1;
  }

  const intakesByProgram = {};
  let groupCount = 0;
  for (const intake of intakes) {
    const j = intake.toJSON();
    const pid = j.programId;
    if (!intakesByProgram[pid]) intakesByProgram[pid] = [];
    const groups = (j.groups || []).slice().sort((a, b) => {
      const na = parseInt(String(a.name).replace(/\D+/g, ""), 10) || 0;
      const nb = parseInt(String(b.name).replace(/\D+/g, ""), 10) || 0;
      return na - nb;
    });
    groupCount += groups.length;
    intakesByProgram[pid].push({
      id: j.id,
      yearOfStudy: j.yearOfStudy,
      size: j.size,
      campusId: j.campusId,
      campus: j.campus,
      groups,
      groupCount: groups.length,
      label: `Year ${j.yearOfStudy} · ${j.campus?.name || "campus"} · ${groups.length} group(s)`,
    });
  }

  const schoolsByCollege = {};
  for (const school of schools) {
    const cid = school.collegeId;
    if (!schoolsByCollege[cid]) schoolsByCollege[cid] = [];
    schoolsByCollege[cid].push(school);
  }

  const programsBySchool = {};
  for (const program of programs) {
    const sid = program.schoolId;
    if (!programsBySchool[sid]) programsBySchool[sid] = [];
    const programIntakes = intakesByProgram[program.id] || [];
    programsBySchool[sid].push({
      ...program.toJSON(),
      moduleCount: moduleCountByProgram[program.id] || 0,
      intakes: programIntakes,
      intakeCount: programIntakes.length,
      groupCount: programIntakes.reduce((s, i) => s + (i.groupCount || 0), 0),
    });
  }

  const tree = colleges.map((college) => {
    const collegeSchools = (schoolsByCollege[college.id] || []).map((school) => ({
      ...school.toJSON(),
      programs: programsBySchool[school.id] || [],
    }));
    return {
      ...college.toJSON(),
      schools: collegeSchools,
    };
  });

  return {
    campuses,
    colleges: tree,
    counts: {
      campuses: campuses.length,
      colleges: colleges.length,
      schools: schools.length,
      programs: programs.length,
      modules: modules.length,
      intakes: intakes.length,
      groups: groupCount,
    },
  };
};
