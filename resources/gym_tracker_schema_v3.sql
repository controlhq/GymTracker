-- ============================================================================
-- GymTracker — Full Database Schema v3 (PostgreSQL 14+)
-- ============================================================================
-- Changes vs v2:
--   - users.is_admin -> users.role (TEXT + CHECK)
--   - role values: 'user', 'admin' (extendable without schema rewrite)
-- ============================================================================

CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "citext";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- ---------- 1. AUTH / IDENTITY ----------------------------------------------

CREATE TABLE users (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    email           CITEXT      NOT NULL UNIQUE,
    password_hash   TEXT,
    display_name    TEXT        NOT NULL,
    unit_system     TEXT        NOT NULL DEFAULT 'metric'
                      CHECK (unit_system IN ('metric', 'imperial')),
    role            TEXT        NOT NULL DEFAULT 'user'
                      CHECK (role IN ('user', 'admin')),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT users_email_format CHECK (email ~ '^[^@]+@[^@]+\.[^@]+$')
);

COMMENT ON COLUMN users.unit_system IS
    'UI display preference only. All weights stored in kg regardless.';
COMMENT ON COLUMN users.role IS
    'User role for authorization. Extend CHECK constraint to add roles (e.g. trainer, moderator).';

-- Partial index: zwykle szukasz nie-default role (adminów, trainerów...).
-- Partial index jest mniejszy niż pełny i szybszy przy tego typu zapytaniach.
CREATE INDEX idx_users_role ON users(role) WHERE role != 'user';

CREATE TABLE auth_providers (
    id                UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id           UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider          TEXT        NOT NULL,
    provider_user_id  TEXT        NOT NULL,
    linked_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (provider, provider_user_id)
);
CREATE INDEX idx_auth_providers_user ON auth_providers(user_id);

CREATE TABLE refresh_tokens (
    id           UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id      UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash   TEXT        NOT NULL UNIQUE,
    expires_at   TIMESTAMPTZ NOT NULL,
    revoked_at   TIMESTAMPTZ,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    user_agent   TEXT,
    ip_inet      INET
);
CREATE INDEX idx_refresh_tokens_user ON refresh_tokens(user_id) WHERE revoked_at IS NULL;

-- ---------- 2. CATALOG (global, admin-managed) -----------------------------

CREATE TABLE muscle_groups (
    id    SMALLINT PRIMARY KEY,
    name  TEXT NOT NULL UNIQUE,
    slug  TEXT NOT NULL UNIQUE
);

CREATE TABLE exercises (
    id               UUID     PRIMARY KEY DEFAULT gen_random_uuid(),
    muscle_group_id  SMALLINT NOT NULL REFERENCES muscle_groups(id) ON DELETE RESTRICT,
    name             TEXT     NOT NULL,
    description      TEXT,
    exercise_type    TEXT     NOT NULL DEFAULT 'compound'
                       CHECK (exercise_type IN ('compound', 'isolation', 'mobility', 'cardio')),
    animation_url    TEXT,
    thumbnail_url    TEXT,
    animation_format TEXT     DEFAULT 'lottie'
                       CHECK (animation_format IN ('lottie', 'webm', 'mp4', 'none')),
    is_active        BOOLEAN  NOT NULL DEFAULT TRUE,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    search_vector tsvector GENERATED ALWAYS AS (
        setweight(to_tsvector('simple', coalesce(name, '')), 'A') ||
        setweight(to_tsvector('simple', coalesce(description, '')), 'B')
    ) STORED,
    UNIQUE (muscle_group_id, name)
);
CREATE INDEX idx_exercises_muscle     ON exercises(muscle_group_id) WHERE is_active;
CREATE INDEX idx_exercises_search     ON exercises USING GIN (search_vector);
CREATE INDEX idx_exercises_name_trgm  ON exercises USING GIN (name gin_trgm_ops);

-- ---------- 3. PLANS (user-owned templates) ---------------------------------

