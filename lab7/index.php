<?php

$allowed_categories = ['Praca', 'Dom', 'Nauka', 'Zdrowie', 'Inne'];
$allowed_priorities = ['Niski', 'Średni', 'Wysoki'];
$allowed_statuses = ['Do zrobienia', 'W trakcie', 'Zakończone'];

$tasks = [
    ['title' => 'Wdrożenie nowego systemu logowania', 'category' => 'Praca', 'priority' => 'Wysoki', 'status' => 'W trakcie', 'estimated_minutes' => 120, 'tags' => ['backend', 'frontend']],
    ['title' => 'Naprawa błędu w płatnościach', 'category' => 'Praca', 'priority' => 'Wysoki', 'status' => 'Do zrobienia', 'estimated_minutes' => 60, 'tags' => ['pilne', 'backend']],
    ['title' => 'Nauka CSS Grid i Flexbox', 'category' => 'Nauka', 'priority' => 'Średni', 'status' => 'W trakcie', 'estimated_minutes' => 90, 'tags' => ['frontend']],
    ['title' => 'Zakupy spożywcze', 'category' => 'Dom', 'priority' => 'Niski', 'status' => 'Zakończone', 'estimated_minutes' => 45, 'tags' => ['dom', 'zakupy']],
];

$errors = [];
$form_data = [
    'title' => '', 'category' => '', 'priority' => '', 'status' => '', 'estimated_minutes' => '', 'tags' => []
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $form_data['title'] = trim($_POST["title"] ?? '');
    $form_data['category'] = $_POST["category"] ?? '';
    $form_data['priority'] = $_POST["priority"] ?? '';
    $form_data['status'] = $_POST["status"] ?? '';
    $form_data['estimated_minutes'] = trim($_POST["estimated_minutes"] ?? '');
    $form_data['tags'] = $_POST["tags"] ?? [];

    if (empty($form_data['title'])) $errors[] = "Tytuł nie może być pusty.";
    if (!is_numeric($form_data['estimated_minutes']) || (int)$form_data['estimated_minutes'] <= 0) $errors[] = "Czas musi być liczbą dodatnią.";
    if (empty($form_data['tags'])) $errors[] = "Należy wybrać co najmniej jeden tag.";
    if (!in_array($form_data['category'], $allowed_categories)) $errors[] = "Nieprawidłowa kategoria.";
    if (!in_array($form_data['priority'], $allowed_priorities)) $errors[] = "Nieprawidłowy priorytet.";
    if (!in_array($form_data['status'], $allowed_statuses)) $errors[] = "Nieprawidłowy status.";

    if (empty($errors)) {
        $tags = $form_data['tags'] ?? [];
        $tags = array_filter($tags, function($t) {
            return trim($t) !== '';
        });
        sort($tags);

        $new_task = [
            'title' => $form_data['title'],
            'category' => $form_data['category'],
            'priority' => $form_data['priority'],
            'status' => $form_data['status'],
            'estimated_minutes' => (int)$form_data['estimated_minutes'],
            'tags' => array_map('htmlspecialchars', $tags)
        ];

        $tasks[] = $new_task;
    }
}

$stat_wszystkie = count($tasks);
$stat_do_zrobienia = count(array_filter($tasks, function($t) {
    return $t['status'] === 'Do zrobienia';
}));
$stat_w_trakcie = count(array_filter($tasks, function($t) {
    return $t['status'] === 'W trakcie';
}));
$stat_zakonczone = count(array_filter($tasks, function($t) {
    return $t['status'] === 'Zakończone';
}));
$stat_minuty = array_sum(array_column($tasks, 'estimated_minutes'));

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Menedżer zadań</title>
    <link href="style.css" rel="stylesheet"
          type="text/css">
</head>
<body class="grid-container">
<header>
    <div id="title">Menedżer Zadań</div>
    <nav class="nav-list">
        <a href="#" class="nav-item">Wszystkie</a>
        <a href="#" class="nav-item">Do zrobienia</a>
        <a href="#" class="nav-item">W trakcie</a>
        <a href="#" class="nav-item nav-active">Zakończone</a>
        <img class="nav-item" id="profile-pic" src="https://www.w3schools.com/howto/img_avatar2.png">
    </nav>
