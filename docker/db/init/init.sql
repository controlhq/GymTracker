CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "citext";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- ---------- 1. AUTH / IDENTITY ----------------------------------------------

CREATE TABLE users (
    id            UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    email         CITEXT      NOT NULL UNIQUE,
    password_hash TEXT,
    display_name  TEXT        NOT NULL,
    unit_system   TEXT        NOT NULL DEFAULT 'metric'
                    CHECK (unit_system IN ('metric', 'imperial')),
    role          TEXT        NOT NULL DEFAULT 'user'
                    CHECK (role IN ('user', 'admin')),
    is_active     BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_login_at TIMESTAMPTZ,
    CONSTRAINT users_email_format CHECK (email ~ '^[^@]+@[^@]+\.[^@]+$')
);

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

-- ---------- 2. CATALOG (global, admin-managed) ------------------------------

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
CREATE INDEX idx_exercises_muscle    ON exercises(muscle_group_id) WHERE is_active;
CREATE INDEX idx_exercises_search    ON exercises USING GIN (search_vector);
CREATE INDEX idx_exercises_name_trgm ON exercises USING GIN (name gin_trgm_ops);

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
    id                     UUID     PRIMARY KEY DEFAULT gen_random_uuid(),
    session_id             UUID     NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
    exercise_id            UUID     REFERENCES exercises(id) ON DELETE SET NULL,
    exercise_name_snapshot TEXT     NOT NULL,
    position               SMALLINT NOT NULL,
    is_completed           BOOLEAN  NOT NULL DEFAULT FALSE,
    UNIQUE (session_id, position)
);
CREATE INDEX idx_session_exercises_session  ON session_exercises(session_id);
CREATE INDEX idx_session_exercises_exercise ON session_exercises(exercise_id);

