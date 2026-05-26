<?php

$dotenvPath = __DIR__ . '/../../../.env';
if (file_exists($dotenvPath)) {
    foreach (file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($val));
    }
}

try {
    $pdo = new PDO(
        'pgsql:host=' . getenv('DB_HOST') . ';port=5432;dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$groupMap = [];
foreach ($pdo->query('SELECT id, slug FROM muscle_groups')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $groupMap[$row['slug']] = (int) $row['id'];
}

$exercises = json_decode(file_get_contents(__DIR__ . '/exercises.json'), true);

$stmt = $pdo->prepare(
    'INSERT INTO exercises (muscle_group_id, name, exercise_type, description)
     VALUES (?, ?, ?, ?)
     ON CONFLICT (muscle_group_id, name) DO NOTHING'
);

$inserted = 0;
$skipped  = 0;

foreach ($exercises as $ex) {
    $groupId = $groupMap[$ex['muscle_group_slug']] ?? null;
    if ($groupId === null) {
        echo "Unknown slug: {$ex['muscle_group_slug']} — skipping {$ex['name']}" . PHP_EOL;
        continue;
    }

    $stmt->execute([$groupId, $ex['name'], $ex['exercise_type'], $ex['description']]);
    if ($stmt->rowCount() > 0) {
        $inserted++;
    } else {
        $skipped++;
    }
}

echo "Done. Inserted: {$inserted}, already existed (skipped): {$skipped}" . PHP_EOL;