</header>
<aside>
    <h4>Dodaj zadanie</h4>
    <div id="errors">
        <?php foreach ($errors as $error): ?>
        <p class="error"><?php echo $error . "\n"; endforeach; ?></p>
    </div>
    <form action="index.php" method="post">
        <div>
            <label for="tytul-zad">Tytuł zadania</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($form_data['title'] ?? ''); ?>" id="tytul-zad" placeholder="Wpisz tytuł..." required>
        </div>
        <div>
            <label for="kategoria-zad">Kategoria</label>
            <select name="category" id="kategoria-zad" required>
            <option disabled <?php echo empty($form_data['category']) ? 'selected' : ''; ?> hidden>-- wybierz --</option>
            <option value="Praca" <?php echo ($form_data['category'] === 'Praca') ? 'selected' : ''; ?>>Praca</option>
            <option value="Dom" <?php echo ($form_data['category'] === 'Dom') ? 'selected' : ''; ?>>Dom</option>
            <option value="Nauka" <?php echo ($form_data['category'] === 'Nauka') ? 'selected' : ''; ?>>Nauka</option>
            <option value="Zdrowie" <?php echo ($form_data['category'] === 'Zdrowie') ? 'selected' : ''; ?>>Zdrowie</option>
            <option value="Inne" <?php echo ($form_data['category'] === 'Inne') ? 'selected' : ''; ?>>Inne</option>
            </select>
        </div>
        <div>
            <label for="priorytet-zad">Priorytet</label>
            <select name="priority" id="priorytet-zad" required>
            <option disabled <?php echo empty($form_data['priority']) ? 'selected' : ''; ?> hidden>-- wybierz --</option>
            <option value="Niski" <?php echo ($form_data['priority'] === 'Niski') ? 'selected' : ''; ?>>Niski</option>
            <option value="Średni" <?php echo ($form_data['priority'] === 'Średni') ? 'selected' : ''; ?>>Średni</option>
            <option value="Wysoki" <?php echo ($form_data['priority'] === 'Wysoki') ? 'selected' : ''; ?>>Wysoki</option>
            </select>
        </div>
        <div>
            <label for="status-zad">Status</label>
            <select name="status" id="status-zad" required>
            <option value="Do zrobienia">Do zrobienia</option>
            <option value="W trakcie">W trakcie</option>
            <option value="Zakończone">Zakończone</option>
            </select>
        </div>
        <div>
            <label for="czas">Szacowany czas (minuty)</label>
            <input type="text" name="estimated_minutes" value="<?php echo htmlspecialchars($form_data['estimated_minutes'] ?? ''); ?>" id="czas" placeholder="60">
        </div>
        <div id="tagi">
            <label>Tagi:</label>
            <div class="tag-box">
                <input type="checkbox" id="pilne" name="tags[]" value="pilne" <?php echo in_array('pilne', $form_data['tags']) ? 'checked' : ''; ?>>
                <label for="pilne">Pilne</label>
            </div>
            <div class="tag-box">
                <input type="checkbox" id="backend" name="tags[]" value="backend" <?php echo in_array('backend', $form_data['tags']) ? 'checked' : ''; ?>>
                <label for="backend">Backend</label>
            </div>
            <div class="tag-box">
                <input type="checkbox" id="frontend" name="tags[]" value="frontend" <?php echo in_array('frontend', $form_data['tags']) ? 'checked' : ''; ?>>
                <label for="frontend">Frontend</label>
            </div>
            <div class="tag-box">
                <input type="checkbox" id="dom" name="tags[]" value="dom" <?php echo in_array('dom', $form_data['tags']) ? 'checked' : ''; ?>>
                <label for="dom">Dom</label>
            </div>
            <div class="tag-box">
                <input type="checkbox" id="zakupy" name="tags[]" value="zakupy" <?php echo in_array('zakupy', $form_data['tags']) ? 'checked' : ''; ?>>
                <label for="zakupy">Zakupy</label>
            </div>
        </div>
        <div>
            <button id="dodaj-zad" type="submit">Dodaj zadanie</button>
        </div>
    </form>
</aside>
<main>
    <div id="pasek-statystyk">
        <p id="wszystkie">
            <span><?php echo $stat_wszystkie; ?></span><br>Wszystkie
        </p>
        <p id="do-zrobienia">
            <span><?php echo $stat_do_zrobienia; ?></span><br>Do zrobienia
        </p>
        <p id="w-trakcie">
            <span><?php echo $stat_w_trakcie; ?></span><br>W trakcie
        </p>
        <p id="zakonczone">
            <span><?php echo $stat_zakonczone; ?></span><br>Zakończone
        </p>
        <p>
            <span><?php echo $stat_minuty; ?></span><br>Suma minut
        </p>
    </div>
    <div id="karty-zadan">
        <?php foreach ($tasks as $task): ?>
            <?php
                $prio_map = [
                    'Niski' => 'niski',
                    'Średni' => 'sredni',
                    'Wysoki' => 'wysoki'
                ];
                $prio_class = $prio_map[$task['priority']];
            ?>
            <div class="karta-zadania karta-<?php echo htmlspecialchars($prio_class); ?>">
                <div class="naglowek">
                    <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                </div>
                <div>
                    <p>
                        Kategoria: <?php echo htmlspecialchars($task['category']); ?>
                    </p>
                    <p>
                        Priorytet: <?php echo htmlspecialchars($task['priority']); ?>
                    </p>
                    <p>
                        Status: <?php echo htmlspecialchars($task['status']); ?>
                    </p>
                    <p>
                        Szacowany czas: <?php echo htmlspecialchars($task['estimated_minutes']); ?> min
                    </p>
                    <p>
                        Tagi: <?php echo htmlspecialchars(implode(', ', $task['tags'])); ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>