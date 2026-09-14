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
npm run db:setup
npm run start:dev
```

Default login after seed import: `administrator@ur.ac.rw` / `23122312`  
(override with `SEED_USER_PASSWORD` in `.env`)

## Seed data

Bundled dump: `data/ur-timetable-seed.sql` (imported into Postgres).

```bash
npm run seed:data
```

Or full setup:

```bash
npm run db:setup
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
