<?php
// index.php – Beispiel mit Wochenfunktion (ISO-Woche) und Bild-Einbindung aus /Assets
// Charset & Zeitzone
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');

// Aktuelle ISO-Woche und Jahr aus Query Parametern lesen, sonst heute
$today = new DateTimeImmutable('today');
$week = isset($_GET['week']) && ctype_digit($_GET['week']) ? (int)$_GET['week'] : (int)$today->format('W');
$year = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int)$_GET['year'] : (int)$today->format('o'); // ISO-Jahr

// Sicherstellen, dass Woche im gültigen Bereich liegt (1..52/53)
function isoWeeksInYear(int $year): int {
    // 28.12 gehört immer zur letzten ISO-Woche eines Jahres
    $dt = new DateTimeImmutable($year . '-12-28');
    return (int)$dt->format('W');
}

$maxWeeks = isoWeeksInYear($year);
if ($week < 1) { $year--; $week = isoWeeksInYear($year); }
if ($week > $maxWeeks) { $week = 1; $year++; }

// Montag der gewünschten ISO-Woche bestimmen
$monday = (new DateTimeImmutable())->setISODate($year, $week); // Montag

// Wochentage auf Deutsch
$weekdayNames = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = $monday->modify("+{$i} day");
    $weekDays[] = [
        'name' => $weekdayNames[$i],
        'date' => $d->format('d.m.Y'),
        'iso'  => $d->format('Y-m-d'),
        'isToday' => $d->format('Y-m-d') === $today->format('Y-m-d')
    ];
}

// Navigation (Vor-/Zurück-Woche) berechnen
$prevWeek = $week - 1; $prevYear = $year;
if ($prevWeek < 1) { $prevYear = $year - 1; $prevWeek = isoWeeksInYear($prevYear); }
$nextWeek = $week + 1; $nextYear = $year;
if ($nextWeek > isoWeeksInYear($year)) { $nextYear = $year + 1; $nextWeek = 1; }

// Hilfsfunktion: sichere Query-URL
function weekUrl($w, $y): string {
    return '?week=' . rawurlencode((string)$w) . '&year=' . rawurlencode((string)$y);
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Übersicht – KW <?= htmlspecialchars($week) ?> / <?= htmlspecialchars($year) ?></title>
  <style>
    :root {
      --bg: #0f172a;       /* slate-900 */
      --card: #111827;     /* gray-900 */
      --muted: #94a3b8;    /* slate-400 */
      --text: #e5e7eb;     /* gray-200 */
      --accent: #22d3ee;   /* cyan-400 */
      --accent-2: #60a5fa; /* blue-400 */
    }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji"; background: var(--bg); color: var(--text); }
    .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
    header { display:flex; align-items:center; gap:16px; margin-bottom: 20px; }
    header img { height: 56px; width: auto; border-radius: 8px; background: #fff; padding: 6px; }
    h1 { margin:0; font-size: clamp(1.4rem, 2.5vw, 2rem); line-height: 1.2; }
    .muted { color: var(--muted); }

    .nav { display:flex; align-items:center; justify-content: space-between; gap:12px; margin: 18px 0 24px; }
    .btn { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius: 10px; border: 1px solid #1f2937; background: linear-gradient(180deg,#1f2937,#111827); color: var(--text); text-decoration:none; }
    .btn:hover { border-color:#374151; filter: brightness(1.05); }
    .pill { display:inline-block; padding:6px 10px; border-radius:999px; background:#1f2937; color: var(--accent); font-weight:600; }

    .week { display:grid; grid-template-columns: repeat(7, 1fr); gap:10px; }
    .day { background: var(--card); border: 1px solid #1f2937; border-radius: 12px; padding: 10px; min-height: 90px; }
    .day.today { outline: 2px solid var(--accent-2); }
    .day h3 { margin:0 0 8px; font-size: 0.95rem; display:flex; align-items:center; justify-content: space-between; }
    .day .date { color: var(--muted); font-size: 0.85rem; }

    .grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-top: 28px; }
    .card { background: var(--card); border: 1px solid #1f2937; border-radius: 14px; padding: 16px; }
    .card h2 { margin:0 0 12px; font-size: 1.1rem; }
    .media { display:flex; align-items:center; justify-content:center; background:#0b1220; border-radius: 12px; padding: 12px; height: 180px; }
    .media img { max-width: 100%; max-height: 160px; object-fit: contain; }
    footer { margin-top: 28px; color: var(--muted); font-size: 0.9rem; }

    @media (max-width: 900px) { .week { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 600px) { .week { grid-template-columns: repeat(2, 1fr); } .grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <img src="Assets/wappen-stockstadt.gif" alt="Wappen Stockstadt" title="Wappen Stockstadt">
      <div>
        <h1>Übersicht – Kalenderwoche <span class="pill">KW <?= htmlspecialchars($week) ?></span> <span class="muted">/ <?= htmlspecialchars($year) ?></span></h1>
        <div class="muted">Montag bis Sonntag der ausgewählten Woche</div>
      </div>
    </header>

    <nav class="nav">
      <a class="btn" href="<?= htmlspecialchars(weekUrl($prevWeek, $prevYear)) ?>" aria-label="Vorherige Woche">⟵ Vorherige Woche</a>
      <div>
        <a class="btn" href="<?= htmlspecialchars(weekUrl((int)$today->format('W'), (int)$today->format('o'))) ?>">Heute / Aktuelle KW</a>
      </div>
      <a class="btn" href="<?= htmlspecialchars(weekUrl($nextWeek, $nextYear)) ?>" aria-label="Nächste Woche">Nächste Woche ⟶</a>
    </nav>

    <section class="week" aria-label="Wochentage">
      <?php foreach ($weekDays as $d): ?>
        <article class="day <?= $d['isToday'] ? 'today' : '' ?>">
          <h3>
            <span><?= htmlspecialchars($d['name']) ?></span>
            <span class="date"><?= htmlspecialchars($d['date']) ?></span>
          </h3>
          <!-- TODO: Hier kannst du für jeden Tag Inhalte einfügen (z.B. Öffnungszeiten, Angebote, Termine) -->
        </article>
      <?php endforeach; ?>
    </section>

    <section class="grid" aria-label="Branchen">
      <div class="card">
        <h2>Metzger</h2>
        <div class="media">
          <img src="Assets/Metzger.png" alt="Metzger" title="Metzger">
        </div>
      </div>
      <div class="card">
        <h2>Bäcker</h2>
        <div class="media">
          <img src="Assets/Bäcker.png" alt="Bäcker" title="Bäcker">
        </div>
      </div>
      <div class="card">
        <h2>Gemüse</h2>
        <div class="media">
          <img src="Assets/Gemüse.png" alt="Gemüse" title="Gemüse">
        </div>
      </div>
    </section>

    <footer>
      <p>Pfad-Hinweis: Bilder werden relativ aus <code>/Assets</code> geladen. Achte auf exakte Groß-/Kleinschreibung der Dateinamen (Linux-Server sind case-sensitive).</p>
    </footer>
  </div>
</body>
</html>
