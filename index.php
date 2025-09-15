<?php
// index.php – Wochenfunktion + QR/UID Aktivierung pro Anbieter (ON/OFF, KW-basiert)
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');

// =============================
// Anbieter-Konfiguration (UIDs)
// =============================
// Jede UID steckt in einem **statischen** QR-Code des Anbieters.
// Aufruf: index.php?uid=UID_METZGER&action=on|off
$vendors = [
  'metzger' => [
    'name' => 'Metzger',
    'uid'  => 'UID_METZGER', // TODO: echte UID einsetzen
    'image'=> 'Assets/butcher_text.png',
  ],
  'baecker' => [
    'name' => 'Bäcker',
    'uid'  => 'UID_BAECKER', // TODO: echte UID einsetzen
    'image'=> 'Assets/baker_text.png',
  ],
  'gemuese' => [
    'name' => 'Gemüse',
    'uid'  => 'UID_GEMUESE', // TODO: echte UID einsetzen
    'image'=> 'Assets/green_text.png',
  ],
];

// Hilfs-Map UID -> Vendor-Key
$uidToVendor = [];
foreach ($vendors as $k => $v) { $uidToVendor[$v['uid']] = $k; }

// =============================
// Wochenlogik (Anzeige)
// =============================
$today = new DateTimeImmutable('today');
$displayWeek = isset($_GET['week']) && ctype_digit($_GET['week']) ? (int)$_GET['week'] : (int)$today->format('W');
$displayYear = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int)$_GET['year'] : (int)$today->format('o');

function isoWeeksInYear(int $year): int {
  $dt = new DateTimeImmutable($year . '-12-28');
  return (int)$dt->format('W');
}
$maxWeeks = isoWeeksInYear($displayYear);
if ($displayWeek < 1) { $displayYear--; $displayWeek = isoWeeksInYear($displayYear); }
if ($displayWeek > $maxWeeks) { $displayWeek = 1; $displayYear++; }

// Montag dieser ISO-KW + Sonntag derselben Woche
$monday = (new DateTimeImmutable())->setISODate($displayYear, $displayWeek);
$sunday = $monday->modify('+6 days');

$prevWeek = $displayWeek - 1; $prevYear = $displayYear;
if ($prevWeek < 1) { $prevYear = $displayYear - 1; $prevWeek = isoWeeksInYear($prevYear); }
$nextWeek = $displayWeek + 1; $nextYear = $displayYear;
if ($nextWeek > isoWeeksInYear($displayYear)) { $nextYear = $displayYear + 1; $nextWeek = 1; }

function weekUrl($w, $y): string { return '?week=' . rawurlencode((string)$w) . '&year=' . rawurlencode((string)$y); }

// =============================
// Persistenz der Aktivierung (pro *aktueller* KW)
// =============================
// Struktur: data/status.json
// { "2025-37": { "metzger": true, "baecker": false, "gemuese": true } }
$dataDir = __DIR__ . '/data';
$dataFile = $dataDir . '/status.json';
if (!is_dir($dataDir)) { @mkdir($dataDir, 0775, true); }

function loadStatus(string $file): array {
  if (!is_file($file)) return [];
  $json = @file_get_contents($file);
  if ($json === false || $json === '') return [];
  $data = json_decode($json, true);
  return is_array($data) ? $data : [];
}

function saveStatus(string $file, array $data): void {
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  @file_put_contents($file, $json, LOCK_EX);
}

$status = loadStatus($dataFile);

$currentIsoWeek = (int)$today->format('W');
$currentIsoYear = (int)$today->format('o');
$currentKey = $currentIsoYear . '-' . $currentIsoWeek; // z.B. 2025-37
if (!isset($status[$currentKey])) { $status[$currentKey] = []; }

// =============================
// QR-Scan: UID + ACTION (on/off) -> Setzen für aktuelle KW
// =============================
$flash = null; // intern
if (isset($_GET['uid'], $_GET['action']) && is_string($_GET['uid']) && is_string($_GET['action'])) {
  $uid = trim($_GET['uid']);
  $action = strtolower(trim($_GET['action']));
  if (isset($uidToVendor[$uid]) && in_array($action, ['on','off'], true)) {
    $vendorKey = $uidToVendor[$uid];
    $status[$currentKey][$vendorKey] = ($action === 'on');
    saveStatus($dataFile, $status);
  }
}

// Status für die *anzeigte* Woche
$displayKey = $displayYear . '-' . $displayWeek;
$weekStatus = $status[$displayKey] ?? [];

