<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();

$ready = db_ready();
if ($ready) {
    ensure_rate_card_schema();
}

$search = trim($_GET['q'] ?? '');

function rate_input_value($value): string
{
    $number = (float) $value;
    return floor($number) === $number ? (string) (int) $number : number_format($number, 2, '.', '');
}

function rate_setting_preview(array $setting): string
{
    $value = (float) ($setting['setting_value'] ?? 0);
    $suffix = trim((string) ($setting['suffix'] ?? ''));
    if ($suffix === 'Rs') {
        return 'Rs ' . number_format($value);
    }
    return trim(rate_input_value($value) . ' ' . $suffix);
}

function rate_card_rows(string $search): array
{
    if (!db_ready()) {
        return [];
    }

    $params = [];
    $where = '';
    if ($search !== '') {
        $where = 'WHERE label LIKE ? OR suffix LIKE ? OR setting_key LIKE ?';
        $params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
    }

    $stmt = db()->prepare("SELECT * FROM rate_card_settings $where ORDER BY display_order ASC, label ASC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function render_rate_card_rows(array $settings, string $search): string
{
    ob_start();
    foreach ($settings as $setting):
        $formId = 'rate-setting-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $setting['setting_key']);
        $back = 'rate-card.php' . ($search !== '' ? '?q=' . rawurlencode($search) : '');
        ?>
        <tr>
          <td>
            <form id="<?php echo h($formId); ?>" method="POST" action="actions.php"></form>
            <input form="<?php echo h($formId); ?>" type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>">
            <input form="<?php echo h($formId); ?>" type="hidden" name="action" value="save_rate_setting">
            <input form="<?php echo h($formId); ?>" type="hidden" name="back" value="<?php echo h($back); ?>">
            <input form="<?php echo h($formId); ?>" type="hidden" name="setting_key" value="<?php echo h($setting['setting_key']); ?>">
            <input form="<?php echo h($formId); ?>" name="label" required value="<?php echo h($setting['label']); ?>">
          </td>
          <td><input form="<?php echo h($formId); ?>" name="setting_value" type="number" min="0" step="0.01" value="<?php echo h(rate_input_value($setting['setting_value'])); ?>" required></td>
          <td><input form="<?php echo h($formId); ?>" name="suffix" value="<?php echo h($setting['suffix'] ?? ''); ?>" placeholder="Rs, guests, hours"></td>
          <td><strong><?php echo h(rate_setting_preview($setting)); ?></strong></td>
          <td><input form="<?php echo h($formId); ?>" name="display_order" type="number" value="<?php echo h($setting['display_order']); ?>"></td>
          <td><button form="<?php echo h($formId); ?>" class="btn gold">Update</button></td>
          <td>
            <form method="POST" action="actions.php" onsubmit="return confirm('Delete this rule rate?');">
              <input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>">
              <input type="hidden" name="action" value="delete_rate_setting">
              <input type="hidden" name="back" value="<?php echo h($back); ?>">
              <input type="hidden" name="setting_key" value="<?php echo h($setting['setting_key']); ?>">
              <button class="btn red">Delete</button>
            </form>
          </td>
        </tr>
    <?php endforeach;
    if (!$settings): ?>
      <tr><td colspan="7">No venue rule rates found. Add Rule Rate is at the top of this page.</td></tr>
    <?php endif;
    return ob_get_clean();
}

$settings = $ready ? rate_card_rows($search) : [];

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'html' => render_rate_card_rows($settings, $search),
        'count' => count($settings),
    ]);
    exit;
}

admin_page_start('Rate Card', 'rate-card');
?>
<div class="top">
  <div><h1>Rate Card</h1><p>Add, search, edit, update and delete venue rule rates from one clear screen.</p></div>
</div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql first.</div><?php endif; ?>

<details class="card add-panel package-add-panel" id="add-rule-rate">
  <summary>Add Rule Rate</summary>
  <form class="form-grid" method="POST" action="actions.php">
    <input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>">
    <input type="hidden" name="action" value="save_rate_setting">
    <input type="hidden" name="back" value="rate-card.php">
    <label>Rule name<input name="label" required placeholder="Late checkout per hour"></label>
    <label>Value<input name="setting_value" type="number" min="0" step="0.01" required placeholder="5000"></label>
    <label>Suffix<input name="suffix" placeholder="Rs, guests, hours"></label>
    <label>Order<input name="display_order" type="number" value="10"></label>
    <button class="btn gold">Save Rule Rate</button>
  </form>
</details>

<section class="card">
  <p class="form-note">In Booking → Payments, cleaning is calculated from guest count and the minimum cleaning charge; the refundable deposit and electricity units are added to the total. Other monetary rates can be selected as extra charges. Saved bookings retain their rates until admin chooses the latest Rate Card.</p>
  <div class="package-toolbar">
    <form class="package-search" method="GET" action="rate-card.php" id="rateSearch">
      <label class="sr-only" for="rateSearchInput">Search rate</label>
      <input id="rateSearchInput" name="q" value="<?php echo h($search); ?>" placeholder="Search rate">
      <button class="icon-btn search-icon" type="submit" aria-label="Search rates" title="Search rates"></button>
      <button class="icon-btn reset-icon" type="button" id="clearRateSearch" aria-label="Clear search" title="Clear search"></button>
    </form>
    <a class="btn gold" href="#add-rule-rate">Add Rule Rate</a>
  </div>
  <div class="table-wrap">
    <table class="rate-rule-table">
      <thead>
        <tr>
          <th>Rule</th>
          <th>Value</th>
          <th>Suffix</th>
          <th>Preview</th>
          <th>Order</th>
          <th>Update</th>
          <th>Delete</th>
        </tr>
      </thead>
      <tbody id="rateTableBody">
        <?php echo render_rate_card_rows($settings, $search); ?>
      </tbody>
    </table>
  </div>
</section>

<script>
  const rateSearch = document.getElementById('rateSearch');
  const rateSearchInput = document.getElementById('rateSearchInput');
  const rateTableBody = document.getElementById('rateTableBody');
  const clearRateSearch = document.getElementById('clearRateSearch');

  const loadRates = async () => {
    const params = new URLSearchParams({
      ajax: '1',
      q: rateSearchInput.value || ''
    });
    rateTableBody.classList.add('is-loading');
    try {
      const response = await fetch('rate-card.php?' + params.toString(), {
        headers: { 'X-Requested-With': 'fetch' }
      });
      if (!response.ok) throw new Error('Search failed');
      const data = await response.json();
      rateTableBody.innerHTML = data.html;
      const cleanParams = new URLSearchParams();
      if (rateSearchInput.value) cleanParams.set('q', rateSearchInput.value);
      window.history.replaceState({}, '', cleanParams.toString() ? 'rate-card.php?' + cleanParams.toString() : 'rate-card.php');
    } catch (error) {
      rateSearch.submit();
    } finally {
      rateTableBody.classList.remove('is-loading');
    }
  };

  rateSearch.addEventListener('submit', (event) => {
    event.preventDefault();
    loadRates();
  });
  clearRateSearch.addEventListener('click', () => {
    rateSearchInput.value = '';
    loadRates();
  });
</script>

<?php admin_page_end(); ?>