CREATE TABLE session_sets (
    id                  UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    session_exercise_id UUID         NOT NULL REFERENCES session_exercises(id) ON DELETE CASCADE,
    set_number          SMALLINT     NOT NULL,
    set_type            TEXT         NOT NULL DEFAULT 'working'
                          CHECK (set_type IN ('warmup', 'working', 'dropset', 'failure', 'amrap')),
    weight_kg           NUMERIC(6,2) CHECK (weight_kg >= 0),
    reps                SMALLINT     CHECK (reps >= 0),
    rpe                 SMALLINT     CHECK (rpe BETWEEN 1 AND 10),
    rest_sec            SMALLINT     CHECK (rest_sec >= 0),
    is_completed        BOOLEAN      NOT NULL DEFAULT FALSE,
    completed_at        TIMESTAMPTZ,
    notes               TEXT,
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

-- ---------- TRIGGER: stamp session ended_at ---------------------------------

CREATE OR REPLACE FUNCTION stamp_session_ended_at() RETURNS TRIGGER AS $$
BEGIN
    -- Gdy sesja przechodzi z 'in_progress' na completed/aborted, znacz czas zakończenia.
    IF NEW.status <> 'in_progress' AND NEW.ended_at IS NULL THEN
        NEW.ended_at = NOW();
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sessions_stamp_ended BEFORE UPDATE ON sessions
    FOR EACH ROW EXECUTE FUNCTION stamp_session_ended_at();

-- ---------- ROW-LEVEL SECURITY ----------------------------------------------

ALTER TABLE workout_plans     ENABLE ROW LEVEL SECURITY;
ALTER TABLE plan_exercises    ENABLE ROW LEVEL SECURITY;
ALTER TABLE plan_sets         ENABLE ROW LEVEL SECURITY;
ALTER TABLE sessions          ENABLE ROW LEVEL SECURITY;
ALTER TABLE session_exercises ENABLE ROW LEVEL SECURITY;
ALTER TABLE session_sets      ENABLE ROW LEVEL SECURITY;

CREATE POLICY plans_owner ON workout_plans
    USING (user_id = current_setting('app.current_user_id', true)::uuid);

CREATE POLICY plan_ex_owner ON plan_exercises
    USING (EXISTS (SELECT 1 FROM workout_plans p
                   WHERE p.id = plan_exercises.workout_plan_id
                     AND p.user_id = current_setting('app.current_user_id', true)::uuid));

CREATE POLICY plan_sets_owner ON plan_sets
    USING (EXISTS (SELECT 1 FROM plan_exercises pe
                   JOIN workout_plans p ON p.id = pe.workout_plan_id
                   WHERE pe.id = plan_sets.plan_exercise_id
                     AND p.user_id = current_setting('app.current_user_id', true)::uuid));

CREATE POLICY sessions_owner ON sessions
    USING (user_id = current_setting('app.current_user_id', true)::uuid);

CREATE POLICY session_ex_owner ON session_exercises
    USING (EXISTS (SELECT 1 FROM sessions s
                   WHERE s.id = session_exercises.session_id
                     AND s.user_id = current_setting('app.current_user_id', true)::uuid));

CREATE POLICY session_sets_owner ON session_sets
    USING (EXISTS (SELECT 1 FROM session_exercises se
                   JOIN sessions s ON s.id = se.session_id
                   WHERE se.id = session_sets.session_exercise_id
                     AND s.user_id = current_setting('app.current_user_id', true)::uuid));

-- ---------- VIEWS -----------------------------------------------------------

-- Zagregowane statystyki kont dla panelu administratora.
CREATE VIEW user_stats AS
SELECT u.id, u.email, u.display_name, u.role, u.is_active,
       u.created_at, u.last_login_at,
       COUNT(DISTINCT wp.id)                                       AS plans_count,
       COUNT(DISTINCT s.id) FILTER (WHERE s.status = 'completed')  AS completed_sessions,
       MAX(s.started_at)                                           AS last_activity_at
FROM users u
LEFT JOIN workout_plans wp ON wp.user_id = u.id
LEFT JOIN sessions s       ON s.user_id  = u.id
GROUP BY u.id;

-- ---------- SEED: muscle groups ---------------------------------------------

INSERT INTO muscle_groups (id, name, slug) VALUES
    (1, 'Chest',     'chest'),
    (2, 'Back',      'back'),
    (3, 'Legs',      'legs'),
    (4, 'Shoulders', 'shoulders'),
    (5, 'Arms',      'arms'),
    (6, 'Core',      'core');

-- ---------- SEED: exercise catalogue -----------------------------------------
-- Resolves muscle_group_slug -> id via join so it stays correct if ids change.

INSERT INTO exercises (muscle_group_id, name, exercise_type, description)
SELECT mg.id, v.name, v.exercise_type, v.description
FROM (VALUES
    ('chest', 'Bench Press', 'compound', 'Classic horizontal barbell press. Lie flat on a bench, lower the bar to mid-chest, press back up. Primary chest builder.'),
    ('chest', 'Incline Bench Press', 'compound', 'Barbell or dumbbell press on a 30-45° incline. Emphasises the upper portion of the pectorals.'),
    ('chest', 'Decline Bench Press', 'compound', 'Press performed on a downward-angled bench. Targets the lower chest and allows heavier loads than flat pressing.'),
    ('chest', 'Dumbbell Flye', 'isolation', 'Lying dumbbell fly with a slight elbow bend. Stretches and contracts the pectorals through a wide arc.'),
    ('chest', 'Cable Crossover', 'isolation', 'High-to-low or level cable fly. Provides constant tension across the full range of motion.'),
    ('chest', 'Push-Up', 'compound', 'Bodyweight pressing movement. Engages chest, anterior delts, and triceps. Scalable via elevation or resistance bands.'),
    ('chest', 'Chest Dip', 'compound', 'Parallel-bar dip with a slight forward lean to maximise pectoral stretch and activation.'),
    ('chest', 'Pec Deck', 'isolation', 'Machine fly that isolates the pectorals without requiring stabilisation. Good for mind-muscle connection work.'),
    ('back', 'Deadlift', 'compound', 'Barbell pull from the floor. Full posterior-chain movement — lats, spinal erectors, glutes, hamstrings. Foundation of strength training.'),
    ('back', 'Pull-Up', 'compound', 'Overhand-grip bodyweight vertical pull. Targets lats, rhomboids, and biceps. Add weight via belt for progression.'),
    ('back', 'Barbell Row', 'compound', 'Bent-over barbell row with overhand or underhand grip. Builds thickness in the mid and upper back.'),
    ('back', 'Lat Pulldown', 'compound', 'Cable pulldown to the upper chest. Develops lat width. Use a wide or neutral grip for different emphases.'),
    ('back', 'Seated Cable Row', 'compound', 'Horizontal cable row from a seated position. Targets the mid-back, rhomboids, and rear delts.'),
    ('back', 'Single-Arm Dumbbell Row', 'compound', 'Unilateral row braced against a bench. Allows a longer range of motion and corrects side-to-side imbalances.'),
    ('back', 'Face Pull', 'isolation', 'Cable pull to the face with rope attachment. Primarily targets rear delts and external rotators. Shoulder health essential.'),
    ('back', 'T-Bar Row', 'compound', 'Landmine or dedicated T-bar row. Allows heavy loading with a neutral spine. Great for back thickness.'),
    ('legs', 'Squat', 'compound', 'Barbell back squat — the king of lower-body exercises. Trains quads, glutes, and entire posterior chain under heavy load.'),
    ('legs', 'Romanian Deadlift', 'compound', 'Hip-hinge movement keeping the bar close to the legs. Primary hamstring and glute stretch-under-load exercise.'),
    ('legs', 'Leg Press', 'compound', 'Machine-based quad-dominant press. Allows heavy loading without spinal compression. Foot position shifts emphasis.'),
    ('legs', 'Leg Curl', 'isolation', 'Lying or seated machine curl that isolates the hamstrings. Key accessory for knee health and hamstring development.'),
    ('legs', 'Leg Extension', 'isolation', 'Quad isolation via machine knee extension. Useful for VMO development and rehabilitation work.'),
    ('legs', 'Standing Calf Raise', 'isolation', 'Plantarflexion against load. Targets the gastrocnemius. Full stretch at the bottom is essential for hypertrophy.'),
    ('legs', 'Lunge', 'compound', 'Unilateral step movement targeting quads and glutes. Walking, reverse, or static variations each have different demands.'),
    ('legs', 'Bulgarian Split Squat', 'compound', 'Rear-foot-elevated split squat. High quad and glute demand. Exposes and corrects limb asymmetries.'),
    ('legs', 'Hack Squat', 'compound', 'Machine or barbell squat variation with an upright torso. Shifts load further onto the quads.'),
    ('shoulders', 'Overhead Press', 'compound', 'Standing barbell press overhead. Primary shoulder mass builder. Also loads the upper chest and triceps.'),
    ('shoulders', 'Dumbbell Shoulder Press', 'compound', 'Seated or standing dumbbell press. Allows greater range of motion than the barbell version and corrects imbalances.'),
    ('shoulders', 'Lateral Raise', 'isolation', 'Dumbbell or cable raise to shoulder height. Targets the medial deltoid — essential for shoulder width.'),
    ('shoulders', 'Front Raise', 'isolation', 'Dumbbell raise to the front. Targets the anterior deltoid. Often deprioritised since pressing movements cover front delts well.'),
    ('shoulders', 'Rear Delt Fly', 'isolation', 'Bent-over or machine fly targeting the posterior deltoid. Critical for shoulder balance and posture.'),
    ('shoulders', 'Arnold Press', 'compound', 'Dumbbell press with a rotational component through the full range. Hits anterior and medial delts through a longer arc.'),
    ('shoulders', 'Upright Row', 'compound', 'Barbell or cable pull to the chin with elbows flared. Trains medial delts and upper traps together.'),
    ('shoulders', 'Cable Lateral Raise', 'isolation', 'Single-arm lateral raise using a low cable pulley. Maintains tension at the bottom of the movement unlike dumbbells.'),
    ('arms', 'Barbell Curl', 'isolation', 'Standing supinated curl with a barbell. Primary biceps mass exercise. Allows the heaviest loading of any curl variation.'),
    ('arms', 'Hammer Curl', 'isolation', 'Neutral-grip dumbbell curl. Targets the brachialis and brachioradialis alongside the biceps. Adds arm thickness.'),
    ('arms', 'Incline Dumbbell Curl', 'isolation', 'Curl performed on an incline bench with arms hanging behind the torso. Maximises the long head stretch of the biceps.'),
    ('arms', 'Preacher Curl', 'isolation', 'Curl with the upper arm braced on a preacher pad. Eliminates cheat and emphasises the peak contraction.'),
    ('arms', 'Tricep Pushdown', 'isolation', 'Cable pushdown with rope or bar attachment. Isolates the triceps through elbow extension. High rep finishing move.'),
    ('arms', 'Skull Crusher', 'isolation', 'Lying EZ-bar or dumbbell extension lowering toward the forehead. Effective for tricep mass — especially the long head.'),
    ('arms', 'Close-Grip Bench Press', 'compound', 'Bench press with a shoulder-width grip. Allows heavy tricep loading. Overlaps with chest but tricep emphasis is primary.'),
    ('arms', 'Overhead Tricep Extension', 'isolation', 'Dumbbell or cable extension with arms overhead. Stretches the long head of the triceps — best for overall tricep mass.'),
    ('arms', 'Tricep Dip', 'compound', 'Parallel-bar dip performed upright. Heavy bodyweight or weighted tricep exercise with carryover to pressing strength.'),
    ('core', 'Plank', 'mobility', 'Isometric hold with a straight body from head to heels. Builds anti-extension core stability. Foundation movement.'),
    ('core', 'Ab Wheel Rollout', 'compound', 'Rolling the ab wheel forward from a kneeling position. High demand on the rectus abdominis and anti-extension capability.'),
    ('core', 'Hanging Leg Raise', 'isolation', 'Leg raise from a dead hang. Targets the lower abs and hip flexors. Keep the pelvis posteriorly tilted at the top.'),
    ('core', 'Cable Crunch', 'isolation', 'Kneeling crunch using a cable overhead. Allows progressive overload on the rectus abdominis unlike bodyweight crunches.'),
    ('core', 'Russian Twist', 'isolation', 'Seated rotation holding a weight. Trains the obliques. Add a plate or medicine ball for progressive resistance.'),
    ('core', 'Crunch', 'isolation', 'Basic spinal flexion from the floor. Targets upper rectus abdominis. High reps or add a plate for resistance.'),
    ('core', 'Dead Bug', 'mobility', 'Supine alternating arm-leg extension. Trains core stability and anti-extension without spinal loading. Rehab-friendly.'),
    ('core', 'Pallof Press', 'isolation', 'Anti-rotation cable press. Resists rotational force, training the obliques isometrically. Underrated core stability exercise.')
) AS v(slug, name, exercise_type, description)
JOIN muscle_groups mg ON mg.slug = v.slug
ON CONFLICT (muscle_group_id, name) DO NOTHING;

-- ---------- SEED: default administrator -------------------------------------
-- A ready-to-use admin account so the admin panel works on a fresh start.
-- Login: admin@gym.local  /  admin12345
INSERT INTO users (email, password_hash, display_name, role)
VALUES (
    'admin@gym.local',
    '$2y$10$6b1zIMQTGNpXoG8J64tPCuvvN2ZesP2NPTCL.3784KbaX1EBeJ/v2',
    'Administrator',
    'admin'
)
ON CONFLICT (email) DO NOTHING;
