<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();

$ready = db_ready();
if ($ready) {
    ensure_terms_schema();
}

$search = trim($_GET['q'] ?? '');

function term_rows(string $search): array
{
    if (!db_ready()) {
        return [];
    }

    $where = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE title LIKE ? OR term_text LIKE ?';
        $params = ['%' . $search . '%', '%' . $search . '%'];
    }

    $stmt = db()->prepare("SELECT * FROM site_terms $where ORDER BY display_order ASC, id ASC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function render_term_rows(array $terms, string $search): string
{
    ob_start();
    foreach ($terms as $term):
        $termFormId = 'term-form-' . (int) $term['id'];
        $back = 'terms.php' . ($search !== '' ? '?q=' . rawurlencode($search) : '');
        ?>
        <tr>
          <td>
            <form id="<?php echo h($termFormId); ?>" method="POST" action="actions.php"></form>
            <input form="<?php echo h($termFormId); ?>" name="title" required value="<?php echo h($term['title']); ?>">
            <input form="<?php echo h($termFormId); ?>" type="hidden" name="action" value="save_term">
            <input form="<?php echo h($termFormId); ?>" type="hidden" name="back" value="<?php echo h($back); ?>">
            <input form="<?php echo h($termFormId); ?>" type="hidden" name="term_id" value="<?php echo (int) $term['id']; ?>">
          </td>
          <td><textarea form="<?php echo h($termFormId); ?>" name="term_text" rows="3" required><?php echo h($term['term_text']); ?></textarea></td>
          <td><input form="<?php echo h($termFormId); ?>" name="display_order" type="number" value="<?php echo h($term['display_order']); ?>"></td>
	          <td><label class="check-label"><input form="<?php echo h($termFormId); ?>" type="checkbox" name="is_booking_required" <?php echo (int) ($term['is_booking_required'] ?? 0) === 1 ? 'checked' : ''; ?>> Booking check</label></td>
	          <td><label class="check-label"><input form="<?php echo h($termFormId); ?>" type="checkbox" name="is_active" <?php echo (int) $term['is_active'] === 1 ? 'checked' : ''; ?>> Active</label></td>
	          <td><button form="<?php echo h($termFormId); ?>" class="btn gold">Update</button></td>
          <td>
            <form method="POST" action="actions.php" onsubmit="return confirm('Delete this term?');">
              <input type="hidden" name="action" value="delete_term">
              <input type="hidden" name="back" value="<?php echo h($back); ?>">
              <input type="hidden" name="term_id" value="<?php echo (int) $term['id']; ?>">
              <button class="btn red">Delete</button>
            </form>
          </td>
        </tr>
    <?php endforeach;
    if (!$terms): ?>
	      <tr><td colspan="7">No terms found. Use Add Terms to create one.</td></tr>
    <?php endif;
    return ob_get_clean();
}

$terms = $ready ? term_rows($search) : [];

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'html' => render_term_rows($terms, $search),
        'count' => count($terms),
    ]);
    exit;
}

admin_page_start('Terms & Conditions', 'terms');
?>
<div class="top">
	  <div><h1>Terms & Conditions</h1><p>Add, search, edit, update and delete terms. Mark up to three as Booking check for the enquiry form.</p></div>
  <a class="btn" href="../terms.php" target="_blank">View Public Page</a>
</div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql first.</div><?php endif; ?>

<details class="card add-panel package-add-panel" id="add-term" hidden>
  <summary>Add Terms</summary>
  <form class="form-grid" method="POST" action="actions.php">
    <input type="hidden" name="action" value="save_term">
    <input type="hidden" name="back" value="terms.php">
	    <label>Title<input name="title" required placeholder="Cancellation"></label>
	    <label>Order<input name="display_order" type="number" value="10"></label>
	    <label class="check-label"><input type="checkbox" name="is_booking_required"> Booking check</label>
	    <label class="check-label"><input type="checkbox" name="is_active" checked> Active</label>
    <label class="full">Terms text<textarea name="term_text" rows="4" required placeholder="Write the full condition here"></textarea></label>
    <button class="btn gold">Save Terms</button>
    <button class="btn" type="button" id="closeTermPanel">Close</button>
  </form>
</details>

<section class="card">
  <div class="package-toolbar" id="termsToolbar">
    <form class="package-search" method="GET" action="terms.php" id="termSearch">
      <label class="sr-only" for="termSearchInput">Search terms</label>
      <input id="termSearchInput" name="q" value="<?php echo h($search); ?>" placeholder="Search terms">
      <button class="icon-btn search-icon" type="submit" aria-label="Search terms" title="Search terms"></button>
      <button class="icon-btn reset-icon" type="button" id="clearTermSearch" aria-label="Clear search" title="Clear search"></button>
    </form>
    <a class="btn gold" href="#add-term" id="showTermPanel">Add Terms</a>
  </div>
  <div class="table-wrap">
    <table class="terms-table">
      <thead>
        <tr>
          <th>Title</th>
	          <th>Terms text</th>
	          <th>Order</th>
	          <th>Booking form</th>
	          <th>Status</th>
          <th>Update</th>
          <th>Delete</th>
        </tr>
      </thead>
      <tbody id="termTableBody">
        <?php echo render_term_rows($terms, $search); ?>
      </tbody>
    </table>
  </div>
</section>

<script>
  const termSearch = document.getElementById('termSearch');
  const termSearchInput = document.getElementById('termSearchInput');
  const termTableBody = document.getElementById('termTableBody');
  const clearTermSearch = document.getElementById('clearTermSearch');
  const addTermPanel = document.getElementById('add-term');
  const showTermPanel = document.getElementById('showTermPanel');
  const closeTermPanel = document.getElementById('closeTermPanel');
  const termsToolbar = document.getElementById('termsToolbar');

  const loadTerms = async () => {
    const params = new URLSearchParams({
      ajax: '1',
      q: termSearchInput.value || ''
    });
    termTableBody.classList.add('is-loading');
    try {
      const response = await fetch('terms.php?' + params.toString(), {
        headers: { 'X-Requested-With': 'fetch' }
      });
      if (!response.ok) throw new Error('Search failed');
      const data = await response.json();
      termTableBody.innerHTML = data.html;
      const cleanParams = new URLSearchParams();
      if (termSearchInput.value) cleanParams.set('q', termSearchInput.value);
      window.history.replaceState({}, '', cleanParams.toString() ? 'terms.php?' + cleanParams.toString() : 'terms.php');
    } catch (error) {
      termSearch.submit();
    } finally {
      termTableBody.classList.remove('is-loading');
    }
  };

  termSearch.addEventListener('submit', (event) => {
    event.preventDefault();
    loadTerms();
  });
  clearTermSearch.addEventListener('click', () => {
    termSearchInput.value = '';
    loadTerms();
  });
  showTermPanel.addEventListener('click', (event) => {
    event.preventDefault();
    addTermPanel.hidden = false;
    addTermPanel.open = true;
    addTermPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const firstField = addTermPanel.querySelector('input[name="title"]');
    if (firstField) setTimeout(() => firstField.focus({ preventScroll: true }), 250);
  });
  closeTermPanel.addEventListener('click', () => {
    addTermPanel.open = false;
    addTermPanel.hidden = true;
    termsToolbar.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
</script>

<?php admin_page_end(); ?>
