<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();

$ready = db_ready();
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['all', 'pending', 'published', 'hidden'], true)) {
    $status = 'all';
}

function review_rows(string $search, string $status): array
{
    if (!db_ready()) {
        return [];
    }

    $whereParts = [];
    $params = [];
    if ($status !== 'all') {
        $whereParts[] = 'status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $whereParts[] = '(client_name LIKE ? OR event_type LIKE ? OR review_text LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
    $stmt = db()->prepare("SELECT * FROM reviews $where ORDER BY created_at DESC, id DESC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function render_review_rows(array $reviews, string $search, string $status): string
{
    ob_start();
    foreach ($reviews as $review):
        $formId = 'review-form-' . (int) $review['id'];
        $back = 'reviews.php?status=' . rawurlencode($status) . ($search !== '' ? '&q=' . rawurlencode($search) : '');
        ?>
        <tr>
          <td>
            <form id="<?php echo h($formId); ?>" method="POST" action="actions.php"></form>
            <input form="<?php echo h($formId); ?>" type="hidden" name="action" value="save_review">
            <input form="<?php echo h($formId); ?>" type="hidden" name="back" value="<?php echo h($back); ?>">
            <input form="<?php echo h($formId); ?>" type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
            <input form="<?php echo h($formId); ?>" name="client_name" required value="<?php echo h($review['client_name']); ?>" placeholder="Client name">
          </td>
          <td><input form="<?php echo h($formId); ?>" name="event_type" value="<?php echo h($review['event_type']); ?>" placeholder="Wedding"></td>
          <td><input form="<?php echo h($formId); ?>" type="number" min="1" max="5" name="rating" value="<?php echo h((string) (int) $review['rating']); ?>"></td>
          <td><textarea form="<?php echo h($formId); ?>" name="review_text" rows="3" required><?php echo h($review['review_text']); ?></textarea></td>
          <td><select form="<?php echo h($formId); ?>" name="status"><option value="pending" <?php echo $review['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="published" <?php echo $review['status'] === 'published' ? 'selected' : ''; ?>>Published</option><option value="hidden" <?php echo $review['status'] === 'hidden' ? 'selected' : ''; ?>>Hidden</option></select></td>
          <td><button form="<?php echo h($formId); ?>" class="btn gold">Update</button></td>
          <td>
            <form method="POST" action="actions.php" onsubmit="return confirm('Delete this review?');">
              <input type="hidden" name="action" value="delete_review">
              <input type="hidden" name="back" value="<?php echo h($back); ?>">
              <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
              <button class="btn red">Delete</button>
            </form>
          </td>
        </tr>
    <?php endforeach;
    if (!$reviews): ?>
      <tr><td colspan="7">No reviews found. Add Review is at the top of this page.</td></tr>
    <?php endif;
    return ob_get_clean();
}

$reviews = $ready ? review_rows($search, $status) : [];

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'html' => render_review_rows($reviews, $search, $status),
        'count' => count($reviews),
    ]);
    exit;
}

admin_page_start('Reviews', 'reviews');
?>
<div class="top">
  <div><h1>Reviews</h1><p>Add, search, edit, update, publish, hide or delete website reviews.</p></div>
</div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql first.</div><?php endif; ?>
<div class="msg ok">Google Maps reviews: add a Google Maps Platform API key with Places API enabled and the Anantha business Place ID in <strong>includes/config.php</strong>. For every Business Profile review, use Google Business Profile OAuth access from the client account; do not store a Gmail password in website files.</div>

<details class="card add-panel package-add-panel" id="add-review">
  <summary>Add Review</summary>
  <form class="form-grid" method="POST" action="actions.php">
    <input type="hidden" name="action" value="save_review">
    <input type="hidden" name="back" value="reviews.php">
    <label>Client<input name="client_name" required placeholder="Client name"></label>
    <label>Event<input name="event_type" placeholder="Wedding"></label>
    <label>Rating<input type="number" min="1" max="5" value="5" name="rating"></label>
    <label>Status<select name="status"><option value="published">Published</option><option value="pending">Pending</option><option value="hidden">Hidden</option></select></label>
    <label class="full">Review<textarea name="review_text" required rows="3" placeholder="Write the review text"></textarea></label>
    <button class="btn gold">Save Review</button>
  </form>
</details>

<section class="card">
  <div class="package-toolbar">
    <form class="package-search" method="GET" action="reviews.php" id="reviewSearch">
      <input type="hidden" name="status" id="reviewStatus" value="<?php echo h($status); ?>">
      <label class="sr-only" for="reviewSearchInput">Search reviews</label>
      <input id="reviewSearchInput" name="q" value="<?php echo h($search); ?>" placeholder="Search reviews">
      <button class="icon-btn search-icon" type="submit" aria-label="Search reviews" title="Search reviews"></button>
      <button class="icon-btn reset-icon" type="button" id="clearReviewSearch" aria-label="Clear search" title="Clear search"></button>
    </form>
    <a class="btn gold" href="#add-review">Add Review</a>
  </div>
  <div class="filters review-filters">
    <?php foreach (['all'=>'All reviews','pending'=>'Pending','published'=>'Published','hidden'=>'Hidden'] as $key => $label): ?>
      <a class="<?php echo $status === $key ? 'on' : ''; ?>" href="?status=<?php echo h($key); ?><?php echo $search !== '' ? '&q=' . h(rawurlencode($search)) : ''; ?>" data-status="<?php echo h($key); ?>"><?php echo h($label); ?></a>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
    <table class="reviews-table">
      <thead>
        <tr>
          <th>Client</th>
          <th>Event</th>
          <th>Rating</th>
          <th>Review</th>
          <th>Status</th>
          <th>Update</th>
          <th>Delete</th>
        </tr>
      </thead>
      <tbody id="reviewTableBody">
        <?php echo render_review_rows($reviews, $search, $status); ?>
      </tbody>
    </table>
  </div>
</section>

<script>
  const reviewSearch = document.getElementById('reviewSearch');
  const reviewSearchInput = document.getElementById('reviewSearchInput');
  const reviewStatus = document.getElementById('reviewStatus');
  const reviewTableBody = document.getElementById('reviewTableBody');
  const clearReviewSearch = document.getElementById('clearReviewSearch');
  const reviewFilters = document.querySelectorAll('.review-filters a');

  const loadReviews = async () => {
    const params = new URLSearchParams({
      ajax: '1',
      status: reviewStatus.value || 'all',
      q: reviewSearchInput.value || ''
    });
    reviewTableBody.classList.add('is-loading');
    try {
      const response = await fetch('reviews.php?' + params.toString(), {
        headers: { 'X-Requested-With': 'fetch' }
      });
      if (!response.ok) throw new Error('Search failed');
      const data = await response.json();
      reviewTableBody.innerHTML = data.html;
      const cleanParams = new URLSearchParams({ status: reviewStatus.value || 'all' });
      if (reviewSearchInput.value) cleanParams.set('q', reviewSearchInput.value);
      window.history.replaceState({}, '', 'reviews.php?' + cleanParams.toString());
    } catch (error) {
      reviewSearch.submit();
    } finally {
      reviewTableBody.classList.remove('is-loading');
    }
  };

  reviewSearch.addEventListener('submit', (event) => {
    event.preventDefault();
    loadReviews();
  });
  clearReviewSearch.addEventListener('click', () => {
    reviewSearchInput.value = '';
    loadReviews();
  });
  reviewFilters.forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      reviewStatus.value = link.dataset.status || 'all';
      reviewFilters.forEach((item) => item.classList.toggle('on', item === link));
      loadReviews();
    });
  });
</script>

<?php admin_page_end(); ?>
