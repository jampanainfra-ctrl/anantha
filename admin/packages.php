<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();

$ready = db_ready();
if ($ready) {
    ensure_package_schema();
}

$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'active', 'inactive'], true)) {
    $filter = 'all';
}
$search = trim($_GET['q'] ?? '');

function package_guest_limits(string $range): array
{
    preg_match_all('/\d+/', $range, $matches);
    $numbers = array_map('intval', $matches[0] ?? []);
    if (count($numbers) >= 2) {
        return [$numbers[0], $numbers[1]];
    }
    if (count($numbers) === 1) {
        return [0, $numbers[0]];
    }
    return [0, 100];
}

function package_rows(string $filter, string $search): array
{
    if (!db_ready()) {
        return [];
    }

    $whereParts = [];
    $params = [];
    if ($filter === 'active') {
        $whereParts[] = 'is_active=1';
    } elseif ($filter === 'inactive') {
        $whereParts[] = 'is_active=0';
    }
    if ($search !== '') {
        $whereParts[] = '(package_name LIKE ? OR guest_range LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
    $stmt = db()->prepare("SELECT * FROM event_packages $where ORDER BY display_order ASC, id ASC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function render_package_rows(array $packages, string $filter, string $search): string
{
    ob_start();
    foreach ($packages as $package):
        $editFormId = 'package-form-' . (int) $package['id'];
        [$guestMin, $guestMax] = package_guest_limits((string) $package['guest_range']);
        $back = 'packages.php?filter=' . rawurlencode($filter) . ($search !== '' ? '&q=' . rawurlencode($search) : '');
        ?>
        <tr>
          <td>
            <form id="<?php echo h($editFormId); ?>" method="POST" action="actions.php"></form>
            <input form="<?php echo h($editFormId); ?>" name="package_name" required value="<?php echo h($package['package_name']); ?>">
            <input form="<?php echo h($editFormId); ?>" type="hidden" name="action" value="save_package">
            <input form="<?php echo h($editFormId); ?>" type="hidden" name="back" value="<?php echo h($back); ?>">
            <input form="<?php echo h($editFormId); ?>" type="hidden" name="package_id" value="<?php echo (int) $package['id']; ?>">
          </td>
          <td><input form="<?php echo h($editFormId); ?>" name="guest_min" type="number" min="0" required value="<?php echo h((string) $guestMin); ?>"></td>
          <td><input form="<?php echo h($editFormId); ?>" name="guest_max" type="number" min="0" required value="<?php echo h((string) $guestMax); ?>"></td>
          <td><input form="<?php echo h($editFormId); ?>" name="price" type="number" step="0.01" value="<?php echo h($package['price']); ?>" placeholder="Optional"></td>
          <td><input form="<?php echo h($editFormId); ?>" name="discount_percent" type="number" min="0" max="100" step="0.01" value="<?php echo h($package['discount_percent'] ?? '0'); ?>" placeholder="0"></td>
          <td><input form="<?php echo h($editFormId); ?>" name="discount_reason" value="<?php echo h($package['discount_reason'] ?? ''); ?>" placeholder="Optional reason"></td>
          <td><input form="<?php echo h($editFormId); ?>" name="display_order" type="number" value="<?php echo h($package['display_order']); ?>"></td>
          <td><label class="check-label"><input form="<?php echo h($editFormId); ?>" type="checkbox" name="is_active" <?php echo (int) $package['is_active'] === 1 ? 'checked' : ''; ?>> Active</label></td>
          <td><button form="<?php echo h($editFormId); ?>" class="btn gold">Save</button></td>
          <td>
            <form method="POST" action="actions.php" onsubmit="return confirm('Delete this package?');">
              <input type="hidden" name="action" value="delete_package">
              <input type="hidden" name="back" value="<?php echo h($back); ?>">
              <input type="hidden" name="package_id" value="<?php echo (int) $package['id']; ?>">
              <button class="btn red">Delete</button>
            </form>
          </td>
        </tr>
    <?php endforeach;
    if (!$packages): ?>
      <tr><td colspan="10">No packages found. Add Package is at the top of this page.</td></tr>
    <?php endif;
    return ob_get_clean();
}

$packages = $ready ? package_rows($filter, $search) : [];

if (isset($_GET['ajax'])) {
    require_admin();
    header('Content-Type: application/json');
    echo json_encode([
        'html' => render_package_rows($packages, $filter, $search),
        'count' => count($packages),
    ]);
    exit;
}

admin_page_start('Packages', 'packages');
?>
<div class="top">
  <div><h1>Packages</h1><p>Manage package names, guest ranges, prices and discounts shown in the public availability form.</p></div>
</div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql first.</div><?php endif; ?>

<details class="card add-panel package-add-panel" id="add-package">
  <summary>Add Package</summary>
  <form class="form-grid" method="POST" action="actions.php">
    <input type="hidden" name="action" value="save_package">
    <input type="hidden" name="back" value="packages.php">
    <label>Package name<input name="package_name" required placeholder="The celebration"></label>
    <label>Min guests<input name="guest_min" type="number" min="0" required value="0"></label>
    <label>Max guests<input name="guest_max" type="number" min="0" required value="100"></label>
    <label>Price<input name="price" type="number" step="0.01" placeholder="Optional"></label>
    <label>Discount %<input name="discount_percent" type="number" min="0" max="100" step="0.01" value="0"></label>
    <label class="wide">Discount reason<input name="discount_reason" placeholder="Optional reason shown on the website"></label>
    <label>Order<input name="display_order" type="number" value="10"></label>
    <label class="check-label"><input type="checkbox" name="is_active" checked> Active</label>
    <button class="btn gold">Save Package</button>
  </form>
</details>

<section class="card">
  <div class="package-toolbar">
    <form class="package-search" method="GET" action="packages.php" id="packageSearch">
      <input type="hidden" name="filter" id="packageFilter" value="<?php echo h($filter); ?>">
      <label class="sr-only" for="packageSearchInput">Search package</label>
      <input id="packageSearchInput" name="q" value="<?php echo h($search); ?>" placeholder="Search package">
      <button class="icon-btn search-icon" type="submit" aria-label="Search packages" title="Search packages"></button>
      <button class="icon-btn reset-icon" type="button" id="clearPackageSearch" aria-label="Clear search" title="Clear search"></button>
    </form>
    <a class="btn gold" href="#add-package">Add Package</a>
  </div>
  <div class="filters package-filters">
    <?php foreach (['all'=>'All packages','active'=>'Active','inactive'=>'Inactive'] as $key => $label): ?>
      <a class="<?php echo $filter === $key ? 'on' : ''; ?>" href="?filter=<?php echo h($key); ?><?php echo $search !== '' ? '&q=' . h(rawurlencode($search)) : ''; ?>" data-filter="<?php echo h($key); ?>"><?php echo h($label); ?></a>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
    <table class="package-table">
      <thead>
        <tr>
          <th>Package</th>
          <th>Min guests</th>
          <th>Max guests</th>
          <th>Price</th>
          <th>Discount %</th>
          <th>Reason</th>
          <th>Order</th>
          <th>Status</th>
          <th>Save</th>
          <th>Delete</th>
        </tr>
      </thead>
      <tbody id="packageTableBody">
        <?php echo render_package_rows($packages, $filter, $search); ?>
      </tbody>
    </table>
  </div>
</section>

<script>
  const packageSearch = document.getElementById('packageSearch');
  const packageSearchInput = document.getElementById('packageSearchInput');
  const packageFilter = document.getElementById('packageFilter');
  const packageTableBody = document.getElementById('packageTableBody');
  const packageFilters = document.querySelectorAll('.package-filters a');
  const clearPackageSearch = document.getElementById('clearPackageSearch');

  const loadPackages = async () => {
    const params = new URLSearchParams({
      ajax: '1',
      filter: packageFilter.value || 'all',
      q: packageSearchInput.value || ''
    });
    packageTableBody.classList.add('is-loading');
    try {
      const response = await fetch('packages.php?' + params.toString(), {
        headers: { 'X-Requested-With': 'fetch' }
      });
      if (!response.ok) throw new Error('Search failed');
      const data = await response.json();
      packageTableBody.innerHTML = data.html;
      const cleanParams = new URLSearchParams({ filter: packageFilter.value || 'all' });
      if (packageSearchInput.value) cleanParams.set('q', packageSearchInput.value);
      window.history.replaceState({}, '', 'packages.php?' + cleanParams.toString());
    } catch (error) {
      packageSearch.submit();
    } finally {
      packageTableBody.classList.remove('is-loading');
    }
  };

  packageSearch.addEventListener('submit', (event) => {
    event.preventDefault();
    loadPackages();
  });
  clearPackageSearch.addEventListener('click', () => {
    packageSearchInput.value = '';
    loadPackages();
  });
  packageFilters.forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      packageFilter.value = link.dataset.filter || 'all';
      packageFilters.forEach((item) => item.classList.toggle('on', item === link));
      loadPackages();
    });
  });
</script>

<?php admin_page_end(); ?>
