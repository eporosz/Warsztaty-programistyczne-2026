<?php

$allowed_categories = ['Praca', 'Dom', 'Nauka', 'Zdrowie', 'Inne'];
$allowed_priorities = ['Niski', 'Średni', 'Wysoki'];
$allowed_statuses = ['Do zrobienia', 'W trakcie', 'Zakończone'];

$tasks = [
        ['title' => 'Wdrożenie nowego systemu logowania', 'category' => 'Praca', 'priority' => 'Wysoki', 'status' => 'W trakcie', 'estimated_minutes' => 120, 'tags' => ['backend', 'frontend'], 'description' => 'Migracja z sesji serwerowych na JWT. Wymaga aktualizacji backendu, frontendu i dokumentacji API. Koniecznie przetestować przepływ OAuth z dostawcą zewnętrznym.', 'date' => '2026-04-10'],
        ['title' => 'Naprawa błędu w płatnościach', 'category' => 'Praca', 'priority' => 'Wysoki', 'status' => 'Do zrobienia', 'estimated_minutes' => 60, 'tags' => ['pilne', 'backend'], 'description' => 'Zduplikowane transakcje przy wielokrotnym kliknięciu przycisku "Zapłać". Dodać blokadę po pierwszym kliknięciu.', 'date' => '2026-04-05'],
        ['title' => 'Nauka CSS Grid i Flexbox', 'category' => 'Nauka', 'priority' => 'Średni', 'status' => 'W trakcie', 'estimated_minutes' => 90, 'tags' => ['frontend'], 'description' => 'Przerobić materiały z wykładu.', 'date' => '2026-04-12'],
        ['title' => 'Zakupy spożywcze', 'category' => 'Dom', 'priority' => 'Niski', 'status' => 'Zakończone', 'estimated_minutes' => 45, 'tags' => ['dom', 'zakupy'], 'description' => "Mleko, chleb, warzywa, kawa.", 'date' => '2026-03-28'],
];

$errors = [];
$form_data = [
    'title' => '', 'category' => '', 'priority' => '', 'status' => '', 'estimated_minutes' => '', 'tags_input' => '', 'description' => '', 'date' => ''
];

// -------
// FUNKCJE
// -------

// Walidacja danych wejściowych, zabezpieczenie przed atakami XSS
function validateInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Wyodrębnianie tagów z tekstu
function extractTags($text) {
    preg_match_all('/#([a-zA-Z0-9_]+)/', $text, $matches); // Szukanie wyrażeń poprzedzonych znakiem #
    return $matches[1] ?? []; //Funkcja zwraca dopasowanie
}

// Formatowanie opisu zadania
function formatTaskDescription($description) {
    $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    global $search_query;

    // Zamiana URL na linki HTML
    $description = preg_replace(
            '/\b(?:https?|ftp):\/\/[a-z0-9-+&@#\/%?=~_|!:,.;]*[a-z0-9-+&@#\/%=~_|]/i',
            '<a href="$0" target="_blank">$0</a>',
            $description
    );

    // Wykrywanie i formatowanie tagów
    $description = preg_replace(
            '/#([a-zA-Z0-9_]+)/',
            '<span class="tag">#$1</span>',
            $description
    );

    // Wykrywanie i formatowanie list punktowanych
    $description = preg_replace(
            '/^[\s]*[-*+][\s]+(.+)$/m',
            '<li>$1</li>',
            $description
    );

    // Owijanie list w znaczniki <ul></ul>
    if (strpos($description, '<li>') !== false) {
        $description = '<ul>' . $description . '</ul>';
        $description = str_replace('</ul><ul>', '', $description);
    }

    // Wykrywanie numerów telefonów (formaty: 123456789, 123-456-789, 123 456 789, opcjonalnie z +48)
    $description = preg_replace(
            '/(?<!\d)(?:\+48\s?)?(?:\d{3}[\s-]?\d{3}[\s-]?\d{3})(?!\d)/',
            '<strong>$0</strong>',
            $description
    );

    // Wykrywanie dat w formacie RRRR-MM-DD
    $description = preg_replace(
            '/\b\d{4}-\d{2}-\d{2}\b/',
            '<strong>$0</strong>',
            $description
    );

    // Wykrywanie godzin w formacie HH:MM
    $description = preg_replace(
            '/\b([01]?[0-9]|2[0-3]):[0-5][0-9]\b/',
            '<strong>$0</strong>',
            $description
    );

    // Wyróżnianie szukanego tekstu w opisie (jeśli wpisano zapytanie)
    if (!empty($search_query) && !str_starts_with($search_query, '/')) {
        $quoted_pattern = preg_quote($search_query, '/');
        $description = preg_replace('/(' . $quoted_pattern . ')/i', '<mark>$1</mark>', $description);
    }

    return $description;
}

