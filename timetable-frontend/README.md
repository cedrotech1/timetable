# UR Timetable Frontend

React + Vite + Tailwind UI for the University of Rwanda Timetable API.

## Run

```bash
npm install
npm run dev
```

Configure `VITE_API_BASE_URL` in `.env` (default `http://localhost:9000/api/v1`).

## Routes

| Path | Page |
|------|------|
| `/login` | Staff sign-in |
| `/app/dashboard` | Overview counts |
| `/app/campuses` | Campuses CRUD |
| `/app/colleges` | Colleges CRUD |
| `/app/schools` | Schools CRUD |
| `/app/programs` | Programs CRUD |
| `/app/modules` | Modules CRUD |
| `/app/facilities` | Facilities CRUD |
| `/app/users` | Users (admin) |
| `/app/profile` | Profile / password |

Default login (from backend seed): `administrator@ur.ac.rw` / `Admin@123`
