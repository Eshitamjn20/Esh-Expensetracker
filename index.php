<?php

// Mini Expense Tracker - uses json files to store data 


// Where to store data
$dataDir = __DIR__ . '/data';
$dataFile = $dataDir . '/expenses.json';

// Ensure data directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}

// Load existing expenses
$expenses = file_exists($dataFile)
    ? json_decode(file_get_contents($dataFile), true)
    : [];

if (!is_array($expenses)) {
    $expenses = [];
}

// Simple helper to persist data
function save_expenses($path, $array)
{
    file_put_contents($path, json_encode($array, JSON_PRETTY_PRINT));
}

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    // Basic validation & sanitization
    $date = $_POST['date'] ?? '';
    $desc = trim($_POST['description'] ?? '');
    $cat = $_POST['category'] ?? 'Other';
    $amt = $_POST['amount'] ?? '';

    $errors = [];
    if (!$date)
        $errors[] = "Date is required.";
    if ($desc === '')
        $errors[] = "Description is required.";
    if (!is_numeric($amt) || floatval($amt) <= 0)
        $errors[] = "Amount must be a positive number.";

    if (empty($errors)) {
        $expenses[] = [
            'id' => uniqid('e_', true),
            'date' => $date,                            // ISO YYYY-MM-DD
            'description' => htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'),
            'category' => htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'),
            'amount' => round(floatval($amt), 2)
        ];
        save_expenses($dataFile, $expenses);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = $_POST['id'] ?? '';
    $expenses = array_values(array_filter($expenses, fn($e) => $e['id'] !== $id));
    save_expenses($dataFile, $expenses);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Compute totals by category and month for mini analytics
$byCategory = [];
$byMonth = []; // "YYYY-MM"
$total = 0.0;

foreach ($expenses as $e) {
    $total += $e['amount'];
    $byCategory[$e['category']] = ($byCategory[$e['category']] ?? 0) + $e['amount'];

    $month = substr($e['date'], 0, 7);
    $byMonth[$month] = ($byMonth[$month] ?? 0) + $e['amount'];
}

// Order months ascending
ksort($byMonth);

// Categories to suggest in the form
$categories = ['Food', 'Transport', 'Shopping', 'Bills', 'Health', 'Entertainment', 'Study', 'Travel', 'Other'];

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Expense Tracker (PHP)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
  <header class="container">
    <h1>Expense Tracker</h1>
    <p class="subtitle">A PHP app with file-based storage + charts for expense tracking </p>
  </header>

  <main class="container">
    <section class="card">
      <h2>Add Expense</h2>
      <?php if (!empty($errors)): ?>
        <div class="alert error">
          <?php foreach ($errors as $err): ?>
            <div><?= $err ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="grid">
        <input type="hidden" name="action" value="add">
        <div>
          <label>Date</label>
          <input type="date" name="date" required value="<?= htmlspecialchars(date('Y-m-d')) ?>">
        </div>
        <div>
          <label>Description</label>
          <input type="text" name="description" placeholder="e.g., Lunch, Bus pass" required>
        </div>
        <div>
          <label>Category</label>
          <select name="category">
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c ?>"><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Amount</label>
          <input type="number" name="amount" step="0.01" min="0" placeholder="e.g., 12.50" required>
        </div>
        <div class="actions">
          <button type="submit" class="btn primary">Add</button>
        </div>
      </form>
    </section>

    <section class="grid-2">
      <div class="card">
        <h2>By Category</h2>
        <canvas id="catChart" height="220"></canvas>
      </div>
      <div class="card">
        <h2>By Month</h2>
        <canvas id="monthChart" height="220"></canvas>
      </div>
    </section>

    <section class="card">
      <div class="table-header">
        <h2>All Expenses</h2>
        <div class="total">Total: <strong>$<?= number_format($total, 2) ?></strong></div>
      </div>

      <?php if (empty($expenses)): ?>
        <div class="muted">No expenses yet. Add your first above!</div>
      <?php else: ?>
        <div class="table">
          <div class="thead">
            <div>Date</div><div>Description</div><div>Category</div><div class="right">Amount</div><div></div>
          </div>
          <?php foreach (array_reverse($expenses) as $e): ?>
            <div class="trow">
              <div><?= htmlspecialchars($e['date']) ?></div>
              <div><?= htmlspecialchars($e['description']) ?></div>
              <div><?= htmlspecialchars($e['category']) ?></div>
              <div class="right">$<?= number_format($e['amount'], 2) ?></div>
              <div class="right">
                <form method="post" class="inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($e['id']) ?>">
                  <button class="btn danger" onclick="return confirm('Delete this expense?')">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <footer class="container muted small">
    &copy; <?= date('Y') ?> • PHP Expense Tracker • By Eshita Mahajan • 104748964
  </footer>

  <script>
    // Data for charts from PHP
    const catLabels = <?= json_encode(array_keys($byCategory)) ?>;
    const catValues = <?= json_encode(array_values($byCategory)) ?>;

    const monthLabels = <?= json_encode(array_keys($byMonth)) ?>;
    const monthValues = <?= json_encode(array_values($byMonth)) ?>;

    // Pie chart (by category)
    new Chart(document.getElementById('catChart'), {
      type: 'pie',
      data: {
        labels: catLabels,
        datasets: [{
          data: catValues
        }]
      },
      options: {
        plugins: { legend: { position: 'bottom' } }
      }
    });

    // Line chart (by month)
    new Chart(document.getElementById('monthChart'), {
      type: 'line',
      data: {
        labels: monthLabels,
        datasets: [{
          label: 'Total ($)',
          data: monthValues,
          fill: false,
          tension: 0.25
        }]
      }
    });
  </script>
</body>
</html>