// Wyszukiwanie zadań według wzorca
function searchTasks($tasks, $pattern) {
    if (empty($pattern)) return $tasks;
    // Walidacja wzorca
    $valid_pattern = @preg_match($pattern, '') !== false;
    if (!$valid_pattern) {
        $pattern = '/' . preg_quote($pattern, '/') . '/i';
    }

    return array_filter($tasks, function($t) use ($pattern) {
        return preg_match($pattern, $t['title']) || preg_match($pattern, $t['description']);
    });
}

// Filtrowanie zadań według tagu
function filterTasksByTag($tasks, $tag) {
    if (empty($tag)) return $tasks;
    return array_filter($tasks, function($t) use ($tag) {
        return in_array(strtolower($tag), array_map('strtolower', $t['tags']));
    });
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $form_data['title'] = trim($_POST["title"] ?? '');
    $form_data['category'] = $_POST["category"] ?? '';
    $form_data['priority'] = $_POST["priority"] ?? '';
    $form_data['status'] = $_POST["status"] ?? '';
    $form_data['estimated_minutes'] = trim($_POST["estimated_minutes"] ?? '');
    $form_data['tags_input'] = trim($_POST["tags_input"] ?? '');
    $form_data['description'] = trim($_POST["description"] ?? '');
    $form_data['date'] = trim($_POST["date"] ?? '');

    // Podstawowa walidacja
    if (empty($form_data['title'])) $errors[] = "Tytuł nie może być pusty.";
    if (!is_numeric($form_data['estimated_minutes']) || (int)$form_data['estimated_minutes'] <= 0) $errors[] = "Czas musi być liczbą dodatnią.";
    if (!in_array($form_data['category'], $allowed_categories)) $errors[] = "Nieprawidłowa kategoria.";
    if (!in_array($form_data['priority'], $allowed_priorities)) $errors[] = "Nieprawidłowy priorytet.";
    if (!in_array($form_data['status'], $allowed_statuses)) $errors[] = "Nieprawidłowy status.";

    // Walidacja poprawności daty (format RRRR-MM-DD)
    if (!empty($form_data['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form_data['date'])) {
        $errors[] = "Data musi być w formacie RRRR-MM-DD.";
    }

    // Walidacja adresu email w opisie zadania
    if (!empty($form_data['description'])) {
        $words = explode(' ', str_replace(["\n", "\r"], ' ', $form_data['description']));
        foreach ($words as $word) {
            if (strpos($word, '@') !== false) {
                if (!preg_match('/^[a-zA-Z0-9._+-]+@[a-zA-Z0-9]{1}[a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$/', trim($word))) {
                    $errors[] = "Niepoprawny format adresu e-mail w opisie: " . htmlspecialchars($word);
                }
            }
        }
    }

    // Walidacja wprowadzonych tagów
    $entry_tags = [];
    if (!empty($form_data['tags_input'])) {
        $raw_tags = explode(' ', $form_data['tags_input']);
        foreach ($raw_tags as $rtag) {
            $rtag = trim($rtag);
            if (!empty($rtag)) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $rtag)) {
                    $errors[] = "Tagi mogą zawierać tylko litery, cyfry i podkreślniki.";
                } else {
                    $entry_tags[] = strtolower($rtag);
                }
            }
        }
    }

    // Dodanie zadania, jeśli nie ma błędów
    if (empty($errors)) {
        // Wyodrębnienie tagów z opisu zadania
        $extracted_tags = extractTags($form_data['description']);

        // Połączenie tagów wpisanych ręcznie z wyodrębnionymi z opisu
        $all_tags = array_unique(array_merge($entry_tags, array_map('strtolower', $extracted_tags)));
        sort($all_tags);

        if (empty($all_tags)) {
            $errors[] = "Należy podać co najmniej jeden tag.";
        } else {
            $new_task = [
                    'title' => $form_data['title'],
                    'category' => $form_data['category'],
                    'priority' => $form_data['priority'],
                    'status' => $form_data['status'],
                    'estimated_minutes' => (int)$form_data['estimated_minutes'],
                    'tags' => $all_tags,
                    'description' => $form_data['description'],
                    'date' => $form_data['date']
            ];
            $tasks[] = $new_task;
        }
    }
}

