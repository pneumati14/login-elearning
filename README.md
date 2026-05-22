# login — E-Learning Platform

A containerized full-stack skeleton for an e-learning web platform: a
Symfony REST API and a Vue.js single-page app, backed by PostgreSQL and
fronted by a Caddy reverse proxy — all orchestrated with Docker Compose.

| Layer          | Technology                                   |
|----------------|----------------------------------------------|
| Backend        | Symfony 7.4 · PHP 8.3 (PHP-FPM)              |
| Frontend       | Vue 3 · TypeScript · Vite 8 (Node 22)       |
| Database       | PostgreSQL 16                                |
| Reverse proxy  | Caddy 2 (automatic HTTPS)                    |

> **Status:** working e-learning platform. Behind a login, users browse
> courses, enrol, study lessons (text, video, PDF, YouTube), take quizzes
> and earn completion certificates; administrators create accounts and
> author courses, lessons and quizzes. There is no public sign-up.
> See [Authentication](#authentication).

## Requirements

- Docker Engine 24+
- Docker Compose v2 (the `docker compose` subcommand)

## Quick start (local development)

```bash
# 1. Create your local environment file
cp .env.example .env

# 2. Build the images and start the stack
docker compose up -d --build

# 3. Create the database schema
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

# 4. (optional) Load demo courses so the app shows data
docker compose exec backend php bin/console app:seed-demo
```

Then open **http://localhost** — the Vue app lists the courses it loads
from the API.

| URL                     | Serves                                              |
|-------------------------|-----------------------------------------------------|
| **http://localhost**    | The full app — Vue SPA + `/api` on one origin (via Caddy) |
| http://localhost:5173   | Raw Vite dev server (UI only; API calls go through Caddy) |
| `localhost:5432`        | PostgreSQL — connect with any database client       |

> `http://localhost` redirects to HTTPS. Caddy serves `localhost` with
> its own internal CA, so the browser shows a certificate warning — that
> is expected locally; accept it to continue.

Stop the stack with `docker compose down` (add `-v` to also delete the
database volume).

## Architecture

Caddy is the single entry point and routes by path:

```
                 ┌──────────────┐
  browser  ──►   │   Caddy :80  │
                 │      :443    │
                 └──────┬───────┘
              /api/*    │    /*
           ┌────────────┴────────────┐
           ▼                         ▼
   ┌───────────────┐         ┌───────────────┐
   │ backend       │         │ frontend      │
   │ Symfony/PHP-FPM│        │ Vue / Vite    │
   └───────┬───────┘         └───────────────┘
           ▼
   ┌───────────────┐
   │ database      │
   │ PostgreSQL 16 │
   └───────────────┘
```

### Compose files

Running `docker compose` with no `-f` flags loads two files:

1. **`docker-compose.yml`** — base service definitions with
   production-safe defaults.
2. **`docker-compose.override.yml`** — development conveniences, merged
   in automatically: source-code bind mounts for live reload, published
   service ports, and `dev` environment values.

Staging and production deliberately do **not** use the override file.
They layer their own file on top of the base instead, so application
code is baked into the images (no bind mounts) and the database port is
never exposed to the host:

```
Local dev    docker-compose.yml  +  docker-compose.override.yml
Staging      docker-compose.yml  +  staging/docker-compose.staging.yml
Production   docker-compose.yml  +  production/docker-compose.production.yml
```

Each environment also sets its own `COMPOSE_PROJECT_NAME`, so multiple
environments can run on the same host without colliding.

### Project layout

```
login-elearning/
├── backend/                     Symfony 7.4 API
│   ├── src/
│   │   ├── Command/             Console commands (app:seed-demo, app:create-user)
│   │   ├── Controller/          API controllers (auth, courses, enrolment, admin)
│   │   ├── Entity/              Doctrine entities (User, Course, Lesson, Enrollment, …)
│   │   ├── Security/            Authentication handlers
│   │   └── Repository/          Doctrine repositories
│   ├── config/  migrations/  public/
├── frontend/                    Vue 3 + Vite SPA
│   └── src/
│       ├── views/               Page components (Home, Courses, Login, Admin…)
│       ├── stores/              Pinia stores (auth, courses, users…)
│       ├── router/              Vue Router configuration
│       └── components/
├── docker/
│   ├── backend/Dockerfile       PHP-FPM image for Symfony
│   ├── frontend/Dockerfile      Node image for Vue
│   └── caddy/Caddyfile          Reverse-proxy routing
├── postgres/init/               SQL run once on first DB start (extensions)
├── storage/                     Persistent shared files (uploads, media)
├── staging/                     Staging overlay + .env template
├── production/                  Production overlay + .env template
├── docker-compose.yml           Base service definitions
├── docker-compose.override.yml  Local-dev overrides (auto-loaded)
└── .env.example                 Environment variable template
```

## API

All endpoints are served under `/api` (routed to the Symfony backend by
Caddy).

| Method · Path                          | Access | Description                                 |
|----------------------------------------|--------|---------------------------------------------|
| `GET /api/health`                      | public | Service + database health check             |
| `POST /api/login`                      | public | Log in with JSON `{email, password}`        |
| `POST /api/logout`                     | public | Destroy the current session                 |
| `GET /api/me`                          | user   | The currently authenticated user            |
| `POST /api/me/password`                | user   | Change your own password                    |
| `POST /api/me/avatar`                  | user   | Upload your profile picture (multipart)     |
| `DELETE /api/me/avatar`                | user   | Remove your profile picture                 |
| `GET /api/users/{id}/avatar`           | user   | Serve a user's profile picture              |
| `GET /api/courses`                     | user   | List courses (with the user's progress)     |
| `GET /api/courses/{slug}`              | user   | A course with its lessons (content + progress) |
| `POST /api/courses/{id}/enroll`        | user   | Enrol in a course                           |
| `DELETE /api/courses/{id}/enroll`      | user   | Cancel an enrolment                         |
| `POST /api/lessons/{id}/complete`      | user   | Mark a lesson finished                      |
| `DELETE /api/lessons/{id}/complete`    | user   | Un-mark a lesson                            |
| `GET /api/lessons/{id}/video`          | user   | Stream a lesson's uploaded video            |
| `GET /api/lessons/{id}/pdf`            | user   | View a lesson's uploaded PDF                |
| `GET /api/courses/{id}/cover`          | user   | Serve a course cover image                  |
| `GET /api/lessons/{id}/cover`          | user   | Serve a lesson cover image                  |
| `GET /api/quizzes/{id}`                | user   | A quiz for taking (no correct answers)      |
| `POST /api/quizzes/{id}/attempt`       | user   | Submit answers, get scored                  |
| `GET /api/certificates`                | user   | List your earned certificates               |
| `GET /api/certificates/{id}`           | user   | One certificate (own, or admin)             |
| `GET /api/publications`                | public | List research publications                  |
| `GET /api/publications/{id}/file`      | public | Open a publication's PDF                    |
| `GET /api/admin/users`                 | admin  | List all users                              |
| `POST /api/admin/users`                | admin  | Create a user                               |
| `DELETE /api/admin/users/{id}`         | admin  | Delete a user                               |
| `PUT /api/admin/users/{id}/password`   | admin  | Reset a user's password                     |
| `POST /api/admin/courses`              | admin  | Create a course                             |
| `PUT /api/admin/courses/{id}`          | admin  | Update a course                             |
| `DELETE /api/admin/courses/{id}`       | admin  | Delete a course (and its lessons)           |
| `POST /api/admin/courses/{id}/lessons` | admin  | Add a lesson to a course                    |
| `PUT /api/admin/lessons/{id}`          | admin  | Update a lesson (incl. YouTube URL)         |
| `DELETE /api/admin/lessons/{id}`       | admin  | Delete a lesson                             |
| `POST /api/admin/lessons/{id}/video`   | admin  | Upload a lesson video (multipart)           |
| `POST /api/admin/lessons/{id}/pdf`     | admin  | Upload a lesson PDF (multipart)             |
| `DELETE /api/admin/lessons/{id}/video` | admin  | Remove a lesson's video                     |
| `DELETE /api/admin/lessons/{id}/pdf`   | admin  | Remove a lesson's PDF                       |
| `POST /api/admin/courses/{id}/cover`   | admin  | Upload a course cover image (multipart)     |
| `POST /api/admin/lessons/{id}/cover`   | admin  | Upload a lesson cover image (multipart)     |
| `DELETE /api/admin/courses/{id}/cover` | admin  | Remove a course's cover image               |
| `DELETE /api/admin/lessons/{id}/cover` | admin  | Remove a lesson's cover image               |
| `POST /api/admin/publications`         | admin  | Upload a research publication (multipart)    |
| `DELETE /api/admin/publications/{id}`  | admin  | Delete a publication                        |
| `POST /api/admin/courses/{id}/quiz`    | admin  | Create (or fetch) a course's quiz           |
| `POST /api/admin/lessons/{id}/quiz`    | admin  | Create (or fetch) a lesson's quiz           |
| `GET /api/admin/quizzes/{id}`          | admin  | A quiz with its questions and answers       |
| `PUT /api/admin/quizzes/{id}`          | admin  | Replace a quiz's questions and options      |
| `DELETE /api/admin/quizzes/{id}`       | admin  | Delete a quiz                               |

*Access:* **public** — no login needed · **user** — any logged-in user
(`ROLE_USER`) · **admin** — administrators only (`ROLE_ADMIN`).

Uploaded media is stored on disk under `backend/var/storage/` (the
`storage/` directory bind-mounted into the backend); raised PHP upload
limits live in `docker/backend/uploads.ini`.

### Data model

| Entity             | Table               | Notable fields & relations                          |
|--------------------|---------------------|-----------------------------------------------------|
| `User`             | `app_user`          | email (unique), roles, password, first/last name, avatar |
| `Course`           | `course`            | title, slug (unique), description, **instructor → User**, **lessons → Lesson[]** |
| `Lesson`           | `lesson`            | title, content, position, **course → Course**       |
| `Enrollment`       | `enrollment`        | **user → User**, **course → Course**, enrolledAt — unique per pair |
| `LessonCompletion` | `lesson_completion` | **user → User**, **lesson → Lesson**, completedAt — unique per pair |
| `Quiz`             | `quiz`              | passThreshold, **course → Course** *or* **lesson → Lesson** (one owner), **questions** |
| `QuizQuestion`     | `quiz_question`     | text, position, **quiz → Quiz**, **options** |
| `QuizOption`       | `quiz_option`       | text, correct, position, **question → QuizQuestion** |
| `QuizAttempt`      | `quiz_attempt`      | score, total, passed, **user → User**, **quiz → Quiz** |
| `Certificate`      | `certificate`       | code (unique), issuedAt, **user → User**, **course → Course** — unique per pair |
| `Publication`      | `publication`       | title, topic, author, description, uploaded file — searchable on the public Research page |

Courses and lessons carry an optional uploaded `coverPath` image; a
lesson also has `videoPath`, `pdfPath` and a `youtubeUrl`. Deleting a
course or lesson removes its uploaded files from disk. Deleting a
course cascades to its lessons, quizzes,
enrolments, completions and certificates; deleting a user removes that
user's enrolments, completions, attempts and certificates. Schema
changes are managed with Doctrine migrations (`backend/migrations/`).

## Authentication

The e-learning area requires a login; the public marketing pages stay
open. Authentication is **session-based** — a cookie keeps the user
signed in — and there is **no public registration**: administrators
create every account.

| Role         | Granted to     | Can …                                         |
|--------------|----------------|-----------------------------------------------|
| `ROLE_USER`  | every account  | enrol, study lessons, take quizzes, earn certificates |
| `ROLE_ADMIN` | administrators | additionally manage users and author courses, lessons and quizzes |

### Creating the first admin

The first account must be created from the command line — nobody is
logged in yet to use the admin screen:

```bash
docker compose exec backend php bin/console app:create-user \
  admin@example.com 'a-strong-password' Admin Name --admin
```

Then open the app, sign in at **`/login`**, and create further accounts
from the **Users** screen (`/admin/users`). Drop `--admin` — or pick the
*Felhasználó* role in the UI — to create a regular, non-admin user.

### Passwords

Any signed-in user can change their own password at **`/account/password`**
(current password required). Administrators can additionally reset any
account's password from the **Users** screen, without knowing the old one.

## Environments

### Staging

```bash
cp staging/.env.example staging/.env      # then edit the secrets
docker compose \
  -f docker-compose.yml \
  -f staging/docker-compose.staging.yml \
  --env-file staging/.env \
  up -d --build
```

### Production

```bash
cp production/.env.example production/.env   # then edit the secrets
docker compose \
  -f docker-compose.yml \
  -f production/docker-compose.production.yml \
  --env-file production/.env \
  up -d --build
```

Remember to run `doctrine:migrations:migrate` in each environment after
the first deploy.

> **Tip:** define a shell alias to avoid repeating the flags, e.g.
> `alias dc-staging='docker compose -f docker-compose.yml -f staging/docker-compose.staging.yml --env-file staging/.env'`

## Deployment

Code is edited directly on the server; `deploy.sh` builds it into images
and rolls out one environment at a time.

```bash
./deploy.sh staging       # build + deploy to staging  → https://178.105.197.49:8443
./deploy.sh production    # build + deploy to live     → https://178.105.197.49
```

Each run type-checks and lints the frontend, builds the images, starts
(or updates) the stack, waits for the database, applies Doctrine
migrations, then health-checks the site. A failing step aborts the
deploy **before** the running environment is replaced.

| Option | Effect |
|---|---|
| `--skip-checks` | Skip the frontend type-check / lint step |
| `--no-cache` | Rebuild images from scratch (see *Docker build cache* below) |

**Typical flow**

```
edit code on the server
  → ./deploy.sh staging        and test at https://178.105.197.49:8443
  → git commit && git push     back up to GitHub
  → ./deploy.sh production      go live at https://178.105.197.49
```

staging and production are fully isolated: separate containers, separate
database volumes and separate upload storage (`storage/` vs.
`storage-staging/`). Deploying to staging never affects live data.

### Docker build cache

If a build fails with a cache or snapshotter error, clear the cache and
retry with a clean build:

```bash
docker builder prune -f
./deploy.sh <env> --no-cache
```

## Common commands

### Docker

| Command | Description |
|---|---|
| `docker compose up -d --build` | Build and start in the background |
| `docker compose ps` | List service status |
| `docker compose logs -f <service>` | Follow a service's logs |
| `docker compose down` | Stop and remove containers |
| `docker compose down -v` | Also remove volumes (drops the database) |

### Backend (Symfony)

| Command | Description |
|---|---|
| `docker compose exec backend php bin/console doctrine:migrations:migrate` | Apply pending migrations |
| `docker compose exec backend php bin/console doctrine:migrations:diff` | Generate a migration from entity changes |
| `docker compose exec backend php bin/console app:seed-demo` | Load demo courses (skips if any exist) |
| `docker compose exec backend php bin/console app:create-user <email> <password> <first> <last> [--admin]` | Create a user account |
| `docker compose exec backend php bin/console debug:router` | List all routes |
| `docker compose exec backend composer require <package>` | Add a dependency |
| `docker compose exec database psql -U elearning elearning` | Open a `psql` shell |

### Frontend (Vue)

| Command | Description |
|---|---|
| `docker compose exec frontend npm run type-check` | Type-check with `vue-tsc` |
| `docker compose exec frontend npm run lint` | Lint and auto-fix |
| `docker compose exec frontend npm run build` | Production build |

## Configuration

All settings are environment variables — see `.env.example` for the full
list with descriptions. The most important:

| Variable | Purpose |
|---|---|
| `COMPOSE_PROJECT_NAME` | Isolates containers/volumes per environment |
| `APP_ENV` | Symfony environment (`dev` / `staging` / `prod`) |
| `APP_SECRET` | Symfony application secret — unique per environment |
| `POSTGRES_*` | Database name, user, password, host port |
| `SITE_ADDRESS` | Domain Caddy serves (`localhost` for development) |
| `ACME_EMAIL` | Contact email for Let's Encrypt (public domains) |
| `VITE_API_URL` | Base path the frontend uses for API calls (`/api`) |

`.env`, `staging/.env` and `production/.env` are git-ignored — **never
commit real secrets.**

## What's next

The skeleton is intentionally small. Natural next steps:

- **Rich text lessons** — the lesson body is plain text today; add Markdown or a WYSIWYG editor.
- **Quiz variety** — multiple correct answers, free-text questions, timed quizzes.
- **Production hardening** — multi-stage image builds (`composer install --no-dev`, a static Vue build) and running migrations automatically on deploy.