// =============================
// Rendering-Helfer
// =============================
function isActive(array $weekStatus, string $vendorKey): bool {
  return isset($weekStatus[$vendorKey]) ? (bool)$weekStatus[$vendorKey] : false;
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Übersicht – KW <?= htmlspecialchars($displayWeek) ?> / <?= htmlspecialchars($displayYear) ?></title>
  <style>
    :root{ --primary:#0B5FA5; --silver:#C0C0C0; --text:#333; --bg:#f7f7f8; }
    *{ box-sizing:border-box; }
    body{ margin:0; font-family: Arial, Helvetica, sans-serif; background: var(--bg); color: var(--text); }

    /* Header */
    header{ border-bottom:1px solid var(--silver); }
    .container{ max-width:1080px; margin:0 auto; padding:1rem; }
    @media (min-width:768px){ .container{ padding:1.5rem; } }

    h1{ color:var(--primary); margin:0; font-size:clamp(1.6rem, 2.5vw, 2.4rem); }
    .sub{ margin:.4rem 0 0 0; color:var(--text); }
    .muted{ color:#666; }
    .kw{ margin-left:.35rem; font-size:1rem; font-weight:700; color:var(--primary); }

    /* Header-Zeile: mobil untereinander, ab Tablet nebeneinander */
    .header-row{ display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; }
    @media (max-width:640px){
      .header-row{ flex-direction:column; align-items:flex-start; gap:.5rem; }
    }
    .crest{ height:56px; width:auto; filter:drop-shadow(0 1px 2px rgba(0,0,0,.15)); }
    @media (max-width:640px){ .crest{ height:48px; } }

    /* KW-Navigation: immer eine Zeile; bei sehr schmal breit scrollbar */
    .week-nav{
      display:flex; gap:.5rem; flex-wrap:nowrap;
      overflow-x:auto; padding:.6rem 0; -webkit-overflow-scrolling:touch;
    }
    .btn{ display:inline-block; background:var(--primary); color:#fff; text-decoration:none; padding:.6rem .9rem; border-radius:12px; font-weight:600; border:none; cursor:pointer; white-space:nowrap; }
    .btn.outline{ background:transparent; color:var(--primary); border:1px solid var(--primary); }

    /* Grid: mobil 1 Spalte, ab 640px 2, ab 1024px 3 */
    .grid{ display:grid; gap:1rem; grid-template-columns:1fr; }
    @media (min-width:640px){ .grid{ grid-template-columns:repeat(2,1fr); } }
    @media (min-width:1024px){ .grid{ grid-template-columns:repeat(3,1fr); } }

    /* Karten */
    .card{ background:#fff; border:1px solid var(--silver); border-radius:16px; overflow:hidden; display:flex; flex-direction:column; }
    .media img{ display:block; width:100%; height:auto; }
    h2{ margin:.75rem 1rem 0 1rem; font-size:1.15rem; color:#0A5594; }
    .status{ margin:1rem; font-size:.85rem; color:#0a0; }
    .muted.small{ font-size:.85rem; margin:0 1rem 1rem 1rem; color:#666; }

    /* Footer */
    footer{ border-top:1px solid var(--silver); margin-top:1.25rem; padding:1rem 0; }
  </style>
</head>
<body>
  <div class="container">

    <header>
      <div class="header-row">
        <img class="crest" src="Assets/wappen-stockstadt.gif" alt="Wappen Stockstadt" title="Wappen Stockstadt">
        <div>
          <h1>
            Übersicht Wochenmarkt Stockstadt
            <span class="kw">KW <?= htmlspecialchars($displayWeek) ?></span>
          </h1>
          <p class="sub muted">
            Montag (<?= htmlspecialchars($monday->format('d.m.')) ?>)
            bis Sonntag (<?= htmlspecialchars($sunday->format('d.m.')) ?>)
            der ausgewählten Woche
          </p>
        </div>
      </div>

      <!-- KW-Navigation (immer eine Zeile) -->
      <nav class="week-nav" aria-label="Kalenderwoche wählen">
        <a class="btn outline" href="<?= htmlspecialchars(weekUrl($prevWeek, $prevYear)) ?>" aria-label="Vorherige Woche">← Vorherige Woche</a>
        <a class="btn" href="<?= htmlspecialchars(weekUrl((int)$today->format('W'), (int)$today->format('o'))) ?>">Heute / Aktuelle KW</a>
        <a class="btn outline" href="<?= htmlspecialchars(weekUrl($nextWeek, $nextYear)) ?>" aria-label="Nächste Woche">Nächste Woche →</a>
      </nav>
    </header>

    <!-- Die Wochentagsliste wurde entfernt wie gewünscht -->

    <section class="grid" aria-label="Anbieter">
      <?php foreach ($vendors as $key => $v):
        if (!isActive($weekStatus, $key)) continue; // Deaktivierte Anbieter NICHT anzeigen
      ?>
        <div class="card">
          <div class="media">
            <img src="<?= htmlspecialchars($v['image']) ?>" alt="<?= htmlspecialchars($v['name']) ?>" title="<?= htmlspecialchars($v['name']) ?>">
          </div>
          <h2><?= htmlspecialchars($v['name']) ?></h2>
          <p class="muted small">Aktiv für KW <?= htmlspecialchars($displayWeek) ?>/<?= htmlspecialchars($displayYear) ?>.</p>
        </div>
      <?php endforeach; ?>
    </section>

    <footer>
      <p class="muted">Es werden nur die Anbieter des dieswöchigen Wochenmarktes angezeigt.</p>
    </footer>
  </div>
</body>
</html>