// --------------------------
// WYSZUKIWANIE I FILTROWANIE
// --------------------------
$search_query = $_GET['search_query'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_priority = $_GET['filter_priority'] ?? '';
$filter_tag = $_GET['filter_tag'] ?? '';

$filtered_tasks = $tasks;

// Filtrowanie
if (!empty($search_query)) {
    // Jeśli input zaczął się od znaku '/', używamy regex, w przeciwnym wypadku traktujemy jako zwykły tekst
    $search_pattern = str_starts_with($search_query, '/') ? $search_query : ('/' . preg_quote($search_query, '/') . '/i');
    $filtered_tasks = searchTasks($filtered_tasks, $search_pattern);
}

if (!empty($filter_status)) {
    $filtered_tasks = array_filter($filtered_tasks, fn($t) => $t['status'] === $filter_status);
}

if (!empty($filter_priority)) {
    $filtered_tasks = array_filter($filtered_tasks, fn($t) => $t['priority'] === $filter_priority);
}

if (!empty($filter_tag)) {
    $filtered_tasks = filterTasksByTag($filtered_tasks, $filter_tag);
}

// ----------------
// STATYSTYKI ZADAŃ
// ----------------

$stat_wszystkie = count($filtered_tasks);
$stat_do_zrobienia = count(array_filter($filtered_tasks, function($t) {
    return $t['status'] === 'Do zrobienia';
}));
$stat_w_trakcie = count(array_filter($filtered_tasks, function($t) {
    return $t['status'] === 'W trakcie';
}));
$stat_zakonczone = count(array_filter($filtered_tasks, function($t) {
    return $t['status'] === 'Zakończone';
}));
$stat_minuty = array_sum(array_column($filtered_tasks, 'estimated_minutes'));

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
                <?php foreach($allowed_categories as $cat): ?>
                    <option value="<?php echo $cat ?>" <?php echo ($form_data['category'] === $cat) ? 'selected' : '' ?>><?php echo $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="priorytet-zad">Priorytet</label>
            <select name="priority" id="priorytet-zad" required>
                <option disabled <?php echo empty($form_data['priority']) ? 'selected' : ''; ?> hidden>-- wybierz --</option>
                <?php foreach($allowed_priorities as $pr): ?>
                    <option value="<?php echo $pr ?>" <?php echo ($form_data['priority'] === $pr) ? 'selected' : '' ?>><?php echo $pr ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status-zad">Status</label>
            <select name="status" id="status-zad" required>
                <?php foreach ($allowed_statuses as $stat): ?>
                    <option value="<?php echo $stat ?>" <?php echo ($form_data['status'] === $stat) ? 'selected' : '' ?>><?php echo $stat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="czas">Szacowany czas (minuty)</label>
            <input type="text" name="estimated_minutes" value="<?php echo htmlspecialchars($form_data['estimated_minutes'] ?? ''); ?>" id="czas" placeholder="60">
        </div>
        <div>
            <label for="data">Termin wykonania</label>
            <input type="text" name="date" value="<?php echo htmlspecialchars($form_data['date']); ?>" id="data" placeholder="2026-12-31">
        </div>
        <div>
            <label for="opis">Opis zadania</label>
            <textarea name="description" id="opis" placeholder="Opcjonalny opis..."><?php echo htmlspecialchars($form_data['description']); ?></textarea>
        </div>
        <div>
            <label for="tagi">Tagi</label>
            <input type="text" name="tags_input" value="<?php echo htmlspecialchars($form_data['tags_input'] ?? ''); ?>" id="tagi" placeholder="np. frontend backend">
        </div>
        <div>
            <button id="dodaj-zad" type="submit">Dodaj zadanie</button>
        </div>
    </form>
</aside>
<main>
    <details>
        <summary>Filtrowanie</summary>
        <form id="filtry" method="get" action="index.php">
            <div>
                <label for="search">Wyszukaj:</label>
                <input type="text" name="search_query" id="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Wpisz szukaną frazę...">
            </div>
            <div>
                <label for="filter_status">Status:</label>
                <select name="filter_status" id="filter_status">
                    <option value="">Wszystkie</option>
                    <?php foreach($allowed_statuses as $s): ?>
                        <option value="<?php echo $s ?>" <?php echo ($filter_status === $s) ? 'selected' : '' ?>><?php echo $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_priority">Priorytet:</label>
                <select name="filter_priority" id="filter_priority">
                    <option value="">Wszystkie</option>
                    <?php foreach($allowed_priorities as $p): ?>
                        <option value="<?php echo $p ?>" <?php echo ($filter_priority === $p) ? 'selected' : '' ?>><?php echo $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_tag">Tag:</label>
                <input type="text" name="filter_tag" id="filter_tag" value="<?php echo htmlspecialchars($filter_tag); ?>" placeholder="np. backend">
            </div>
            <div>
                <button type="submit">Filtruj</button>
            </div>
        </form>
    </details>

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
        <?php if (empty($filtered_tasks)): ?>
            <p>Brak zadań spełniających kryteria.</p>
        <?php else: ?>
            <?php foreach ($filtered_tasks as $task): ?>
                <?php
                $prio_map = [
                    'Niski' => 'niski',
                    'Średni' => 'sredni',
                    'Wysoki' => 'wysoki'
                ];
                $prio_class = $prio_map[$task['priority']];

                // Formatowanie opisu z użyciem regex
                $form_desc = '';
                if (!empty($task['description'])) {
                    $form_desc = formatTaskDescription($task['description']);
                }

                // Wyróżnianie szukanego tekstu w tytule
                $display_title = htmlspecialchars($task['title']);
                if (!empty($search_query) && !str_starts_with($search_query, '/')) {
                    $display_title = preg_replace('/(' . preg_quote($search_query, '/') . ')/i', '<mark>$1</mark>', $display_title);
                }
                ?>
                <div class="karta-zadania karta-<?php echo htmlspecialchars($prio_class); ?>">
                    <div class="naglowek">
                        <h4><?php echo $display_title ?></h4>
                    </div>
                    <div>
                        <p>
                            <strong>Kategoria:</strong> <?php echo htmlspecialchars($task['category']); ?>
                        </p>
                        <p>
                            <strong>Priorytet:</strong> <?php echo htmlspecialchars($task['priority']); ?> | <strong>Status:</strong> <?php echo htmlspecialchars($task['status']); ?>
                        </p>
                        <p>
                            <strong>Czas:</strong> <?php echo htmlspecialchars($task['estimated_minutes']); ?> min
                            <?php if(!empty($task['date'])): ?>
                                | <strong>Termin: </strong> <?php echo htmlspecialchars($task['date']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="opis">
                        <?php echo $form_desc; ?>
                    </div>
                    <div>
                        <strong>Tagi:</strong>
                        <?php foreach ($task['tags'] as $tag): ?>
                            <span>#<?php echo htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
</body>
</html>