CREATE TABLE workout_plans (
    id            UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id       UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name          TEXT        NOT NULL,
    description   TEXT,
    status        TEXT        NOT NULL DEFAULT 'draft'
                    CHECK (status IN ('draft', 'routine', 'active', 'archived')),
    intensity     SMALLINT    CHECK (intensity BETWEEN 1 AND 5),
    duration_min  SMALLINT    CHECK (duration_min > 0),
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_plans_user_status ON workout_plans(user_id, status);
CREATE UNIQUE INDEX idx_one_active_plan_per_user
    ON workout_plans(user_id) WHERE status = 'active';

CREATE TABLE plan_exercises (
    id               UUID     PRIMARY KEY DEFAULT gen_random_uuid(),
    workout_plan_id  UUID     NOT NULL REFERENCES workout_plans(id) ON DELETE CASCADE,
    exercise_id      UUID     NOT NULL REFERENCES exercises(id) ON DELETE RESTRICT,
    position         SMALLINT NOT NULL,
    notes            TEXT,
    UNIQUE (workout_plan_id, position)
);
CREATE INDEX idx_plan_exercises_plan ON plan_exercises(workout_plan_id);

CREATE TABLE plan_sets (
    id                UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    plan_exercise_id  UUID         NOT NULL REFERENCES plan_exercises(id) ON DELETE CASCADE,
    set_number        SMALLINT     NOT NULL,
    set_type          TEXT         NOT NULL DEFAULT 'working'
                        CHECK (set_type IN ('warmup', 'working', 'dropset', 'failure', 'amrap')),
    target_weight_kg  NUMERIC(6,2) CHECK (target_weight_kg >= 0),
    target_reps       SMALLINT     CHECK (target_reps > 0),
    target_rest_sec   SMALLINT     CHECK (target_rest_sec >= 0),
    UNIQUE (plan_exercise_id, set_number)
);

-- ---------- 4. SESSIONS (immutable history) ---------------------------------

CREATE TABLE sessions (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    source_plan_id  UUID        REFERENCES workout_plans(id) ON DELETE SET NULL,
    name            TEXT        NOT NULL,
    status          TEXT        NOT NULL DEFAULT 'in_progress'
                      CHECK (status IN ('in_progress', 'completed', 'aborted')),
    started_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ended_at        TIMESTAMPTZ,
    CHECK (ended_at IS NULL OR ended_at >= started_at)
);
CREATE INDEX idx_sessions_user_started ON sessions(user_id, started_at DESC);
CREATE UNIQUE INDEX idx_one_active_session_per_user
    ON sessions(user_id) WHERE status = 'in_progress';

CREATE TABLE session_exercises (
    id                       UUID     PRIMARY KEY DEFAULT gen_random_uuid(),
    session_id               UUID     NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
    exercise_id              UUID     REFERENCES exercises(id) ON DELETE SET NULL,
    exercise_name_snapshot   TEXT     NOT NULL,
    position                 SMALLINT NOT NULL,
    UNIQUE (session_id, position)
);
CREATE INDEX idx_session_exercises_session  ON session_exercises(session_id);
CREATE INDEX idx_session_exercises_exercise ON session_exercises(exercise_id);

CREATE TABLE session_sets (
    id                    UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    session_exercise_id   UUID         NOT NULL REFERENCES session_exercises(id) ON DELETE CASCADE,
    set_number            SMALLINT     NOT NULL,
    set_type              TEXT         NOT NULL DEFAULT 'working'
                            CHECK (set_type IN ('warmup', 'working', 'dropset', 'failure', 'amrap')),
    weight_kg             NUMERIC(6,2) CHECK (weight_kg >= 0),
    reps                  SMALLINT     CHECK (reps >= 0),
    rpe                   SMALLINT     CHECK (rpe BETWEEN 1 AND 10),
    rest_sec              SMALLINT     CHECK (rest_sec >= 0),
    is_completed          BOOLEAN      NOT NULL DEFAULT FALSE,
    completed_at          TIMESTAMPTZ,
    notes                 TEXT,
    UNIQUE (session_exercise_id, set_number)
);
CREATE INDEX idx_session_sets_exercise ON session_sets(session_exercise_id);

-- ---------- TRIGGER: updated_at ---------------------------------------------

CREATE OR REPLACE FUNCTION touch_updated_at() RETURNS TRIGGER AS $$
BEGIN NEW.updated_at = NOW(); RETURN NEW; END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_users_updated BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE TRIGGER trg_plans_updated BEFORE UPDATE ON workout_plans
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ---------- ROW-LEVEL SECURITY ----------------------------------------------

ALTER TABLE workout_plans     ENABLE ROW LEVEL SECURITY;
ALTER TABLE plan_exercises    ENABLE ROW LEVEL SECURITY;
ALTER TABLE plan_sets         ENABLE ROW LEVEL SECURITY;
ALTER TABLE sessions          ENABLE ROW LEVEL SECURITY;
ALTER TABLE session_exercises ENABLE ROW LEVEL SECURITY;
ALTER TABLE session_sets      ENABLE ROW LEVEL SECURITY;

CREATE POLICY plans_owner ON workout_plans
    USING (user_id = current_setting('app.current_user_id')::uuid);

CREATE POLICY plan_ex_owner ON plan_exercises
    USING (EXISTS (SELECT 1 FROM workout_plans p
                   WHERE p.id = plan_exercises.workout_plan_id
                     AND p.user_id = current_setting('app.current_user_id')::uuid));

CREATE POLICY plan_sets_owner ON plan_sets
    USING (EXISTS (SELECT 1 FROM plan_exercises pe
                   JOIN workout_plans p ON p.id = pe.workout_plan_id
                   WHERE pe.id = plan_sets.plan_exercise_id
                     AND p.user_id = current_setting('app.current_user_id')::uuid));

CREATE POLICY sessions_owner ON sessions
    USING (user_id = current_setting('app.current_user_id')::uuid);

CREATE POLICY session_ex_owner ON session_exercises
    USING (EXISTS (SELECT 1 FROM sessions s
                   WHERE s.id = session_exercises.session_id
                     AND s.user_id = current_setting('app.current_user_id')::uuid));

CREATE POLICY session_sets_owner ON session_sets
    USING (EXISTS (SELECT 1 FROM session_exercises se
                   JOIN sessions s ON s.id = se.session_id
                   WHERE se.id = session_sets.session_exercise_id
                     AND s.user_id = current_setting('app.current_user_id')::uuid));

-- ---------- SEED: muscle groups ---------------------------------------------

INSERT INTO muscle_groups (id, name, slug) VALUES
    (1, 'Chest',     'chest'),
    (2, 'Back',      'back'),
    (3, 'Legs',      'legs'),
    (4, 'Shoulders', 'shoulders'),
    (5, 'Arms',      'arms'),
    (6, 'Core',      'core');

-- ---------- HOW TO ADD A NEW ROLE LATER -------------------------------------
-- Example: add 'trainer' role.
--
--   ALTER TABLE users DROP CONSTRAINT users_role_check;
--   ALTER TABLE users ADD CONSTRAINT users_role_check
--       CHECK (role IN ('user', 'admin', 'trainer'));
--
-- No data migration needed. Existing rows keep their values.
