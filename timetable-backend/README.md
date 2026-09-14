# University of Rwanda — Timetable Backend

Node/Express + Sequelize (PostgreSQL) API migrated from the PHP `timetable-v3` schema.

## Core entities

| Entity   | Table        | Relations                          |
|----------|--------------|------------------------------------|
| Campus   | `campuses`   | → facilities, users                |
| College  | `colleges`   | → schools, users                   |
| School   | `schools`    | → college, programs, users         |
| Program  | `programs`   | → school, modules                  |
| Module   | `modules`    | → program                          |
| Facility | `facilities` | → campus                           |
| User     | `users`      | → campus, college, school (optional) |

## Setup

```bash
npm install
# Configure .env (see .env.example) — create DB e.g. ur_timetable
npm run migrate
npm run seed
npm run start:dev
```

Default admin after seed: `administrator@ur.ac.rw` / `Admin@123`

## Seed from PHP `timetable-v3.sql`

```bash
npm run seed:php
```

Imports campuses, colleges, schools, programs, modules, facilities, and users from `../timetable-php/timetable-v3.sql` (passwords kept as PHP bcrypt hashes).

Optional path override:

```bash
node scripts/import-timetable-sql.js --sql "C:\path\to\timetable-v3.sql"
```

## API (`/api/v1`)

| Method | Path | Auth |
|--------|------|------|
| GET | `/health` | public |
| POST | `/auth/login` | public (`email` or `urEmail` + `password`) |
| GET | `/auth/me` | Bearer |
| PUT | `/auth/change-password` | Bearer |
| CRUD | `/campuses`, `/colleges`, `/schools`, `/programs`, `/modules`, `/facilities` | GET public; writes need manage role |
| CRUD | `/users` | admin |

Filters: `schools?collegeId=`, `programs?schoolId=`, `modules?programId=&year=&semester=`, `facilities?campusId=&type=`
